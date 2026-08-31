<?php

namespace App\Livewire\Fees;

use App\Models\FeeInvoice;
use App\Services\MpesaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class FeePayment extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $termFilter   = '';
    public string $statusFilter = '';
    public bool   $showMpesaModal = false;
    public ?int   $selectedInvoiceId = null;
    public string $mpesaPhone   = '';
    public string $paymentAmount = '';
    public string $paymentMethod = 'cash';
    public string $paymentReference = '';

    protected $queryString = ['search', 'termFilter', 'statusFilter'];

    public function updatingSearch(): void { $this->resetPage(); }

    public function payMpesa(int $invoiceId): void
    {
        $this->selectedInvoiceId = $invoiceId;
        $this->paymentMethod = 'mpesa';
        $this->paymentAmount = (string) FeeInvoice::findOrFail($invoiceId)->balance;
        $this->showMpesaModal    = true;
    }

    public function openPaymentModal(): void
    {
        $this->selectedInvoiceId = null;
        $this->paymentAmount = '';
        $this->paymentMethod = 'cash';
        $this->showMpesaModal = true;
    }

    public function recordPayment(): void
    {
        $data = $this->validate([
            'selectedInvoiceId' => 'required|exists:fee_invoices,id',
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|in:mpesa,bank,cash,bursary,waiver',
            'paymentReference' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            $invoice = FeeInvoice::lockForUpdate()->findOrFail($data['selectedInvoiceId']);
            $amount = min((float) $data['paymentAmount'], (float) $invoice->balance);
            if ($amount <= 0) {
                throw new \RuntimeException('This invoice has no outstanding balance.');
            }
            FeePayment::create([
                'receipt_number' => 'RCP-' . strtoupper(Str::random(8)),
                'learner_id' => $invoice->learner_id, 'fee_invoice_id' => $invoice->id,
                'amount' => $amount, 'payment_method' => $data['paymentMethod'],
                'transaction_reference' => $data['paymentReference'] ?: null,
                'status' => 'confirmed', 'paid_at' => now(),
                'received_by' => auth()->user()->staffMember?->id,
            ]);
            $invoice->increment('amount_paid', $amount);
            $invoice->refresh();
            $invoice->update(['status' => $invoice->balance <= 0 ? 'paid' : 'partial']);
        });

        $this->showMpesaModal = false;
        $this->reset(['paymentAmount', 'paymentReference']);
        session()->flash('success', 'Payment recorded successfully.');
    }

    public function initiateStkPush(): void
    {
        $this->validate(['mpesaPhone' => 'required|min:9']);
        $invoice = FeeInvoice::findOrFail($this->selectedInvoiceId);

        try {
            $mpesa  = app(MpesaService::class);
            $result = $mpesa->stkPush($this->mpesaPhone, $invoice->balance, $invoice->invoice_number);
            if ($result['success']) {
                session()->flash('success', 'STK Push sent. Prompt the parent to enter their M-Pesa PIN.');
                $this->showMpesaModal = false;
            } else {
                session()->flash('error', $result['message'] ?? 'STK Push failed.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'M-Pesa error: ' . $e->getMessage());
        }
    }

    public function printReceipt(int $invoiceId): void
    {
        $this->redirectRoute('admin.fees.receipt', ['invoice' => $invoiceId]);
    }

    public function render()
    {
        $invoices = FeeInvoice::with(['learner'])
            ->when($this->search, fn($q) => $q->whereHas('learner', fn($q) =>
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name',  'like', "%{$this->search}%")
                  ->orWhere('admission_number', 'like', "%{$this->search}%")))
            ->when($this->termFilter, fn($q) => $q->where('term', $this->termFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(20);

        return view('livewire.fees.fee-payment', [
            'invoices' => $invoices,
            'unpaidInvoices' => FeeInvoice::with('learner')->whereIn('status', ['unpaid', 'partial'])->latest()->get(),
        ])
            ->layout('layouts.finance');
    }
}
