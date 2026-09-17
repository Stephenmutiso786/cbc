<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Broadcast Center</h1>
        <p class="text-sm text-gray-500">Send a high-priority announcement to every school at once.</p>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="card p-5 space-y-4">
        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Title</span>
            <input wire:model="title" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
            @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Message</span>
            <textarea wire:model="message" rows="4" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></textarea>
            @error('message') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="flex items-start gap-2">
            <input type="checkbox" wire:model="sendSms" class="mt-1">
            <span class="text-sm text-gray-700">Also send via SMS to every school's guardians <span class="block text-xs text-yellow-700">Uses each school's own SMS credit balance — only enable if schools have been informed.</span></span>
        </label>
        <button wire:click="send" wire:confirm="Send this to every active school now?" class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">
            Send broadcast
        </button>
    </div>

    <div class="card overflow-x-auto">
        <h2 class="p-4 font-bold text-gray-800">History</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Title</th><th class="px-4 py-3">SMS?</th><th class="px-4 py-3">Schools notified</th><th class="px-4 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($broadcasts as $broadcast)
                    <tr>
                        <td class="px-4 py-3">{{ $broadcast->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $broadcast->title }}</td>
                        <td class="px-4 py-3">{{ $broadcast->send_sms ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $broadcast->schools_notified }} / {{ $broadcast->total_schools }}</td>
                        <td class="px-4 py-3 capitalize">{{ $broadcast->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No broadcasts sent yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

