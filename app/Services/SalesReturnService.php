<?php

namespace App\Services;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Goods coming back from a customer.
 *
 * A return is stored as an invoice of type 'return' pointing at the sale
 * (parent_invoice_id), with one line per returned sale line (parent_item_id).
 * Each line is refunded at what the customer actually paid for it: the line
 * total less its share of any bill-level and member discount, plus its share
 * of any tax added by hand. The courier charge is not refunded.
 *
 * Money goes back in this order: first the customer's unpaid due on the sale
 * is reduced, then anything left is refunded by the chosen method.
 */
class SalesReturnService
{
    public const REFUND_METHODS = ['cash', 'card', 'mobile_banking', 'bank_transfer'];

    /**
     * What each sold line is worth back in full, and the tax inside that.
     *
     * @return array<int, array{value: float, tax: float}> keyed by invoice_item id
     */
    public function lineValues(Invoice $invoice): array
    {
        $goods = $invoice->goodsTotal();
        $billDiscount = (float) $invoice->discount + (float) $invoice->membership_discount;
        // The bill discount lowers every line, tax included, by the same share (PricingService::discountTax()).
        $kept = $goods > 0 ? max(0, 1 - $billDiscount / $goods) : 1;
        $paid = (float) $invoice->items->sum('subtotal') * $kept;
        $extraTax = $invoice->extraTax();

        return $invoice->items->mapWithKeys(function (InvoiceItem $item) use ($kept, $paid, $extraTax) {
            $value = (float) $item->subtotal * $kept;
            $extra = $paid > 0 ? $extraTax * $value / $paid : 0;

            return [$item->id => ['value' => $value + $extra, 'tax' => (float) $item->tax_amount * $kept + $extra]];
        })->all();
    }

    /** What one unit of each sold line is worth back. */
    public function unitRefunds(Invoice $invoice): array
    {
        $values = $this->lineValues($invoice);

        return $invoice->items->mapWithKeys(fn (InvoiceItem $item) => [
            $item->id => $item->quantity > 0 ? $values[$item->id]['value'] / $item->quantity : 0,
        ])->all();
    }

    /**
     * What bringing back $qty of a sold line gives back, to the cent. The last
     * units take whatever is left, so a line returned piece by piece adds up
     * to exactly what was paid for it.
     *
     * @param  array{value: float, tax: float}  $line  from lineValues()
     * @return array{value: float, tax: float}
     */
    public function refundFor(InvoiceItem $item, float $qty, array $line): array
    {
        if ($item->quantity <= 0 || $qty <= 0) {
            return ['value' => 0.0, 'tax' => 0.0];
        }

        if ($qty >= $item->returnableQuantity() - InventoryService::EPSILON) {
            $done = $item->returnItems()->selectRaw('COALESCE(SUM(subtotal), 0) as value, COALESCE(SUM(tax_amount), 0) as tax')->toBase()->first();

            return [
                'value' => max(0, round($line['value'] - (float) $done->value, 2)),
                'tax' => max(0, round($line['tax'] - (float) $done->tax, 2)),
            ];
        }

        $share = $qty / (float) $item->quantity;

        return ['value' => round($line['value'] * $share, 2), 'tax' => round($line['tax'] * $share, 2)];
    }

    /**
     * @param  array<int, array{quantity: float|string, restock?: bool}>  $lines  keyed by sold invoice_item id
     */
    public function process(
        Invoice $sale,
        array $lines,
        string $refundMethod,
        ?int $paymentAccountId = null,
        ?string $reference = null,
        ?string $reason = null,
    ): Invoice {
        if ($sale->isReturn() || $sale->is_held || in_array($sale->status, ['draft', 'cancelled'], true)) {
            throw ValidationException::withMessages(['lines' => 'Only an issued sale can take a return.']);
        }

        $shift = app(ShiftService::class)->currentFor(auth()->user());

        $return = DB::transaction(function () use ($sale, $lines, $refundMethod, $paymentAccountId, $reference, $reason, $shift) {
            $sale = Invoice::whereKey($sale->id)->lockForUpdate()->with('items.product')->firstOrFail();
            $values = $this->lineValues($sale);

            // Validate every line before touching anything.
            $picked = [];
            foreach ($lines as $itemId => $line) {
                $qty = round((float) ($line['quantity'] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }

                $item = $sale->items->firstWhere('id', (int) $itemId);
                if (! $item) {
                    throw ValidationException::withMessages(['lines' => 'That item is not on this invoice.']);
                }

                $returnable = $item->returnableQuantity();
                if ($qty > $returnable + InventoryService::EPSILON) {
                    throw ValidationException::withMessages([
                        "lines.{$itemId}.quantity" => 'Only '.format_qty($returnable, $item->product?->unit)." of {$item->product_name} can be returned.",
                    ]);
                }
                if ($item->product && ! $item->product->isLoose() && floor($qty) != $qty) {
                    throw ValidationException::withMessages([
                        "lines.{$itemId}.quantity" => "{$item->product_name} is sold in whole units.",
                    ]);
                }

                $back = $this->refundFor($item, $qty, $values[$item->id]);

                $picked[] = [$item, $qty, (bool) ($line['restock'] ?? true), $back['value'], $back['tax']];
            }

            if ($picked === []) {
                throw ValidationException::withMessages(['lines' => 'Enter a quantity for at least one item.']);
            }

            $value = round(array_sum(array_column($picked, 3)), 2);
            $tax = round(array_sum(array_column($picked, 4)), 2);
            $dueReduced = round(min((float) $sale->due_amount, $value), 2);
            $refund = round($value - $dueReduced, 2);

            if ($refund > 0) {
                if (! in_array($refundMethod, self::REFUND_METHODS, true)) {
                    throw ValidationException::withMessages(['refundMethod' => 'Choose how the money is given back.']);
                }
                if ($refundMethod === 'cash' && ! $shift) {
                    throw ValidationException::withMessages(['refundMethod' => 'Open a shift to pay a cash refund from the drawer.']);
                }
                // Required for card and bank refunds, and it must be an active account of that kind.
                Validator::make(
                    ['paymentAccountId' => $paymentAccountId],
                    ['paymentAccountId' => PaymentAccount::rule($refundMethod)],
                    ['paymentAccountId.required' => 'Select the account the refund is paid from.'],
                )->validate();
                if (Payment::needsReference($refundMethod) && blank($reference)) {
                    throw ValidationException::withMessages(['reference' => 'Enter the '.wallet_label().' transaction ID.']);
                }
            }

            $return = Invoice::create([
                'type' => 'return',
                'parent_invoice_id' => $sale->id,
                'warehouse_id' => $sale->warehouse_id,
                'customer_id' => $sale->customer_id,
                'created_by' => auth()->id(),
                'shift_id' => $shift?->id,
                'customer_name' => $sale->customer_name,
                'customer_email' => $sale->customer_email,
                'customer_phone' => $sale->customer_phone,
                'customer_address' => $sale->customer_address,
                'status' => 'paid',
                'payment_method' => $refund > 0 ? $refundMethod : null,
                // Refunded amounts contain their tax, whichever way the sale was priced.
                'prices_include_tax' => true,
                'subtotal' => round($value - $tax, 2),
                'tax' => $tax,
                'total' => $value,
                'paid_amount' => $refund,
                'due_amount' => 0,
                'invoice_date' => now(),
                'paid_date' => now(),
                'notes' => trim(($reason ? "Reason: {$reason}" : '').($dueReduced > 0 ? "\nDue reduced by ".money($dueReduced) : '')) ?: null,
            ]);

            $inventory = app(InventoryService::class);

            foreach ($picked as [$item, $qty, $restock, $amount, $lineTax]) {
                InvoiceItem::create([
                    'invoice_id' => $return->id,
                    'parent_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $qty,
                    'unit_price' => round($amount / $qty, 2),
                    'unit_cost' => $item->unit_cost,
                    'tax_rate' => $item->tax_rate,
                    'subtotal' => $amount,
                    'tax_amount' => $lineTax,
                    'is_gift' => $item->is_gift,
                    'restock' => $restock,
                ]);

                // Damaged goods are refunded but never go back on the shelf.
                if ($restock && $sale->warehouse_id) {
                    $inventory->add(
                        productId: $item->product_id,
                        warehouseId: $sale->warehouse_id,
                        quantity: $qty,
                        type: 'return',
                        extra: ['reference' => $return, 'unit_cost' => (float) $item->unit_cost ?: null, 'notes' => $reason],
                    );
                }
            }

            if ($refund > 0) {
                Payment::create([
                    'invoice_id' => $return->id,
                    'created_by' => auth()->id(),
                    'shift_id' => $shift?->id,
                    'payment_account_id' => PaymentAccount::requiredFor($refundMethod) ? $paymentAccountId : null,
                    'amount' => $refund,
                    'method' => $refundMethod,
                    'status' => 'refunded',
                    'reference' => $reference,
                    'payment_date' => now(),
                    'notes' => "Refund for {$sale->invoice_number}",
                ]);
            }

            // The sale now reflects what was kept.
            $fullyReturned = $sale->items->every(fn (InvoiceItem $i) => $i->fresh()->returnableQuantity() <= InventoryService::EPSILON);
            $newDue = round((float) $sale->due_amount - $dueReduced, 2);

            $sale->update([
                'returned_amount' => round((float) $sale->returned_amount + $value, 2),
                'due_amount' => $newDue,
                'paid_amount' => round((float) $sale->paid_amount - $refund, 2),
                // Still owing (a courier charge, say) keeps the sale on the dues lists.
                'status' => $newDue > 0 ? $sale->status : ($fullyReturned ? 'returned' : 'paid'),
            ]);

            return $return;
        });

        DashboardIndex::flushCache();

        return $return;
    }
}
