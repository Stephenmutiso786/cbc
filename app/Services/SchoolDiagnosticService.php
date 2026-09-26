<?php

namespace App\Services;

class SchoolDiagnosticService
{
    /**
     * A transparent local operational diagnostic. It deliberately does not
     * misuse the learner-risk CatBoost model with unrelated ticket payloads.
     */
    public function assess(array $payload): array
    {
        $message = strtolower((string) ($payload['message'] ?? $payload['description'] ?? ''));
        $prediction = str_contains($message, 'login') || str_contains($message, 'password') ? 'Account access issue'
            : (str_contains($message, 'payment') || str_contains($message, 'mpesa') ? 'Payment configuration issue'
            : (str_contains($message, 'timetable') ? 'Timetable configuration issue' : 'General school system request'));
        $action = match ($prediction) {
            'Account access issue' => 'Review the affected user account status and reset access through User Accounts.',
            'Payment configuration issue' => 'Review the school billing and M-Pesa configuration before retrying the payment.',
            'Timetable configuration issue' => 'Review teacher allocations and the timetable conflict report before publishing.',
            default => 'Review the submitted support details and assign the request to the appropriate school administrator.',
        };
        return ['prediction' => $prediction, 'confidence' => 100, 'recommended_action' => $action];
    }
}
