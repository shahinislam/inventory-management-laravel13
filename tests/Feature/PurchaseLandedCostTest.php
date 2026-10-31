<?php

/**
 * Stock is received at its landed cost: everything paid to the supplier for
 * the goods (order discount, freight billed on the order, order tax) spread
 * over the lines by line value. Returns to the supplier go back at the same.
 */

use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Purchases\PurchaseReturnForm;
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
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->supplier = Supplier::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Lines 10 × 10 + 5 × 20 = 200, less 20 discount, plus 10 tax and 30 freight
 * billed by the supplier: 220, so every line lands at 1.1× its price. The 25
 * paid to a courier separately is not on the supplier's bill.
 */
function landedTestOrder(Product $a, Product $b): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::first()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => 'ordered',
        'subtotal' => 200,
        'discount' => 20,
        'tax' => 10,
        'courier_charge' => 30,
        'courier_cost' => 25,
        'total' => 220,
        'order_date' => today(),
    ]);

    $order->items()->create(['product_id' => $a->id, 'quantity' => 10, 'unit_cost' => 10, 'subtotal' => 100]);
    $order->items()->create(['product_id' => $b->id, 'quantity' => 5, 'unit_cost' => 20, 'subtotal' => 100]);

    return $order;
}

it('receives stock at landed cost, the same on every partial receipt', function () {
    $a = Product::factory()->create(['quantity' => 0, 'cost_price' => 10]);
    $b = Product::factory()->create(['quantity' => 0, 'cost_price' => 20]);

    Livewire::test(PurchaseForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->call('addProduct', $a->id)
        ->call('addProduct', $b->id)
        ->set('items.0.quantity', '10')
        ->set('items.1.quantity', '5')
        ->set('discount', '20')
        ->set('tax', '10')
        ->set('hasCourier', true)
        ->set('courierCharge', '30')
        ->set('courierCost', '25')
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = PurchaseOrder::latest('id')->first();
    [$lineA, $lineB] = $order->items()->orderBy('id')->get()->all();

    expect((float) $order->total)->toBe(220.0);

    // 4 of A first, nothing of B yet.
    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->set("receiveQuantities.{$lineA->id}", '4')
        ->set("receiveQuantities.{$lineB->id}", '0')
        ->call('receiveStock')
        ->assertHasNoErrors();

    expect((float) $a->fresh()->cost_price)->toBe(11.0)
        ->and($order->fresh()->status)->toBe('ordered');

    // Then the rest.
    Livewire::test(PurchaseForm::class, ['order' => $order->fresh()])
        ->call('openReceiveModal')
        ->call('receiveStock')
        ->assertHasNoErrors();

    $movements = StockMovement::where('type', 'purchase')->orderBy('id')->get();
    $value = $movements->sum(fn ($m) => $m->quantity * (float) $m->unit_cost);

    expect($movements->where('product_id', $a->id)->map(fn ($m) => (float) $m->unit_cost)->values()->all())->toBe([11.0, 11.0])
        ->and((float) $movements->firstWhere('product_id', $b->id)->unit_cost)->toBe(22.0)
        ->and((float) $a->fresh()->cost_price)->toBe(11.0)
        ->and((float) $b->fresh()->cost_price)->toBe(22.0)
        ->and(round($value, 2))->toBe(220.0)                 // stock is worth exactly the supplier bill
        ->and($order->fresh()->status)->toBe('received');
});

it('receives a 10.00 carton of 24 at 0.4167 a piece, so the carton sells at 10.00', function () {
    $product = Product::factory()->create([
        'quantity' => 0, 'cost_price' => 0, 'purchase_unit' => 'carton', 'purchase_unit_factor' => 24,
    ]);

    $order = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
        'status' => 'ordered',
        'subtotal' => 10,
        'total' => 10,
        'order_date' => today(),
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 10,
        'unit_label' => 'carton', 'unit_factor' => 24, 'subtotal' => 10,
    ]);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->call('receiveStock')
        ->assertHasNoErrors();

    $taken = app(InventoryService::class)->remove($product->id, $this->warehouse->id, 24, 'sale');

    expect((float) StockMovement::where('type', 'purchase')->sole()->unit_cost)->toBe(0.4167)
        ->and((float) $product->fresh()->cost_price)->toBe(0.4167)
        ->and(round(24 * $taken['unit_cost'], 2))->toBe(10.0);      // 10.08 at 0.42
});

it('keeps 4-decimal unit costs on the order form', function () {
    $product = Product::factory()->create([
        'quantity' => 0, 'cost_price' => 0.4167, 'purchase_unit' => 'carton', 'purchase_unit_factor' => 24,
    ]);

    $component = Livewire::test(PurchaseForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->call('addProduct', $product->id);

    expect($component->get('items.0.unit_cost'))->toBe('0.4167');

    $component->call('setLineUnit', 0, 'purchase')->call('setLineUnit', 0, 'base');
    expect($component->get('items.0.unit_cost'))->toBe('0.4167');

    $component->set('items.0.quantity', '48')->call('saveDraft')->assertHasNoErrors();

    $order = PurchaseOrder::latest('id')->first();
    expect((float) $order->items()->sole()->unit_cost)->toBe(0.4167)
        ->and((float) $order->subtotal)->toBe(20.0);                 // 48 × 0.4167 = 20.0016

    // A whole-cent cost reads back as "10.00", not "10.0000".
    $plain = Product::factory()->create(['cost_price' => 10]);
    expect(Livewire::test(PurchaseForm::class)->call('addProduct', $plain->id)->get('items.0.unit_cost'))->toBe('10.00');
});

it('sends goods back to the supplier at the landed cost they came in at', function () {
    $a = Product::factory()->create(['quantity' => 0, 'cost_price' => 10]);
    $b = Product::factory()->create(['quantity' => 0, 'cost_price' => 20]);
    $order = landedTestOrder($a, $b);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->call('receiveStock');

    $form = Livewire::test(PurchaseReturnForm::class, ['order' => $order->fresh()]);
    expect($form->get('lines.0.unit_cost'))->toBe(11.0);

    $form->set('lines.0.qty', '2')->call('save')->assertHasNoErrors();

    $return = PurchaseOrder::query()->returns()->sole();
    $order->refresh();

    expect((float) $return->total)->toBe(22.0)
        ->and((float) $return->items()->sole()->unit_cost)->toBe(11.0)
        ->and((float) StockMovement::where('type', 'purchase_return')->sole()->unit_cost)->toBe(11.0)
        ->and((float) $order->returned_amount)->toBe(22.0)
        ->and($order->due_amount)->toBe(198.0)
        ->and((float) $a->fresh()->cost_price)->toBe(11.0);           // what stays is still worth 11 each

    // Everything back clears the bill exactly.
    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->call('returnAll')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $order->fresh()->returned_amount)->toBe(220.0)
        ->and($order->fresh()->due_amount)->toBe(0.0)
        ->and($order->fresh()->credit_amount)->toBe(0.0);
});
