<?php

namespace App\Livewire\Purchases;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PurchaseForm extends Component
{
    public ?PurchaseOrder $order = null;

    // Header fields
    public ?int $supplier_id = null;

    public ?int $warehouse_id = null;

    public string $order_date = '';

    public string $expected_date = '';

    public string $notes = '';

    public string $tax = '0';

    public string $discount = '0';

    // Line items
    public array $items = [];

    // Product search
    public string $productSearch = '';

    // Receive modal
    public bool $showReceiveModal = false;

    public array $receiveQuantities = [];

    public function mount(?PurchaseOrder $order = null): void
    {
        $this->order_date = now()->format('Y-m-d');

        if ($order?->exists) {
            $this->order = $order->load('items.product');
            $this->supplier_id = $order->supplier_id;
            $this->warehouse_id = $order->warehouse_id;
            $this->order_date = $order->order_date->format('Y-m-d');
            $this->expected_date = $order->expected_date?->format('Y-m-d') ?? '';
            $this->notes = $order->notes ?? '';
            // Cast explicitly: these are string-typed properties and the columns
            // are nullable in practice, so a null would raise a TypeError.
            $this->tax = (string) ($order->tax ?? '0');
            $this->discount = (string) ($order->discount ?? '0');

            $this->items = $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'sku' => $item->product->sku,
                'unit' => $item->product->unit,
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
                'received' => $item->received_quantity,
            ])->toArray();
        } else {
            $this->warehouse_id = Warehouse::getDefault()?->id;
        }
    }

    protected function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
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

        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'quantity' => '1',
            'unit_cost' => (string) $product->cost_price,
            'received' => 0,
        ];

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

    public function getTotalProperty(): float
    {
        $subtotal = $this->subtotal;
        $tax = (float) ($this->tax ?: 0);
        $discount = (float) ($this->discount ?: 0);

        return max(0, $subtotal + $tax - $discount);
    }

    private function buildItemsData(): array
    {
        return array_map(function ($item) {
            $qty = (float) $item['quantity'];
            $cost = (float) $item['unit_cost'];

            return [
                'product_id' => $item['product_id'],
                'quantity' => $qty,
                'unit_cost' => $cost,
                'subtotal' => $qty * $cost,
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

    public function submitForApproval(): void
    {
        $this->validate();
        $this->persist('pending');
        session()->flash('success', 'Purchase order submitted for approval!');
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
                'notes' => $this->notes ?: null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
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

    public function approve(): void
    {
        if (! $this->authorizeStateChange('approve')) {
            return;
        }

        if (! $this->order?->canApprove()) {
            session()->flash('error', 'Only draft or pending orders can be approved.');

            return;
        }

        $this->order->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        session()->flash('success', 'Purchase order approved!');
    }

    public function markAsOrdered(): void
    {
        if (! $this->authorizeStateChange('modify')) {
            return;
        }

        if ($this->order?->status !== 'approved') {
            session()->flash('error', 'Only approved orders can be marked as ordered.');

            return;
        }

        $this->order->update(['status' => 'ordered']);
        session()->flash('success', 'Purchase order marked as ordered!');
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

    public function openReceiveModal(): void
    {
        if (! $this->authorizeStateChange('receive')) {
            return;
        }

        if (! $this->canReceiveStock()) {
            session()->flash('error', 'Only approved or ordered purchase orders can receive stock.');

            return;
        }

        $this->receiveQuantities = [];
        foreach ($this->order->items as $item) {
            $remaining = $item->quantity - $item->received_quantity;
            $this->receiveQuantities[$item->id] = $remaining > 0 ? (string) $remaining : '0';
        }
        $this->showReceiveModal = true;
    }

    /**
     * Stock may be received against an approved order, or an ordered one that
     * is still only partially received.
     */
    private function canReceiveStock(): bool
    {
        return in_array($this->order?->status, ['approved', 'ordered'], true);
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

        DB::transaction(function () {
            $allReceived = true;

            foreach ($this->order->items as $item) {
                $qtyToReceive = (int) ($this->receiveQuantities[$item->id] ?? 0);
                if ($qtyToReceive <= 0) {
                    if ($item->received_quantity < $item->quantity) {
                        $allReceived = false;
                    }

                    continue;
                }

                $newReceived = min($item->quantity, $item->received_quantity + $qtyToReceive);
                $actualQty = $newReceived - $item->received_quantity;

                $item->update(['received_quantity' => $newReceived]);

                if ($newReceived < $item->quantity) {
                    $allReceived = false;
                }

                // Stock arrives in the warehouse the order was raised against.
                app(InventoryService::class)->add(
                    productId: $item->product_id,
                    warehouseId: $this->order->warehouse_id,
                    quantity: $actualQty,
                    type: 'purchase',
                    extra: [
                        'reference' => $this->order,
                        'unit_cost' => $item->unit_cost,
                        'batch_number' => $item->batch_number,
                        'expiry_date' => $item->expiry_date,
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
        $this->order->refresh()->load('items.product');
        session()->flash('success', 'Stock received and inventory updated!');
    }

    public function render()
    {
        $suppliers = Supplier::active()->get();
        $warehouses = Warehouse::active()->get();
        $searchResults = $this->searchProducts();

        return view('livewire.purchases.purchase-form', compact('suppliers', 'warehouses', 'searchResults'))
            ->layout('layouts.app', ['title' => $this->order?->exists ? "Order {$this->order->order_number}" : 'New Purchase Order']);
    }
}
