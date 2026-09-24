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
    Schedule::command('records:drive')
        ->everyFifteenMinutes()
        ->withoutOverlapping(30);
} else {
    Schedule::command('backup:drive --threshold=100')
        ->hourly()
        ->withoutOverlapping(30);
}

Schedule::command('risk:predict')->dailyAt('01:15')->withoutOverlapping(120);
Schedule::command('risk:train-model')->weeklyOn(0, '02:15')->withoutOverlapping(240);

// A held school SMS order is fulfilled automatically once Olympus capacity is
// available. This never credits an order whose M-Pesa callback has not first
// confirmed payment.
Artisan::command('sms:refresh-capacity', function (): void {
    try {
        $capacity = app(\App\Services\SmsCapacityService::class);
        $balance = $capacity->refreshProviderBalance();
        $fulfilled = $capacity->fulfilHeldOrders();
        $this->info("Olympus balance {$balance}; automatically fulfilled {$fulfilled} held order(s).");
    } catch (\Throwable $exception) {
        report($exception);
        $this->error('SMS capacity refresh failed: ' . $exception->getMessage());
    }
})->purpose('Refresh Olympus SMS capacity and fulfil eligible paid orders');

Schedule::command('sms:refresh-capacity')->everyFifteenMinutes()->withoutOverlapping(10);
