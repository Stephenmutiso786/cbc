<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-800">SMS Center</h2>
            <p class="text-sm text-gray-500">Live balance and account management for Olympus SMS.</p>
        </div>
        <button wire:click="refresh" wire:loading.attr="disabled"
                class="rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800 disabled:opacity-60">
            <span wire:loading.remove wire:target="refresh">Refresh balance</span>
            <span wire:loading wire:target="refresh">Checking...</span>
        </button>
    </div>

    @if($error)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $error }}
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-xl bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Remaining SMS units</p>
            <p class="mt-3 text-4xl font-bold text-green-700">
                @if($loading)
                    <span class="text-2xl text-gray-400">Loading...</span>
                @elseif($units !== null)
                    {{ number_format((float) $units, 0) }}
                @else
                    <span class="text-2xl text-gray-400">Unavailable</span>
                @endif
            </p>
            @if($checkedAt)
                <p class="mt-2 text-xs text-gray-500">Last checked {{ \Illuminate\Support\Carbon::parse($checkedAt)->format('d M Y, H:i') }}</p>
            @endif
        </section>

        <section class="rounded-xl border border-amber-200 bg-amber-50 p-6">
            <h3 class="font-semibold text-gray-800">Recharge SMS</h3>
            <p class="mt-2 text-sm leading-6 text-gray-700">
                Recharge is completed securely in your Olympus account. After payment, return here and refresh the balance.
            </p>
            <a href="{{ config('services.olympus_sms.portal_url', 'https://sms.ots.co.ke/login') }}"
               target="_blank" rel="noopener noreferrer"
               class="mt-4 inline-flex rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                Open Olympus recharge portal
            </a>
            <p class="mt-3 text-xs text-gray-600">Olympus has not published a recharge/top-up API in the supplied documentation, so the system does not simulate or collect payments.</p>
        </section>
    </div>

    <div class="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm">
        SMS sending remains available from <a class="font-medium text-green-700 hover:underline" href="{{ route('admin.notifications.index') }}">Notifications</a>. The balance shown above is fetched live from Olympus and is not stored as a fake local credit balance.
    </div>
</div>
