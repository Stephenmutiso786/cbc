<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

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
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'maintenance_mode' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
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
            'at_env' => ['nullable', 'in:sandbox,production'],
            'olympus_sms_api_url' => ['nullable', 'url', 'max:500'],
            'olympus_sms_api_token' => ['nullable', 'string', 'max:500'],
            'olympus_sms_sender_id' => ['required', 'string', 'max:11'],
            'firebase_server_key' => ['nullable', 'string', 'max:1000'],
            'firebase_project_id' => ['nullable', 'string', 'max:255'],
            'kemis_api_url' => ['nullable', 'url', 'max:500'],
            'kemis_api_key' => ['nullable', 'string', 'max:500'],
            'kemis_school_code' => ['nullable', 'string', 'max:100'],
            'google_drive_enabled' => ['required', 'boolean'],
            'google_drive_folder_id' => ['nullable', 'string', 'max:255'],
            'google_drive_credentials' => ['nullable', 'json', 'max:30000'],
            'google_drive_credentials_file' => ['nullable', 'file', 'mimes:json,txt', 'max:100'],
        ]);

        if ($request->hasFile('google_drive_credentials_file')) {
            $credentials = file_get_contents($request->file('google_drive_credentials_file')->getRealPath());
            if (json_decode($credentials, true) === null) {
                throw ValidationException::withMessages(['google_drive_credentials_file' => 'The selected file is not valid Google service-account JSON.']);
            }
            $data['google_drive_credentials'] = $credentials;
        } elseif (!empty($data['google_drive_credentials']) && json_decode($data['google_drive_credentials'], true) === null) {
            throw ValidationException::withMessages(['google_drive_credentials' => 'Paste the original JSON file contents, including quotes and \\n characters.']);
        }
        unset($data['google_drive_credentials_file']);

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $data['logo_data'] = 'data:' . $logo->getMimeType() . ';base64,' . base64_encode(file_get_contents($logo->getRealPath()));
        }
        unset($data['logo']);

        $secretKeys = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey', 'at_api_key', 'olympus_sms_api_token', 'firebase_server_key', 'kemis_api_key', 'google_drive_credentials'];
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
                str_starts_with($key, 'olympus_sms_') => 'services.olympus_sms.' . substr($key, 12),
                str_starts_with($key, 'firebase_') => 'services.firebase.' . substr($key, 9),
                str_starts_with($key, 'kemis_') => 'services.kemis.' . substr($key, 6),
                default => 'school.' . $key,
            };
            config()->set($configKey, $configValue);
        }

        return back()->with('success', 'School settings updated successfully.');
    }
}
