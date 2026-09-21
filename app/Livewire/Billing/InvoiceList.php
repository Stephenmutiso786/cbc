<?php
namespace App\Livewire\Billing;
use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;
class InvoiceList extends Component {
 use WithPagination; public ?int $rejectingId=null; public string $rejectionReason='';
 public function mount(): void { abort_unless(auth()->user()?->school_id,403); }
 public function accept(int $id): void { $invoice=Invoice::findOrFail($id); abort_unless($invoice->status==='sent',422); $invoice->accept(auth()->id()); session()->flash('success','Invoice accepted.'); }
 public function reject(int $id): void { $this->validate(['rejectionReason'=>['required','string','max:1000']]); $invoice=Invoice::findOrFail($id); abort_unless($invoice->status==='sent',422); $invoice->reject(auth()->id(),$this->rejectionReason); $this->rejectingId=null; $this->rejectionReason=''; session()->flash('success','Invoice returned to the platform for correction.'); }
 public function render() { return view('livewire.billing.invoice-list',['invoices'=>Invoice::with('items')->latest()->paginate(15)])->layout('layouts.admin'); }
}
