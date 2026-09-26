<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Subscription Plans</h1>
            <p class="text-sm text-gray-500">What schools pay to use the platform, and which modules each plan unlocks.</p>
        </div>
        <button wire:click="create" class="rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800">+ New plan</button>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($packages as $package)
            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-bold text-gray-800">{{ $package->name }}</p>
                        <p class="text-2xl font-bold text-green-700 mt-1">KSh {{ number_format($package->price, 0) }}<span class="text-sm font-normal text-gray-500">/student/{{ rtrim($package->billing_cycle, 'ly') === 'term' ? 'term' : $package->billing_cycle }}</span></p>
                    </div>
                    @if ($package->is_active)
                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Active</span>
                    @else
                        <span class="rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Retired</span>
                    @endif
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ $package->description }}</p>
                <p class="mt-2 text-xs text-gray-500">{{ $package->schools_count }} school(s) on this plan · SMS units are paid and allocated separately after payment confirmation.</p>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($package->features ?? [] as $feature)
                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{{ \App\Livewire\SuperAdmin\PackageManager::AVAILABLE_FEATURES[$feature] ?? $feature }}</span>
                    @endforeach
                </div>
                <div class="mt-4 flex gap-3">
                    <button wire:click="edit({{ $package->id }})" class="text-sm font-semibold text-green-700 hover:underline">Edit</button>
                    <button wire:click="toggleActive({{ $package->id }})" wire:confirm="Are you sure?" class="text-sm font-semibold text-gray-500 hover:underline">
                        {{ $package->is_active ? 'Retire' : 'Reactivate' }}
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    @if ($showForm)
        <div class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4" wire:click.self="$set('showForm', false)" role="dialog" aria-modal="true" aria-labelledby="package-form-title">
            <div class="modal-panel my-6 max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto p-6">
                <h2 id="package-form-title" class="text-lg font-bold text-gray-800">{{ $editingId ? 'Edit plan' : 'New plan' }}</h2>

                <form wire:submit="save" class="space-y-4">
                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plan name</span>
                        <input wire:model="form.name" placeholder="e.g. Shule Basic" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        @error('form.name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Description</span>
                        <textarea wire:model="form.description" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"></textarea>
                    </label>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Price (KSh per student)</span>
                            <input wire:model="form.price" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            @error('form.price') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Billing cycle</span>
                            <select wire:model="form.billing_cycle" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                                <option value="termly">Per term</option>
                                <option value="monthly">Per month</option>
                                <option value="yearly">Per year</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Max students (blank = unlimited)</span>
                            <input wire:model="form.max_students" type="number" min="1" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Max staff (blank = unlimited)</span>
                            <input wire:model="form.max_staff" type="number" min="1" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        </label>
                    </div>

                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Modules included in this plan</span>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach (\App\Livewire\SuperAdmin\PackageManager::AVAILABLE_FEATURES as $key => $label)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="features.{{ $key }}"> {{ $label }}</label>
                            @endforeach
                        </div>
                    </div>

                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active"> <span class="text-sm">Offered to schools</span></label>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
