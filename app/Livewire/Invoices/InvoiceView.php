<?php

namespace App\Livewire\Invoices;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
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

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['items.product', 'customer', 'warehouse', 'createdBy', 'payments']);
    }

    public function openPaymentModal(): void
    {
        // No thousands separator — see PosTerminal::openPaymentModal().
        $this->payment_amount = number_format($this->invoice->due_amount, 2, '.', '');
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        // Strip any thousands separators the user typed before validating, so
        // "1,100.00" is not rejected as non-numeric.
        $this->payment_amount = str_replace(',', '', $this->payment_amount);

        $this->validate([
            'payment_amount' => 'required|numeric|min:0.01|max:'.$this->invoice->due_amount,
            'payment_method' => 'required|in:cash,card,bank_transfer,cheque,other',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $amount = (float) $this->payment_amount;

        DB::transaction(function () use ($amount) {
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
                'amount' => $amount,
                'method' => $this->payment_method,
                'status' => 'completed',
                'reference' => $this->payment_reference ?: null,
                'payment_date' => now(),
            ]);

            $newPaid = $invoice->paid_amount + $amount;
            $newDue = max(0, $invoice->total - $newPaid);

            $invoice->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'status' => $newDue <= 0 ? 'paid' : 'partial',
                'paid_date' => $newDue <= 0 ? now() : $invoice->paid_date,
            ]);
        });

        DashboardIndex::flushCache();

        $this->invoice->refresh()->load('payments');
        $this->showPaymentModal = false;
        session()->flash('success', 'Payment recorded successfully!');
    }

    public function downloadPdf()
    {
        $company = Setting::getGroup('company');
        $invoice = $this->invoice;

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'company'));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "{$invoice->invoice_number}.pdf"
        );
    }

    public function render()
    {
        $company = Setting::getGroup('company');

        return view('livewire.invoices.invoice-view', compact('company'))
            ->layout('layouts.app', ['title' => $this->invoice->invoice_number]);
    }
}
