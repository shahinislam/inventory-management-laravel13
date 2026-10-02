<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;

/**
 * Park a sale (customer still shopping) and serve the next one.
 *
 * A held sale is a draft invoice with is_held set: no number is used up, no
 * stock moves and it never counts as a sale. Resuming loads it back into the
 * cart at today's prices and stock, then deletes it.
 */
trait HoldsSales
{
    public bool $showHoldModal = false;

    public bool $showHeldList = false;

    public string $holdLabel = '';

    public function getHeldSalesProperty()
    {
        return Invoice::held()
            ->where('warehouse_id', $this->warehouse_id)
            ->withCount('items')
            ->with('createdBy:id,name')
            ->latest()
            ->get();
    }

    public function openHoldModal(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Nothing to hold — the cart is empty.');

            return;
        }

        $this->holdLabel = $this->selectedCustomer?->name ?? '';
        $this->showHoldModal = true;
    }

    public function holdSale(): void
    {
        if (empty($this->cart)) {
            return;
        }

        $this->validate(['holdLabel' => 'nullable|string|max:100']);

        DB::transaction(function () {
            $customer = $this->customer_id ? Customer::find($this->customer_id) : null;

            $held = Invoice::create([
                'is_held' => true,
                'held_label' => trim($this->holdLabel) ?: 'Held sale',
                'warehouse_id' => $this->warehouse_id,
                'customer_id' => $customer?->id,
                'created_by' => auth()->id(),
                'customer_name' => $customer?->name ?? 'Walk-in Customer',
                'customer_phone' => $customer?->phone,
                'status' => 'draft',
                'subtotal' => $this->cartSubtotal,
                // Only the manually entered extra tax is kept; line tax is recalculated on resume.
                'tax' => (float) ($this->tax ?: 0),
                'discount' => (float) ($this->discount ?: 0),
                'courier_charge' => $this->courierChargeValue,
                'courier_cost' => $this->courierCostValue,
                'total' => $this->cartTotal,
                'invoice_date' => now(),
            ]);

            // Reward gifts are not held; rewards are offered again on resume.
            foreach (array_filter($this->cart, fn ($i) => empty($i['is_gift'])) as $item) {
                InvoiceItem::create([
                    'invoice_id' => $held->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $item['discount'],
                    'subtotal' => $this->lineSubtotal($item),
                ]);
            }
        });

        $label = trim($this->holdLabel) ?: 'Sale';
        $this->showHoldModal = false;
        $this->holdLabel = '';
        $this->clearCart();
        session()->flash('success', "“{$label}” is on hold. Open Held to resume it.");
    }

    public function resumeHeld(int $id): void
    {
        $held = Invoice::held()->with('items')->find($id);

        if (! $held) {
            session()->flash('error', 'That held sale is no longer available.');

            return;
        }

        if (! empty($this->cart)) {
            session()->flash('error', 'Finish or hold the current sale first.');

            return;
        }

        $notes = [];

        foreach ($held->items as $line) {
            $product = Product::find($line->product_id);
            $available = $product ? $this->stockHere($product->id) : 0;

            if (! $product || $product->status !== 'active' || $available <= 0) {
                $notes[] = "{$line->product_name} is no longer available";

                continue;
            }

            $quantity = min((float) $line->quantity, $available);
            if ($quantity < (float) $line->quantity) {
                $notes[] = "{$line->product_name}: only ".format_qty($available, $product->unit).' left';
            }

            $discount = app(PricingService::class)->resolveDiscount($product);
            if ((float) $product->selling_price !== (float) $line->unit_price) {
                $notes[] = "{$product->name} price changed to ".money($product->selling_price);
            }

            $this->cart[] = $this->cartLine($product, $quantity, $available, $discount);
        }

        if ($held->customer_id && Customer::find($held->customer_id)) {
            $this->selectCustomer($held->customer_id);
        }

        $this->discount = (string) (float) $held->discount;
        $this->tax = (string) (float) $held->tax;
        if ((float) $held->courier_charge > 0 || (float) $held->courier_cost > 0) {
            $this->hasCourier = true;
            $this->courierCharge = (string) (float) $held->courier_charge;
            $this->courierCost = (string) (float) $held->courier_cost;
        }

        $held->items()->delete();
        $held->forceDelete();

        $this->showHeldList = false;
        $this->syncRewards();

        $notes === []
            ? session()->flash('success', "Resumed “{$held->held_label}”.")
            : session()->flash('error', 'Resumed with changes: '.implode('; ', $notes).'.');
    }

    public function discardHeld(int $id): void
    {
        $held = Invoice::held()->find($id);
        $held?->items()->delete();
        $held?->forceDelete();
    }
}
