<?php

namespace App\Livewire\Notifications;

use App\Models\School;
use App\Models\SchoolNotification;
use App\Models\SmsCreditOrder;
use App\Models\SmsCreditTransaction;
use App\Services\MpesaService;
use Livewire\Component;

/** A tenant-safe SMS wallet page; no provider credentials or cross-school data. */
class SchoolSmsDashboard extends Component
{
    public int $smsUnits = 100;
    public string $phone = '';
    public ?int $pendingSmsOrderId = null;
    public string $pendingSmsStatus = '';
    public string $error = '';

    public function mount(): void
    {
        if (auth()->user()?->hasRole('super-admin')) {
            $this->redirectRoute('admin.sms-control.index');
            return;
        }
        abort_unless(auth()->user()?->school_id, 403);
        $this->phone = (string) auth()->user()->school?->phone;
    }

    public function buySmsCredits(): void
    {
        $this->error = '';
        $this->validate(['phone' => ['required', 'string', 'min:9'], 'smsUnits' => ['required', 'integer', 'min:10', 'max:100000']]);
        $school = auth()->user()->school;
        $unitPrice = (float) config('services.platform_mpesa.sms_unit_price', 1);
        $mpesa = MpesaService::platform();
        if ($unitPrice <= 0 || ! $mpesa->isConfigured()) {
            $this->error = 'SMS payment is not configured by the platform administrator.';
            return;
        }

        $order = SmsCreditOrder::create(['school_id' => $school->id, 'units' => $this->smsUnits, 'amount' => round($this->smsUnits * $unitPrice, 2), 'phone' => $this->phone, 'status' => 'pending', 'initiated_by' => auth()->id()]);
        try {
            $result = $mpesa->stkPush($this->phone, (float) $order->amount, 'SMS'.$school->id.'-'.$order->id, "SMS credits — {$school->name}");
            $order->update(['checkout_request_id' => $result['CheckoutRequestID'] ?? null, 'merchant_request_id' => $result['MerchantRequestID'] ?? null]);
            if (! $order->checkout_request_id) {
                $order->update(['status' => 'failed', 'failure_reason' => $result['errorMessage'] ?? 'No checkout ID returned']);
                $this->error = $order->failure_reason;
                return;
            }
            $this->pendingSmsOrderId = $order->id;
            $this->pendingSmsStatus = 'pending';
        } catch (\Throwable $exception) {
            report($exception);
            $order->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);
            $this->error = 'SMS payment initiation failed. Try again.';
        }
    }

    public function checkSmsStatus(): void
    {
        if ($this->pendingSmsOrderId && ($order = SmsCreditOrder::find($this->pendingSmsOrderId))) {
            $this->pendingSmsStatus = $order->status;
        }
    }

    public function render()
    {
        $schoolId = (int) auth()->user()->school_id;
        $school = School::withoutGlobalScopes()->findOrFail($schoolId);
        $transactions = SmsCreditTransaction::query()->where('school_id', $schoolId);
        $purchased = (int) (clone $transactions)->where('type', 'topup')->sum('amount');
        $used = abs((int) (clone $transactions)->where('type', 'usage')->sum('amount'));
        $spent = (float) (clone $transactions)->where('type', 'topup')->sum('amount_paid');
        $recentOrders = SmsCreditOrder::query()->latest()->limit(8)->get();
        $recentMessages = SchoolNotification::query()->where('channel', 'sms')->latest()->limit(8)->get();
        $dailyUsage = (clone $transactions)->where('type', 'usage')->where('created_at', '>=', now()->subDays(6)->startOfDay())->selectRaw('DATE(created_at) as day, ABS(SUM(amount)) as units')->groupBy('day')->orderBy('day')->get();

        $smsUnitPrice = (float) config('services.platform_mpesa.sms_unit_price', 1);
        return view('livewire.notifications.school-sms-dashboard', compact('school', 'purchased', 'used', 'spent', 'recentOrders', 'recentMessages', 'dailyUsage', 'smsUnitPrice'))
            ->layout('layouts.admin');
    }
}
