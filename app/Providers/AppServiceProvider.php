<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Crypt;
use App\Models\Exam;
use App\Policies\ExamPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::before(function ($user, string $ability) {
            return $user->hasAnyRole(['admin', 'super-admin']) ? true : null;
        });

        try {
            foreach (DB::table('school_settings')->pluck('value', 'key') as $key => $value) {
                try {
                $value = is_string($value) && str_starts_with($value, 'enc:')
                    ? Crypt::decryptString(substr($value, 4))
                    : $value;
                $configKey = match (true) {
                    str_starts_with($key, 'mpesa_') => 'services.mpesa.' . substr($key, 6),
                    str_starts_with($key, 'at_') => 'services.africastalking.' . substr($key, 3),
                    str_starts_with($key, 'olympus_sms_') => 'services.olympus_sms.' . substr($key, 12),
                    str_starts_with($key, 'firebase_') => 'services.firebase.' . substr($key, 9),
                    str_starts_with($key, 'kemis_') => 'services.kemis.' . substr($key, 6),
                    str_starts_with($key, 'google_drive_') => 'services.google_drive.' . substr($key, 13),
                    default => 'school.' . $key,
                };
                config()->set($configKey, $value);
                } catch (\Throwable $exception) {
                    // A corrupt secret must not prevent unrelated settings loading.
                    report($exception);
                }
            }
        } catch (\Throwable) {
            // The table is unavailable during a first install or migration.
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
