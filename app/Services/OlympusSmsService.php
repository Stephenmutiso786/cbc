<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OlympusSmsService
{
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
            'type' => 'plain',
            'message' => $message,
        ];

        $baseUrl = rtrim((string) config('services.olympus_sms.api_url', 'https://sms.ots.co.ke'), '/') ?: 'https://sms.ots.co.ke';
        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500)
            ->post($baseUrl . '/api/v3/sms/send', $payload);

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
}
