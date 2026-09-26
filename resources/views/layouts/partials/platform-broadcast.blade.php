@php($latestPlatformBroadcast = \App\Models\PlatformBroadcast::where('status', 'sent')->where(fn ($query) => $query->where('is_pinned', true)->orWhere('sent_at', '>=', now()->subDays(7)))->orderByDesc('is_pinned')->latest('sent_at')->first())
@if ($latestPlatformBroadcast && ! in_array($latestPlatformBroadcast->id, session('dismissed_broadcasts', [])))
    <div class="mb-4 flex items-start justify-between rounded-lg border border-yellow-300 bg-yellow-50 p-4">
        <div><p class="font-semibold text-yellow-900">📢 {{ $latestPlatformBroadcast->title }}</p><p class="mt-1 text-sm text-yellow-800">{{ $latestPlatformBroadcast->message }}</p></div>
        <form method="POST" action="{{ route('broadcasts.dismiss', $latestPlatformBroadcast) }}">@csrf<button type="submit" class="ml-4 text-yellow-700 hover:text-yellow-900" aria-label="Dismiss">&times;</button></form>
    </div>
@endif
