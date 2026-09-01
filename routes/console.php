<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run this command from a scheduler or Render Cron Job. It is intentionally
// threshold-based so an oversized database is not uploaded on every tick.
Schedule::command('backup:drive --threshold=100')
    ->hourly()
    ->withoutOverlapping(30);
