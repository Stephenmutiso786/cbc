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
        return filter_var(config('services.google_drive.enabled'), FILTER_VALIDATE_BOOLEAN)
            && ($this->credentials() || $this->oauthToken())
            && config('services.google_drive.folder_id');
    }

    public function oauthConfigured(): bool
    {
        return (bool) config('services.google_drive.client_id')
            && (bool) config('services.google_drive.client_secret')
            && (bool) $this->oauthRedirectUri();
    }

    public function oauthConnected(): bool
    {
        return $this->oauthToken() !== null;
    }

    public function authorizationUrl(string $state): string
    {
        if (!$this->oauthConfigured()) {
            throw new \RuntimeException('Google Drive OAuth is not configured. Add the Google client ID, client secret, and redirect URI in Railway.');
        }

        $client = $this->oauthClient();
        return $client->createAuthUrl(null, ['state' => $state, 'include_granted_scopes' => 'true']);
    }

    public function exchangeOAuthCode(string $code): array
    {
        if (!$this->oauthConfigured()) {
            throw new \RuntimeException('Google Drive OAuth is not configured.');
        }

        $token = $this->oauthClient()->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException('Google Drive authorization failed: ' . ($token['error_description'] ?? $token['error']));
        }
        if (empty($token['refresh_token'])) {
            throw new \RuntimeException('Google did not return a refresh token. Disconnect the app in Google and connect again.');
        }

        return $token;
    }

    public function testConnection(): string
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Save a valid Google Drive service account, folder ID, and enable Drive first.');
        }
        $folderId = (string) config('services.google_drive.folder_id');
        $this->drive()->files->get($folderId, ['fields' => 'id,name,mimeType']);
        return $folderId;
    }

    public function listFiles(): array
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Connect Google Drive, enable storage, and save a folder ID first.');
        }

        $files = $this->drive()->files->listFiles([
            'q' => "'" . config('services.google_drive.folder_id') . "' in parents and trashed = false",
            'orderBy' => 'createdTime desc',
            'pageSize' => 100,
            'fields' => 'files(id,name,mimeType,size,createdTime,modifiedTime,webViewLink)',
        ])->getFiles();

        return array_map(fn (DriveFile $file): array => [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mime_type' => $file->getMimeType(),
            'size' => (int) ($file->getSize() ?? 0),
            'created_at' => $file->getCreatedTime(),
            'modified_at' => $file->getModifiedTime(),
            'url' => $file->getWebViewLink() ?: 'https://drive.google.com/open?id=' . $file->getId(),
        ], $files);
    }

    public function storeOrReplace(string $contents, string $folder, string $name, string $mime): string
    {
        if (!$this->enabled()) {
            return $this->store($contents, $folder, $name, $mime);
        }
        if (strlen($contents) > $this->transferPolicy->maxFileBytes()) {
            return $this->store($contents, $folder, $name, $mime);
        }

        $this->transferPolicy->reserve(strlen($contents), 'Google Drive upload');
        $drive = $this->drive();
        $escapedName = str_replace("'", "\\'", $name);
        $existing = $drive->files->listFiles([
            'q' => "'" . config('services.google_drive.folder_id') . "' in parents and name = '" . $escapedName . "' and trashed = false",
            'pageSize' => 1,
            'fields' => 'files(id)',
        ])->getFiles()[0] ?? null;
        $metadata = new DriveFile([
            'name' => $name,
            'parents' => [config('services.google_drive.folder_id')],
            'description' => 'CBC School Management - ' . trim($folder, '/'),
        ]);
        $saved = $existing
            ? $drive->files->update($existing->getId(), $metadata, ['data' => $contents, 'mimeType' => $mime, 'uploadType' => 'multipart', 'fields' => 'id'])
            : $drive->files->create($metadata, ['data' => $contents, 'mimeType' => $mime, 'uploadType' => 'multipart', 'fields' => 'id']);

        return 'gdrive:' . $saved->getId();
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

    /** Store locally while retaining the same compression and part format. */
    public function storeLocal(string $contents, string $folder, string $name, string $mime): string
    {
        if (strlen($contents) > $this->transferPolicy->maxFileBytes()) {
            return $this->storeChunked($contents, $folder, $name, $mime, false);
        }
        $path = trim($folder, '/') . '/' . $name;
        Storage::disk('public')->put($path, $contents);
        return $path;
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
            $contents = collect($manifest['parts'] ?? [])->map(fn (string $part) => $this->contents($part))->implode('');
            return ($manifest['encoding'] ?? null) === 'gzip' ? gzdecode($contents) : $contents;
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
    private function storeChunked(string $contents, string $folder, string $name, string $mime, bool $remote = true): string
    {
        $encoding = 'identity';
        $compressed = gzencode($contents, 6);
        if (is_string($compressed) && strlen($compressed) < strlen($contents)) {
            $contents = $compressed;
            $encoding = 'gzip';
        }
        $partSize = min($this->transferPolicy->maxFileBytes(), 1_800_000);
        $parts = [];
        for ($offset = 0, $number = 1, $length = strlen($contents); $offset < $length; $offset += $partSize, $number++) {
            $parts[] = $remote ? $this->store(
                substr($contents, $offset, $partSize),
                $folder,
                $name . '.part-' . str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                $mime,
            ) : $this->storeLocal(
                substr($contents, $offset, $partSize),
                $folder,
                $name . '.part-' . str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                $mime,
            );
        }
        return 'cbc-parts:' . base64_encode(json_encode(['format' => 'cbc-parts-v1', 'mime' => $mime, 'encoding' => $encoding, 'parts' => $parts], JSON_THROW_ON_ERROR));
    }

    private function drive(): Drive
    {
        $client = new Client();
        if ($token = $this->oauthToken()) {
            $client->setClientId((string) config('services.google_drive.client_id'));
            $client->setClientSecret((string) config('services.google_drive.client_secret'));
            $client->setRedirectUri($this->oauthRedirectUri());
            $client->setAccessToken($token);
            if ($client->isAccessTokenExpired() && !empty($token['refresh_token'])) {
                $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            }
        } else {
            $credentials = json_decode($this->credentials(), true, 512, JSON_THROW_ON_ERROR);
            $client->setAuthConfig($credentials);
        }
        $client->setScopes([Drive::DRIVE]);
        return new Drive($client);
    }

    private function oauthClient(): Client
    {
        $client = new Client();
        $client->setClientId((string) config('services.google_drive.client_id'));
        $client->setClientSecret((string) config('services.google_drive.client_secret'));
        $client->setRedirectUri($this->oauthRedirectUri());
        $client->setScopes([Drive::DRIVE]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        return $client;
    }

    private function oauthRedirectUri(): string
    {
        return (string) (config('services.google_drive.redirect_uri') ?: url('/admin/settings/google-drive/callback'));
    }

    private function oauthToken(): ?array
    {
        $value = config('services.google_drive.oauth_token');
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return null;
        $token = json_decode($value, true);
        return is_array($token) && !empty($token['refresh_token']) ? $token : null;
    }

    private function credentials(): ?string
    {
        $value = config('services.google_drive.credentials');
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
