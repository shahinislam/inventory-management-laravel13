<?php

namespace App\Livewire\Warehouses;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;

class WarehouseList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $deleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
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

        $warehouse = Warehouse::findOrFail($this->deleteId);

        if ($warehouse->is_default) {
            session()->flash('error', 'Cannot delete the default warehouse.');
            $this->deleteId = null;

            return;
        }

        $warehouse->delete();
        $this->deleteId = null;
        session()->flash('success', 'Warehouse deleted successfully!');
    }

    public function toggleStatus(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);

        if ($warehouse->is_default && $warehouse->is_active) {
            session()->flash('error', 'Cannot deactivate the default warehouse.');

            return;
        }

        $warehouse->update(['is_active' => ! $warehouse->is_active]);
    }

    public function setDefault(int $id): void
    {
        Warehouse::where('is_default', true)->update(['is_default' => false]);
        Warehouse::findOrFail($id)->update(['is_default' => true, 'is_active' => true]);
        session()->flash('success', 'Default warehouse updated!');
    }

    public function render()
    {
        $warehouses = Warehouse::query()
            ->with('manager')
            ->withCount('stockMovements')
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")
            ))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->latest()
            ->paginate(15);

        return view('livewire.warehouses.warehouse-list', compact('warehouses'))
            ->layout('layouts.app', ['title' => 'Warehouses']);
    }
}
