<?php

namespace App\Livewire\Partners;

use App\Concerns\AuthorizesDestructiveActions;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use Livewire\Component;
use Livewire\WithPagination;

class PartnerTransactions extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $typeFilter = '';

    public ?int $partnerFilter = null;

    public bool $showModal = false;

    public ?int $deleteId = null;

    // Form state
    public ?int $partner_id = null;

    public string $type = 'investment';

    public string $amount = '';

    public string $transaction_date = '';

    public string $reference = '';

    public string $notes = '';

    protected function rolesAllowedToDelete(): array
    {
        return ['admin'];
    }

    public function mount(): void
    {
        $this->transaction_date = now()->format('Y-m-d');
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPartnerFilter(): void
    {
        $this->resetPage();
    }

    public function openModal(string $type = 'investment'): void
    {
        $this->resetValidation();
        $this->type = $type;
        $this->partner_id = null;
        $this->amount = '';
        $this->transaction_date = now()->format('Y-m-d');
        $this->reference = '';
        $this->notes = '';
        $this->showModal = true;
    }

    protected function rules(): array
    {
        return [
            'partner_id' => 'required|exists:partners,id',
            'type' => 'required|in:investment,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];
    }

    public function save(): void
    {
        $this->validate();

        PartnerTransaction::create([
            'partner_id' => $this->partner_id,
            'created_by' => auth()->id(),
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'transaction_date' => $this->transaction_date,
            'reference' => $this->reference ?: null,
            'notes' => $this->notes ?: null,
        ]);

        $this->showModal = false;
        session()->flash('success', $this->type === 'investment'
            ? 'Investment recorded.'
            : 'Withdrawal recorded.');
    }

    public function delete(): void
    {
        if (! $this->canDelete()) {
            $this->deleteId = null;

            return;
        }

        PartnerTransaction::find($this->deleteId)?->delete();
        $this->deleteId = null;
        session()->flash('success', 'Entry removed.');
    }

    public function render()
    {
        $transactions = PartnerTransaction::query()
            ->with(['partner', 'createdBy'])
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->partnerFilter, fn ($q) => $q->where('partner_id', $this->partnerFilter))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.partners.partner-transactions', [
            'transactions' => $transactions,
            'partners' => Partner::active()->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => 'Partner Transactions']);
    }
}
