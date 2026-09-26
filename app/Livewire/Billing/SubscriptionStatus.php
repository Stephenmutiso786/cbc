<?php

namespace App\Livewire\Billing;

use App\Models\Package;
use App\Models\SubscriptionPayment;
use App\Models\Invoice;
use App\Services\PlatformPaymentGateway;
use Livewire\Component;

class SubscriptionStatus extends Component
{
    public ?int $selectedPackageId = null;
    public string $phone = '';
    public ?int $pendingPaymentId = null;
    public string $pendingStatus = '';
    public string $error = '';

    public function mount(): void
    {
        $school = auth()->user()->school;
        abort_unless($school, 403);
        $this->selectedPackageId = $school->package_id ?? Package::where('is_active', true)->orderBy('price')->value('id');
        $this->phone = (string) $school->phone;
    }

    public function selectPackage(int $packageId): void
    {
        $this->selectedPackageId = $packageId;
    }

    public function pay(): void
    {
        $this->error = '';
        $this->validate([
            'phone' => ['required', 'string', 'min:9'],
            'selectedPackageId' => ['required', 'exists:packages,id'],
        ]);

        $school = auth()->user()->school;
        $package = Package::where('is_active', true)->findOrFail($this->selectedPackageId);
        $students = max($school->activeStudentCount(), 1);
        $amount = round($package->price * $students, 2);

        $gateway = app(PlatformPaymentGateway::class);
        if (! $gateway->configured()) {
            $this->error = 'Subscription payments are not yet configured. Contact the platform administrator.';
            return;
        }

        $payment = SubscriptionPayment::create([
            'school_id'     => $school->id,
            'package_id'    => $package->id,
            'student_count' => $students,
            'amount'        => $amount,
            'phone'         => $this->phone,
            'payment_provider' => $gateway->provider(),
            'status'        => 'pending',
            'initiated_by'  => auth()->id(),
        ]);

        try {
            $result = $gateway->initiate($this->phone, $amount, 'SCH' . $school->id . '-' . $payment->id, "{$package->name} subscription — {$school->name}", auth()->user()->name);

            $payment->update([
                'checkout_request_id' => $result['checkout_request_id'] ?? null,
                'merchant_request_id' => $result['merchant_request_id'] ?? null,
            ]);

            if (empty($result['checkout_request_id'])) {
                $payment->update(['status' => 'failed', 'failure_reason' => $result['errorMessage'] ?? 'No checkout ID returned']);
                $this->error = $result['errorMessage'] ?? 'Payment initiation failed.';
                return;
            }

            $this->pendingPaymentId = $payment->id;
            $this->pendingStatus = 'pending';
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            $this->error = 'Payment initiation failed. Try again.';
        }
    }

    public function checkStatus(): void
    {
        if (! $this->pendingPaymentId) {
            return;
        }
        $payment = SubscriptionPayment::find($this->pendingPaymentId);
        if (! $payment) {
            return;
        }
        $this->pendingStatus = $payment->status;
    }

    public function render()
    {
        $school = auth()->user()->school()->with('package')->first();

        return view('livewire.billing.subscription-status', [
            'school' => $school,
            'packages' => Package::where('is_active', true)->orderBy('price')->get(),
            'payments' => SubscriptionPayment::latest()->limit(10)->get(),
            // This is the school-scoped platform billing record. Confirmed
            // subscription payments create a paid invoice, while invoices
            // sent by the platform remain here until they are settled.
            'subscriptionInvoices' => Invoice::with('items')->latest()->limit(15)->get(),
            'dueInvoiceTotal' => (float) Invoice::whereIn('status', ['sent', 'accepted'])
                ->sum('total_amount'),
        ])->layout('layouts.admin');
    }
}
