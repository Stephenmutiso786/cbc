<div class="mx-auto max-w-2xl space-y-6">
    <div><p class="text-sm font-semibold uppercase tracking-widest text-indigo-600">ElimuHub platform control</p><h1 class="text-2xl font-bold text-gray-900">Maintenance mode</h1><p class="mt-1 text-sm text-gray-600">This is platform-wide. When enabled, only super-admin accounts can access the system; every school account and visitor receives the maintenance page.</p></div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
    <form wire:submit="save" class="card space-y-5 p-6">
        <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4"><input wire:model="enabled" type="checkbox" class="mt-1 h-5 w-5"><span><span class="block font-semibold text-gray-900">Enable platform maintenance mode</span><span class="mt-1 block text-sm text-gray-600">School administrators, staff, parents, and learners will be unable to use the application until you turn it off.</span></span></label>
        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Public maintenance message</span><textarea wire:model="message" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></textarea>@error('message')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
        <button class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-800">Save platform maintenance settings</button>
    </form>
</div>
