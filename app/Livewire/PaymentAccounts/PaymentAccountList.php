<?php

namespace App\Livewire\PaymentAccounts;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\PaymentAccount;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentAccountList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public ?int $deleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'typeFilter' => ['except' => ''],
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

        // Soft delete: past payments keep pointing at the account.
        PaymentAccount::findOrFail($this->deleteId)->delete();

        $this->deleteId = null;
        session()->flash('success', 'Payment account deleted successfully!');
    }

    public function toggleStatus(int $id): void
    {
        if (! auth()->user()->hasRole(['admin', 'manager'])) {
            return;
        }

        $account = PaymentAccount::findOrFail($id);
        $account->update(['is_active' => ! $account->is_active]);
    }

    public function render()
    {
        $accounts = PaymentAccount::query()
            ->withCount(['payments', 'purchasePayments'])
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('bank_name', 'like', "%{$this->search}%")
                ->orWhere('account_number', 'like', "%{$this->search}%")
            ))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.payment-accounts.payment-account-list', compact('accounts'))
            ->layout('layouts.app', ['title' => 'Payment Accounts']);
    }
}
