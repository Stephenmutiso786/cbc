<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The PostgreSQL dump contains every live database record: learners,
// admissions, classes, staff, exams, marks, finance, settings, and audit data.
// Railway must run `php artisan schedule:run` every minute for this schedule.
if (filter_var(env('GOOGLE_DRIVE_DAILY_BACKUP', true), FILTER_VALIDATE_BOOLEAN)) {
    Schedule::command('backup:drive --force')
        ->dailyAt(env('GOOGLE_DRIVE_BACKUP_TIME', '02:00'))
        ->withoutOverlapping(30);
} else {
    Schedule::command('backup:drive --threshold=100')
        ->hourly()
        ->withoutOverlapping(30);
}
