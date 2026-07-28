<?php

namespace App\Livewire\Customers;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerList extends Component
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

        Customer::findOrFail($this->deleteId)->delete();
        $this->deleteId = null;
        session()->flash('success', 'Customer deleted successfully!');
    }

    public function toggleStatus(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $customer->update(['is_active' => ! $customer->is_active]);
    }

    public function render()
    {
        $customers = Customer::query()
            ->with('media')
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
            ))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->latest()
            ->paginate(15);

        return view('livewire.customers.customer-list', compact('customers'))
            ->layout('layouts.app', ['title' => 'Customers']);
    }
}
