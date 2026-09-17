<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
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
            return $user->hasRole('super-admin') ? true : null;
        });

        // Per-school branding/settings/credentials (M-Pesa, Drive, KEMIS,
        // etc.) are loaded per-request by App\Http\Middleware\LoadSchoolSettings,
        // once the current user's school is known.
        //
        // SMS is different: the super-admin holds one provider account for
        // the whole platform (schools just draw down a credit balance), so
        // it's genuinely global and safe to load here on every boot.
        $this->loadPlatformSmsSettings();
        $this->loadPlatformMpesaSettings();

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }

    private function loadPlatformMpesaSettings(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                return;
            }
            foreach (['env', 'consumer_key', 'consumer_secret', 'shortcode', 'passkey', 'callback_url'] as $field) {
                if ($value = SystemSetting::get('platform_mpesa_' . $field)) {
                    config()->set('services.platform_mpesa.' . $field, $value);
                }
            }
        } catch (\Throwable) {
            // Table unavailable during a first install or migration.
        }
    }

    private function loadPlatformSmsSettings(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                return;
            }
            if ($value = SystemSetting::get('olympus_sms_api_url')) {
                config()->set('services.olympus_sms.api_url', $value);
            }
            if ($value = SystemSetting::get('olympus_sms_api_token')) {
                config()->set('services.olympus_sms.api_token', $value);
            }
            if ($value = SystemSetting::get('olympus_sms_sender_id')) {
                config()->set('services.olympus_sms.sender_id', $value);
            }
            if ($value = SystemSetting::get('at_api_key')) {
                config()->set('services.africastalking.api_key', $value);
            }
        } catch (\Throwable) {
            // Table unavailable during a first install or migration.
        }
    }
}
