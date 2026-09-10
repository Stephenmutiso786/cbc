<?php

namespace App\Http\Controllers;

use App\Models\CookieConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CookieConsentController extends Controller
{
    public function store(Request $request)
    {
        $preferences = $request->validate([
            'functional' => ['required', 'boolean'],
            'analytics' => ['required', 'boolean'],
        ]);

        $existing = json_decode((string) $request->cookie(config('privacy.cookie_consent_name', 'cbe_cookie_consent')), true);
        $token = is_array($existing) && isset($existing['id']) && Str::isUuid($existing['id'])
            ? $existing['id']
            : (string) Str::uuid();
        $consent = CookieConsent::updateOrCreate(
            ['consent_token' => $token],
            [
                'user_id' => $request->user()?->id,
                'essential' => true,
                'functional' => (bool) $preferences['functional'],
                'analytics' => (bool) $preferences['analytics'],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'consented_at' => now(),
            ]
        );

        $payload = json_encode([
            'id' => $consent->consent_token,
            'essential' => true,
            'functional' => $consent->functional,
            'analytics' => $consent->analytics,
            'version' => config('privacy.cookie_policy_version'),
        ], JSON_THROW_ON_ERROR);

        return back()->withCookie(cookie(
            config('privacy.cookie_consent_name', 'cbe_cookie_consent'),
            $payload,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));
    }
}
