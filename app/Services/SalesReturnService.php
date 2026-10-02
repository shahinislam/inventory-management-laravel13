<?php

namespace App\Services;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goods coming back from a customer.
 *
 * A return is stored as an invoice of type 'return' pointing at the sale
 * (parent_invoice_id), with one line per returned sale line (parent_item_id).
 * Each line is refunded at what the customer actually paid for it: the line
 * total less its share of any bill-level and member discount.
 *
 * Money goes back in this order: first the customer's unpaid due on the sale
 * is reduced, then anything left is refunded by the chosen method.
 */
class SalesReturnService
{
    public const REFUND_METHODS = ['cash', 'card', 'mobile_banking', 'bank_transfer'];

    /** What one unit of each sold line is worth back, after bill-level discounts. */
    public function unitRefunds(Invoice $invoice): array
    {
        $items = $invoice->items;
        $lineTotal = (float) $items->sum('subtotal');
        $billDiscount = (float) $invoice->discount + (float) $invoice->membership_discount;

        return $items->mapWithKeys(function (InvoiceItem $item) use ($lineTotal, $billDiscount) {
            $share = $lineTotal > 0 ? $billDiscount * ((float) $item->subtotal / $lineTotal) : 0;
            $net = max(0, (float) $item->subtotal - $share);

            return [$item->id => $item->quantity > 0 ? $net / $item->quantity : 0];
        })->all();
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
            $perUnit = $this->unitRefunds($sale);

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

                $picked[] = [$item, $qty, (bool) ($line['restock'] ?? true), round($perUnit[$item->id] * $qty, 2)];
            }

            if ($picked === []) {
                throw ValidationException::withMessages(['lines' => 'Enter a quantity for at least one item.']);
            }

            $value = round(array_sum(array_column($picked, 3)), 2);
            $dueReduced = round(min((float) $sale->due_amount, $value), 2);
            $refund = round($value - $dueReduced, 2);

            if ($refund > 0) {
                if (! in_array($refundMethod, self::REFUND_METHODS, true)) {
                    throw ValidationException::withMessages(['refundMethod' => 'Choose how the money is given back.']);
                }
                if ($refundMethod === 'cash' && ! $shift) {
                    throw ValidationException::withMessages(['refundMethod' => 'Open a shift to pay a cash refund from the drawer.']);
                }
                if (PaymentAccount::requiredFor($refundMethod) && ! $paymentAccountId) {
                    throw ValidationException::withMessages(['paymentAccountId' => 'Select the account the refund is paid from.']);
                }
                if (Payment::needsReference($refundMethod) && blank($reference)) {
                    throw ValidationException::withMessages(['reference' => 'Enter the bKash / Nagad transaction ID.']);
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
                'subtotal' => $value,
                'total' => $value,
                'paid_amount' => $refund,
                'due_amount' => 0,
                'invoice_date' => now(),
                'paid_date' => now(),
                'notes' => trim(($reason ? "Reason: {$reason}" : '').($dueReduced > 0 ? "\nDue reduced by ".money($dueReduced) : '')) ?: null,
            ]);

            $inventory = app(InventoryService::class);

            foreach ($picked as [$item, $qty, $restock, $amount]) {
                InvoiceItem::create([
                    'invoice_id' => $return->id,
                    'parent_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $qty,
                    'unit_price' => round($amount / $qty, 2),
                    'unit_cost' => $item->unit_cost,
                    'subtotal' => $amount,
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
                'status' => $fullyReturned ? 'returned' : ($newDue <= 0 ? 'paid' : $sale->status),
            ]);

            if ($sale->customer_id) {
                Customer::whereKey($sale->customer_id)->update([
                    'total_purchases' => DB::raw('GREATEST(0, total_purchases - '.(float) $value.')'),
                ]);
            }

            return $return;
        });

        DashboardIndex::flushCache();

        return $return;
    }
}
