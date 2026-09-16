<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SchoolSettingsLoader
{
    public static function for(int $schoolId): void
    {
        try {
            $secrets = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey', 'at_api_key', 'olympus_sms_api_token', 'firebase_server_key', 'kemis_api_key', 'google_drive_credentials'];
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
}
