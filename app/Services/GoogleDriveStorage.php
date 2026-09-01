<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GoogleDriveStorage
{
    public function enabled(): bool
    {
        return filter_var(config('services.google_drive.enabled'), FILTER_VALIDATE_BOOLEAN) && $this->credentials() && config('services.google_drive.folder_id');
    }

    public function store(UploadedFile|string $file, string $folder, ?string $name = null, ?string $mime = null): string
    {
        if (!$this->enabled()) {
            if ($file instanceof UploadedFile) return $file->store($folder, 'public');
            throw new \RuntimeException('Google Drive storage is not configured.');
        }

        $contents = $file instanceof UploadedFile ? file_get_contents($file->getRealPath()) : $file;
        $name ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($folder);
        $mime ??= $file instanceof UploadedFile ? $file->getMimeType() : 'application/octet-stream';
        $driveFile = new DriveFile(['name' => $name, 'parents' => [config('services.google_drive.folder_id')], 'description' => 'CBC School Management - ' . trim($folder, '/')]);
        $created = $this->drive()->files->create($driveFile, ['data' => $contents, 'mimeType' => $mime, 'uploadType' => 'multipart', 'fields' => 'id']);

        return 'gdrive:' . $created->getId();
    }

    /**
     * Upload a local file without loading the entire file into PHP memory.
     */
    public function storeFilePath(string $path, string $folder, ?string $name = null, ?string $mime = null): string
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Google Drive storage is not configured.');
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('The file to upload does not exist or is not readable.');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('The file could not be opened for upload.');
        }

        try {
            $driveFile = new DriveFile([
                'name' => $name ?? basename($path),
                'parents' => [config('services.google_drive.folder_id')],
                'description' => 'CBC School Management - ' . trim($folder, '/'),
            ]);

            $created = $this->drive()->files->create($driveFile, [
                'data' => $stream,
                'mimeType' => $mime ?? 'application/octet-stream',
                'uploadType' => 'resumable',
                'fields' => 'id',
            ]);
        } finally {
            fclose($stream);
        }

        return 'gdrive:' . $created->getId();
    }

    public function contents(string $path): string
    {
        if (!str_starts_with($path, 'gdrive:')) return Storage::disk('public')->get($path);
        return $this->drive()->files->get(substr($path, 7), ['alt' => 'media'])->getBody()->getContents();
    }

    public function delete(?string $path): void
    {
        if (!$path) return;
        if (str_starts_with($path, 'gdrive:')) {
            $this->drive()->files->delete(substr($path, 7));
            return;
        }
        Storage::disk('public')->delete($path);
    }

    private function drive(): Drive
    {
        $client = new Client();
        $credentials = json_decode($this->credentials(), true, 512, JSON_THROW_ON_ERROR);
        $client->setAuthConfig($credentials);
        $client->setScopes([Drive::DRIVE]);
        return new Drive($client);
    }

    private function credentials(): ?string
    {
        $value = config('services.google_drive.credentials');
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
