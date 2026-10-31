<?php

/**
 * Guards on a placed purchase order: it cannot be cancelled once stock has
 * arrived, and it cannot be rewritten from the form (which would rebuild the
 * lines and let the same goods be received twice).
 */

use App\Livewire\Purchases\PurchaseForm;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

/** 10 units at 100: 1000 owed once placed. */
function guardTestOrder(Product $product, string $status = 'ordered'): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => $status,
        'subtotal' => 1000,
        'total' => 1000,
        'order_date' => today(),
    ]);

    $order->items()->create(['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 100, 'subtotal' => 1000]);

    return $order;
}

it('will not cancel an order once part of it has been received', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = guardTestOrder($product);
    $itemId = $order->items()->sole()->id;

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->set("receiveQuantities.{$itemId}", '4')
        ->call('receiveStock');

    Livewire::test(PurchaseForm::class, ['order' => $order->fresh()])
        ->assertDontSee('Cancel Order')
        ->call('cancel')
        ->assertSee('cannot be cancelled');

    // The 4 in stock are still owed for: cancelling used to drop the whole 1000.
    expect($order->fresh()->status)->toBe('ordered')
        ->and($order->fresh()->due_amount)->toBe(1000.0)
        ->and($product->fresh()->quantity)->toBe(4.0);
});

it('still cancels an order nothing has arrived against', function () {
    $order = guardTestOrder(Product::factory()->create(['quantity' => 0]));

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->assertSee('Cancel Order')
        ->call('cancel');

    expect($order->fresh()->status)->toBe('cancelled');
});

it('refuses to save a placed order as a draft', function () {
    $order = guardTestOrder(Product::factory()->create(['quantity' => 0]));
    $item = $order->items()->sole();

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->set('items.0.quantity', '20')
        ->call('saveDraft')
        ->assertNoRedirect();

    $line = $order->items()->sole();
    expect($order->fresh()->status)->toBe('ordered')
        ->and($line->id)->toBe($item->id)
        ->and($line->quantity)->toBe(10.0)
        ->and((float) $order->fresh()->total)->toBe(1000.0);
});

it('cannot receive the same goods twice by placing a received order again', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $order = guardTestOrder($product);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->call('receiveStock');

    expect($order->fresh()->status)->toBe('received')
        ->and($product->fresh()->quantity)->toBe(10.0);

    // Placing it again used to rebuild the lines as unreceived.
    $component = Livewire::test(PurchaseForm::class, ['order' => $order->fresh()])
        ->call('placeOrder')
        ->assertNoRedirect();

    expect($order->fresh()->status)->toBe('received')
        ->and($order->items()->sole()->received_quantity)->toBe(10.0);

    $component->call('openReceiveModal')->call('receiveStock');

    expect($product->fresh()->quantity)->toBe(10.0)
        ->and(StockMovement::where('type', 'purchase')->count())->toBe(1);
});

it('still saves and places new and draft orders', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $draft = guardTestOrder($product, 'draft');

    Livewire::test(PurchaseForm::class, ['order' => $draft])
        ->set('items.0.quantity', '20')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertRedirect(route('purchases.index'));

    expect($draft->fresh()->status)->toBe('ordered')
        ->and($draft->items()->sole()->quantity)->toBe(20.0)
        ->and((float) $draft->fresh()->total)->toBe(2000.0);
});
