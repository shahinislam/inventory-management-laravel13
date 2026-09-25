<?php

namespace App\Livewire\PaymentAccounts;

use App\Models\PaymentAccount;
use Livewire\Component;

class PaymentAccountForm extends Component
{
    public ?PaymentAccount $account = null;

    public string $name = '';

    public string $type = 'bank';

    public string $bank_name = '';

    public string $account_number = '';

    public string $holder_name = '';

    public bool $is_active = true;

    public string $notes = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'type' => 'required|in:'.implode(',', array_keys(PaymentAccount::TYPES)),
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:100',
            'holder_name' => 'nullable|string|max:150',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function mount(?PaymentAccount $account = null): void
    {
        if ($account?->exists) {
            $this->account = $account;
            $this->name = $account->name;
            $this->type = $account->type;
            $this->bank_name = $account->bank_name ?? '';
            $this->account_number = $account->account_number ?? '';
            $this->holder_name = $account->holder_name ?? '';
            $this->is_active = $account->is_active;
            $this->notes = $account->notes ?? '';
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['bank_name'] = $data['bank_name'] ?: null;
        $data['account_number'] = $data['account_number'] ?: null;
        $data['holder_name'] = $data['holder_name'] ?: null;
        $data['notes'] = $data['notes'] ?: null;

        if ($this->account?->exists) {
            $this->account->update($data);
            $message = 'Payment account updated successfully!';
        } else {
            PaymentAccount::create($data);
            $message = 'Payment account created successfully!';
        }

        session()->flash('success', $message);
        $this->redirect(route('payment-accounts.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.payment-accounts.payment-account-form')
            ->layout('layouts.app', ['title' => $this->account?->exists ? 'Edit Payment Account' : 'Add Payment Account']);
    }
}
