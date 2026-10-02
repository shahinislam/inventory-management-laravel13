<?php

namespace App\Livewire\Returns;

use App\Concerns\HandlesBarcodeScans;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Services\SalesReturnService;
use App\Services\ShiftService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Take goods back from a customer: find the sale (receipt number, scan or
 * phone), choose what came back, and how the money is returned.
 */
class ReturnForm extends Component
{
    use HandlesBarcodeScans;

    #[Url(as: 'invoice')]
    public ?int $invoiceId = null;

    public string $search = '';

    /** @var array<int, array{quantity: string, restock: bool}> keyed by sold invoice_item id */
    public array $lines = [];

    public string $refundMethod = 'cash';

    public ?int $paymentAccountId = null;

    public string $reference = '';

    public string $reason = '';

    public function mount(): void
    {
        if ($this->invoiceId) {
            $this->selectInvoice($this->invoiceId);
        }
    }

    public function getSaleProperty(): ?Invoice
    {
        return $this->invoiceId
            ? Invoice::sales()->with(['items.product', 'items.returnItems', 'returns'])->find($this->invoiceId)
            : null;
    }

    /** Sales matching the receipt number or the customer's phone. */
    public function getMatchesProperty()
    {
        $term = trim($this->search);
        if (mb_strlen($term) < 2 || $this->invoiceId) {
            return collect();
        }

        return Invoice::sales()
            ->whereNotIn('status', ['draft', 'cancelled', 'returned'])
            ->where(fn ($q) => $q->where('invoice_number', 'like', "%{$term}%")
                ->orWhere('customer_phone', 'like', '%'.preg_replace('/[\s\-]/', '', $term).'%')
                ->orWhere('customer_name', 'like', "%{$term}%"))
            ->latest('invoice_date')->latest('id')
            ->limit(8)->get();
    }

    public function selectInvoice(int $id): void
    {
        $sale = Invoice::sales()->with('items')->find($id);

        if (! $sale) {
            session()->flash('error', 'That sale was not found.');
            $this->invoiceId = null;

            return;
        }

        $this->invoiceId = $sale->id;
        $this->search = '';
        $this->lines = $sale->items->mapWithKeys(fn ($item) => [
            $item->id => ['quantity' => '', 'restock' => true],
        ])->all();
        unset($this->sale);
    }

    public function clearInvoice(): void
    {
        $this->reset('invoiceId', 'lines', 'search', 'reason', 'reference');
    }

    /** Everything still returnable on the sale. */
    public function returnAll(): void
    {
        foreach ($this->sale?->items ?? [] as $item) {
            $left = $item->returnableQuantity();
            $this->lines[$item->id]['quantity'] = $left > 0 ? rtrim(rtrim(number_format($left, 3, '.', ''), '0'), '.') : '';
        }
    }

    public function getUnitRefundsProperty(): array
    {
        return $this->sale ? app(SalesReturnService::class)->unitRefunds($this->sale) : [];
    }

    public function getReturnValueProperty(): float
    {
        $total = 0;
        foreach ($this->lines as $id => $line) {
            $total += ((float) ($line['quantity'] ?: 0)) * ($this->unitRefunds[$id] ?? 0);
        }

        return round($total, 2);
    }

    /** First the customer's unpaid due is reduced; the rest is paid back. */
    public function getDueReducedProperty(): float
    {
        return round(min((float) ($this->sale?->due_amount ?? 0), $this->returnValue), 2);
    }

    public function getRefundAmountProperty(): float
    {
        return round($this->returnValue - $this->dueReduced, 2);
    }

    public function updatedRefundMethod(): void
    {
        $accounts = PaymentAccount::forMethod($this->refundMethod)->get();
        $this->paymentAccountId = $accounts->count() === 1 ? $accounts->first()->id : null;
        $this->reference = '';
    }

    public function submit(): void
    {
        $this->validate(['reason' => 'nullable|string|max:200']);

        try {
            $return = app(SalesReturnService::class)->process(
                $this->sale,
                $this->lines,
                $this->refundMethod,
                $this->paymentAccountId,
                $this->reference ?: null,
                $this->reason ?: null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session()->flash('success', 'Return '.$return->invoice_number.' saved'
            .($return->paid_amount > 0 ? ' — give back '.money($return->paid_amount).' by '.Payment::methodLabel($return->payment_method).'.' : '.'));

        $this->redirect(route('invoices.show', $return), navigate: true);
    }

    /** A scanned receipt opens that sale; a scanned product adds one to its line. */
    protected function handleScan(string $code): bool
    {
        $invoice = Invoice::sales()->where('invoice_number', $code)->first();
        if ($invoice) {
            $this->selectInvoice($invoice->id);

            return true;
        }

        $sale = $this->sale;
        $product = $this->findScannedProduct($code);
        $item = $sale && $product ? $sale->items->firstWhere('product_id', $product->id) : null;

        if (! $item) {
            $this->scanError = $sale ? "That item is not on {$sale->invoice_number}." : "No sale found for \"{$code}\".";

            return false;
        }

        $current = (float) ($this->lines[$item->id]['quantity'] ?: 0);
        $this->lines[$item->id]['quantity'] = (string) min($current + 1, $item->returnableQuantity());

        return true;
    }

    public function render()
    {
        return view('livewire.returns.return-form', [
            'accounts' => PaymentAccount::forMethod($this->refundMethod)->get(),
            'hasShift' => (bool) app(ShiftService::class)->currentFor(auth()->user()),
        ])->layout('layouts.app', ['title' => 'Return Items']);
    }
}
