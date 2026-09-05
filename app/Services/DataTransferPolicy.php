<?php

namespace App\Services;

use App\Models\DataTransferUsage;
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
        $this->assertFileSize($bytes, ucfirst($label));
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
}
