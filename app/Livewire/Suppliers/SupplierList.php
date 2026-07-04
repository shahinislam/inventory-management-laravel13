<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierList extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public ?int $deleteId       = null;

    protected $queryString = [
        'search'       => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void { $this->resetPage(); }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        Supplier::findOrFail($this->deleteId)->delete();
        Cache::forget('suppliers_list');

        $this->deleteId = null;
        session()->flash('success', 'Supplier deleted successfully!');
    }

    public function toggleStatus(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['is_active' => !$supplier->is_active]);
        Cache::forget('suppliers_list');
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->with('media')
            ->withCount('products')
            ->when($this->search, fn($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
            )
            ->when($this->statusFilter !== '', fn($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->latest()
            ->paginate(15);

        return view('livewire.suppliers.supplier-list', compact('suppliers'))
            ->layout('layouts.app', ['title' => 'Suppliers']);
    }
}
