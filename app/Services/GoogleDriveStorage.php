<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GoogleDriveStorage
{
    public function __construct(private readonly DataTransferPolicy $transferPolicy) {}

    public function enabled(): bool
    {
        return filter_var(config('services.google_drive.enabled'), FILTER_VALIDATE_BOOLEAN) && $this->credentials() && config('services.google_drive.folder_id');
    }

    public function store(UploadedFile|string $file, string $folder, ?string $name = null, ?string $mime = null): string
    {
        $contents = $file instanceof UploadedFile ? file_get_contents($file->getRealPath()) : $file;
        if (!is_string($contents)) throw new \RuntimeException('The file could not be read.');
        $name ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($folder);
        $mime ??= $file instanceof UploadedFile ? $file->getMimeType() : 'application/octet-stream';
        if (strlen($contents) > $this->transferPolicy->maxFileBytes()) {
            return $this->storeChunked($contents, $folder, $name, $mime);
        }
        if (!$this->enabled()) {
            $path = trim($folder, '/') . '/' . ($name ?? uniqid('file_', true));
            Storage::disk('public')->put($path, $contents);
            return $path;
        }
        $this->transferPolicy->reserve(strlen($contents), 'Google Drive upload');
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

        $bytes = filesize($path);
        if ($bytes === false) throw new \RuntimeException('The file size could not be determined.');
        if ($bytes > $this->transferPolicy->maxFileBytes()) {
            $contents = file_get_contents($path);
            if (!is_string($contents)) throw new \RuntimeException('The file could not be read for splitting.');
            return $this->storeChunked($contents, $folder, $name ?? basename($path), $mime ?? 'application/octet-stream');
        }
        $this->transferPolicy->reserve($bytes, 'Google Drive upload');

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
        if (str_starts_with($path, 'cbc-parts:')) {
            $manifest = json_decode(base64_decode(substr($path, 10), true) ?: '', true, 512, JSON_THROW_ON_ERROR);
            return collect($manifest['parts'] ?? [])->map(fn (string $part) => $this->contents($part))->implode('');
        }
        if (!str_starts_with($path, 'gdrive:')) return Storage::disk('public')->get($path);
        return $this->drive()->files->get(substr($path, 7), ['alt' => 'media'])->getBody()->getContents();
    }

    public function delete(?string $path): void
    {
        if (!$path) return;
        if (str_starts_with($path, 'cbc-parts:')) {
            $manifest = json_decode(base64_decode(substr($path, 10), true) ?: '', true, 512, JSON_THROW_ON_ERROR);
            foreach ($manifest['parts'] ?? [] as $part) $this->delete($part);
            return;
        }
        if (str_starts_with($path, 'gdrive:')) {
            $this->drive()->files->delete(substr($path, 7));
            return;
        }
        Storage::disk('public')->delete($path);
    }

    /** Store large content as limited parts and reconstruct it when read. */
    private function storeChunked(string $contents, string $folder, string $name, string $mime): string
    {
        $partSize = min($this->transferPolicy->maxFileBytes(), 1_800_000);
        $parts = [];
        for ($offset = 0, $number = 1, $length = strlen($contents); $offset < $length; $offset += $partSize, $number++) {
            $parts[] = $this->store(
                substr($contents, $offset, $partSize),
                $folder,
                $name . '.part-' . str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                $mime,
            );
        }
        return 'cbc-parts:' . base64_encode(json_encode(['format' => 'cbc-parts-v1', 'mime' => $mime, 'parts' => $parts], JSON_THROW_ON_ERROR));
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
