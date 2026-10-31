<?php

namespace App\Livewire\Invoices;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Setting;
use App\Services\ShiftService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class InvoiceView extends Component
{
    public Invoice $invoice;

    public bool $showPaymentModal = false;

    public string $payment_amount = '';

    public string $payment_method = 'cash';

    public string $payment_reference = '';

    public ?int $payment_account_id = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['items.product', 'items.returnItems', 'customer', 'warehouse', 'createdBy', 'payments.paymentAccount', 'parent', 'returns']);
    }

    /** Viewers may open invoices (route allows it) but must not take payments. */
    private function canRecordPayment(): bool
    {
        return (bool) auth()->user()?->hasRole(['admin', 'manager', 'staff'])
            && ! $this->invoice->isReturn()
            && ! in_array($this->invoice->status, ['cancelled', 'draft'], true);
    }

    public function openPaymentModal(): void
    {
        abort_unless($this->canRecordPayment(), 403);

        // No thousands separator — see PosTerminal::openPaymentModal().
        $this->payment_amount = number_format($this->invoice->due_amount, 2, '.', '');
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        // Re-checked here: Livewire methods are callable straight from the browser.
        abort_unless($this->canRecordPayment(), 403);

        // Strip any thousands separators the user typed before validating, so
        // "1,100.00" is not rejected as non-numeric.
        $this->payment_amount = str_replace(',', '', $this->payment_amount);

        $this->validate([
            'payment_amount' => 'required|numeric|min:0.01|max:'.$this->invoice->due_amount,
            'payment_method' => Payment::methodRule(),
            'payment_reference' => Payment::needsReference($this->payment_method) ? 'required|string|max:100' : 'nullable|string|max:100',
            'payment_account_id' => PaymentAccount::rule($this->payment_method),
        ], [
            'payment_account_id.required' => 'Select the account or card this was paid to.',
            'payment_reference.required' => 'Enter the '.wallet_label().' transaction ID.',
        ]);

        // A due paid at the counter goes into the cashier's drawer count. Cash
        // needs a drawer to go into, as at the POS and on returns.
        $shift = app(ShiftService::class)->currentFor(auth()->user());

        if ($this->payment_method === 'cash' && ! $shift) {
            $this->addError('payment_method', 'Open a shift before taking cash, so it is counted in your drawer. Or choose another method.');

            return;
        }

        $amount = (float) $this->payment_amount;

        DB::transaction(function () use ($amount, $shift) {
            // Lock the invoice before reading its balance: two payments recorded
            // at once would otherwise both read the old paid_amount and one
            // would be silently overwritten.
            $invoice = Invoice::whereKey($this->invoice->id)->lockForUpdate()->firstOrFail();

            // The max: rule above validated against a possibly stale balance,
            // so re-check against the locked row.
            if ($amount > $invoice->calculateDue()) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Amount exceeds the outstanding balance of '
                        .number_format($invoice->calculateDue(), 2).'.',
                ]);
            }

            Payment::create([
                'invoice_id' => $invoice->id,
                'created_by' => auth()->id(),
                'shift_id' => $shift?->id,
                'payment_account_id' => PaymentAccount::requiredFor($this->payment_method) ? $this->payment_account_id : null,
                'amount' => $amount,
                'method' => $this->payment_method,
                'status' => 'completed',
                'reference' => $this->payment_reference ?: null,
                'payment_date' => now(),
            ]);

            $newPaid = $invoice->paid_amount + $amount;
            $newDue = max(0, $invoice->total - $invoice->returned_amount - $newPaid);

            $invoice->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'status' => $newDue <= 0 ? 'paid' : 'partial',
                'paid_date' => $newDue <= 0 ? now() : $invoice->paid_date,
            ]);
        });

        DashboardIndex::flushCache();

        $this->invoice->refresh()->load('payments.paymentAccount');
        $this->showPaymentModal = false;
        session()->flash('success', 'Payment recorded successfully!');
    }

    public function updatedPaymentMethod(): void
    {
        $accounts = PaymentAccount::forMethod($this->payment_method)->get();
        $this->payment_account_id = $accounts->count() === 1 ? $accounts->first()->id : null;
    }

    public function render()
    {
        // The invoice group carries the footer and terms printed on the document.
        $company = Setting::getGroup('company') + Setting::getGroup('invoice');

        $paymentAccounts = PaymentAccount::forMethod($this->payment_method)->get();

        // Only asked while the payment form is open: cash then needs an open shift.
        $needsShift = $this->showPaymentModal && $this->payment_method === 'cash'
            && ! app(ShiftService::class)->currentFor(auth()->user());

        return view('livewire.invoices.invoice-view', compact('company', 'paymentAccounts', 'needsShift'))
            ->layout('layouts.app', ['title' => $this->invoice->invoice_number]);
    }
}
