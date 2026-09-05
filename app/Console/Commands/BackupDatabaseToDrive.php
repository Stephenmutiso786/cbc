<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\GoogleDriveStorage;
use App\Services\DataTransferPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class BackupDatabaseToDrive extends Command
{
    protected $signature = 'backup:drive
        {--threshold=100 : Minimum database size in megabytes before a backup is created}
        {--force : Create a backup even when the threshold has not been reached}';

    protected $description = 'Compress the database and upload a threshold backup to Google Drive';

    public function handle(GoogleDriveStorage $drive, DataTransferPolicy $transferPolicy): int
    {
        $driver = (string) config('database.default');
        if ($driver !== 'pgsql') {
            $this->error("Automatic Drive backups currently support PostgreSQL/Supabase, not {$driver}.");
            return self::FAILURE;
        }

        $databaseSize = $this->databaseSize($driver);
        $thresholdBytes = max(1, (int) $this->option('threshold')) * 1024 * 1024;

        if (!$this->option('force') && $databaseSize < $thresholdBytes) {
            $this->line(sprintf(
                'Database is %.2f MB; threshold is %.2f MB. No backup required.',
                $databaseSize / 1024 / 1024,
                $thresholdBytes / 1024 / 1024,
            ));
            return self::SUCCESS;
        }

        $lastBackup = DatabaseBackup::query()
            ->where('driver', $driver)
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if (!$this->option('force') && $lastBackup && $databaseSize < ($lastBackup->database_size_bytes + $thresholdBytes)) {
            $this->line('A backup already covers the current database size; no duplicate upload required.');
            return self::SUCCESS;
        }

        if (!$drive->enabled()) {
            $this->error('Google Drive is not configured. Enable it and save valid credentials and a folder ID in Admin Settings.');
            return self::FAILURE;
        }

        $backup = DatabaseBackup::create([
            'driver' => $driver,
            'database_size_bytes' => $databaseSize,
            'status' => 'running',
        ]);
        $directory = storage_path('app/backups');
        $archive = $directory . '/database-' . now()->format('Ymd-His') . '.dump';

        try {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException('The backup directory could not be created.');
            }

            $this->createPostgresDump($archive);
            $archiveSize = filesize($archive);
            if ($archiveSize === false || $archiveSize < 1) {
                throw new \RuntimeException('pg_dump produced an empty archive.');
            }

            $drivePath = $this->uploadArchive($drive, $transferPolicy, $archive, $archiveSize);

            $backup->update([
                'archive_size_bytes' => $archiveSize,
                'drive_file_id' => $drivePath,
                'status' => 'completed',
            ]);

            $this->info(sprintf(
                'Uploaded compressed database backup (%s from %.2f MB) to Google Drive.',
                $this->formatBytes($archiveSize),
                $databaseSize / 1024 / 1024,
            ));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 65535),
            ]);
            $this->error($exception->getMessage());
            return self::FAILURE;
        } finally {
            if (is_file($archive)) {
                unlink($archive);
            }
        }
    }

    private function uploadArchive(GoogleDriveStorage $drive, DataTransferPolicy $transferPolicy, string $archive, int $archiveSize): string
    {
        $folder = 'backups/database';
        $baseName = basename($archive);
        if ($archiveSize <= $transferPolicy->maxFileBytes()) {
            return $drive->storeFilePath($archive, $folder, $baseName, 'application/octet-stream');
        }

        $partSize = min($transferPolicy->maxFileBytes(), 1_800_000);
        $input = fopen($archive, 'rb');
        if ($input === false) throw new \RuntimeException('The compressed backup could not be opened for splitting.');

        $parts = [];
        $partNumber = 0;
        try {
            while (!feof($input)) {
                $part = storage_path('app/backups/' . $baseName . '.part-' . str_pad((string) (++$partNumber), 5, '0', STR_PAD_LEFT));
                $output = fopen($part, 'wb');
                if ($output === false) throw new \RuntimeException('A backup part could not be created.');
                $remaining = $partSize;
                while ($remaining > 0 && !feof($input)) {
                    $chunk = fread($input, min(1024 * 1024, $remaining));
                    if ($chunk === false) throw new \RuntimeException('A backup part could not be read.');
                    if ($chunk !== '') fwrite($output, $chunk);
                    $remaining -= strlen($chunk);
                }
                fclose($output);
                if (filesize($part) < 1) { unlink($part); break; }
                $parts[] = [
                    'name' => basename($part),
                    'path' => $drive->storeFilePath($part, $folder, basename($part), 'application/octet-stream'),
                    'bytes' => filesize($part),
                ];
                unlink($part);
            }
        } finally {
            fclose($input);
        }

        if ($parts === []) throw new \RuntimeException('No backup parts were created.');
        $manifest = json_encode([
            'format' => 'cbc-postgres-backup-parts-v1',
            'archive' => $baseName,
            'bytes' => $archiveSize,
            'parts' => $parts,
        ], JSON_THROW_ON_ERROR);
        $transferPolicy->assertFileSize(strlen($manifest), 'Backup manifest');
        return $drive->store($manifest, $folder, $baseName . '.manifest.json', 'application/json');
    }

    private function databaseSize(string $driver): int
    {
        if ($driver === 'pgsql') {
            return (int) DB::selectOne('select pg_database_size(current_database()) as size')->size;
        }

        return 0;
    }

    private function createPostgresDump(string $archive): void
    {
        $url = env('DB_MIGRATION_URL') ?: config('database.connections.pgsql.url');
        $parts = is_string($url) && trim($url) !== '' ? parse_url($url) : false;
        $connection = config('database.connections.pgsql');
        $query = [];
        if (is_array($parts) && isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $host = $parts['host'] ?? $connection['host'];
        $port = (string) ($parts['port'] ?? $connection['port']);
        $database = ltrim((string) ($parts['path'] ?? $connection['database']), '/');
        $username = isset($parts['user']) ? urldecode($parts['user']) : $connection['username'];
        $password = isset($parts['pass']) ? urldecode($parts['pass']) : $connection['password'];
        $sslmode = $query['sslmode'] ?? ($connection['sslmode'] ?? 'require');

        foreach (['host' => $host, 'database' => $database, 'username' => $username] as $name => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new \RuntimeException("PostgreSQL {$name} is missing from DB_URL.");
            }
        }

        $process = new Process([
            'pg_dump',
            '--format=custom',
            '--compress=9',
            '--no-owner',
            '--no-acl',
            '--host=' . $host,
            '--port=' . $port,
            '--username=' . $username,
            '--file=' . $archive,
            $database,
        ], base_path(), [
            'PGPASSWORD' => (string) $password,
            'PGSSLMODE' => (string) $sslmode,
        ], null, (float) env('BACKUP_COMMAND_TIMEOUT', 900));

        $process->run();
        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new \RuntimeException('PostgreSQL backup failed: ' . ($error ?: 'pg_dump returned a non-zero status.'));
        }
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 2) . ' MB'
            : number_format($bytes / 1024, 2) . ' KB';
    }
}
