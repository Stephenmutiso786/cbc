<?php

namespace App\Livewire\Billing;

use App\Models\Package;
use App\Models\SubscriptionPayment;
use App\Models\SmsCreditOrder;
use App\Services\MpesaService;
use Livewire\Component;

class SubscriptionStatus extends Component
{
    public ?int $selectedPackageId = null;
    public string $phone = '';
    public ?int $pendingPaymentId = null;
    public string $pendingStatus = '';
    public string $error = '';
    public int $smsUnits = 100;
    public ?int $pendingSmsOrderId = null;
    public string $pendingSmsStatus = '';

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

    public function buySmsCredits(): void
    {
        $this->error = '';
        $this->validate(['phone' => ['required','string','min:9'], 'smsUnits' => ['required','integer','min:10','max:100000']]);
        $school = auth()->user()->school;
        $unitPrice = (float) config('services.platform_mpesa.sms_unit_price', 1);
        if ($unitPrice <= 0 || ! ($mpesa = MpesaService::platform())->isConfigured()) { $this->error = 'Platform subscription M-Pesa or SMS pricing is not configured.'; return; }
        $order = SmsCreditOrder::create(['school_id'=>$school->id,'units'=>$this->smsUnits,'amount'=>round($this->smsUnits*$unitPrice,2),'phone'=>$this->phone,'status'=>'pending','initiated_by'=>auth()->id()]);
        try { $result = $mpesa->stkPush($this->phone,(float)$order->amount,'SMS'.$school->id.'-'.$order->id,"SMS credits — {$school->name}"); $order->update(['checkout_request_id'=>$result['CheckoutRequestID'] ?? null,'merchant_request_id'=>$result['MerchantRequestID'] ?? null]); if (! $order->checkout_request_id) { $order->update(['status'=>'failed','failure_reason'=>$result['errorMessage'] ?? 'No checkout ID returned']); $this->error=$order->failure_reason; return; } $this->pendingSmsOrderId=$order->id; $this->pendingSmsStatus='pending'; } catch (\Throwable $exception) { $order->update(['status'=>'failed','failure_reason'=>$exception->getMessage()]); $this->error='SMS payment initiation failed.'; }
    }
    public function checkSmsStatus(): void { if ($this->pendingSmsOrderId && ($order = SmsCreditOrder::find($this->pendingSmsOrderId))) $this->pendingSmsStatus=$order->status; }

    public function render()
    {
        $school = auth()->user()->school()->with('package')->first();

        return view('livewire.billing.subscription-status', [
            'school' => $school,
            'packages' => Package::where('is_active', true)->orderBy('price')->get(),
            'payments' => SubscriptionPayment::latest()->limit(10)->get(),
            'smsOrders' => SmsCreditOrder::latest()->limit(10)->get(),
            'smsUnitPrice' => (float) config('services.platform_mpesa.sms_unit_price', 1),
        ])->layout('layouts.admin');
    }
}
