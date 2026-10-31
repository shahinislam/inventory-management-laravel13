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
    // A supplier's cash refund goes into a drawer, so it needs an open shift.
    openShift();
});

/** A purchase of $qty units at 100 each (or $item's unit_cost), fully received into stock. */
function receivedOrder(Product $product, float $qty = 10, array $item = []): PurchaseOrder
{
    $value = (float) ($item['unit_cost'] ?? 100) * $qty;

    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => 'ordered',
        'subtotal' => $value,
        'total' => $value,
        'order_date' => today(),
    ]);

    $order->items()->create($item + [
        'product_id' => $product->id,
        'quantity' => $qty,
        'unit_cost' => 100,
        'subtotal' => $value,
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

it('records a refund from the supplier on the return and nets it off the order', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 5);               // 500
    $order->update(['paid_amount' => 500]);            // paid in full

    // 2 back (200) on a paid-up order: all of it may come back; 150 does now.
    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '2')
        ->set('refund_amount', '150')
        ->set('refund_method', 'cash')
        ->call('save')
        ->assertHasNoErrors();

    $return = PurchaseOrder::query()->returns()->sole();
    $payment = PurchasePayment::sole();
    $order->refresh();

    expect($payment->purchase_order_id)->toBe($return->id)
        ->and((float) $payment->amount)->toBe(150.0)
        ->and((float) $return->paid_amount)->toBe(150.0)
        ->and((float) $order->paid_amount)->toBe(350.0)     // 500 paid less 150 handed back
        ->and($order->due_amount)->toBe(0.0)
        ->and($order->credit_amount)->toBe(50.0);           // the 50 not refunded stays as credit
});

// ============ RETURNS AGAINST WHAT IS OWED ============

it('takes a return off what is owed before allowing a refund', function () {
    // PO 100 (10 × 10), 50 paid, 6 sent back (60). 50 of the return clears
    // the debt; only the 10 overpaid can come back. Refunding 60 used to
    // leave "due 0" while the shop really still owed 50.
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 10, ['unit_cost' => 10]);
    $order->update(['paid_amount' => 50]);

    $form = Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '6');

    expect($form->instance()->dueReduced)->toBe(50.0)
        ->and($form->instance()->refundable)->toBe(10.0);

    $form->set('refund_amount', '60')
        ->set('refund_method', 'cash')
        ->call('save')
        ->assertHasErrors('refund_amount');

    expect(PurchaseOrder::query()->returns()->count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(10.0);

    $form->set('refund_amount', '10')->call('save')->assertHasNoErrors();

    $order->refresh();
    expect((float) $order->returned_amount)->toBe(60.0)
        ->and((float) $order->paid_amount)->toBe(40.0)      // 50 paid, 10 back
        ->and($order->due_amount)->toBe(0.0)                // kept 40 worth, paid 40 net
        ->and($order->credit_amount)->toBe(0.0)
        ->and((float) PurchasePayment::sole()->amount)->toBe(10.0);
});

it('refuses any refund while the order is still unpaid', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 5);               // 500, nothing paid

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '2')
        ->set('refund_amount', '1')
        ->set('refund_method', 'cash')
        ->call('save')
        ->assertHasErrors('refund_amount');

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '2')
        ->call('save')
        ->assertHasNoErrors();

    expect($order->fresh()->due_amount)->toBe(300.0)
        ->and(PurchasePayment::count())->toBe(0);
});

it('keeps an overpayment that is not refunded as supplier credit', function () {
    // PO 100, 50 paid, 60 sent back, nothing refunded: the supplier owes 10.
    $product = Product::factory()->create(['quantity' => 0]);
    $order = receivedOrder($product, 10, ['unit_cost' => 10]);
    $order->update(['paid_amount' => 50]);

    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '6')
        ->call('save')
        ->assertHasNoErrors();

    $order->refresh();
    expect($order->due_amount)->toBe(0.0)
        ->and($order->credit_amount)->toBe(10.0)
        ->and($order->supplier->balance)->toBe(-10.0);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->assertSee('Supplier credit')
        ->assertSee(money(10));

    // A later refund of that credit is allowed on the next return.
    Livewire::test(PurchaseReturnForm::class, ['order' => $order])
        ->set('lines.0.qty', '1')
        ->set('refund_amount', '20')
        ->set('refund_method', 'cash')
        ->call('save')
        ->assertHasNoErrors();

    expect($order->fresh()->credit_amount)->toBe(0.0)
        ->and((float) $order->fresh()->paid_amount)->toBe(30.0);
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
