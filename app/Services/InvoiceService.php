<?php
namespace App\Services;
use App\Models\{Invoice, Package, School, SubscriptionPayment};
class InvoiceService {
    public function create(School $school, string $title, array $items, ?Package $package=null, ?SubscriptionPayment $payment=null, ?int $createdBy=null, string $status='draft', ?string $dueDate=null, ?string $notes=null): Invoice {
        $invoice = Invoice::create(['invoice_number'=>Invoice::nextNumber(),'school_id'=>$school->id,'package_id'=>$package?->id,'subscription_payment_id'=>$payment?->id,'title'=>$title,'notes'=>$notes,'status'=>'draft','due_date'=>$dueDate,'created_by'=>$createdBy]);
        foreach (array_values($items) as $position=>$item) { $quantity=(float)($item['quantity']??1); $invoice->items()->create(['description'=>$item['description'],'quantity'=>$quantity,'unit_price'=>$item['unit_price'],'amount'=>round($quantity*$item['unit_price'],2),'position'=>$position]); }
        $invoice->recalculateTotals(); if ($status === 'sent') $invoice->send(); if ($status === 'paid') { $invoice->send(); $invoice->markPaid(); } return $invoice->fresh('items');
    }
    public function forConfirmedPayment(School $school, Package $package, SubscriptionPayment $payment, ?int $createdBy=null): Invoice { return $this->create($school, "{$package->name} subscription renewal", [['description'=>"{$package->name} subscription",'quantity'=>$payment->student_count,'unit_price'=>$package->price]], $package, $payment, $createdBy, 'paid', null, $payment->mpesa_receipt_number ? "M-Pesa receipt: {$payment->mpesa_receipt_number}" : 'Paid subscription.'); }
}
