<?php

use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Stock\StockAdjustment;
use App\Livewire\Stock\StockTransfer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->from = Warehouse::factory()->default()->create();
    $this->to = Warehouse::factory()->create();
    $this->actingAs($this->user);
});

// ---------------------------------------------------------------- adjustments

it('increases stock on an add adjustment', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    Livewire::test(StockAdjustment::class)
        ->call('selectProduct', $product->id)
        ->set('adjustment_type', 'add')
        ->set('quantity', '5')
        ->set('reason', 'restock')
        ->call('save');

    expect($product->fresh()->quantity)->toBe(15);

    $movement = StockMovement::sole();
    expect($movement->type)->toBe('adjustment')
        ->and($movement->before_quantity)->toBe(10)
        ->and($movement->after_quantity)->toBe(15);
});

it('decreases stock on a remove adjustment', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    Livewire::test(StockAdjustment::class)
        ->call('selectProduct', $product->id)
        ->set('adjustment_type', 'remove')
        ->set('quantity', '4')
        ->set('reason', 'damaged')
        ->call('save');

    expect($product->fresh()->quantity)->toBe(6);
});

it('sets an absolute quantity on a set adjustment', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    Livewire::test(StockAdjustment::class)
        ->call('selectProduct', $product->id)
        ->set('adjustment_type', 'set')
        ->set('quantity', '42')
        ->set('reason', 'stock count')
        ->call('save');

    expect($product->fresh()->quantity)->toBe(42);
});

it('never drives stock below zero on a remove adjustment', function () {
    $product = Product::factory()->create(['quantity' => 3]);

    Livewire::test(StockAdjustment::class)
        ->call('selectProduct', $product->id)
        ->set('adjustment_type', 'remove')
        ->set('quantity', '10')
        ->set('reason', 'damaged')
        ->call('save');

    expect($product->fresh()->quantity)->toBe(0);
});

it('rejects an adjustment that changes nothing', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    Livewire::test(StockAdjustment::class)
        ->call('selectProduct', $product->id)
        ->set('adjustment_type', 'set')
        ->set('quantity', '10')
        ->set('reason', 'stock count')
        ->call('save')
        ->assertHasErrors('quantity');

    expect(StockMovement::count())->toBe(0);
});

// ------------------------------------------------------------------ transfers

it('moves stock from one warehouse to the other without changing the total', function () {
    $product = Product::factory()->create(['quantity' => 20]);
    $inventory = app(InventoryService::class);

    // All 20 start in the default (source) warehouse.
    expect($inventory->stockIn($product->id, $this->from->id))->toBe(20)
        ->and($inventory->stockIn($product->id, $this->to->id))->toBe(0);

    Livewire::test(StockTransfer::class)
        ->call('selectProduct', $product->id)
        ->set('from_warehouse_id', $this->from->id)
        ->set('to_warehouse_id', $this->to->id)
        ->set('quantity', '5')
        ->call('save');

    expect($inventory->stockIn($product->id, $this->from->id))->toBe(15)
        ->and($inventory->stockIn($product->id, $this->to->id))->toBe(5)
        // The cached total is unchanged — the stock only moved.
        ->and($product->fresh()->quantity)->toBe(20);
});

it('writes a matching out and in movement pair for a transfer', function () {
    $product = Product::factory()->create(['quantity' => 20]);

    Livewire::test(StockTransfer::class)
        ->call('selectProduct', $product->id)
        ->set('from_warehouse_id', $this->from->id)
        ->set('to_warehouse_id', $this->to->id)
        ->set('quantity', '5')
        ->call('save');

    $out = StockMovement::where('type', 'transfer_out')->sole();
    $in = StockMovement::where('type', 'transfer_in')->sole();

    // before/after now describe the level in each warehouse, not the global total.
    expect($out->warehouse_id)->toBe($this->from->id)
        ->and($out->before_quantity)->toBe(20)
        ->and($out->after_quantity)->toBe(15)
        ->and($in->warehouse_id)->toBe($this->to->id)
        ->and($in->before_quantity)->toBe(0)
        ->and($in->after_quantity)->toBe(5);
});

it('refuses to transfer more than is on hand', function () {
    $product = Product::factory()->create(['quantity' => 3]);

    Livewire::test(StockTransfer::class)
        ->call('selectProduct', $product->id)
        ->set('from_warehouse_id', $this->from->id)
        ->set('to_warehouse_id', $this->to->id)
        ->set('quantity', '10')
        ->call('save')
        ->assertHasErrors('quantity');

    expect(StockMovement::count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(3);
});

it('refuses a transfer to the same warehouse', function () {
    $product = Product::factory()->create(['quantity' => 20]);

    Livewire::test(StockTransfer::class)
        ->call('selectProduct', $product->id)
        ->set('from_warehouse_id', $this->from->id)
        ->set('to_warehouse_id', $this->from->id)
        ->set('quantity', '5')
        ->call('save')
        ->assertHasErrors('from_warehouse_id');

    expect(StockMovement::count())->toBe(0);
});

// --------------------------------------------------------- purchase receiving

function approvedOrder(Product $product, User $user, Warehouse $warehouse, int $qty = 10): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
        'status' => 'approved',
        'subtotal' => 1000,
        'total' => 1000,
        'order_date' => now(),
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => $qty,
        'unit_cost' => 100,
        'subtotal' => 100 * $qty,
    ]);

    return $order;
}

it('adds received quantity to stock', function () {
    $product = Product::factory()->create(['quantity' => 5]);
    $order = approvedOrder($product, $this->user, $this->from, 10);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->call('receiveStock');

    expect($product->fresh()->quantity)->toBe(15);

    $movement = StockMovement::where('type', 'purchase')->sole();
    expect($movement->quantity)->toBe(10)
        ->and($movement->before_quantity)->toBe(5)
        ->and($movement->after_quantity)->toBe(15);
});

it('marks the order received when everything arrives', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = approvedOrder($product, $this->user, $this->from, 10);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->call('receiveStock');

    expect($order->fresh()->status)->toBe('received');
});

it('keeps the order open on a partial receipt', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = approvedOrder($product, $this->user, $this->from, 10);
    $itemId = $order->items()->sole()->id;

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->set("receiveQuantities.{$itemId}", '4')
        ->call('receiveStock');

    expect($product->fresh()->quantity)->toBe(4)
        ->and($order->fresh()->status)->toBe('ordered');
});

it('refuses to receive stock against a draft order', function () {
    $product = Product::factory()->create(['quantity' => 5]);
    $order = approvedOrder($product, $this->user, $this->from, 10);
    $order->update(['status' => 'draft']);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('receiveStock');

    expect($product->fresh()->quantity)->toBe(5)
        ->and(StockMovement::count())->toBe(0);
});
