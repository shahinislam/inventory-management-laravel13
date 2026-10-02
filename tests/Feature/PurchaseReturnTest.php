<?php

/**
 * Returning received goods to the supplier: stock leaves, the parent order's
 * balance drops, and nothing more than was received can go back.
 */

use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Purchases\PurchaseReturnForm;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

/** A purchase of $qty units at 100 each, fully received into stock. */
function receivedOrder(Product $product, float $qty = 10, array $item = []): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => 'ordered',
        'subtotal' => 100 * $qty,
        'total' => 100 * $qty,
        'order_date' => today(),
    ]);

    $order->items()->create($item + [
        'product_id' => $product->id,
        'quantity' => $qty,
        'unit_cost' => 100,
        'subtotal' => 100 * $qty,
    ]);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->call('receiveStock');

    return $order->fresh();
}

it('returns part of an order: stock out, returned amount up, due down', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 10);

    expect($order->due_amount)->toBe(1000.0);

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '3')
        ->set('reason', 'Damaged')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $return = PurchaseOrder::query()->returns()->sole();
    $movement = StockMovement::where('type', 'purchase_return')->sole();

    expect($return->parent_order_id)->toBe($order->id)
        ->and((float) $return->total)->toBe(300.0)
        ->and($return->items()->sole()->parent_item_id)->toBe($order->items()->sole()->id)
        ->and($movement->quantity)->toBe(3.0)
        ->and($movement->notes)->toBe('Damaged')
        ->and($product->fresh()->quantity)->toBe(7.0)
        ->and(app(InventoryService::class)->stockIn($product->id, $this->warehouse->id))->toBe(7.0)
        ->and((float) $order->fresh()->returned_amount)->toBe(300.0)
        ->and($order->fresh()->due_amount)->toBe(700.0);
});

it('returns cartons as base units', function () {
    $product = Product::factory()->create(['quantity' => 0, 'purchase_unit' => 'carton', 'purchase_unit_factor' => 24]);
    $order = receivedOrder($product, 2, ['unit_cost' => 240, 'unit_label' => 'carton', 'unit_factor' => 24, 'subtotal' => 480]);

    expect($product->fresh()->quantity)->toBe(48.0);

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '1')
        ->call('save')
        ->assertHasNoErrors();

    $movement = StockMovement::where('type', 'purchase_return')->sole();

    expect($product->fresh()->quantity)->toBe(24.0)
        ->and((float) $movement->unit_cost)->toBe(10.0)
        ->and((float) PurchaseOrder::query()->returns()->sole()->total)->toBe(240.0);
});

it('cannot return more than was received', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 5);

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '6')
        ->call('save')
        ->assertHasErrors('lines.0.qty');

    expect(PurchaseOrder::query()->returns()->count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(5.0);
});

it('counts earlier returns against what is left to return', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 5);

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '4')
        ->call('save')
        ->assertHasNoErrors();

    $component = Livewire::test(PurchaseReturnForm::class, ['order' => $order->fresh()]);
    expect($component->get('lines.0.returnable'))->toBe(1.0);

    $component->set('lines.0.qty', '2')
        ->call('save')
        ->assertHasErrors('lines.0.qty');

    expect(PurchaseOrder::query()->returns()->count())->toBe(1);
});

it('refuses a return when the stock has already been sold', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 5);
    app(InventoryService::class)->remove($product->id, $this->warehouse->id, 4, 'sale');

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '3')
        ->call('save')
        ->assertHasErrors('lines.0.qty');

    expect(PurchaseOrder::query()->returns()->count())->toBe(0);
});

it('records a refund from the supplier on the return', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 5);

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '2')
        ->set('refund_amount', '150')
        ->set('refund_method', 'cash')
        ->call('save')
        ->assertHasNoErrors();

    $return = PurchaseOrder::query()->returns()->sole();
    $payment = PurchasePayment::sole();

    expect($payment->purchase_order_id)->toBe($return->id)
        ->and((float) $payment->amount)->toBe(150.0)
        ->and((float) $return->paid_amount)->toBe(150.0);
});

it('shows a recorded return and redirects the purchase form to it', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 5);

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '1')
        ->call('save');

    $return = PurchaseOrder::query()->returns()->sole();

    Livewire::test(PurchaseReturnForm::class, ['return' => $return])
        ->assertSee($return->order_number)
        ->assertSee($order->order_number);

    Livewire::test(PurchaseForm::class, ['order' => $return])
        ->assertRedirect(route('purchases.returns.show', $return));

    Livewire::test(PurchaseForm::class, ['order' => $order->fresh()])
        ->assertSee('Return to supplier')
        ->assertSee($return->order_number);
});
