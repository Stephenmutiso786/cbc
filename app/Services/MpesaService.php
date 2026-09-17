<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private string $env;
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;
    private string $baseUrl;

    public function __construct(?array $credentials = null)
    {
        $credentials ??= [
            'env'             => config('services.mpesa.env', 'sandbox'),
            'consumer_key'    => config('services.mpesa.consumer_key', ''),
            'consumer_secret' => config('services.mpesa.consumer_secret', ''),
            'shortcode'       => config('services.mpesa.shortcode', ''),
            'passkey'         => config('services.mpesa.passkey', ''),
            'callback_url'    => config('services.mpesa.callback_url'),
        ];

        $this->env            = $credentials['env'] ?? 'sandbox';
        $this->consumerKey    = $credentials['consumer_key'] ?? '';
        $this->consumerSecret = $credentials['consumer_secret'] ?? '';
        $this->shortcode      = $credentials['shortcode'] ?? '';
        $this->passkey        = $credentials['passkey'] ?? '';
        $this->callbackUrl    = $credentials['callback_url'] ?? '';
        $this->baseUrl        = $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * The platform's own M-Pesa till/paybill — used for school subscription
     * billing, deliberately separate from each school's own configured
     * credentials (which are for collecting fees from parents).
     */
    public static function platform(): self
    {
        return new self([
            'env'             => config('services.platform_mpesa.env', 'sandbox'),
            'consumer_key'    => config('services.platform_mpesa.consumer_key', ''),
            'consumer_secret' => config('services.platform_mpesa.consumer_secret', ''),
            'shortcode'       => config('services.platform_mpesa.shortcode', ''),
            'passkey'         => config('services.platform_mpesa.passkey', ''),
            'callback_url'    => config('services.platform_mpesa.callback_url'),
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->consumerKey !== '' && $this->consumerSecret !== '' && $this->shortcode !== '' && $this->passkey !== '';
    }

    /** Get OAuth access token */
    public function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->get("{$this->baseUrl}/oauth/v1/generate", ['grant_type' => 'client_credentials']);

        if (!$response->successful()) {
            throw new \RuntimeException('M-Pesa token request failed: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /** Initiate Lipa na M-Pesa STK Push */
    public function stkPush(string $phone, float $amount, string $accountRef, string $description = 'Payment'): array
    {
        $token     = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => $this->shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => (int) ceil($amount),
                'PartyA'            => $this->formatPhone($phone),
                'PartyB'            => $this->shortcode,
                'PhoneNumber'       => $this->formatPhone($phone),
                'CallBackURL'       => $this->callbackUrl,
                'AccountReference'  => $accountRef,
                'TransactionDesc'   => $description,
            ]);

        $result = $response->json();
        Log::info('M-Pesa STK Push', ['phone' => $phone, 'amount' => $amount, 'result' => $result]);

        return $result;
    }

    /** Query STK Push transaction status */
    public function stkQuery(string $checkoutRequestId): array
    {
        $token     = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                'BusinessShortCode' => $this->shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ]);

        return $response->json();
    }

    /** Register C2B URLs with Safaricom */
    public function registerC2BUrls(): array
    {
        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/c2b/v1/registerurl", [
                'ShortCode'       => $this->shortcode,
                'ResponseType'    => 'Completed',
                'ConfirmationURL' => config('services.mpesa.confirmation_url'),
                'ValidationURL'   => config('services.mpesa.validation_url'),
            ]);

        return $response->json();
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            return '254' . substr($phone, 1);
        }
        if (str_starts_with($phone, '+')) {
            return substr($phone, 1);
        }
        return $phone;
    }
}
