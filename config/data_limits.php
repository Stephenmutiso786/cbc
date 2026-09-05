<?php

return [
    // Keep a little room below 2 MiB for metadata and provider overhead.
    'max_file_bytes' => (int) env('MAX_FILE_SIZE_BYTES', 1_900_000),
    'daily_transfer_bytes' => (int) env('DAILY_TRANSFER_LIMIT_BYTES', 50 * 1024 * 1024),
];
