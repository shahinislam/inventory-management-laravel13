<?php

namespace App\Livewire\Purchases;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePayment;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Goods sent back to the supplier against a received purchase order.
 *
 * A return is stored as a purchase_orders row with type=return and
 * parent_order_id pointing at the original order. Its lines point at the
 * original lines (parent_item_id) and are in the same unit they were ordered
 * in (e.g. cartons); stock leaves in base units (qty × unit_factor).
 *
 * The return's total is taken off what the shop owes on the parent order
 * (parent.returned_amount). Money the supplier hands back is recorded as a
 * PurchasePayment on the return itself.
 */
class PurchaseReturnForm extends Component
{
    /** The purchase order goods are returned from (create mode). */
    public ?PurchaseOrder $order = null;

    /** An existing return being viewed (show mode). */
    public ?PurchaseOrder $return = null;

    /**
     * One row per returnable line of the parent order:
     * item_id, name, sku, unit, unit_label, factor, unit_cost, returnable, available, loose, qty.
     */
    public array $lines = [];

    public string $return_date = '';

    public string $reason = '';

    // Refund received from the supplier now (optional)
    public string $refund_amount = '';

    public string $refund_method = 'cash';

    public ?int $refund_account_id = null;

    public string $refund_reference = '';

    public function mount(?PurchaseOrder $order = null, ?PurchaseOrder $return = null): void
    {
        if ($return?->exists) {
            if (! $return->isReturn()) {
                $this->redirect(route('purchases.edit', $return), navigate: true);

                return;
            }

            $this->return = $return->load([
                'items.product', 'parent', 'supplier', 'warehouse', 'createdBy', 'payments.paymentAccount',
            ]);

            return;
        }

        if (! $order?->exists || $order->isReturn() || in_array($order->status, ['draft', 'cancelled'], true)) {
            session()->flash('error', 'Only placed purchase orders can be returned.');
            $this->redirect($order?->exists ? route('purchases.edit', $order) : route('purchases.index'), navigate: true);

            return;
        }

        $this->order = $order->load(['items.product', 'supplier', 'warehouse']);
        $this->return_date = now()->format('Y-m-d');
        $this->buildLines();
    }

    /** How much of each parent line has already gone back, in ordered units. */
    private static function returnedSoFar(array $itemIds): array
    {
        return PurchaseOrderItem::query()
            ->whereIn('parent_item_id', $itemIds)
            ->whereHas('purchaseOrder', fn ($q) => $q->returns())
            ->groupBy('parent_item_id')
            ->selectRaw('parent_item_id, SUM(quantity) as qty')
            ->pluck('qty', 'parent_item_id')
            ->map(fn ($q) => (float) $q)
            ->all();
    }

    private function buildLines(): void
    {
        $inventory = app(InventoryService::class);
        $returned = self::returnedSoFar($this->order->items->pluck('id')->all());

        $this->lines = $this->order->items
            ->map(function (PurchaseOrderItem $item) use ($inventory, $returned) {
                $returnable = round($item->received_quantity - ($returned[$item->id] ?? 0), 3);

                return [
                    'item_id' => $item->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'unit' => $item->product->unit,
                    'unit_label' => $item->unit_label ?: $item->product->unit,
                    'factor' => $item->factor,
                    'unit_cost' => (float) $item->unit_cost,
                    'returnable' => max(0, $returnable),
                    'available' => $inventory->stockIn($item->product_id, $this->order->warehouse_id),
                    'loose' => $item->product->isLoose(),
                    'qty' => '',
                ];
            })
            ->filter(fn ($line) => $line['returnable'] > 0)
            ->values()
            ->all();
    }

    /** Line value: qty × cost, less the line discount %, plus line tax %. */
    private static function lineSubtotal(PurchaseOrderItem $item, float $qty): float
    {
        $sub = $qty * (float) $item->unit_cost;
        $sub -= $sub * ((float) $item->discount / 100);
        $sub += $sub * ((float) $item->tax_rate / 100);

        return round($sub, 2);
    }

    public function getReturnTotalProperty(): float
    {
        if (! $this->order) {
            return 0.0;
        }

        $items = $this->order->items->keyBy('id');

        return round(collect($this->lines)->sum(function ($line) use ($items) {
            $item = $items->get($line['item_id']);

            return $item ? self::lineSubtotal($item, max(0, (float) ($line['qty'] ?: 0))) : 0;
        }), 2);
    }

    public function returnAll(): void
    {
        foreach ($this->lines as $i => $line) {
            $this->lines[$i]['qty'] = rtrim(rtrim(number_format($line['returnable'], 3, '.', ''), '0'), '.');
        }
    }

    public function updatedRefundMethod(): void
    {
        $accounts = PaymentAccount::forMethod($this->refund_method)->get();
        $this->refund_account_id = $accounts->count() === 1 ? $accounts->first()->id : null;
    }

    public function save(): void
    {
        if (! auth()->user()->hasRole(['admin', 'manager'])) {
            session()->flash('error', 'You do not have permission to return purchases.');

            return;
        }

        if (! $this->order) {
            return;
        }

        $this->refund_amount = str_replace(',', '', $this->refund_amount);
        $refund = (float) ($this->refund_amount ?: 0);

        $rules = [
            'return_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'refund_amount' => 'nullable|numeric|min:0',
        ];
        foreach ($this->lines as $i => $line) {
            $fraction = ! empty($line['loose']) && (float) $line['factor'] <= 1;
            $rules["lines.{$i}.qty"] = 'nullable|numeric|min:0'.($fraction ? '' : '|integer');
        }
        if ($refund > 0) {
            $rules['refund_method'] = Payment::methodRule();
            $rules['refund_reference'] = Payment::needsReference($this->refund_method)
                ? 'required|string|max:100'
                : 'nullable|string|max:100';
            $rules['refund_account_id'] = PaymentAccount::rule($this->refund_method);
        }

        $this->validate($rules, [
            'lines.*.qty.integer' => 'Whole numbers only.',
            'refund_reference.required' => 'Enter the transaction ID for this refund.',
            'refund_account_id.required' => 'Select the account the refund was received into.',
        ]);

        $inventory = app(InventoryService::class);

        $return = DB::transaction(function () use ($inventory, $refund) {
            $parent = PurchaseOrder::whereKey($this->order->id)->lockForUpdate()->firstOrFail();
            $items = $parent->items()->with('product')->get()->keyBy('id');
            $returned = self::returnedSoFar($items->keys()->all());

            // Work out every line first, so nothing is written if one fails.
            $rows = [];
            foreach ($this->lines as $i => $line) {
                $qty = round((float) ($line['qty'] ?: 0), 3);
                $item = $items->get($line['item_id'] ?? null);

                if ($qty <= 0 || ! $item) {
                    continue;
                }

                $returnable = round($item->received_quantity - ($returned[$item->id] ?? 0), 3);
                if ($qty > $returnable + InventoryService::EPSILON) {
                    throw ValidationException::withMessages([
                        "lines.{$i}.qty" => 'Only '.format_qty(max(0, $returnable), $item->unit_label ?: $item->product->unit).' can be returned.',
                    ]);
                }

                $baseQty = round($qty * $item->factor, 3);
                $available = $inventory->stockIn($item->product_id, $parent->warehouse_id);
                if ($baseQty > $available + InventoryService::EPSILON) {
                    throw ValidationException::withMessages([
                        "lines.{$i}.qty" => 'Only '.format_qty($available, $item->product->unit).' in stock.',
                    ]);
                }

                $rows[] = [$item, $qty, $baseQty, self::lineSubtotal($item, $qty)];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['lines' => 'Enter a quantity to return on at least one line.']);
            }

            $total = round(array_sum(array_column($rows, 3)), 2);

            if ($refund > $total + 0.005) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Refund cannot be more than the return total of '.money($total).'.',
                ]);
            }

            $return = PurchaseOrder::create([
                'type' => 'return',
                'parent_order_id' => $parent->id,
                'supplier_id' => $parent->supplier_id,
                'warehouse_id' => $parent->warehouse_id,
                'created_by' => auth()->id(),
                'status' => 'received',
                'order_date' => $this->return_date,
                'received_date' => $this->return_date,
                'subtotal' => $total,
                'tax' => 0,
                'discount' => 0,
                'total' => $total,
                'paid_amount' => 0,
                'notes' => $this->reason ?: null,
            ]);

            foreach ($rows as [$item, $qty, $baseQty, $subtotal]) {
                $return->items()->create([
                    'product_id' => $item->product_id,
                    'parent_item_id' => $item->id,
                    'quantity' => $qty,
                    'received_quantity' => $qty,
                    'unit_cost' => $item->unit_cost,
                    'tax_rate' => $item->tax_rate ?? 0,
                    'discount' => $item->discount ?? 0,
                    'unit_label' => $item->unit_label,
                    'unit_factor' => $item->factor,
                    'subtotal' => $subtotal,
                ]);

                $inventory->remove($item->product_id, $parent->warehouse_id, $baseQty, 'purchase_return', [
                    'reference' => $return,
                    'unit_cost' => (float) $item->unit_cost / $item->factor,
                    'notes' => $this->reason ?: null,
                ]);
            }

            $parent->increment('returned_amount', $total);

            if ($refund > 0) {
                PurchasePayment::create([
                    'purchase_order_id' => $return->id,
                    'created_by' => auth()->id(),
                    'payment_account_id' => PaymentAccount::requiredFor($this->refund_method) ? $this->refund_account_id : null,
                    'amount' => $refund,
                    'method' => $this->refund_method,
                    'reference' => $this->refund_reference ?: null,
                    'payment_date' => $this->return_date,
                    'notes' => 'Refund from supplier',
                ]);

                $return->increment('paid_amount', $refund);
            }

            return $return;
        });

        DashboardIndex::flushCache();

        session()->flash('success', "Return {$return->order_number} recorded and stock updated.");
        $this->redirect(route('purchases.returns.show', $return), navigate: true);
    }

    public function render()
    {
        $refundAccounts = $this->order
            ? PaymentAccount::forMethod($this->refund_method)->get()
            : collect();

        $title = $this->return
            ? "Return {$this->return->order_number}"
            : 'Return to supplier'.($this->order ? " — {$this->order->order_number}" : '');

        return view('livewire.purchases.purchase-return-form', compact('refundAccounts'))
            ->layout('layouts.app', ['title' => $title]);
    }
}
