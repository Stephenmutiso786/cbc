<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\School;
use App\Models\SubscriptionPayment;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    /** School admin initiates payment for their chosen plan. */
    public function stkPush(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'      => ['required', 'string'],
            'package_id' => ['required', 'exists:packages,id'],
        ]);

        $school = $request->user()->school;
        abort_unless($school, 403, 'Only a school account can pay for a subscription.');

        $package = Package::where('is_active', true)->findOrFail($data['package_id']);
        $students = max($school->activeStudentCount(), 1);
        $amount = round($package->price * $students, 2);

        $mpesa = MpesaService::platform();
        if (! $mpesa->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Subscription payments are not yet configured. Contact the platform administrator.'], 503);
        }

        $payment = SubscriptionPayment::create([
            'school_id'     => $school->id,
            'package_id'    => $package->id,
            'student_count' => $students,
            'amount'        => $amount,
            'phone'         => $data['phone'],
            'status'        => 'pending',
            'initiated_by'  => $request->user()->id,
        ]);

        try {
            $result = $mpesa->stkPush(
                $data['phone'],
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
                return response()->json(['success' => false, 'message' => $result['errorMessage'] ?? 'Payment initiation failed.'], 500);
            }

            return response()->json([
                'success'             => true,
                'payment_id'          => $payment->id,
                'checkout_request_id' => $payment->checkout_request_id,
                'amount'              => $amount,
                'message'             => 'STK Push sent. Enter your M-Pesa PIN to complete payment.',
            ]);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            Log::error('Subscription STK Push failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Payment initiation failed. Try again.'], 500);
        }
    }

    /** Poll from the browser while waiting for the callback (STK push has no instant response). */
    public function status(Request $request, int $paymentId): JsonResponse
    {
        $payment = SubscriptionPayment::withoutSchoolScope()->findOrFail($paymentId);
        abort_unless($request->user()->school_id === $payment->school_id, 403);

        return response()->json(['status' => $payment->status, 'failure_reason' => $payment->failure_reason]);
    }

    /** M-Pesa STK Push callback — called by Safaricom against the platform's own till. */
    public function callback(Request $request): JsonResponse
    {
        $body = $request->all();
        Log::info('Subscription M-Pesa Callback', $body);

        $stkCallback = $body['Body']['stkCallback'] ?? null;
        if (! $stkCallback) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $payment = SubscriptionPayment::withoutSchoolScope()->where('checkout_request_id', $checkoutRequestId)->first();

        if (! $payment) {
            Log::warning('Subscription callback matched no pending payment', ['checkout_request_id' => $checkoutRequestId]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($payment->status !== 'pending') {
            // Already processed (Safaricom can retry callbacks).
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $resultCode = $stkCallback['ResultCode'] ?? 1;

        if ((int) $resultCode !== 0) {
            $payment->update([
                'status'         => 'failed',
                'failure_reason' => $stkCallback['ResultDesc'] ?? 'Payment was not completed.',
            ]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $metadata = collect($stkCallback['CallbackMetadata']['Item'] ?? [])->pluck('Value', 'Name');

        DB::transaction(function () use ($payment, $metadata) {
            $school = School::findOrFail($payment->school_id);
            $package = $payment->package;

            $baseline = $school->package_expires_at && ! $school->packageExpired() ? $school->package_expires_at : now();
            $periodEnd = $baseline->copy()->addMonths(4); // one term

            $payment->update([
                'status'                => 'confirmed',
                'mpesa_receipt_number'  => $metadata['MpesaReceiptNumber'] ?? null,
                'period_start'          => now()->toDateString(),
                'period_end'            => $periodEnd->toDateString(),
            ]);

            $school->assignPackage(
                $package,
                $periodEnd->toDateString(),
                $payment->initiated_by,
                "Paid via M-Pesa — receipt {$payment->mpesa_receipt_number}"
            );
        });

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}

