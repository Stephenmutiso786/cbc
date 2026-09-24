<?php

namespace App\Services;

use App\Models\School;
use App\Models\SmsCreditOrder;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

/** Keeps paid school SMS wallets within the live balance of the shared provider account. */
class SmsCapacityService
{
    public function refreshProviderBalance(): int
    {
        $balance = (int) floor((float) app(OlympusSmsService::class)->getBalance()['units']);
        SystemSetting::put('olympus_sms_last_balance', $balance);
        SystemSetting::put('olympus_sms_last_checked_at', now()->toIso8601String());

        return $balance;
    }

    /**
     * Confirm a paid order only where the provider has enough unallocated
     * units. A paid order that cannot be covered remains auditable and held.
     */
    public function allocatePaidOrder(SmsCreditOrder $order): bool
    {
        try {
            $providerBalance = $this->refreshProviderBalance();
        } catch (\Throwable $exception) {
            $order->update([
                'status' => 'awaiting_allocation',
                'failure_reason' => 'Payment confirmed. SMS allocation is awaiting a provider-balance check.',
                'confirmed_at' => now(),
            ]);
            report($exception);
            return false;
        }

        return DB::transaction(function () use ($order, $providerBalance): bool {
            $order = SmsCreditOrder::withoutSchoolScope()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'confirmed') {
                return true;
            }

            // Lock all wallets while calculating the central unallocated pool,
            // so simultaneous M-Pesa callbacks cannot oversell it.
            $allocated = School::query()->lockForUpdate()->get()->sum('sms_credits');
            $available = max(0, $providerBalance - $allocated);
            if ($available < $order->units) {
                $order->update([
                    'status' => 'awaiting_allocation',
                    'failure_reason' => "Payment confirmed, but Olympus has {$available} unallocated SMS unit(s); {$order->units} are required.",
                    'confirmed_at' => $order->confirmed_at ?? now(),
                ]);
                return false;
            }

            $school = School::lockForUpdate()->findOrFail($order->school_id);
            $school->allocateSmsCredits($order->units, (float) $order->amount, $order->mpesa_receipt_number, $order->initiated_by, 'SMS order paid through platform subscription M-Pesa');
            $order->update(['status' => 'confirmed', 'failure_reason' => null, 'confirmed_at' => $order->confirmed_at ?? now()]);
            return true;
        });
    }

    public function summary(): array
    {
        $provider = (int) SystemSetting::get('olympus_sms_last_balance', 0);
        $allocated = (int) School::sum('sms_credits');
        $held = (int) SmsCreditOrder::withoutSchoolScope()->where('status', 'awaiting_allocation')->sum('units');

        return [
            'provider_balance' => $provider,
            'allocated' => $allocated,
            'available' => max(0, $provider - $allocated),
            'held' => $held,
            'checked_at' => SystemSetting::get('olympus_sms_last_checked_at'),
        ];
    }
}
