<?php

/**
 * Ordering by the carton, fractional quantities, batch/expiry on receipt and
 * supplier payments by mobile banking.
 */

use App\Livewire\Purchases\PurchaseForm;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->supplier = Supplier::factory()->create();
    $this->actingAs($this->user);
});

function unitOrder(Product $product, array $item, string $status = 'ordered'): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::first()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => $status,
        'subtotal' => 480,
        'total' => 480,
        'order_date' => today(),
    ]);

    $order->items()->create($item + ['product_id' => $product->id, 'subtotal' => 480]);

    return $order;
}

it('orders by the carton and stores the unit on the line', function () {
    $product = Product::factory()->create([
        'quantity' => 0, 'cost_price' => 10, 'purchase_unit' => 'carton', 'purchase_unit_factor' => 24,
    ]);

    $component = Livewire::test(PurchaseForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->call('addProduct', $product->id)
        ->call('setLineUnit', 0, 'purchase');

    expect($component->get('items.0.unit_label'))->toBe('carton')
        ->and((float) $component->get('items.0.unit_cost'))->toBe(240.0);

    $component->set('items.0.quantity', '2')->call('placeOrder')->assertHasNoErrors();

    $item = PurchaseOrder::latest('id')->first()->items()->sole();
    expect($item->unit_label)->toBe('carton')
        ->and($item->factor)->toBe(24.0)
        ->and($item->quantity)->toBe(2.0)
        ->and((float) $item->subtotal)->toBe(480.0);
});

it('receives 2 cartons of 24 as 48 pieces at a 24th of the carton cost', function () {
    $product = Product::factory()->create([
        'quantity' => 0, 'cost_price' => 10, 'purchase_unit' => 'carton', 'purchase_unit_factor' => 24,
    ]);
    $order = unitOrder($product, ['quantity' => 2, 'unit_cost' => 240, 'unit_label' => 'carton', 'unit_factor' => 24]);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->call('receiveStock')
        ->assertHasNoErrors();

    $movement = StockMovement::where('type', 'purchase')->sole();

    expect($product->fresh()->quantity)->toBe(48.0)
        ->and($movement->quantity)->toBe(48.0)
        ->and((float) $movement->unit_cost)->toBe(10.0)
        ->and($order->items()->sole()->received_quantity)->toBe(2.0)
        ->and($order->fresh()->status)->toBe('received');
});

it('rejects fractional quantities for piece products', function () {
    $product = Product::factory()->create(['unit' => 'pcs']);

    Livewire::test(PurchaseForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->call('addProduct', $product->id)
        ->set('items.0.quantity', '1.5')
        ->call('placeOrder')
        ->assertHasErrors('items.0.quantity');
});

it('accepts fractional quantities for loose products', function () {
    $product = Product::factory()->create(['unit' => 'kg', 'quantity' => 0]);

    Livewire::test(PurchaseForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->call('addProduct', $product->id)
        ->set('items.0.quantity', '2.5')
        ->call('placeOrder')
        ->assertHasNoErrors();

    expect(PurchaseOrder::latest('id')->first()->items()->sole()->quantity)->toBe(2.5);
});

it('requires an expiry date when receiving a product that tracks expiry', function () {
    $product = Product::factory()->create(['quantity' => 0, 'track_expiry' => true]);
    $order = unitOrder($product, ['quantity' => 10, 'unit_cost' => 48]);
    $itemId = $order->items()->sole()->id;

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->set("receiveExpiry.{$itemId}", '')
        ->call('receiveStock')
        ->assertHasErrors("receiveExpiry.{$itemId}");

    expect(StockMovement::count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(0.0);
});

it('records the batch and expiry when receiving', function () {
    $product = Product::factory()->create(['quantity' => 0, 'track_expiry' => true]);
    $order = unitOrder($product, ['quantity' => 10, 'unit_cost' => 48]);
    $itemId = $order->items()->sole()->id;
    $expiry = now()->addYear()->format('Y-m-d');

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openReceiveModal')
        ->set("receiveBatches.{$itemId}", 'B-77')
        ->set("receiveExpiry.{$itemId}", $expiry)
        ->call('receiveStock')
        ->assertHasNoErrors();

    $batch = StockBatch::where('product_id', $product->id)->sole();
    $item = $order->items()->sole();

    expect($batch->batch_number)->toBe('B-77')
        ->and($batch->expiry_date->format('Y-m-d'))->toBe($expiry)
        ->and((float) $batch->quantity)->toBe(10.0)
        ->and($item->batch_number)->toBe('B-77')
        ->and($item->expiry_date->format('Y-m-d'))->toBe($expiry);
});

it('requires a transaction ID for a mobile banking supplier payment', function () {
    $product = Product::factory()->create();
    $order = unitOrder($product, ['quantity' => 10, 'unit_cost' => 48]);
    $wallet = PaymentAccount::create(['name' => 'bKash', 'type' => 'mobile_wallet', 'is_active' => true]);

    $component = Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openPaymentModal')
        ->set('payment_method', 'mobile_banking')
        ->set('payment_account_id', $wallet->id)
        ->set('payment_amount', '100')
        ->set('payment_reference', '')
        ->call('recordPayment')
        ->assertHasErrors('payment_reference');

    expect(PurchasePayment::count())->toBe(0);

    $component->set('payment_reference', 'TX123')
        ->call('recordPayment')
        ->assertHasNoErrors();

    $payment = PurchasePayment::sole();
    expect($payment->method)->toBe('mobile_banking')
        ->and($payment->reference)->toBe('TX123')
        ->and($payment->payment_account_id)->toBe($wallet->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(100.0);
});
