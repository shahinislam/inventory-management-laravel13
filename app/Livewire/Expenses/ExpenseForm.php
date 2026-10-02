<?php

namespace App\Livewire\Expenses;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Media;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Services\ShiftService;
use Livewire\Component;

class ExpenseForm extends Component
{
    public ?Expense $expense = null;

    public string $category = '';

    public string $amount = '';

    public string $expense_date = '';

    public string $method = 'cash';

    public ?int $payment_account_id = null;

    public string $reference = '';

    public string $paid_to = '';

    public string $notes = '';

    public ?int $media_id = null;

    /** Cash taken out of the user's open till, so the shift close expects less cash. */
    public bool $fromTill = false;

    public bool $showMediaPicker = false;

    protected $listeners = ['select-media' => 'selectMedia'];

    protected function rules(): array
    {
        return [
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|gt:0|max:9999999999',
            'expense_date' => 'required|date',
            'method' => Payment::methodRule(),
            'payment_account_id' => PaymentAccount::rule($this->method),
            'reference' => Payment::needsReference($this->method) ? 'required|string|max:100' : 'nullable|string|max:100',
            'paid_to' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:2000',
            'media_id' => 'nullable|exists:media,id',
            'fromTill' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'amount.gt' => 'The amount must be more than zero.',
            'payment_account_id.required' => 'Choose which account this was paid from.',
            'reference.required' => 'Enter the bKash / Nagad transaction ID.',
        ];
    }

    public function mount(?Expense $expense = null): void
    {
        $this->expense_date = today()->format('Y-m-d');

        if ($expense?->exists) {
            // Till cash in / out are managed from the shift screen.
            abort_if(in_array($expense->type, ['cash_in', 'cash_out'], true), 404);

            $this->expense = $expense;
            $this->category = $expense->category ?? '';
            $this->amount = (string) $expense->amount;
            $this->expense_date = $expense->expense_date?->format('Y-m-d') ?? $this->expense_date;
            $this->method = $expense->method;
            $this->payment_account_id = $expense->payment_account_id;
            $this->reference = $expense->reference ?? '';
            $this->paid_to = $expense->paid_to ?? '';
            $this->notes = $expense->notes ?? '';
            $this->media_id = $expense->media_id;
            $this->fromTill = (bool) $expense->shift_id;
        }
    }

    public function updatedMethod(): void
    {
        $this->payment_account_id = null;
        $this->resetValidation(['payment_account_id', 'reference']);

        if ($this->method !== 'cash') {
            $this->fromTill = false;
        }
    }

    /** The shift this expense is (or would be) paid from. */
    private function tillShift(): ?CashShift
    {
        // Keep an existing link even if that shift has since closed.
        if ($this->expense?->shift_id) {
            return $this->expense->shift;
        }

        return app(ShiftService::class)->currentFor(auth()->user());
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['amount'] = round((float) $data['amount'], 2);
        $data['category'] = trim($data['category']);
        $data['payment_account_id'] = PaymentAccount::requiredFor($data['method']) ? $data['payment_account_id'] : null;
        $data['reference'] = $data['reference'] ?: null;
        $data['paid_to'] = $data['paid_to'] ?: null;
        $data['notes'] = $data['notes'] ?: null;

        $shift = $this->tillShift();
        $data['shift_id'] = ($this->fromTill && $data['method'] === 'cash' && $shift) ? $shift->id : null;
        unset($data['fromTill']);

        if ($this->expense?->exists) {
            $this->expense->update($data);
            $message = 'Expense updated successfully!';
        } else {
            Expense::create($data + ['type' => 'expense', 'created_by' => auth()->id()]);
            $message = 'Expense recorded successfully!';
        }

        session()->flash('success', $message);
        $this->redirect(route('expenses.index'), navigate: true);
    }

    public function selectMedia(int $mediaId): void
    {
        $this->media_id = $mediaId;
        $this->showMediaPicker = false;
    }

    public function removeMedia(): void
    {
        $this->media_id = null;
    }

    public function render()
    {
        $categories = collect(Expense::CATEGORIES)
            ->merge(Expense::query()->costs()->whereNotNull('category')->distinct()->pluck('category'))
            ->unique()
            ->sort()
            ->values();

        return view('livewire.expenses.expense-form', [
            'categories' => $categories,
            'methods' => Payment::METHODS,
            'accounts' => PaymentAccount::requiredFor($this->method)
                ? PaymentAccount::query()->forMethod($this->method)->get()
                : collect(),
            'needsAccount' => PaymentAccount::requiredFor($this->method),
            'needsReference' => Payment::needsReference($this->method),
            'shift' => $this->tillShift(),
            'media' => $this->media_id ? Media::find($this->media_id) : null,
        ])->layout('layouts.app', ['title' => $this->expense?->exists ? 'Edit Expense' : 'Record Expense']);
    }
}
