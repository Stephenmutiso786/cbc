<?php
namespace App\Livewire\SuperAdmin;
use App\Models\{Invoice, School};
use App\Services\InvoiceService;
use Livewire\Component;
use Livewire\WithPagination;
class InvoiceManager extends Component {
 use WithPagination; public bool $showForm=false; public string $statusFilter=''; public ?int $schoolId=null; public string $title=''; public string $dueDate=''; public string $notes=''; public array $items=[];
 public function mount(): void { abort_unless(auth()->user()?->hasRole('super-admin'),403); }
 public function create(): void { $this->resetValidation(); $this->schoolId=null; $this->title=''; $this->notes=''; $this->dueDate=now()->addDays(14)->toDateString(); $this->items=[['description'=>'','quantity'=>1,'unit_price'=>0]]; $this->showForm=true; }
 public function addItem(): void { $this->items[]=['description'=>'','quantity'=>1,'unit_price'=>0]; } public function removeItem(int $index): void { unset($this->items[$index]); $this->items=array_values($this->items); }
 public function save(bool $send=false): void { $this->validate(['schoolId'=>['required','exists:schools,id'],'title'=>['required','string','max:255'],'dueDate'=>['nullable','date'],'items'=>['required','array','min:1'],'items.*.description'=>['required','string','max:255'],'items.*.quantity'=>['required','numeric','min:0.01'],'items.*.unit_price'=>['required','numeric','min:0']]); app(InvoiceService::class)->create(School::findOrFail($this->schoolId),$this->title,$this->items,null,null,auth()->id(),$send?'sent':'draft',$this->dueDate?:null,$this->notes?:null); $this->showForm=false; session()->flash('success',$send?'Invoice sent to the school.':'Invoice saved as draft.'); }
 public function send(int $id): void { $invoice=Invoice::withoutSchoolScope()->findOrFail($id); abort_unless(in_array($invoice->status,['draft','rejected']),422); $invoice->send(); session()->flash('success','Invoice sent to the school.'); }
 public function markPaid(int $id): void { Invoice::withoutSchoolScope()->findOrFail($id)->markPaid(); session()->flash('success','Invoice marked paid.'); }
 public function render() { return view('livewire.super-admin.invoice-manager',['schools'=>School::orderBy('name')->get(),'invoices'=>Invoice::withoutSchoolScope()->with('school')->when($this->statusFilter,fn($q)=>$q->where('status',$this->statusFilter))->latest()->paginate(15)])->layout('layouts.admin'); }
}
