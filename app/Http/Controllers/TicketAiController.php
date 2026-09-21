cat << 'EOF' > app/Http/Controllers/TicketAiController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TicketAiController extends Controller
{
    public function predict(Request $request, $code)
    {
        $lang = $request->header('Accept-Language', 'en');

        $ticketPayload = [
            'area' => $request->input('area', 'Login / access'),
            'device' => $request->input('device', 'Windows 11 desktop'),
            'userReport' => $request->input('userReport', 'PC fails to join domain by name'),
            'errorMessage' => $request->input('errorMessage', 'Domain controller could not be contacted'),
            'schoolName' => $request->input('schoolName', 'Kyandulu School'),
        ];

        try {
            $catboostUrl = config('services.catboost.url', 'http://127.0.0.1:8000/api/ml/predict-ticket');
            
            $response = Http::withHeaders([
                'Accept-Language' => $lang,
                'Content-Type' => 'application/json',
            ])->post($catboostUrl, $ticketPayload);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'CatBoost Service Unavailable',
                'message' => $e->getMessage()
            ], 503);
        }
    }
}
EOF
