<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AdminSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'motto' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['required', 'integer', 'min:2000', 'max:2200'],
            'current_term' => ['required', 'integer', 'between:1,3'],
            'type' => ['required', 'in:primary,secondary,mixed'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'mpesa_env' => ['required', 'in:sandbox,production'],
            'mpesa_consumer_key' => ['nullable', 'string', 'max:500'],
            'mpesa_consumer_secret' => ['nullable', 'string', 'max:500'],
            'mpesa_shortcode' => ['nullable', 'string', 'max:50'],
            'mpesa_passkey' => ['nullable', 'string', 'max:500'],
            'mpesa_callback_url' => ['nullable', 'url', 'max:500'],
            'mpesa_confirmation_url' => ['nullable', 'url', 'max:500'],
            'mpesa_validation_url' => ['nullable', 'url', 'max:500'],
            'at_api_key' => ['nullable', 'string', 'max:500'],
            'at_username' => ['nullable', 'string', 'max:100'],
            'at_sender_id' => ['nullable', 'string', 'max:50'],
            'at_env' => ['required', 'in:sandbox,production'],
            'firebase_server_key' => ['nullable', 'string', 'max:1000'],
            'firebase_project_id' => ['nullable', 'string', 'max:255'],
            'kemis_api_url' => ['nullable', 'url', 'max:500'],
            'kemis_api_key' => ['nullable', 'string', 'max:500'],
            'kemis_school_code' => ['nullable', 'string', 'max:100'],
        ]);

        $secretKeys = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey', 'at_api_key', 'firebase_server_key', 'kemis_api_key'];
        foreach ($data as $key => $value) {
            $setting = SchoolSetting::firstOrNew(['key' => $key]);
            $configValue = $value;
            if (in_array($key, $secretKeys, true)) {
                if ($value === '' && $setting->exists) continue;
                $value = $value === '' ? null : 'enc:' . Crypt::encryptString($value);
            }
            $setting->value = $value;
            $setting->save();
            $configKey = match (true) {
                str_starts_with($key, 'mpesa_') => 'services.mpesa.' . substr($key, 6),
                str_starts_with($key, 'at_') => 'services.africastalking.' . substr($key, 3),
                str_starts_with($key, 'firebase_') => 'services.firebase.' . substr($key, 9),
                str_starts_with($key, 'kemis_') => 'services.kemis.' . substr($key, 6),
                default => 'school.' . $key,
            };
            config()->set($configKey, $configValue);
        }

        return back()->with('success', 'School settings updated successfully.');
    }
}
