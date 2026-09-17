<?php

namespace App\Livewire\Billing;

use App\Models\Package;
use App\Models\SubscriptionPayment;
use App\Services\MpesaService;
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

        $mpesa = MpesaService::platform();
        if (! $mpesa->isConfigured()) {
            $this->error = 'Subscription payments are not yet configured. Contact the platform administrator.';
            return;
        }

        $payment = SubscriptionPayment::create([
            'school_id'     => $school->id,
            'package_id'    => $package->id,
            'student_count' => $students,
            'amount'        => $amount,
            'phone'         => $this->phone,
            'status'        => 'pending',
            'initiated_by'  => auth()->id(),
        ]);

        try {
            $result = $mpesa->stkPush(
                $this->phone,
                $amount,
                'SCH' . $school->id . '-' . $payment->id,
                "{$package->name} subscription — {$school->name}"
            );

            $payment->update([
                'checkout_request_id' => $result['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $result['MerchantRequestID'] ?? null,
            ]);

            if (empty($result['CheckoutRequestID'])) {
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
        ])->layout('layouts.admin');
    }
}

