<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\SchoolSettingAsset;
use App\Models\SystemSetting;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use App\Services\OlympusSmsService;
use App\Services\DataTransferPolicy;
use App\Services\GoogleDriveStorage;
use App\Services\TimetableTemplateService;
use Illuminate\Support\Facades\DB;

class AdminSettingsController extends Controller
{
    public function testSms(Request $request, OlympusSmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'test_phone' => ['required', 'string', 'max:50'],
            'test_message' => ['required', 'string', 'max:480'],
        ]);

        try {
            $sms->sendSms($data['test_phone'], $data['test_message']);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['test_phone' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Test SMS accepted by Olympus for delivery.');
    }

    public function testDrive(GoogleDriveStorage $drive): RedirectResponse
    {
        try {
            $drive->testConnection();
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['google_drive' => 'Google Drive connection failed: ' . $exception->getMessage()]);
        }

        return back()->with('success', 'Google Drive connection is working and the configured folder is accessible.');
    }

    public function connectDrive(Request $request, GoogleDriveStorage $drive): \Symfony\Component\HttpFoundation\Response
    {
        $state = bin2hex(random_bytes(32));
        $request->session()->put('google_drive_oauth_state', $state);

        return redirect()->away($drive->authorizationUrl($state));
    }

    public function googleDriveCallback(Request $request, GoogleDriveStorage $drive): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('google_drive_oauth_state');
        if ($expectedState === '' || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect()->route('admin.settings.index')->withErrors(['google_drive' => 'Google Drive connection could not be verified. Please try again.']);
        }
        if ($request->filled('error')) {
            return redirect()->route('admin.settings.index')->withErrors(['google_drive' => 'Google Drive authorization was cancelled.']);
        }

        try {
            $token = $drive->exchangeOAuthCode((string) $request->query('code'));
            $encoded = 'enc:' . Crypt::encryptString(json_encode($token, JSON_THROW_ON_ERROR));
            SchoolSetting::updateOrCreate(['key' => 'google_drive_oauth_token'], ['value' => $encoded]);
            config()->set('services.google_drive.oauth_token', json_encode($token, JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('admin.settings.index')->withErrors(['google_drive' => 'Google Drive connection failed: ' . $exception->getMessage()]);
        }

        return redirect()->route('admin.settings.index')->with('success', 'Google Drive connected successfully. Save a folder ID and enable Drive storage.');
    }

    public function disconnectDrive(): RedirectResponse
    {
        SchoolSetting::where('key', 'google_drive_oauth_token')->delete();
        config()->set('services.google_drive.oauth_token', null);
        return back()->with('success', 'Google Drive disconnected.');
    }

    public function update(Request $request, DataTransferPolicy $transferPolicy): RedirectResponse
    {
        $templateService = app(TimetableTemplateService::class);
        $templateKeys = array_keys($templateService->templates());
        // allow schools to choose a built-in template or a site-specific "custom" template
        $allowedCustom = ['custom'];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'motto' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['required', 'integer', 'min:2000', 'max:2200'],
            'current_term' => ['required', 'integer', 'between:1,3'],
            'timetable_template_lower_primary' => ['required', 'string', 'in:' . implode(',', array_merge(array_values(array_filter($templateKeys, fn ($key) => str_contains($key, 'lower-primary'))), $allowedCustom))],
            'timetable_template_upper_primary' => ['required', 'string', 'in:' . implode(',', array_merge(array_values(array_filter($templateKeys, fn ($key) => str_contains($key, 'upper-primary'))), $allowedCustom))],
            'timetable_template_junior_secondary' => ['required', 'string', 'in:' . implode(',', array_merge(array_values(array_filter($templateKeys, fn ($key) => str_contains($key, 'junior-secondary'))), $allowedCustom))],
            // Custom period definitions are comma separated start-end pairs, e.g. "08:20-08:50,08:50-09:20"
            'timetable_template_lower_primary_custom' => ['nullable', 'string', 'max:1000'],
            'timetable_template_upper_primary_custom' => ['nullable', 'string', 'max:1000'],
            'timetable_template_junior_secondary_custom' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:primary,secondary,mixed'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png'],
            'official_signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png'],
            'official_stamp' => ['nullable', 'image', 'mimes:jpg,jpeg,png'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_official_signature' => ['sometimes', 'boolean'],
            'remove_official_stamp' => ['sometimes', 'boolean'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:500'],
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
            'olympus_sms_sender_id' => ['nullable', 'string', 'max:11'],
            'firebase_server_key' => ['nullable', 'string', 'max:1000'],
            'firebase_project_id' => ['nullable', 'string', 'max:255'],
            'kemis_api_url' => ['nullable', 'url', 'max:500'],
            'kemis_api_key' => ['nullable', 'string', 'max:500'],
            'kemis_school_code' => ['nullable', 'string', 'max:100'],
            'google_drive_enabled' => ['required', 'boolean'],
            'google_drive_folder_id' => ['nullable', 'string', 'max:255'],
            'google_drive_credentials' => ['nullable', 'json', 'max:100000'],
            'google_drive_credentials_file' => ['nullable', 'file', 'mimes:json,txt', 'max:100'],
            'platform_mpesa_env' => ['nullable', 'in:sandbox,production'],
            'platform_mpesa_consumer_key' => ['nullable', 'string', 'max:500'],
            'platform_mpesa_consumer_secret' => ['nullable', 'string', 'max:500'],
            'platform_mpesa_shortcode' => ['nullable', 'string', 'max:50'],
            'platform_mpesa_passkey' => ['nullable', 'string', 'max:500'],
            'platform_mpesa_callback_url' => ['nullable', 'url', 'max:500'],
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

        // Logos are images and can be substantially larger than the normal
        // settings value column. Store them with the other long-form assets
        // so they survive every report-card / PDF rendering path.
        $assetData = [];
        if ($request->hasFile('logo')) {
            $assetData['logo_data'] = $transferPolicy->imageDataUrl($request->file('logo'));
        }
        unset($data['logo']);

        foreach (['official_signature', 'official_stamp'] as $upload) {
            if ($request->hasFile($upload)) {
                $data[$upload . '_data'] = $transferPolicy->imageDataUrl($request->file($upload));
            }
            unset($data[$upload]);
        }
        foreach (['official_signature_data', 'official_stamp_data'] as $key) {
            if (array_key_exists($key, $data)) {
                $assetData[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        $assetRemovals = [];
        foreach ([
            'remove_logo' => 'logo_data',
            'remove_official_signature' => 'official_signature_data',
            'remove_official_stamp' => 'official_stamp_data',
        ] as $requestKey => $assetKey) {
            if ($request->boolean($requestKey) && ! array_key_exists($assetKey, $assetData)) {
                $assetRemovals[] = $assetKey;
            }
            unset($data[$requestKey]);
        }

        // maintenance_mode / maintenance_message affect the whole platform
        // (including the shared, pre-login page), not one school — they're
        // stored separately and only super-admin may change them. A
        // school-admin's own submission simply leaves the current value alone.
        $globalMaintenance = [];
        foreach (['maintenance_mode', 'maintenance_message'] as $key) {
            if (array_key_exists($key, $data)) {
                if (auth()->user()->hasRole('super-admin')) {
                    $globalMaintenance[$key] = $data[$key];
                }
                unset($data[$key]);
            }
        }

        // SMS provider credentials are platform-wide now too (see the
        // centralize_sms_settings migration): the super-admin holds the one
        // provider account, and schools just draw down a credit balance.
        // Writing these into a specific school's settings would have no
        // effect since nothing reads them from there anymore.
        $globalSmsKeys = ['at_api_key', 'at_username', 'at_sender_id', 'at_env', 'olympus_sms_api_url', 'olympus_sms_api_token', 'olympus_sms_sender_id'];
        $platformMpesaKeys = ['platform_mpesa_env', 'platform_mpesa_consumer_key', 'platform_mpesa_consumer_secret', 'platform_mpesa_shortcode', 'platform_mpesa_passkey', 'platform_mpesa_callback_url'];
        foreach ([...$globalSmsKeys, ...$platformMpesaKeys] as $key) {
            if (array_key_exists($key, $data)) {
                if (auth()->user()->hasRole('super-admin') && $data[$key] !== '' && $data[$key] !== null) {
                    $globalMaintenance[$key] = $data[$key];
                }
                unset($data[$key]);
            }
        }

        $secretKeys = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey', 'firebase_server_key', 'kemis_api_key', 'google_drive_credentials'];
        $globalSecretKeys = ['at_api_key', 'olympus_sms_api_token', 'platform_mpesa_consumer_key', 'platform_mpesa_consumer_secret', 'platform_mpesa_passkey'];
        DB::transaction(function () use ($data, $secretKeys, $globalSecretKeys, $assetData, $assetRemovals, $globalMaintenance): void {
            foreach ($globalMaintenance as $key => $value) {
                if (in_array($key, $globalSecretKeys, true) && is_string($value) && $value !== '') {
                    $value = 'enc:' . Crypt::encryptString($value);
                }
                SystemSetting::put($key, $value);
            }
            foreach ($data as $key => $value) {
                $setting = SchoolSetting::firstOrNew(['key' => $key]);
                if (in_array($key, $secretKeys, true) && ($value === '' || $value === null)) {
                    // An environment variable is the production source of truth when
                    // no replacement secret was entered in the settings form.
                    continue;
                }
                $configValue = $value;
                if (in_array($key, $secretKeys, true)) {
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
            foreach ($assetData as $key => $value) {
                SchoolSettingAsset::updateOrCreate(['key' => $key], ['data' => $value]);
                config()->set('school.' . $key, $value);
            }
            foreach ($assetRemovals as $key) {
                SchoolSettingAsset::where('key', $key)->delete();
                // A few existing schools still have these assets in the old
                // short settings table. Remove both representations so a
                // deleted image can never reappear on a document.
                SchoolSetting::where('key', $key)->delete();
                config()->set('school.' . $key, null);
            }

            // Keep Manage Schools, billing, platform reports and this
            // school's Settings page on the same identity. Previously a
            // school-admin could change Settings while the central schools
            // record still displayed the old/default name.
            if (($schoolId = auth()->user()?->school_id) !== null) {
                $schoolIdentity = [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'motto' => $data['motto'],
                    'address' => $data['address'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                ];

                // Keep the canonical school record in sync as well. It is a
                // durable fallback for reports generated outside an
                // authenticated settings request and for older school rows.
                if (isset($assetData['logo_data'])) {
                    $schoolIdentity['logo_data'] = $assetData['logo_data'];
                }
                if (in_array('logo_data', $assetRemovals, true)) {
                    $schoolIdentity['logo_data'] = null;
                }

                School::whereKey($schoolId)->update($schoolIdentity);
            }
        });

        return back()->with('success', 'School settings updated successfully.');
    }
}
