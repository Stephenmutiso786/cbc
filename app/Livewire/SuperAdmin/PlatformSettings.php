<?php

namespace App\Livewire\SuperAdmin;

use App\Models\SystemSetting;
use App\Models\SmsCreditOrder;
use Illuminate\Support\Facades\Crypt;
use App\Services\OlympusSmsService;
use App\Services\SmsCapacityService;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Super-admin-only settings for the shared ElimuHub platform. */
class PlatformSettings extends Component
{
    use WithFileUploads;

    public string $platformName = '';
    public string $platformTagline = '';
    public string $platformFooter = '';
    public string $supportEmail = '';
    public string $supportPhone = '';
    public string $smsApiUrl = '';
    public string $smsSenderId = '';
    public string $smsApiToken = '';
    public string $smsTestPhone = '';
    public string $mpesaEnvironment = 'sandbox';
    public string $mpesaConsumerKey = '';
    public string $mpesaConsumerSecret = '';
    public string $mpesaShortcode = '';
    public string $mpesaPasskey = '';
    public string $mpesaCallbackUrl = '';
    public string $smsUnitPrice = '1';
    public string $paymentProvider = 'mpesa';
    public string $payheroBaseUrl = 'https://backend.payhero.co.ke';
    public string $payheroUsername = '';
    public string $payheroPassword = '';
    public string $payheroChannelId = '';
    public string $payheroCallbackUrl = '';
    public $loginLogo;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);

        $this->platformName = (string) (SystemSetting::get('platform_name') ?: config('platform.name'));
        $this->platformTagline = (string) (SystemSetting::get('platform_tagline') ?: config('platform.tagline'));
        $this->platformFooter = (string) (SystemSetting::get('platform_footer') ?: config('platform.footer'));
        $this->supportEmail = (string) SystemSetting::get('platform_support_email', config('platform.support_email'));
        $this->supportPhone = (string) SystemSetting::get('platform_support_phone', config('platform.support_phone'));
        $this->smsApiUrl = (string) SystemSetting::get('olympus_sms_api_url', config('services.olympus_sms.api_url'));
        $this->smsSenderId = (string) SystemSetting::get('olympus_sms_sender_id', config('services.olympus_sms.sender_id'));
        $this->mpesaEnvironment = (string) SystemSetting::get('platform_mpesa_env', config('services.platform_mpesa.env', 'sandbox'));
        $this->mpesaShortcode = (string) SystemSetting::get('platform_mpesa_shortcode', config('services.platform_mpesa.shortcode'));
        $this->mpesaCallbackUrl = (string) SystemSetting::get('platform_mpesa_callback_url', config('services.platform_mpesa.callback_url'));
        $this->smsUnitPrice = (string) SystemSetting::get('platform_mpesa_sms_unit_price', config('services.platform_mpesa.sms_unit_price', 1));
        $this->paymentProvider = (string) SystemSetting::get('platform_payment_provider', config('services.platform_payments.default', 'mpesa'));
        $this->payheroBaseUrl = (string) SystemSetting::get('payhero_base_url', config('services.payhero.base_url'));
        $this->payheroChannelId = (string) SystemSetting::get('payhero_channel_id', config('services.payhero.channel_id'));
        $this->payheroCallbackUrl = (string) SystemSetting::get('payhero_callback_url', config('services.payhero.callback_url', url('/api/subscription/payhero/callback')));
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
        $this->validate([
            'platformName' => ['required', 'string', 'max:80'],
            'platformTagline' => ['nullable', 'string', 'max:160'],
            'platformFooter' => ['nullable', 'string', 'max:120'],
            'supportEmail' => ['nullable', 'email', 'max:255'],
            'supportPhone' => ['nullable', 'string', 'max:50'],
            'smsApiUrl' => ['nullable', 'url', 'max:500'],
            'smsSenderId' => ['nullable', 'string', 'max:11'],
            'smsApiToken' => ['nullable', 'string', 'max:500'],
            'mpesaEnvironment' => ['required', 'in:sandbox,production'],
            'mpesaConsumerKey' => ['nullable', 'string', 'max:500'],
            'mpesaConsumerSecret' => ['nullable', 'string', 'max:500'],
            'mpesaShortcode' => ['nullable', 'string', 'max:50'],
            'mpesaPasskey' => ['nullable', 'string', 'max:500'],
            'mpesaCallbackUrl' => ['nullable', 'url', 'max:500'],
            'smsUnitPrice' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'paymentProvider' => ['required', 'in:mpesa,payhero'],
            'payheroBaseUrl' => ['required_if:paymentProvider,payhero', 'nullable', 'url', 'max:500'],
            'payheroUsername' => ['nullable', 'string', 'max:500'],
            'payheroPassword' => ['nullable', 'string', 'max:500'],
            'payheroChannelId' => ['nullable', 'integer', 'min:1'],
            'payheroCallbackUrl' => ['nullable', 'url', 'max:500'],
            'loginLogo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        foreach ([
            'platform_name' => $this->platformName,
            'platform_tagline' => $this->platformTagline,
            'platform_footer' => $this->platformFooter,
            'platform_support_email' => $this->supportEmail,
            'platform_support_phone' => $this->supportPhone,
            'olympus_sms_api_url' => $this->smsApiUrl,
            'olympus_sms_sender_id' => $this->smsSenderId,
            'platform_mpesa_env' => $this->mpesaEnvironment,
            'platform_mpesa_shortcode' => $this->mpesaShortcode,
            'platform_mpesa_callback_url' => $this->mpesaCallbackUrl,
            'platform_mpesa_sms_unit_price' => $this->smsUnitPrice,
            'platform_payment_provider' => $this->paymentProvider,
            'payhero_base_url' => $this->payheroBaseUrl,
            'payhero_channel_id' => $this->payheroChannelId,
            'payhero_callback_url' => $this->payheroCallbackUrl,
        ] as $key => $value) {
            SystemSetting::put($key, $value);
        }

        if ($this->loginLogo) {
            SystemSetting::put('platform_logo_data', 'data:' . $this->loginLogo->getMimeType() . ';base64,' . base64_encode(file_get_contents($this->loginLogo->getRealPath())));
        }

        foreach ([
            'olympus_sms_api_token' => $this->smsApiToken,
            'platform_mpesa_consumer_key' => $this->mpesaConsumerKey,
            'platform_mpesa_consumer_secret' => $this->mpesaConsumerSecret,
            'platform_mpesa_passkey' => $this->mpesaPasskey,
            'payhero_username' => $this->payheroUsername,
            'payhero_password' => $this->payheroPassword,
        ] as $key => $value) {
            if ($value !== '') {
                SystemSetting::put($key, 'enc:' . Crypt::encryptString($value));
            }
        }

        $this->smsApiToken = $this->mpesaConsumerKey = $this->mpesaConsumerSecret = $this->mpesaPasskey = $this->payheroUsername = $this->payheroPassword = '';
        $this->loginLogo = null;
        session()->flash('success', 'Global platform settings saved. Secret fields remain hidden after saving.');
    }

    public function refreshSmsCapacity(SmsCapacityService $capacity): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
        try {
            $balance = $capacity->refreshProviderBalance();
            $fulfilled = $capacity->fulfilHeldOrders();
            session()->flash('success', "Olympus balance refreshed: {$balance} SMS units available at the provider. {$fulfilled} held paid order(s) were automatically allocated.");
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('smsTestPhone', 'Could not refresh Olympus balance: ' . $exception->getMessage());
        }
    }


    /** Send one real delivery request using saved credentials or the token currently entered above. */
    public function testSms(OlympusSmsService $sms): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
        $this->validate(['smsTestPhone' => ['required', 'string', 'min:9', 'max:20']]);

        $token = $this->smsApiToken !== ''
            ? $this->smsApiToken
            : (string) SystemSetting::get('olympus_sms_api_token', config('services.olympus_sms.api_token'));
        if ($token === '') {
            $this->addError('smsTestPhone', 'Enter and save a valid Olympus API token before sending a test SMS.');
            return;
        }

        // Let a super-admin test values newly entered on this form without
        // exposing them or writing them to a school-level setting.
        config()->set('services.olympus_sms.api_url', $this->smsApiUrl);
        config()->set('services.olympus_sms.sender_id', $this->smsSenderId);
        config()->set('services.olympus_sms.api_token', $token);

        try {
            $sms->sendSms($this->smsTestPhone, 'ElimuHub SMS test: your platform gateway is configured.');
            session()->flash('success', 'Test SMS was accepted by the provider for delivery. Check the test phone and the provider message report.');
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('smsTestPhone', 'SMS provider rejected the test: ' . $exception->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.super-admin.platform-settings', [
            'smsCapacity' => app(SmsCapacityService::class)->summary(),
            'heldSmsOrders' => SmsCreditOrder::withoutSchoolScope()->with('school')->where('status', 'awaiting_allocation')->latest()->get(),
        ])->layout('layouts.app', ['title' => 'Global Platform Settings']);
    }
}
