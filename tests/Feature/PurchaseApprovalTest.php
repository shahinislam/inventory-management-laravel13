<?php

/**
 * Purchase orders have no approval gate: draft goes straight to ordered.
 *
 * The `pending` and `approved` statuses survive only so that orders created
 * before the gate was removed can still be moved forward.
 */

use App\Livewire\Purchases\PurchaseForm;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
});

function makeOrder(string $status): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::first()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => $status,
        'subtotal' => 400,
        'tax' => 0,
        'discount' => 0,
        'total' => 400,
        'paid_amount' => 0,
        'order_date' => today(),
    ]);

    PurchaseOrderItem::create([
        'purchase_order_id' => $order->id,
        'product_id' => Product::first()->id,
        'quantity' => 4,
        'unit_cost' => 100,
        'subtotal' => 400,
    ]);

    return $order;
}

it('places a draft order directly, with no approval step', function () {
    $order = makeOrder('draft');

    Livewire::actingAs($this->admin)
        ->test(PurchaseForm::class, ['order' => $order])
        ->call('markAsOrdered');

    expect($order->fresh()->status)->toBe('ordered');
});

it('offers Mark as Ordered on a draft order', function () {
    $order = makeOrder('draft');

    Livewire::actingAs($this->admin)
        ->test(PurchaseForm::class, ['order' => $order])
        ->assertSee('Mark as Ordered');
});

it('no longer offers an approve action', function () {
    $order = makeOrder('draft');

    Livewire::actingAs($this->admin)
        ->test(PurchaseForm::class, ['order' => $order])
        ->assertDontSee('Approve');
});

it('saves a new order straight to ordered when placed from the form', function () {
    Livewire::actingAs($this->admin)
        ->test(PurchaseForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('warehouse_id', $this->warehouse->id)
        ->set('items', [[
            'product_id' => $this->product->id,
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'unit' => 'pcs',
            'quantity' => '4',
            'unit_cost' => '100',
            'received' => 0,
        ]])
        ->call('placeOrder');

    expect(PurchaseOrder::latest('id')->first()->status)->toBe('ordered');
});

it('lets a legacy pending order still be placed', function () {
    $order = makeOrder('pending');

    Livewire::actingAs($this->admin)
        ->test(PurchaseForm::class, ['order' => $order])
        ->call('markAsOrdered');

    expect($order->fresh()->status)->toBe('ordered');
});

it('lets a legacy approved order still be placed', function () {
    $order = makeOrder('approved');

    Livewire::actingAs($this->admin)
        ->test(PurchaseForm::class, ['order' => $order])
        ->call('markAsOrdered');

    expect($order->fresh()->status)->toBe('ordered');
});

it('can receive stock against an ordered order', function () {
    $order = makeOrder('ordered');

    expect($order->canReceive())->toBeTrue();
});

it('cannot receive stock against a draft order', function () {
    // Stock must not move before the order has actually been placed.
    $order = makeOrder('draft');

    expect($order->canReceive())->toBeFalse();
});

it('refuses to place an already received order', function () {
    $order = makeOrder('received');

    Livewire::actingAs($this->admin)
        ->test(PurchaseForm::class, ['order' => $order])
        ->call('markAsOrdered');

    expect($order->fresh()->status)->toBe('received');
});

it('refuses to place an order from a staff user', function () {
    $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
    $order = makeOrder('draft');

    Livewire::actingAs($staff)
        ->test(PurchaseForm::class, ['order' => $order])
        ->call('markAsOrdered');

    expect($order->fresh()->status)->toBe('draft');
});
