<?php

namespace App\Livewire\Pos;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PosTerminal extends Component
{
    public string $search        = '';
    public array $cart           = [];

    public ?int $customer_id      = null;
    public string $customerSearch = '';
    public int $customerHighlight = 0;
    public bool $showCustomerSearch = false;

    public ?int $warehouse_id     = null;

    public string $discount       = '0';
    public string $tax            = '0';
    public string $paymentMethod  = 'cash';
    public string $amountReceived = '';

    public bool $showPaymentModal = false;
    public bool $showSuccessModal = false;
    public ?Invoice $lastInvoice  = null;

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
        if (trim($this->search) === '') return collect();

        return Product::where('status', 'active')
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('sku', 'like', "%{$this->search}%")
                  ->orWhere('barcode', 'like', "%{$this->search}%");
            })
            ->where('quantity', '>', 0)
            ->limit(8)
            ->get();
    }

    public function moveHighlight(int $direction): void
    {
        $count = $this->searchResults->count();
        if ($count === 0) return;

        $this->highlightIndex = ($this->highlightIndex + $direction + $count) % $count;
    }

    public function selectHighlighted(int $index = 0): void
    {
        $results = $this->searchResults;
        if ($results->count() === 0) return;

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
        if (!$product || $product->quantity <= 0) return;

        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $productId) {
                if ($item['quantity'] < $product->quantity) {
                    $this->cart[$index]['quantity']++;
                }
                return;
            }
        }

        [$discountAmount, $promotionId, $promotionLabel] = $this->resolveDiscount($product);

        $this->cart[] = [
            'product_id'      => $product->id,
            'name'            => $product->name,
            'sku'             => $product->sku,
            'unit'            => $product->unit,
            'price'           => (float) $product->selling_price,
            'tax_rate'        => (float) $product->tax_rate,
            'discount'        => $discountAmount,
            'promotion_id'    => $promotionId,
            'promotion_label' => $promotionLabel,
            'quantity'        => 1,
            'max_quantity'    => $product->quantity,
        ];
    }

    /**
     * Resolve the best discount for a product: an active promotion
     * (product-specific or category-wide) always takes priority over
     * the product's own flat/percentage discount field.
     *
     * @return array{0: float, 1: ?int, 2: ?string}
     */
    private function resolveDiscount(Product $product): array
    {
        $promotion = Promotion::active()
            ->where(function ($q) use ($product) {
                $q->where('product_id', $product->id)
                  ->orWhere('category_id', $product->category_id);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            // Prefer a product-specific promo over a category-wide one
            ->orderByRaw('CASE WHEN product_id = ? THEN 0 ELSE 1 END', [$product->id])
            ->first();

        if ($promotion) {
            $perUnitDiscount = $promotion->type === 'fixed'
                ? (float) $promotion->value
                : (float) $product->selling_price * ((float) $promotion->value / 100);

            if ($promotion->max_discount) {
                $perUnitDiscount = min($perUnitDiscount, (float) $promotion->max_discount);
            }

            return [$perUnitDiscount, $promotion->id, $promotion->name];
        }

        // Fallback to product's own discount field
        $productDiscount = $product->discount_type === 'fixed'
            ? (float) $product->discount
            : (float) $product->selling_price * ((float) $product->discount / 100);

        return [$productDiscount, null, null];
    }

    public function quickAdd(int $productId): void
    {
        $this->addToCart($productId);
        $this->search          = '';
        $this->highlightIndex  = 0;
    }

    public function incrementQty(int $index): void
    {
        if (!isset($this->cart[$index])) return;
        if ($this->cart[$index]['quantity'] < $this->cart[$index]['max_quantity']) {
            $this->cart[$index]['quantity']++;
        }
    }

    public function decrementQty(int $index): void
    {
        if (!isset($this->cart[$index])) return;
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity']--;
        } else {
            $this->removeFromCart($index);
        }
    }

    public function updateQty(int $index, $value): void
    {
        if (!isset($this->cart[$index])) return;
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
        $this->cart            = [];
        $this->customer_id     = null;
        $this->customerSearch  = '';
        $this->discount        = '0';
        $this->tax             = '0';
        $this->amountReceived  = '';
        $this->paymentMethod   = 'cash';
    }

    // ============ TOTALS ============

    public function getCartSubtotalProperty(): float
    {
        return array_reduce($this->cart, function ($sum, $item) {
            $lineTotal = ($item['price'] * $item['quantity']) - ($item['discount'] * $item['quantity']);
            $lineTax   = $lineTotal * ($item['tax_rate'] / 100);
            return $sum + $lineTotal + $lineTax;
        }, 0);
    }

    public function getCartItemDiscountTotalProperty(): float
    {
        return array_reduce($this->cart, fn($sum, $item) => $sum + ($item['discount'] * $item['quantity']), 0);
    }

    public function getCartTotalProperty(): float
    {
        $subtotal = $this->cartSubtotal;
        $discount = (float) ($this->discount ?: 0);
        $tax      = (float) ($this->tax ?: 0);
        return max(0, $subtotal - $discount + $tax);
    }

    public function getChangeDueProperty(): float
    {
        $received = (float) ($this->amountReceived ?: 0);
        return max(0, $received - $this->cartTotal);
    }

    // ============ CUSTOMER ============

    public function getCustomerResultsProperty()
    {
        if (empty($this->customerSearch)) return collect();

        return Customer::active()
            ->where(fn($q) => $q
                ->where('name', 'like', "%{$this->customerSearch}%")
                ->orWhere('phone', 'like', "%{$this->customerSearch}%")
            )
            ->limit(6)
            ->get();
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::find($id);
        $this->customer_id    = $customer->id;
        $this->customerSearch = $customer->name . ($customer->phone ? " ({$customer->phone})" : '');
        $this->showCustomerSearch = false;
    }

    public function clearCustomer(): void
    {
        $this->customer_id    = null;
        $this->customerSearch = '';
    }

    // ============ PAYMENT / CHECKOUT ============

    public function openPaymentModal(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Cart is empty.');
            return;
        }
        $this->amountReceived  = number_format($this->cartTotal, 2);
        $this->showPaymentModal = true;
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
        if ($method === 'cash') {
            $this->amountReceived = number_format($this->cartTotal, 2);
        }
    }

    public function completeSale(): void
    {
        if (empty($this->cart)) return;

        $received = (float) ($this->amountReceived ?: 0);
        $total    = $this->cartTotal;

        if ($this->paymentMethod === 'cash' && $received < $total) {
            session()->flash('error', 'Received amount is less than total.');
            return;
        }

        $invoice = DB::transaction(function () use ($total, $received) {
            $customer = $this->customer_id ? Customer::find($this->customer_id) : null;

            $invoice = Invoice::create([
                'warehouse_id'     => $this->warehouse_id,
                'customer_id'      => $this->customer_id,
                'created_by'       => auth()->id(),
                'customer_name'    => $customer?->name ?? 'Walk-in Customer',
                'customer_email'   => $customer?->email,
                'customer_phone'   => $customer?->phone,
                'customer_address' => $customer?->full_address,
                'status'           => 'paid',
                'payment_method'   => $this->paymentMethod,
                'subtotal'         => $this->cartSubtotal,
                'tax'              => (float) ($this->tax ?: 0),
                'discount'         => (float) ($this->discount ?: 0),
                'total'            => $total,
                'paid_amount'      => $total,
                'due_amount'       => 0,
                'invoice_date'     => now(),
                'paid_date'        => now(),
            ]);

            foreach ($this->cart as $item) {
                InvoiceItem::create([
                    'invoice_id'   => $invoice->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_sku'  => $item['sku'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['price'],
                    'tax_rate'     => $item['tax_rate'],
                    'discount'     => $item['discount'],
                    'subtotal'     => ($item['price'] * $item['quantity']) - ($item['discount'] * $item['quantity']) + ((($item['price'] * $item['quantity']) - ($item['discount'] * $item['quantity'])) * $item['tax_rate'] / 100),
                ]);

                if (!empty($item['promotion_id'])) {
                    Promotion::where('id', $item['promotion_id'])->increment('used_count');
                }

                // Stock movement
                $product = Product::find($item['product_id']);
                $before  = $product->quantity;
                $after   = $before - $item['quantity'];
                $product->update(['quantity' => $after]);

                StockMovement::create([
                    'product_id'      => $product->id,
                    'warehouse_id'    => $this->warehouse_id,
                    'created_by'      => auth()->id(),
                    'type'            => 'sale',
                    'quantity'        => $item['quantity'],
                    'before_quantity' => $before,
                    'after_quantity'  => $after,
                    'unit_cost'       => $product->cost_price,
                    'reference_type'  => Invoice::class,
                    'reference_id'    => $invoice->id,
                ]);
            }

            Payment::create([
                'invoice_id'   => $invoice->id,
                'created_by'   => auth()->id(),
                'amount'       => $total,
                'method'       => $this->paymentMethod,
                'status'       => 'completed',
                'payment_date' => now(),
            ]);

            // Update customer stats
            if ($customer) {
                $customer->increment('total_orders');
                $customer->increment('total_purchases', $total);
            }

            return $invoice;
        });

        Cache::forget('dashboard_stats_today');

        $this->lastInvoice      = $invoice;
        $this->showPaymentModal = false;
        $this->showSuccessModal = true;
        $this->clearCart();
    }

    public function newSale(): void
    {
        $this->showSuccessModal = false;
        $this->lastInvoice      = null;
    }

    public function render()
    {
        $warehouses = Warehouse::active()->get();

        return view('livewire.pos.pos-terminal', compact('warehouses'))
            ->layout('layouts.app', ['title' => 'POS Terminal']);
    }
}
