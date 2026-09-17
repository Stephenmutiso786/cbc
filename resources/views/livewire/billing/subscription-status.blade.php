<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Subscription</h1>
        <p class="text-sm text-gray-500">{{ $school->name }}</p>
    </div>

    <div class="card p-5 {{ $school->isOnActiveSubscription() ? 'border-green-200 bg-green-50' : 'border-red-300 bg-red-50' }}">
        <p class="font-semibold {{ $school->isOnActiveSubscription() ? 'text-green-800' : 'text-red-800' }}">
            {{ $school->subscriptionStatusLabel() }}
        </p>
        @if ($school->package)
            <p class="mt-1 text-sm text-gray-600">Current plan: <strong>{{ $school->package->name }}</strong> — KSh {{ number_format($school->package->price, 0) }} per student, per term.</p>
        @endif
        @if (! $school->isOnActiveSubscription())
            <p class="mt-2 text-sm text-red-700">Access to the system is locked for staff and teachers until this is renewed. Pay below to restore access immediately.</p>
        @endif
    </div>

    <div class="card p-5 space-y-4">
        <h2 class="font-bold text-gray-800">Choose a plan</h2>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($packages as $package)
                <button type="button" wire:click="selectPackage({{ $package->id }})"
                    class="text-left rounded-xl border-2 p-4 transition {{ $selectedPackageId === $package->id ? 'border-green-600 bg-green-50' : 'border-gray-200 hover:border-gray-300' }}">
                    <p class="font-bold text-gray-800">{{ $package->name }}</p>
                    <p class="mt-1 text-2xl font-bold text-green-700">KSh {{ number_format($package->price, 0) }}<span class="text-sm font-normal text-gray-500">/student/term</span></p>
                    <p class="mt-2 text-xs text-gray-500">{{ $package->description }}</p>
                </button>
            @endforeach
        </div>

        <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
            Active learners: <strong>{{ $school->activeStudentCount() }}</strong>
            @if ($selectedPackageId)
                @php($selected = $packages->firstWhere('id', $selectedPackageId))
                @if ($selected)
                    — Amount due: <strong>KSh {{ number_format($selected->price * max($school->activeStudentCount(), 1), 0) }}</strong>
                @endif
            @endif
        </div>

        <label class="block max-w-xs">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">M-Pesa phone number</span>
            <input wire:model="phone" placeholder="07XXXXXXXX" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
            @error('phone') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        @if ($error)
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $error }}</div>
        @endif

        @if ($pendingPaymentId && $pendingStatus === 'pending')
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
                <p>STK push sent — enter your M-Pesa PIN on your phone to complete payment, then check the status below.</p>
                <button type="button" wire:click="checkStatus" wire:loading.attr="disabled" wire:target="checkStatus" class="mt-3 rounded-lg border border-yellow-400 bg-white px-3 py-2 text-xs font-semibold text-yellow-900 disabled:opacity-50">
                    <span wire:loading.remove wire:target="checkStatus">Check payment status</span>
                    <span wire:loading wire:target="checkStatus">Checking…</span>
                </button>
            </div>
        @elseif ($pendingStatus === 'confirmed')
            <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                Payment confirmed! Your subscription has been renewed.
            </div>
        @elseif ($pendingStatus === 'failed')
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                Payment failed or was cancelled. You can try again below.
            </div>
        @endif

        <button wire:click="pay" wire:loading.attr="disabled" class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50">
            Pay with M-Pesa
        </button>
    </div>

    <div class="card overflow-x-auto p-5">
        <h2 class="font-bold text-gray-800 mb-3">Payment history</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr><th class="py-2">Date</th><th class="py-2">Plan</th><th class="py-2">Students</th><th class="py-2">Amount</th><th class="py-2">Status</th><th class="py-2">Receipt</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="py-2">{{ $payment->created_at->format('d M Y') }}</td>
                        <td class="py-2">{{ $payment->package?->name }}</td>
                        <td class="py-2">{{ $payment->student_count }}</td>
                        <td class="py-2">KSh {{ number_format($payment->amount, 0) }}</td>
                        <td class="py-2 capitalize">{{ $payment->status }}</td>
                        <td class="py-2 font-mono text-xs">{{ $payment->mpesa_receipt_number }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-gray-500">No payments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
