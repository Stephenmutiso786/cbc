<?php

namespace App\Services;

use App\Models\DataTransferUsage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DataTransferPolicy
{
    public function maxFileBytes(): int
    {
        return max(1, (int) config('data_limits.max_file_bytes', 1_900_000));
    }

    public function assertFileSize(int $bytes, string $label = 'File'): void
    {
        if ($bytes > $this->maxFileBytes()) {
            throw new \RuntimeException(sprintf(
                '%s is too large. The maximum allowed size is %s.',
                $label,
                $this->formatBytes($this->maxFileBytes()),
            ));
        }
    }

    /** Reserve provider transfer bytes before an upload starts. */
    public function reserve(int $bytes, string $label = 'transfer'): void
    {
        if ($bytes < 0) throw new \InvalidArgumentException('Transfer size cannot be negative.');
        $limit = max(1, (int) config('data_limits.daily_transfer_bytes', 50 * 1024 * 1024));

        DB::transaction(function () use ($bytes, $label, $limit): void {
            $usage = DataTransferUsage::query()
                ->whereDate('usage_date', today())
                ->lockForUpdate()
                ->first();

            if (!$usage) {
                $usage = DataTransferUsage::create(['usage_date' => today(), 'bytes' => 0]);
            }

            if ($usage->bytes + $bytes > $limit) {
                throw new \RuntimeException(sprintf(
                    'Daily data-transfer limit reached. %s needs %s, but only %s remains today.',
                    ucfirst($label),
                    $this->formatBytes($bytes),
                    $this->formatBytes(max(0, $limit - $usage->bytes)),
                ));
            }

            $usage->increment('bytes', $bytes);
        });
    }

    public function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 2) . ' MB'
            : number_format($bytes / 1024, 2) . ' KB';
    }

    /** Re-encode an image so settings never retain an unnecessarily large original. */
    public function imageDataUrl(UploadedFile $file): string
    {
        $source = @file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($source ?: '');
        if (!$image) throw new \RuntimeException('The image could not be read for compression.');

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1200 / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagepng($canvas, null, 9);
        $encoded = ob_get_clean();
        $mime = 'image/png';
        if (!is_string($encoded) || strlen($encoded) > $this->maxFileBytes()) {
            $mime = 'image/jpeg';
            for ($quality = 75; $quality >= 25 && (!is_string($encoded) || strlen($encoded) > $this->maxFileBytes()); $quality -= 10) {
                ob_start();
                imagejpeg($canvas, null, $quality);
                $encoded = ob_get_clean();
            }
        }
        // Resize further for unusually detailed photographs that remain large.
        while (is_string($encoded) && strlen($encoded) > $this->maxFileBytes() && imagesx($canvas) > 320) {
            $newWidth = max(320, (int) floor(imagesx($canvas) * 0.75));
            $newHeight = max(320, (int) floor(imagesy($canvas) * 0.75));
            $smaller = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($smaller, $canvas, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($canvas), imagesy($canvas));
            imagedestroy($canvas);
            $canvas = $smaller;
            ob_start();
            imagejpeg($canvas, null, 60);
            $encoded = ob_get_clean();
        }
        imagedestroy($canvas);
        imagedestroy($image);

        if (!is_string($encoded) || $encoded === '' || strlen($encoded) > $this->maxFileBytes()) {
            throw new \RuntimeException('The image could not be compressed below the 2 MB limit.');
        }
        return 'data:' . $mime . ';base64,' . base64_encode($encoded);
    }
}
