<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Learner;
use App\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryList extends Component
{
    use WithPagination;

    public string $search      = '';
    public string $typeFilter  = '';
    public bool   $lowStock    = false;
    public int    $perPage     = 20;

    // Issue item modal
    public bool   $showIssueModal = false;
    public ?int   $selectedItemId = null;
    public int    $issueQty       = 1;
    public string $issueType      = 'learner'; // learner | staff
    public ?int   $issueTo        = null;
    public string $issueRemarks   = '';

    protected $queryString = ['search', 'typeFilter', 'lowStock'];

    public function updatingSearch(): void { $this->resetPage(); }

    public function openIssueModal(int $itemId): void
    {
        $this->selectedItemId = $itemId;
        $this->showIssueModal = true;
        $this->issueQty       = 1;
    }

    public function closeIssueModal(): void { $this->showIssueModal = false; }

    public function issueItem(): void
    {
        $this->validate([
            'selectedItemId' => 'required|exists:inventory_items,id',
            'issueQty' => 'required|integer|min:1',
            'issueType' => 'required|in:learner,staff',
            'issueTo' => 'required|integer',
        ]);

        try {
            $itemName = DB::transaction(function () {
            $item = InventoryItem::lockForUpdate()->findOrFail($this->selectedItemId);
            if ($this->issueQty > $item->quantity_in_stock) {
                throw new \RuntimeException('Insufficient stock. Available: ' . $item->quantity_in_stock);
            }
            $recipientColumn = $this->issueType === 'learner' ? 'learner_id' : 'staff_id';
            $recipientTable = $this->issueType === 'learner' ? 'learners' : 'staff_members';
            if (! DB::table($recipientTable)->where('id', $this->issueTo)->exists()) {
                throw new \RuntimeException('The selected recipient does not exist.');
            }
            $newBalance = $item->quantity_in_stock - $this->issueQty;
            InventoryTransaction::create([
                'item_id' => $item->id, 'type' => 'issued', 'quantity' => $this->issueQty,
                'balance_after' => $newBalance, $recipientColumn => $this->issueTo,
                'processed_by' => auth()->user()->staffMember?->id,
                'academic_year' => config('school.academic_year'),
                'remarks' => $this->issueRemarks, 'transaction_date' => today(),
            ]);
            $item->update(['quantity_in_stock' => $newBalance, 'quantity_issued' => $item->quantity_issued + $this->issueQty]);
            return $item->name;
            });
        } catch (\Throwable $e) {
            $this->addError('issueQty', $e->getMessage());
            return;
        }

        $this->closeIssueModal();
        session()->flash('success', "{$this->issueQty} item(s) of '{$itemName}' issued successfully.");
    }

    public function render()
    {
        $items = InventoryItem::with('category')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%"))
            ->when($this->typeFilter === 'textbook', fn($q) => $q->where('is_textbook', true))
            ->when($this->typeFilter === 'low_stock', fn($q) => $q->whereColumn('quantity_in_stock', '<=', 'minimum_stock_level'))
            ->when($this->lowStock, fn($q) => $q->whereColumn('quantity_in_stock', '<=', 'minimum_stock_level'))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.inventory.inventory-list', [
            'items' => $items,
            'learners' => Learner::active()->orderBy('last_name')->get(),
            'staffMembers' => StaffMember::active()->orderBy('last_name')->get(),
        ])
            ->layout('layouts.admin');
    }
}
