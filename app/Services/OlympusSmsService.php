<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OlympusSmsService
{
    /**
     * Read the provider balance without changing anything on the account.
     */
    public function getBalance(): array
    {
        $token = (string) config('services.olympus_sms.api_token');
        if ($token === '') {
            throw new RuntimeException('Olympus SMS API token is not configured. Add it in Admin Settings.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(20)
            ->retry(2, 500)
            ->get($this->baseUrl() . '/api/v3/balance');

        $result = $response->json() ?: ['status' => 'error', 'message' => $response->body()];

        if (!$response->successful() || ($result['status'] ?? null) !== 'success') {
            throw new RuntimeException('Unable to read Olympus SMS balance: ' . ($result['message'] ?? 'Unknown provider error'));
        }

        $units = $this->findBalanceValue($result['data'] ?? $result);
        if ($units === null) {
            Log::warning('Olympus SMS balance response did not contain a unit value', [
                'http_status' => $response->status(),
                'data_type' => get_debug_type($result['data'] ?? null),
                'data_keys' => is_array($result['data'] ?? null) ? array_keys($result['data']) : [],
            ]);
            throw new RuntimeException('Olympus returned a successful balance response, but no SMS unit value was found. Check the provider response format.');
        }

        return [
            'status' => 'success',
            'data' => $result['data'] ?? null,
            'units' => $units,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function sendSms(string|array $recipients, string $message): array
    {
        $token = (string) config('services.olympus_sms.api_token');
        if ($token === '') {
            throw new RuntimeException('Olympus SMS API token is not configured. Add it in Admin Settings.');
        }

        $phones = is_array($recipients)
            ? implode(',', array_map([$this, 'formatPhone'], $recipients))
            : $this->formatPhone($recipients);

        $payload = [
            'recipient' => $phones,
            'sender_id' => (string) config('services.olympus_sms.sender_id', 'SCHOOL'),
            // Olympus requires the voice value for text messages on this endpoint.
            'type' => 'voice',
            'language' => 'en-gb',
            'gender' => 'female',
            'message' => $message,
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500)
            ->post($this->baseUrl() . '/api/v3/sms/send', $payload);

        $result = $response->json() ?: ['status' => 'error', 'message' => $response->body()];
        Log::info('Olympus SMS request completed', [
            'recipients_count' => is_array($recipients) ? count($recipients) : 1,
            'http_status' => $response->status(),
            'status' => $result['status'] ?? null,
        ]);

        if (!$response->successful() || ($result['status'] ?? null) !== 'success') {
            throw new RuntimeException('Olympus SMS failed: ' . ($result['message'] ?? 'Unknown provider error'));
        }

        return $result;
    }

    public function sendBulkSms(array $recipients, string $message): array
    {
        return collect(array_chunk($recipients, 50))
            ->map(fn (array $batch) => $this->sendSms($batch, $message))
            ->all();
    }

    /**
     * Read the provider's paginated inbound and outbound message reports.
     */
    public function getMessages(int $page = 1): array
    {
        $token = (string) config('services.olympus_sms.api_token');
        if ($token === '') {
            throw new RuntimeException('Olympus SMS API token is not configured. Add it in Admin Settings.');
        }

        $request = Http::withToken($token)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(5);
        $response = $page > 1
            ? $request->get($this->baseUrl() . '/api/v3/sms', ['page' => $page])
            : $request->get($this->baseUrl() . '/api/v3/sms');

        $result = $response->json() ?: ['status' => 'error', 'message' => $response->body()];
        if (!$response->successful() || ($result['status'] ?? null) !== 'success') {
            throw new RuntimeException('Unable to read Olympus SMS messages: ' . ($result['message'] ?? 'Unknown provider error'));
        }

        $data = $result['data'] ?? [];
        $items = is_array($data) && isset($data['data']) && is_array($data['data'])
            ? $data['data']
            : (is_array($data) ? $data : []);

        return [
            'items' => array_values(array_filter($items, 'is_array')),
            'meta' => is_array($data) ? [
                'current_page' => (int) ($data['current_page'] ?? $page),
                'last_page' => (int) ($data['last_page'] ?? $page),
                'total' => (int) ($data['total'] ?? count($items)),
            ] : ['current_page' => $page, 'last_page' => $page, 'total' => 0],
        ];
    }

    private function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));
        if (str_starts_with($digits, '0')) {
            return '254' . substr($digits, 1);
        }
        if (str_starts_with($digits, '7') || str_starts_with($digits, '1')) {
            return '254' . $digits;
        }
        return $digits;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.olympus_sms.api_url', 'https://sms.ots.co.ke'), '/') ?: 'https://sms.ots.co.ke';
    }

    /**
     * Olympus has used different names for the returned balance field. Keep
     * the raw provider data available while displaying common numeric keys.
     */
    private function findBalanceValue(mixed $value): int|float|string|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/(?<![\d.])\d+(?:\.\d+)?(?![\d.])/', $value, $match)) {
            return str_contains($match[0], '.') ? (float) $match[0] : (int) $match[0];
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (['balance', 'sms_balance', 'sms_unit', 'sms_units', 'sms_units_balance', 'units', 'credits', 'remaining', 'available', 'quantity', 'count', 'total'] as $key) {
            if (array_key_exists($key, $value) && is_scalar($value[$key])) {
                return $value[$key];
            }
        }

        foreach ($value as $nested) {
            $found = $this->findBalanceValue($nested);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
