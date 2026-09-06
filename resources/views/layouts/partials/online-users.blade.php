@php
    try {
        $onlineUsers = \App\Models\User::query()
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'last_seen_at']);
    } catch (\Throwable) {
        $onlineUsers = collect();
    }
@endphp
<details class="relative">
    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg border border-green-200 px-3 py-2 text-sm text-gray-700 hover:bg-green-50">
        <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
        <span>Online ({{ $onlineUsers->count() }})</span>
    </summary>
    <div class="absolute right-0 top-11 z-50 w-64 rounded-xl border border-gray-200 bg-white p-3 shadow-xl">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Currently online</p>
        @forelse($onlineUsers as $onlineUser)
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0">
                <span class="truncate text-sm font-medium text-gray-800">{{ $onlineUser->name }}</span>
                <span class="shrink-0 text-xs text-green-600">Online</span>
            </div>
        @empty
            <p class="py-2 text-sm text-gray-500">No active users.</p>
        @endforelse
        <p class="mt-2 text-[11px] text-gray-400">Active within the last 5 minutes</p>
    </div>
</details>
<script>
    (() => {
        const ping = () => fetch(@json(route('presence.ping')), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        }).catch(() => {});
        window.setInterval(ping, 60000);
    })();
</script>
