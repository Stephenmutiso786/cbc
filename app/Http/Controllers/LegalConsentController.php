<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LegalConsentController extends Controller
{
    public function accept(Request $request)
    {
        $validated = $request->validate([
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'You must accept the Terms and Conditions to continue.',
            'accept_privacy.accepted' => 'You must accept the Privacy Policy to continue.',
        ]);

        $request->user()->forceFill([
            'legal_terms_accepted_at' => now(),
            'legal_privacy_accepted_at' => now(),
            'legal_acceptance_version' => config('legal.version'),
            'legal_acceptance_ip' => $request->ip(),
            'legal_acceptance_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        return redirect()->intended($this->portalFor($request->user()));
    }

    private function portalFor($user): string
    {
        if ($user->canAny(['manage system settings', 'manage roles', 'manage staff', 'manage curriculum'])) {
            return route('admin.dashboard');
        }
        if ($user->canAny(['enter marks', 'view assessments', 'view results', 'view notes', 'view timetable'])) {
            return route('teacher.dashboard');
        }
        if ($user->canAny(['view fees', 'record payments', 'view finance reports'])) {
            return route('finance.dashboard');
        }

        return route('login');
    }
}
