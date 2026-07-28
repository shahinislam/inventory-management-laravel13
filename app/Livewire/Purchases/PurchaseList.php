<?php

namespace App\Livewire\Purchases;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $supplierFilter = '';

    public ?int $deleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'supplierFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierFilter(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        if (! $this->canDelete()) {
            return;
        }

        $order = PurchaseOrder::findOrFail($this->deleteId);

        if (! $order->isDraft()) {
            session()->flash('error', 'Only draft orders can be deleted.');
            $this->deleteId = null;

            return;
        }

        $order->delete();
        $this->deleteId = null;
        session()->flash('success', 'Purchase order deleted successfully!');
    }

    public function render()
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'createdBy'])
            ->withCount('items')
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('order_number', 'like', "%{$this->search}%")
                ->orWhereHas('supplier', fn ($sup) => $sup->where('name', 'like', "%{$this->search}%"))
            ))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->supplierFilter, fn ($q) => $q->where('supplier_id', $this->supplierFilter))
            ->latest()
            ->paginate(15);

        $suppliers = Supplier::active()->get();

        return view('livewire.purchases.purchase-list', compact('orders', 'suppliers'))
            ->layout('layouts.app', ['title' => 'Purchase Orders']);
    }
}
