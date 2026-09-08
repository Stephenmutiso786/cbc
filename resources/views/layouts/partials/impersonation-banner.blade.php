@if(session('impersonator_id'))
<div class="flex items-center justify-between gap-3 bg-amber-500 px-4 py-2 text-sm font-semibold text-amber-950"><span>You are impersonating {{ auth()->user()->name }}. Activity is being audited.</span><form method="POST" action="{{ route('impersonate.stop.global') }}">@csrf<button class="rounded bg-white/80 px-3 py-1 text-xs font-bold">Stop impersonating</button></form></div>
@endif
