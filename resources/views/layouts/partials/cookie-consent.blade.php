@php($cookieName = config('privacy.cookie_consent_name', 'cbe_cookie_consent'))
@php($cookieConsent = json_decode((string) request()->cookie($cookieName), true))
@unless(is_array($cookieConsent) && ($cookieConsent['version'] ?? null) === config('privacy.cookie_policy_version'))
<aside id="cookie-consent" class="fixed inset-x-3 bottom-3 z-[100] mx-auto max-w-3xl rounded-2xl border border-green-200 bg-white p-5 shadow-2xl" role="dialog" aria-labelledby="cookie-consent-title" aria-describedby="cookie-consent-copy">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 id="cookie-consent-title" class="font-semibold text-gray-900">Cookies and privacy</h2>
            <p id="cookie-consent-copy" class="mt-1 text-sm text-gray-600">We use essential cookies for sign-in, security and this school system. Optional functional and analytics cookies are off unless you choose them.</p>
            <a href="{{ route('legal.privacy') }}#cookies" class="mt-2 inline-block text-xs font-medium text-green-700 underline">Read the cookie section</a>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <form method="POST" action="{{ route('cookie-consent.store') }}">@csrf<input type="hidden" name="functional" value="0"><input type="hidden" name="analytics" value="0"><button class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Essential only</button></form>
            <form method="POST" action="{{ route('cookie-consent.store') }}">@csrf<input type="hidden" name="functional" value="1"><input type="hidden" name="analytics" value="1"><button class="rounded-lg bg-green-700 px-3 py-2 text-sm font-semibold text-white hover:bg-green-800">Accept all</button></form>
        </div>
    </div>
</aside>
@endunless
