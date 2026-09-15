<?php

namespace App\Livewire\Pos;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PosTerminal extends Component
{
    public string $search = '';

    public int $highlightIndex = 0;

    public array $cart = [];

    public ?int $customer_id = null;

    public string $customerSearch = '';

    public int $customerHighlight = 0;

    public bool $showCustomerSearch = false;

    public ?int $warehouse_id = null;

    public string $discount = '0';

    public string $tax = '0';

    public bool $hasCourier = false;

    /** Billed to the customer; part of the invoice total. */
    public string $courierCharge = '0';

    /** Paid to the courier; internal, never shown on the customer's copy. */
    public string $courierCost = '0';

    public string $paymentMethod = 'cash';

    public string $amountReceived = '';

    public bool $showPaymentModal = false;

    public bool $showSuccessModal = false;

    public ?Invoice $lastInvoice = null;

    public function mount(): void
    {
        $this->warehouse_id = Warehouse::getDefault()?->id;
    }

    // ============ PRODUCT SEARCH ============

    public function updatedSearch(): void
    {
        $this->highlightIndex = 0;

        // Auto-add on exact barcode match
        if (strlen($this->search) >= 8) {
            $product = Product::where('barcode', $this->search)->active()->first();
            if ($product) {
                $this->addToCart($product->id);
                $this->search = '';
            }
        }
    }

    public function getSearchResultsProperty()
    {
        if (trim($this->search) === '') {
            return collect();
        }

        // Only show products that are in stock in the warehouse being sold from.
        return Product::where('status', 'active')
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('barcode', 'like', "%{$this->search}%");
            })
            ->whereHas('warehouses', fn ($q) => $q
                ->where('warehouses.id', $this->warehouse_id)
                ->where('product_warehouse.quantity', '>', 0))
            ->limit(8)
            ->get();
    }

    /**
     * How much of this product is on hand in the warehouse currently selected.
     */
    private function stockHere(int $productId): int
    {
        return app(InventoryService::class)->stockIn($productId, $this->warehouse_id);
    }

    /**
     * Switching warehouse invalidates the cart: the quantities and availability
     * were all taken from the previous location.
     */
    public function updatedWarehouseId(): void
    {
        if ($this->cart !== []) {
            $this->cart = [];
            session()->flash('error', 'Cart cleared — stock differs between warehouses.');
        }

        $this->search = '';
    }

    public function moveHighlight(int $direction): void
    {
        $count = $this->searchResults->count();
        if ($count === 0) {
            return;
        }

        $this->highlightIndex = ($this->highlightIndex + $direction + $count) % $count;
    }

    public function selectHighlighted(int $index = 0): void
    {
        $results = $this->searchResults;
        if ($results->count() === 0) {
            return;
        }

        $product = $results->values()->get($index);
        if ($product) {
            $this->addToCart($product->id);
            $this->search = '';
        }
    }

    // ============ CART ============

    public function addToCart(int $productId): void
    {
        $product = Product::find($productId);
        $available = $this->stockHere($productId);

        if (! $product || $available <= 0) {
            return;
        }

        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $productId) {
                if ($item['quantity'] < $available) {
                    $this->cart[$index]['quantity']++;
                }

                return;
            }
        }

        $discount = app(PricingService::class)->resolveDiscount($product);

        $this->cart[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'price' => (float) $product->selling_price,
            'tax_rate' => (float) $product->tax_rate,
            'discount' => $discount->perUnit,
            'promotion_id' => $discount->promotionId,
            'promotion_label' => $discount->label,
            'quantity' => 1,
            'max_quantity' => $available,
        ];
    }

    public function quickAdd(int $productId): void
    {
        $this->addToCart($productId);
        $this->search = '';
        $this->highlightIndex = 0;
    }

    public function incrementQty(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        if ($this->cart[$index]['quantity'] < $this->cart[$index]['max_quantity']) {
            $this->cart[$index]['quantity']++;
        }
    }

    public function decrementQty(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity']--;
        } else {
            $this->removeFromCart($index);
        }
    }

    public function updateQty(int $index, $value): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        $qty = max(1, min((int) $value, $this->cart[$index]['max_quantity']));
        $this->cart[$index]['quantity'] = $qty;
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->discount = '0';
        $this->tax = '0';
        $this->amountReceived = '';
        $this->paymentMethod = 'cash';
        $this->resetCourier();
    }

    // ============ COURIER ============

    public function updatedHasCourier(): void
    {
        if (! $this->hasCourier) {
            $this->resetCourier();
        }
    }

    private function resetCourier(): void
    {
        $this->hasCourier = false;
        $this->courierCharge = '0';
        $this->courierCost = '0';
    }

    /** The charge, or zero when the courier option is switched off. */
    public function getCourierChargeValueProperty(): float
    {
        return $this->hasCourier ? (float) ($this->courierCharge ?: 0) : 0.0;
    }

    public function getCourierCostValueProperty(): float
    {
        return $this->hasCourier ? (float) ($this->courierCost ?: 0) : 0.0;
    }

    // ============ TOTALS ============

    /**
     * Net value of the goods in the cart, excluding line tax.
     * Line tax is reported separately via $this->cartItemTax.
     */
    public function getCartSubtotalProperty(): float
    {
        return array_reduce($this->cart, function ($sum, $item) {
            return $sum + ($item['price'] * $item['quantity']) - ($item['discount'] * $item['quantity']);
        }, 0);
    }

    /** Tax accumulated from each line's tax_rate. */
    public function getCartItemTaxProperty(): float
    {
        return array_reduce($this->cart, function ($sum, $item) {
            $lineTotal = ($item['price'] * $item['quantity']) - ($item['discount'] * $item['quantity']);

            return $sum + ($lineTotal * ($item['tax_rate'] / 100));
        }, 0);
    }

    /** Per-line tax plus the manually entered additional tax. */
    public function getCartTaxTotalProperty(): float
    {
        return $this->cartItemTax + (float) ($this->tax ?: 0);
    }

    public function getCartItemDiscountTotalProperty(): float
    {
        return array_reduce($this->cart, fn ($sum, $item) => $sum + ($item['discount'] * $item['quantity']), 0);
    }

    public function getCartTotalProperty(): float
    {
        $discount = (float) ($this->discount ?: 0);

        return max(0, $this->cartSubtotal - $discount + $this->cartTaxTotal + $this->courierChargeValue);
    }

    public function getChangeDueProperty(): float
    {
        return max(0, $this->amountReceivedValue() - $this->cartTotal);
    }

    /**
     * Parse the cash-received input into a number.
     *
     * A plain (float) cast is not enough: a cashier may type "1,100.00", and
     * PHP truncates at the comma, yielding 1.0 — which silently reads as
     * underpayment and blocks the sale.
     */
    private function amountReceivedValue(): float
    {
        return (float) str_replace(',', '', $this->amountReceived ?: '0');
    }

    // ============ CUSTOMER ============

    public function getCustomerResultsProperty()
    {
        if (empty($this->customerSearch)) {
            return collect();
        }

        return Customer::active()
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->customerSearch}%")
                ->orWhere('phone', 'like', "%{$this->customerSearch}%")
            )
            ->limit(6)
            ->get();
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::find($id);
        $this->customer_id = $customer->id;
        $this->customerSearch = $customer->name.($customer->phone ? " ({$customer->phone})" : '');
        $this->showCustomerSearch = false;
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customerSearch = '';
    }

    // ============ PAYMENT / CHECKOUT ============

    public function openPaymentModal(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Cart is empty.');

            return;
        }
        // No thousands separator: this populates a numeric input that is later
        // cast with (float), and "1,100.00" would cast to 1.0.
        $this->amountReceived = number_format($this->cartTotal, 2, '.', '');
        $this->showPaymentModal = true;
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
        if ($method === 'cash') {
            $this->amountReceived = number_format($this->cartTotal, 2, '.', '');
        }
    }

    public function completeSale(): void
    {
        if (empty($this->cart)) {
            return;
        }

        $received = $this->amountReceivedValue();
        $total = $this->cartTotal;

        if ($this->paymentMethod === 'cash' && $received < $total) {
            session()->flash('error', 'Received amount is less than total.');

            return;
        }

        $invoice = DB::transaction(function () use ($total) {
            $customer = $this->customer_id ? Customer::find($this->customer_id) : null;

            $invoice = Invoice::create([
                'warehouse_id' => $this->warehouse_id,
                'customer_id' => $this->customer_id,
                'created_by' => auth()->id(),
                'customer_name' => $customer?->name ?? 'Walk-in Customer',
                'customer_email' => $customer?->email,
                'customer_phone' => $customer?->phone,
                'customer_address' => $customer?->full_address,
                'status' => 'paid',
                'payment_method' => $this->paymentMethod,
                'subtotal' => $this->cartSubtotal,
                'tax' => $this->cartTaxTotal,
                'discount' => (float) ($this->discount ?: 0),
                'courier_charge' => $this->courierChargeValue,
                'courier_cost' => $this->courierCostValue,
                'total' => $total,
                'paid_amount' => $total,
                'due_amount' => 0,
                'invoice_date' => now(),
                'paid_date' => now(),
            ]);

            $inventory = app(InventoryService::class);

            foreach ($this->cart as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $item['discount'],
                    'subtotal' => $this->lineSubtotal($item),
                ]);

                $this->consumePromotion($item);

                // Takes the stock out of this terminal's warehouse. Throws (and
                // rolls the whole sale back) if the stock is no longer there.
                $inventory->remove(
                    productId: $item['product_id'],
                    warehouseId: $this->warehouse_id,
                    quantity: (int) $item['quantity'],
                    type: 'sale',
                    extra: ['reference' => $invoice],
                );
            }

            Payment::create([
                'invoice_id' => $invoice->id,
                'created_by' => auth()->id(),
                'amount' => $total,
                'method' => $this->paymentMethod,
                'status' => 'completed',
                'payment_date' => now(),
            ]);

            // Update customer stats
            if ($customer) {
                $customer->increment('total_orders');
                $customer->increment('total_purchases', $total);
            }

            return $invoice;
        });

        DashboardIndex::flushCache();

        $this->lastInvoice = $invoice;
        $this->showPaymentModal = false;
        $this->showSuccessModal = true;
        $this->clearCart();
    }

    /** Line total after per-unit discount, plus this line's tax. */
    private function lineSubtotal(array $item): float
    {
        $afterDiscount = ($item['price'] - $item['discount']) * $item['quantity'];

        return $afterDiscount + ($afterDiscount * $item['tax_rate'] / 100);
    }

    /**
     * Record one use of the promotion attached to this line.
     *
     * The usage_limit is re-checked in the WHERE clause rather than in PHP, so
     * concurrent sales cannot push used_count past the limit.
     */
    private function consumePromotion(array $item): void
    {
        if (empty($item['promotion_id'])) {
            return;
        }

        Promotion::where('id', $item['promotion_id'])
            ->where(fn ($q) => $q
                ->whereNull('usage_limit')
                ->orWhereColumn('used_count', '<', 'usage_limit'))
            ->increment('used_count');
    }

    public function newSale(): void
    {
        $this->showSuccessModal = false;
        $this->lastInvoice = null;
    }

    public function render()
    {
        $warehouses = Warehouse::active()->get();

        return view('livewire.pos.pos-terminal', compact('warehouses'))
            ->layout('layouts.app', ['title' => 'POS Terminal']);
    }
}
