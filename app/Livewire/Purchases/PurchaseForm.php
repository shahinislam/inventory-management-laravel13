<?php

namespace App\Livewire\Purchases;

use App\Concerns\HandlesBarcodeScans;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PurchaseForm extends Component
{
    use HandlesBarcodeScans;

    private const RELATIONS = ['items.product', 'payments.paymentAccount', 'returns'];

    public ?PurchaseOrder $order = null;

    // Header fields
    public ?int $supplier_id = null;

    public ?int $warehouse_id = null;

    public string $order_date = '';

    public string $expected_date = '';

    public string $payment_due_date = '';

    public string $notes = '';

    public string $tax = '0';

    public string $discount = '0';

    public bool $hasCourier = false;

    /** Freight billed by the supplier; part of the order total. */
    public string $courierCharge = '0';

    /** Freight paid separately to a courier, outside the supplier invoice. */
    public string $courierCost = '0';

    /**
     * Line items. Quantity and unit_cost are in the line's chosen unit: the
     * product's base unit (pcs) or its purchase unit (carton), whose size in
     * base units is unit_factor.
     */
    public array $items = [];

    // Product search
    public string $productSearch = '';

    // Receive modal
    public bool $showReceiveModal = false;

    public array $receiveQuantities = [];

    /** Batch number per order item id, entered while receiving. */
    public array $receiveBatches = [];

    /** Expiry date per order item id; required for products that track expiry. */
    public array $receiveExpiry = [];

    // Record payment modal
    public bool $showPaymentModal = false;

    public string $payment_amount = '';

    public string $payment_method = 'cash';

    public ?int $payment_account_id = null;

    public string $payment_reference = '';

    public function mount(?PurchaseOrder $order = null): void
    {
        $this->order_date = now()->format('Y-m-d');

        // Returns to the supplier live in the same table but have their own screen.
        if ($order?->exists && $order->isReturn()) {
            $this->redirect(route('purchases.returns.show', $order), navigate: true);

            return;
        }

        if ($order?->exists) {
            $this->order = $order->load(self::RELATIONS);
            $this->supplier_id = $order->supplier_id;
            $this->warehouse_id = $order->warehouse_id;
            $this->order_date = $order->order_date->format('Y-m-d');
            $this->expected_date = $order->expected_date?->format('Y-m-d') ?? '';
            $this->payment_due_date = $order->payment_due_date?->format('Y-m-d') ?? '';
            $this->notes = $order->notes ?? '';
            // Cast explicitly: these are string-typed properties and the columns
            // are nullable in practice, so a null would raise a TypeError.
            $this->tax = (string) ($order->tax ?? '0');
            $this->discount = (string) ($order->discount ?? '0');
            $this->courierCharge = (string) ($order->courier_charge ?? '0');
            $this->courierCost = (string) ($order->courier_cost ?? '0');
            $this->hasCourier = (float) $this->courierCharge > 0 || (float) $this->courierCost > 0;

            $this->items = $order->items->map(fn (PurchaseOrderItem $item) => $this->lineFor($item->product, [
                'unit_label' => $item->unit_label ?: $item->product->unit,
                'unit_factor' => $item->factor,
                'quantity' => self::qtyString($item->quantity),
                'unit_cost' => (string) $item->unit_cost,
                'received' => $item->received_quantity,
            ]))->toArray();
        } else {
            $this->warehouse_id = Warehouse::getDefault()?->id;
        }
    }

    /** A quantity as an input value: "2", "1.25" (no thousands separator). */
    private static function qtyString(float|int|string|null $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, '.', ''), '0'), '.') ?: '0';
    }

    /** One line of the items array, defaulting to one base unit at cost price. */
    private function lineFor(Product $product, array $overrides = []): array
    {
        $factor = (float) ($product->purchase_unit_factor ?: 1);

        return array_merge([
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'loose' => $product->isLoose(),
            // Only offered when a carton actually holds more than one piece.
            'purchase_unit' => $product->purchase_unit && $factor > 1 ? $product->purchase_unit : null,
            'purchase_factor' => $factor > 1 ? $factor : 1.0,
            'unit_label' => $product->unit,
            'unit_factor' => 1.0,
            'quantity' => '1',
            'unit_cost' => (string) $product->cost_price,
            'received' => 0,
        ], $overrides);
    }

    /** Fractions are allowed only for loose goods ordered in their base unit. */
    private static function allowsFraction(array $item): bool
    {
        return ! empty($item['loose']) && (float) ($item['unit_factor'] ?? 1) <= 1;
    }

    /**
     * Switch a line between base units ('base') and the purchase unit
     * ('purchase'). The unit cost is converted so the price per piece holds.
     */
    public function setLineUnit(int $index, string $unit): void
    {
        $item = $this->items[$index] ?? null;

        if (! $item || ($this->order?->exists && ! $this->order->isDraft())) {
            return;
        }

        $toPurchase = $unit === 'purchase' && ! empty($item['purchase_unit']);
        $newFactor = $toPurchase ? (float) $item['purchase_factor'] : 1.0;
        $oldFactor = max(1.0, (float) ($item['unit_factor'] ?? 1));

        if (abs($newFactor - $oldFactor) < 0.0005) {
            return;
        }

        $perPiece = (float) ($item['unit_cost'] ?: 0) / $oldFactor;
        $this->items[$index]['unit_factor'] = $newFactor;
        $this->items[$index]['unit_label'] = $toPurchase ? $item['purchase_unit'] : $item['unit'];
        $this->items[$index]['unit_cost'] = (string) round($perPiece * $newFactor, 2);

        if (! self::allowsFraction($this->items[$index])) {
            $this->items[$index]['quantity'] = (string) max(1, (int) round((float) $item['quantity']));
        }
    }

    protected function rules(): array
    {
        $rules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'payment_due_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'courierCharge' => 'nullable|numeric|min:0',
            'courierCost' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ];

        foreach ($this->items as $i => $item) {
            $rules["items.{$i}.quantity"] = self::allowsFraction($item)
                ? 'required|numeric|min:0.001'
                : 'required|numeric|integer|min:1';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'items.*.quantity.integer' => 'Whole numbers only for this product.',
        ];
    }

    public function searchProducts()
    {
        if (empty($this->productSearch)) {
            return collect();
        }

        return Product::where(fn ($s) => $s
            ->where('name', 'like', "%{$this->productSearch}%")
            ->orWhere('sku', 'like', "%{$this->productSearch}%")
            ->orWhere('barcode', 'like', "%{$this->productSearch}%"))
            ->limit(8)
            ->get();
    }

    public function selectHighlighted(int $index): void
    {
        $product = $this->searchProducts()->values()->get($index);
        if ($product) {
            $this->addProduct($product->id);
        }
    }

    public function addProduct(int $productId): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        // Check if already added
        foreach ($this->items as $item) {
            if ($item['product_id'] === $productId) {
                $this->productSearch = '';

                return;
            }
        }

        $this->items[] = $this->lineFor($product);

        $this->productSearch = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getSubtotalProperty(): float
    {
        return array_reduce($this->items, function ($sum, $item) {
            return $sum + ((float) ($item['quantity'] ?: 0) * (float) ($item['unit_cost'] ?: 0));
        }, 0);
    }

    /*
     * Mirroring the freight charge into the cost happens in the browser
     * (purchase-form.blade.php). Inputs are deferred, so the server gets a batch
     * of updates in no guaranteed order and a server-side mirror could overwrite
     * a cost typed by hand.
     */
    public function updatedHasCourier(): void
    {
        if (! $this->hasCourier) {
            $this->courierCharge = '0';
            $this->courierCost = '0';
        }
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

    public function getTotalProperty(): float
    {
        $subtotal = $this->subtotal;
        $tax = (float) ($this->tax ?: 0);
        $discount = (float) ($this->discount ?: 0);

        return max(0, $subtotal + $tax - $discount + $this->courierChargeValue);
    }

    private function buildItemsData(): array
    {
        return array_map(function ($item) {
            $qty = (float) $item['quantity'];
            $cost = (float) $item['unit_cost'];

            return [
                'product_id' => $item['product_id'],
                'quantity' => round($qty, 3),
                'unit_cost' => $cost,
                'unit_label' => $item['unit_label'] ?? $item['unit'] ?? null,
                'unit_factor' => max(1.0, (float) ($item['unit_factor'] ?? 1)),
                'subtotal' => round($qty * $cost, 2),
            ];
        }, $this->items);
    }

    public function saveDraft(): void
    {
        $this->validate();
        $this->persist('draft');
        session()->flash('success', 'Purchase order saved as draft!');
        $this->redirect(route('purchases.index'), navigate: true);
    }

    public function placeOrder(): void
    {
        $this->validate();
        $this->persist('ordered');
        session()->flash('success', 'Purchase order placed!');
        $this->redirect(route('purchases.index'), navigate: true);
    }

    private function persist(string $status): void
    {
        $itemsData = $this->buildItemsData();
        $subtotal = $this->subtotal;
        $tax = (float) ($this->tax ?: 0);
        $discount = (float) ($this->discount ?: 0);
        $total = $this->total;

        DB::transaction(function () use ($itemsData, $subtotal, $tax, $discount, $total, $status) {
            $data = [
                'supplier_id' => $this->supplier_id,
                'warehouse_id' => $this->warehouse_id,
                'order_date' => $this->order_date,
                'expected_date' => $this->expected_date ?: null,
                'payment_due_date' => $this->payment_due_date ?: null,
                'notes' => $this->notes ?: null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'courier_charge' => $this->courierChargeValue,
                'courier_cost' => $this->courierCostValue,
                'total' => $total,
                'status' => $status,
                'created_by' => $this->order?->created_by ?? auth()->id(),
            ];

            if ($this->order?->exists) {
                $this->order->update($data);
                $this->order->items()->delete();
            } else {
                $this->order = PurchaseOrder::create($data);
            }

            foreach ($itemsData as $item) {
                $this->order->items()->create($item);
            }
        });
    }

    /**
     * Only admins and managers may change the state of a purchase order.
     *
     * The route group already restricts these roles, but every state-changing
     * action re-checks: Livewire methods are callable directly from the browser,
     * so route middleware alone is not a sufficient guard.
     */
    private function authorizeStateChange(string $action): bool
    {
        if (! auth()->user()->hasRole(['admin', 'manager'])) {
            session()->flash('error', "You do not have permission to {$action} orders.");

            return false;
        }

        return true;
    }

    /**
     * Move a saved order to "ordered", meaning it has been placed with the
     * supplier and is now awaiting delivery.
     *
     * There is no approval gate: orders go straight from draft to ordered.
     * `pending` and `approved` remain valid states only so that orders created
     * before the gate was removed still move forward.
     */
    public function markAsOrdered(): void
    {
        if (! $this->authorizeStateChange('modify')) {
            return;
        }

        if (! $this->order?->canPlace()) {
            session()->flash('error', 'This order has already been placed.');

            return;
        }

        $this->order->update(['status' => 'ordered']);
        session()->flash('success', 'Purchase order placed!');
    }

    public function cancel(): void
    {
        if (! $this->authorizeStateChange('cancel')) {
            return;
        }

        if (in_array($this->order?->status, ['received', 'cancelled'], true)) {
            session()->flash('error', 'A received or already-cancelled order cannot be cancelled.');

            return;
        }

        $this->order->update(['status' => 'cancelled']);
        session()->flash('success', 'Purchase order cancelled.');
        $this->redirect(route('purchases.index'), navigate: true);
    }

    // ============ SUPPLIER PAYMENTS ============

    public function openPaymentModal(): void
    {
        if (! $this->authorizeStateChange('pay')) {
            return;
        }

        if (! $this->order?->canRecordPayment()) {
            session()->flash('error', 'Payments can only be recorded on placed orders with a balance due.');

            return;
        }

        $this->resetErrorBag();
        // No thousands separator — see PosTerminal::openPaymentModal().
        $this->payment_amount = number_format($this->order->due_amount, 2, '.', '');
        $this->payment_reference = '';
        $this->updatedPaymentMethod();
        $this->showPaymentModal = true;
    }

    public function updatedPaymentMethod(): void
    {
        $accounts = PaymentAccount::forMethod($this->payment_method)->get();
        $this->payment_account_id = $accounts->count() === 1 ? $accounts->first()->id : null;
    }

    public function recordPayment(): void
    {
        if (! $this->authorizeStateChange('pay')) {
            return;
        }

        // Re-checked here: this method is directly callable from the browser.
        if (! $this->order?->canRecordPayment()) {
            session()->flash('error', 'Payments can only be recorded on placed orders with a balance due.');

            return;
        }

        $this->payment_amount = str_replace(',', '', $this->payment_amount);

        $this->validate([
            'payment_amount' => 'required|numeric|min:0.01|max:'.$this->order->due_amount,
            'payment_method' => Payment::methodRule(),
            'payment_reference' => Payment::needsReference($this->payment_method)
                ? 'required|string|max:100'
                : 'nullable|string|max:100',
            'payment_account_id' => PaymentAccount::rule($this->payment_method),
        ], [
            'payment_account_id.required' => 'Select the account or card this was paid from.',
            'payment_amount.max' => 'Amount cannot be more than the balance due.',
            'payment_reference.required' => 'Enter the transaction ID for this payment.',
        ]);

        $amount = (float) $this->payment_amount;

        DB::transaction(function () use ($amount) {
            // Lock before reading the balance so two payments at once cannot
            // both pass the check — see InvoiceView::recordPayment().
            $order = PurchaseOrder::whereKey($this->order->id)->lockForUpdate()->firstOrFail();

            if ($amount > $order->due_amount) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Amount exceeds the outstanding balance of '
                        .number_format($order->due_amount, 2).'.',
                ]);
            }

            PurchasePayment::create([
                'purchase_order_id' => $order->id,
                'created_by' => auth()->id(),
                'payment_account_id' => PaymentAccount::requiredFor($this->payment_method) ? $this->payment_account_id : null,
                'amount' => $amount,
                'method' => $this->payment_method,
                'reference' => $this->payment_reference ?: null,
                'payment_date' => now(),
            ]);

            $order->increment('paid_amount', $amount);
        });

        $this->order->refresh()->load(self::RELATIONS);
        $this->showPaymentModal = false;
        session()->flash('success', 'Payment recorded successfully!');
    }

    public function openReceiveModal(): void
    {
        if (! $this->authorizeStateChange('receive')) {
            return;
        }

        if (! $this->canReceiveStock()) {
            session()->flash('error', 'Only approved or ordered purchase orders can receive stock.');

            return;
        }

        $this->resetErrorBag();
        $this->receiveQuantities = [];
        $this->receiveBatches = [];
        $this->receiveExpiry = [];
        foreach ($this->order->items as $item) {
            $this->receiveQuantities[$item->id] = self::qtyString($item->remaining_quantity);
            $this->receiveBatches[$item->id] = (string) ($item->batch_number ?? '');
            $this->receiveExpiry[$item->id] = $item->expiry_date?->format('Y-m-d') ?? '';
        }
        $this->showReceiveModal = true;
    }

    /**
     * Stock may be received against an approved order, or an ordered one that
     * is still only partially received.
     */
    private function canReceiveStock(): bool
    {
        return ! $this->order?->isReturn()
            && in_array($this->order?->status, ['pending', 'approved', 'ordered'], true);
    }

    public function receiveStock(): void
    {
        if (! $this->authorizeStateChange('receive')) {
            return;
        }

        // Re-checked here, not just in openReceiveModal(): this method is
        // directly callable and must not trust that the modal gated it.
        if (! $this->canReceiveStock()) {
            session()->flash('error', 'Only approved or ordered purchase orders can receive stock.');

            return;
        }

        $this->validateReceipt();

        DB::transaction(function () {
            $allReceived = true;

            foreach ($this->order->items as $item) {
                $qtyToReceive = round((float) ($this->receiveQuantities[$item->id] ?? 0), 3);
                if ($qtyToReceive <= 0) {
                    if (! $item->isFullyReceived()) {
                        $allReceived = false;
                    }

                    continue;
                }

                // received_quantity stays in the ordered unit (e.g. cartons).
                $newReceived = round(min($item->quantity, $item->received_quantity + $qtyToReceive), 3);
                $actualQty = round($newReceived - $item->received_quantity, 3);

                if ($actualQty <= 0) {
                    continue;
                }

                $batch = trim((string) ($this->receiveBatches[$item->id] ?? '')) ?: null;
                $expiry = ($this->receiveExpiry[$item->id] ?? '') ?: null;

                $item->update([
                    'received_quantity' => $newReceived,
                    'batch_number' => $batch ?? $item->batch_number,
                    'expiry_date' => $expiry ?? $item->expiry_date,
                ]);

                if (! $item->isFullyReceived()) {
                    $allReceived = false;
                }

                // Stock is kept in base units: 2 cartons of 24 add 48 pcs,
                // each costing a 24th of the carton price.
                $factor = $item->factor;

                // Stock arrives in the warehouse the order was raised against.
                app(InventoryService::class)->add(
                    productId: $item->product_id,
                    warehouseId: $this->order->warehouse_id,
                    quantity: round($actualQty * $factor, 3),
                    type: 'purchase',
                    extra: [
                        'reference' => $this->order,
                        'unit_cost' => (float) $item->unit_cost / $factor,
                        'batch_number' => $batch,
                        'expiry_date' => $expiry,
                    ],
                );
            }

            $this->order->update([
                'status' => $allReceived ? 'received' : 'ordered',
                'received_date' => $allReceived ? now() : $this->order->received_date,
            ]);
        });

        DashboardIndex::flushCache();

        $this->showReceiveModal = false;
        $this->order->refresh()->load(self::RELATIONS);
        session()->flash('success', 'Stock received and inventory updated!');
    }

    /**
     * Quantities must fit the product (whole units unless loose and ordered by
     * the base unit), and expiry is required for products that track it.
     */
    private function validateReceipt(): void
    {
        $rules = [];
        $messages = [];
        $attributes = [];

        foreach ($this->order->items as $item) {
            $id = $item->id;
            $name = $item->product->name;
            $qty = (float) ($this->receiveQuantities[$id] ?? 0);
            $fraction = $item->product->isLoose() && $item->factor <= 1;

            $rules["receiveQuantities.{$id}"] = 'nullable|numeric|min:0'.($fraction ? '' : '|integer');
            $rules["receiveBatches.{$id}"] = 'nullable|string|max:100';
            $rules["receiveExpiry.{$id}"] = ($qty > 0 && $item->product->track_expiry ? 'required' : 'nullable').'|date';

            $attributes["receiveQuantities.{$id}"] = "quantity for {$name}";
            $attributes["receiveExpiry.{$id}"] = "expiry date for {$name}";
            $messages["receiveExpiry.{$id}.required"] = "{$name} tracks expiry, so enter its expiry date.";
            $messages["receiveQuantities.{$id}.integer"] = "Whole numbers only for {$name}.";
        }

        $this->validate($rules, $messages, $attributes);
    }

    /** Received or partially received purchases can be sent back to the supplier. */
    public function getCanReturnProperty(): bool
    {
        if (! $this->order?->exists || $this->order->isReturn()) {
            return false;
        }

        return $this->order->status === 'received'
            || (! in_array($this->order->status, ['draft', 'cancelled'], true)
                && $this->order->items->contains(fn ($i) => $i->received_quantity > 0));
    }

    protected function handleScan(string $code): bool
    {
        if ($this->order?->exists && ! $this->order->isDraft()) {
            $this->scanError = 'This order has been placed and can no longer be edited.';

            return false;
        }

        $product = $this->findScannedProduct($code);

        if (! $product) {
            return false;
        }

        // Scanning a product already on the order adds one more.
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] === $product->id) {
                $this->items[$i]['quantity'] = self::qtyString((float) $item['quantity'] + 1);

                return true;
            }
        }

        $this->addProduct($product->id);

        return true;
    }

    public function render()
    {
        $suppliers = Supplier::active()->get();
        $warehouses = Warehouse::active()->get();
        $searchResults = $this->searchProducts();
        $paymentAccounts = $this->showPaymentModal
            ? PaymentAccount::forMethod($this->payment_method)->get()
            : collect();

        return view('livewire.purchases.purchase-form', compact('suppliers', 'warehouses', 'searchResults', 'paymentAccounts'))
            ->layout('layouts.app', ['title' => $this->order?->exists ? "Order {$this->order->order_number}" : 'New Purchase Order']);
    }
}
