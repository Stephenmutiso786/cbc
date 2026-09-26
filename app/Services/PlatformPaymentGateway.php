<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Platform-only subscription/SMS checkout gateway; never used for parent fees. */
class PlatformPaymentGateway
{
    public function provider(): string
    {
        return (string) config('services.platform_payments.default', 'mpesa');
    }

    public function configured(): bool
    {
        if ($this->provider() === 'payhero') {
            return (string) config('services.payhero.username') !== ''
                && (string) config('services.payhero.password') !== ''
                && (string) config('services.payhero.channel_id') !== '';
        }
        return MpesaService::platform()->isConfigured();
    }

    public function initiate(string $phone, float $amount, string $externalReference, string $description, string $customerName): array
    {
        if ($this->provider() !== 'payhero') {
            $response = MpesaService::platform()->stkPush($phone, $amount, $externalReference, $description);
            return [
                'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                'message' => $response['CustomerMessage'] ?? 'STK Push sent. Enter your M-Pesa PIN to complete payment.',
                'raw' => $response,
            ];
        }

        $response = Http::acceptJson()->asJson()
            ->withBasicAuth((string) config('services.payhero.username'), (string) config('services.payhero.password'))
            ->post(rtrim((string) config('services.payhero.base_url'), '/') . '/api/v2/payments', [
                'amount' => (int) ceil($amount),
                'phone_number' => $phone,
                'channel_id' => (int) config('services.payhero.channel_id'),
                'provider' => 'm-pesa',
                'external_reference' => $externalReference,
                'customer_name' => $customerName,
                'callback_url' => config('services.payhero.callback_url'),
            ]);
        $data = $response->json() ?: [];
        if (! $response->successful() || empty($data['success']) || empty($data['reference'])) {
            throw new \RuntimeException($data['error_message'] ?? $data['message'] ?? 'PayHero rejected the payment request.');
        }
        return [
            'checkout_request_id' => $data['reference'],
            'merchant_request_id' => $data['CheckoutRequestID'] ?? null,
            'message' => 'PayHero STK Push sent. Enter your M-Pesa PIN to complete payment.',
            'raw' => $data,
        ];
    }

    /** Verify a PayHero reference server-to-server before crediting an order. */
    public function verifyPayhero(string $reference): array
    {
        $response = Http::acceptJson()->withBasicAuth(
            (string) config('services.payhero.username'),
            (string) config('services.payhero.password')
        )->get(rtrim((string) config('services.payhero.base_url'), '/') . '/api/v2/transaction-status', ['reference' => $reference]);
        $data = $response->json() ?: [];
        if (! $response->successful()) throw new \RuntimeException($data['error_message'] ?? 'PayHero status verification failed.');
        return $data;
    }
}
