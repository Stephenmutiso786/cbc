<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Schools</h1>
            <p class="text-sm text-gray-500">Every school (tenant) using this system, and their first admin account.</p>
        </div>
        <button wire:click="create" class="rounded-lg bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800">+ New school</button>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if ($generatedPassword)
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-900">
            <p class="font-semibold">Admin login for the new school (shown once):</p>
            <p class="mt-1">Email: <span class="font-mono">{{ $adminEmail }}</span></p>
            <p>Temporary password: <span class="font-mono">{{ $generatedPassword }}</span></p>
            @if ($smsStatus === 'sent')
                <p class="mt-2 text-green-700">✓ These credentials were also sent by SMS to {{ $adminPhone }}.</p>
            @elseif ($smsStatus === 'failed')
                <p class="mt-2 text-red-700">⚠ Could not send the SMS — share the credentials below manually.</p>
            @endif
            <p class="mt-2 text-xs">Share this securely and ask them to change the password after first login.</p>
        </div>
    @endif

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Users</th>
                    <th class="px-4 py-3">Learners</th>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">SMS credits</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($schools as $school)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $school->name }}</td>
                        <td class="px-4 py-3 capitalize">{{ $school->type }}</td>
                        <td class="px-4 py-3">{{ $school->users_count }}</td>
                        <td class="px-4 py-3">{{ $school->learners_count }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $school->package?->name ?? '—' }}</span>
                            <span class="block text-xs {{ $school->isOnActiveSubscription() ? 'text-green-600' : 'text-red-600' }}">{{ $school->subscriptionStatusLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 font-semibold text-gray-700">{{ number_format($school->sms_credits) }}</td>
                        <td class="px-4 py-3">
                            @if ($school->is_active)
                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Active</span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="edit({{ $school->id }})" class="text-sm font-semibold text-green-700 hover:underline">Edit</button>
                            <button wire:click="openPlanForm({{ $school->id }})" class="ml-3 text-sm font-semibold text-blue-700 hover:underline">Plan</button>
                            <button wire:click="openSmsForm({{ $school->id }})" class="ml-3 text-sm font-semibold text-amber-700 hover:underline">SMS credit</button>
                            <button wire:click="toggleActive({{ $school->id }})" wire:confirm="Are you sure?" class="ml-3 text-sm font-semibold text-gray-500 hover:underline">
                                {{ $school->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No schools yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $schools->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showForm', false)">
            <div class="card w-full max-w-lg space-y-4 p-6">
                <h2 class="text-lg font-bold text-gray-800">{{ $editingId ? 'Edit school' : 'New school' }}</h2>

                <form wire:submit="save" class="space-y-4">
                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">School name</span>
                        <input wire:model="form.name" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        @error('form.name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Type</span>
                        <select wire:model="form.type" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </label>

                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Motto</span>
                        <input wire:model="form.motto" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</span>
                            <input wire:model="form.phone" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email</span>
                            <input wire:model="form.email" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        </label>
                    </div>

                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Address</span>
                        <input wire:model="form.address" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>

                    @if (! $editingId)
                        <hr class="my-2">
                        <p class="text-sm font-semibold text-gray-700">First admin account for this school</p>
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin name</span>
                            <input wire:model="adminName" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            @error('adminName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin email</span>
                            <input wire:model="adminEmail" type="email" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            @error('adminEmail') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin phone (M-Pesa/SMS format, e.g. 07XXXXXXXX)</span>
                            <input wire:model="adminPhone" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            @error('adminPhone') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <p class="text-xs text-gray-500">A temporary password will be generated, shown once here, and sent to this phone by SMS.</p>
                    @else
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active"> <span class="text-sm">Active</span></label>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showSmsForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showSmsForm', false)">
            <div class="card w-full max-w-md space-y-4 p-6">
                <h2 class="text-lg font-bold text-gray-800">Allocate paid SMS credits</h2>
                <p class="text-sm text-gray-600">Confirm the school's payment first, then issue the SMS units. This creates a permanent allocation record; it does not use the school's parent-fee M-Pesa till.</p>

                <form wire:submit="allocateSmsCredits" class="space-y-4">
                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">SMS units to allocate</span>
                        <input wire:model="smsUnits" type="number" min="1" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        @error('smsUnits') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Amount paid (KSh)</span>
                        <input wire:model="smsAmountPaid" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Payment reference</span>
                        <input wire:model="smsPaymentReference" placeholder="e.g. M-Pesa code or bank reference" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>
                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Note</span>
                        <input wire:model="smsNote" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showSmsForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Confirm &amp; allocate</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showPlanForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" wire:click.self="$set('showPlanForm', false)">
            <div class="card w-full max-w-md space-y-4 p-6">
                <h2 class="text-lg font-bold text-gray-800">Grant / extend plan</h2>
                <p class="text-sm text-gray-500">Use this for payments received outside M-Pesa (cash, bank transfer). The school can also pay for renewals themselves from their own Billing page.</p>

                <form wire:submit="grantPlan" class="space-y-4">
                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plan</span>
                        <select wire:model="grantPackageId" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                            <option value="">Select a plan</option>
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}">{{ $package->name }} — KSh {{ number_format($package->price, 0) }}/student</option>
                            @endforeach
                        </select>
                        @error('grantPackageId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Access expires on</span>
                        <input wire:model="grantExpiresAt" type="date" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        @error('grantExpiresAt') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block"><span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Note</span>
                        <input wire:model="grantNote" placeholder="e.g. Paid by bank transfer, ref #1234" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showPlanForm', false)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600">Cancel</button>
                        <button type="submit" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
