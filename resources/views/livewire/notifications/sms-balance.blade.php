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

    @if(auth()->user()->can('send notifications'))
        <section class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">Send SMS</h3>
            <livewire:notifications.send-notification />
        </section>
    @endif

    <div class="rounded-xl bg-white p-6 text-sm text-gray-600 shadow-sm">
        SMS balance, sending, delivery history, and recharge access are managed from this SMS Center. The balance shown above is fetched live from Olympus and is not stored as a fake local credit balance.
    </div>

    <section class="rounded-xl bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Sent messages</h3>
                <p class="text-sm text-gray-500">Messages reported by Olympus SMS, including delivery information where available.</p>
            </div>
            <span class="text-xs text-gray-500">{{ number_format($messagesTotal) }} total</span>
        </div>

        @if($messagesError)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Olympus message history is temporarily unavailable: {{ $messagesError }}
                <button wire:click="refreshMessagesNow" class="ml-2 font-semibold underline">Try again</button>
            </div>
        @elseif($messagesLoading)
            <p class="py-8 text-center text-sm text-gray-500">Loading sent messages...</p>
        @elseif(empty($messages))
            <p class="py-8 text-center text-sm text-gray-500">No messages were returned by Olympus.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Recipient</th><th class="px-3 py-2 text-left">Message</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Sent</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($messages as $message)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-3">{{ data_get($message, 'recipient', data_get($message, 'to', '—')) }}</td>
                                <td class="min-w-[18rem] max-w-xl px-3 py-3">{{ data_get($message, 'message', data_get($message, 'body', '—')) }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ ucfirst((string) data_get($message, 'status', data_get($message, 'delivery_status', 'Reported'))) }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ data_get($message, 'created_at', data_get($message, 'sent_at', data_get($message, 'schedule_time', '—'))) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($messagesLastPage > 1)
                <div class="mt-4 flex items-center justify-between text-sm">
                    <button wire:click="previousMessagesPage" wire:loading.attr="disabled" @disabled($messagesPage <= 1) class="rounded-lg border px-3 py-2 disabled:opacity-50">Previous</button>
                    <span>Page {{ $messagesPage }} of {{ $messagesLastPage }}</span>
                    <button wire:click="nextMessagesPage" wire:loading.attr="disabled" @disabled($messagesPage >= $messagesLastPage) class="rounded-lg border px-3 py-2 disabled:opacity-50">Next</button>
                </div>
            @endif
        @endif
    </section>
</div>
