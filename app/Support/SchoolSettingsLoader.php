<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SchoolSettingsLoader
{
    /**
     * Queue workers process many schools in one PHP process. Keep the
     * application defaults so each tenant starts with a clean configuration,
     * rather than inheriting a missing logo or credential from the preceding
     * job.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $baseSchoolConfig = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $baseServiceConfig = null;

    public static function for(int $schoolId): void
    {
        try {
            self::resetTenantConfig();
            // The school record is the durable source for the identity entered
            // during onboarding. Settings then override it only where the
            // school has deliberately saved a different value. This prevents
            // a newly created school from ever falling back to the generic
            // application name while its settings rows are being created.
            $school = School::find($schoolId);
            if ($school) {
                foreach (['name', 'type', 'motto', 'address', 'phone', 'email'] as $key) {
                    if ($school->{$key} !== null && $school->{$key} !== '') {
                        config()->set('school.' . $key, $school->{$key});
                    }
                }

                // Schools created before settings assets were introduced keep
                // their official branding on the schools record. Load it as a
                // fallback first; a newer school_setting_assets value below
                // deliberately overrides it. This keeps every existing school
                // (including Kyandulu) visible in reports and PDFs.
                if (is_string($school->logo_data) && str_starts_with($school->logo_data, 'data:image/')) {
                    config()->set('school.logo_data', $school->logo_data);
                }
            }
            $secrets = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey', 'at_api_key', 'olympus_sms_api_token', 'firebase_server_key', 'kemis_api_key', 'google_drive_credentials', 'ml_service_api_key'];
            foreach (DB::table('school_settings')->where('school_id', $schoolId)->pluck('value', 'key') as $key => $value) {
                try {
                    $value = is_string($value) && str_starts_with($value, 'enc:') ? Crypt::decryptString(substr($value, 4)) : $value;
                    if (in_array($key, $secrets, true) && ($value === null || $value === '')) continue;
                    $configKey = match (true) {
                        str_starts_with($key, 'mpesa_') => 'services.mpesa.' . substr($key, 6),
                        str_starts_with($key, 'at_') => 'services.africastalking.' . substr($key, 3),
                        str_starts_with($key, 'olympus_sms_') => 'services.olympus_sms.' . substr($key, 12),
                        str_starts_with($key, 'firebase_') => 'services.firebase.' . substr($key, 9),
                        str_starts_with($key, 'kemis_') => 'services.kemis.' . substr($key, 6),
                        str_starts_with($key, 'google_drive_') => 'services.google_drive.' . substr($key, 13),
                        str_starts_with($key, 'ml_service_') => 'services.risk_prediction.' . substr($key, 11),
                        default => 'school.' . $key,
                    };
                    config()->set($configKey, $value);
                } catch (\Throwable $exception) { report($exception); }
            }
            foreach (DB::table('school_setting_assets')->where('school_id', $schoolId)->pluck('data', 'key') as $key => $value) config()->set('school.' . $key, $value);
        } catch (\Throwable) {
            // Tables are unavailable before tenant migrations complete.
        }
    }

    private static function resetTenantConfig(): void
    {
        self::$baseSchoolConfig ??= config('school', []);
        config()->set('school', self::$baseSchoolConfig);

        $services = ['mpesa', 'africastalking', 'olympus_sms', 'firebase', 'kemis', 'google_drive', 'risk_prediction'];
        if (self::$baseServiceConfig === null) {
            self::$baseServiceConfig = [];
            foreach ($services as $service) {
                self::$baseServiceConfig[$service] = config('services.' . $service, []);
            }
        }

        foreach (self::$baseServiceConfig as $service => $settings) {
            config()->set('services.' . $service, $settings);
        }
    }
}
