<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
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
        $this->payment_amount  = number_format($this->invoice->due_amount, 2);
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        $this->validate([
            'payment_amount'    => 'required|numeric|min:0.01|max:' . $this->invoice->due_amount,
            'payment_method'    => 'required|in:cash,card,bank_transfer,cheque,other',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $amount = (float) $this->payment_amount;

        Payment::create([
            'invoice_id'   => $this->invoice->id,
            'created_by'   => auth()->id(),
            'amount'       => $amount,
            'method'       => $this->payment_method,
            'status'       => 'completed',
            'reference'    => $this->payment_reference ?: null,
            'payment_date' => now(),
        ]);

        $newPaid = $this->invoice->paid_amount + $amount;
        $newDue  = max(0, $this->invoice->total - $newPaid);

        $this->invoice->update([
            'paid_amount' => $newPaid,
            'due_amount'  => $newDue,
            'status'      => $newDue <= 0 ? 'paid' : 'partial',
            'paid_date'   => $newDue <= 0 ? now() : $this->invoice->paid_date,
        ]);

        Cache::forget('dashboard_stats_today');

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
            fn() => print($pdf->output()),
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
