<?php

use App\Livewire\Pos\PosTerminal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    openShift();
});

it('adds a product to the cart with its resolved price and discount', function () {
    $product = Product::factory()->create([
        'selling_price' => 200,
        'discount' => 10,
        'quantity' => 50,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->assertCount('cart', 1)
        ->assertSet('cart.0.price', 200.0)
        ->assertSet('cart.0.discount', 20.0)
        ->assertSet('cart.0.quantity', 1);
});

it('increments quantity instead of duplicating an existing cart line', function () {
    $product = Product::factory()->create(['quantity' => 50]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('addToCart', $product->id)
        ->assertCount('cart', 1)
        ->assertSet('cart.0.quantity', 2);
});

it('refuses to add an out-of-stock product', function () {
    $product = Product::factory()->outOfStock()->create();

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->assertCount('cart', 0);
});

it('never lets cart quantity exceed available stock', function () {
    $product = Product::factory()->create(['quantity' => 2]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('incrementQty', 0)
        ->call('incrementQty', 0)   // would be 3, stock is 2
        ->assertSet('cart.0.quantity', 2);
});

it('separates goods subtotal from tax', function () {
    $product = Product::factory()->create([
        'selling_price' => 500,
        'tax_rate' => 10,
        'quantity' => 50,
    ]);

    $component = Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('updateQty', 0, 2);

    // goods 1000, line tax 100, total 1100
    expect($component->instance()->cartSubtotal)->toBe(1000.0)
        ->and($component->instance()->cartItemTax)->toBe(100.0)
        ->and($component->instance()->cartTaxTotal)->toBe(100.0)
        ->and($component->instance()->cartTotal)->toBe(1100.0);
});

it('adds the manual tax surcharge on top of line tax', function () {
    $product = Product::factory()->create([
        'selling_price' => 500,
        'tax_rate' => 10,
        'quantity' => 50,
    ]);

    $component = Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('updateQty', 0, 2)
        ->set('tax', '50');

    expect($component->instance()->cartTaxTotal)->toBe(150.0)
        ->and($component->instance()->cartTotal)->toBe(1150.0);
});

it('deducts stock and records a movement on checkout', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('updateQty', 0, 3)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect($product->fresh()->quantity)->toBe(7.0);

    $movement = StockMovement::where('product_id', $product->id)->sole();
    expect($movement->type)->toBe('sale')
        ->and($movement->quantity)->toBe(3.0)
        ->and($movement->before_quantity)->toBe(10.0)
        ->and($movement->after_quantity)->toBe(7.0);
});

it('creates an invoice with the tax actually charged', function () {
    $product = Product::factory()->create([
        'selling_price' => 500,
        'tax_rate' => 10,
        'quantity' => 10,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('updateQty', 0, 2)
        ->call('openPaymentModal')
        ->call('completeSale');

    $invoice = Invoice::sole();

    // The 100 of line tax must be visible in the tax column, not hidden
    // inside subtotal.
    expect((float) $invoice->subtotal)->toBe(1000.0)
        ->and((float) $invoice->tax)->toBe(100.0)
        ->and((float) $invoice->total)->toBe(1100.0)
        ->and($invoice->status)->toBe('paid')
        ->and((float) $invoice->due_amount)->toBe(0.0);
});

it('completes a cash sale over 1000 where the total needs a thousands separator', function () {
    // Regression: openPaymentModal() used to prefill "1,100.00", which
    // (float) truncates to 1.0 — every cash sale of 1000+ was blocked.
    $product = Product::factory()->create(['selling_price' => 1500, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(Invoice::count())->toBe(1)
        ->and((float) Invoice::sole()->total)->toBe(1500.0)
        ->and($product->fresh()->quantity)->toBe(9.0);
});

it('accepts a hand-typed amount containing a thousands separator', function () {
    $product = Product::factory()->create(['selling_price' => 1200, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->set('amountReceived', '1,500.00')
        ->call('completeSale');

    expect(Invoice::count())->toBe(1);
});

it('computes change due from an amount containing a separator', function () {
    $product = Product::factory()->create(['selling_price' => 1200, 'quantity' => 10]);

    $component = Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->set('amountReceived', '1,500.00');

    expect($component->instance()->changeDue)->toBe(300.0);
});

it('records a payment for the sale', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(Payment::count())->toBe(1)
        ->and((float) Payment::sole()->amount)->toBe(100.0);
});

it('blocks a cash sale when the amount received is short', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->set('paymentMethod', 'cash')
        ->set('amountReceived', '50')
        ->call('completeSale');

    expect(Invoice::count())->toBe(0)
        ->and($product->fresh()->quantity)->toBe(10.0);
});

it('rolls the whole sale back when stock ran out mid-checkout', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 5]);

    $component = Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('updateQty', 0, 5);

    // Another terminal sells the same stock before this cart checks out, so the
    // warehouse now holds less than the cart wants.
    app(InventoryService::class)->remove(
        productId: $product->id,
        warehouseId: $this->warehouse->id,
        quantity: 3,
        type: 'sale',
    );

    $component->call('openPaymentModal')->call('completeSale');

    // The sale rolled back entirely: no invoice, no payment, no sale movement
    // beyond the one the competing sale already wrote, and stock unchanged.
    expect(Invoice::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(StockMovement::where('type', 'sale')->count())->toBe(1)
        ->and($product->fresh()->quantity)->toBe(2.0);
});

it('consumes a promotion usage on checkout', function () {
    $product = Product::factory()->create(['selling_price' => 200, 'quantity' => 10]);

    $promotion = Promotion::factory()->percentage(25)->create([
        'product_id' => $product->id,
        'usage_limit' => 10,
        'used_count' => 0,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect($promotion->fresh()->used_count)->toBe(1);
});

it('updates customer totals on checkout', function () {
    $customer = Customer::factory()->create(['total_orders' => 2, 'total_purchases' => 500]);
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('selectCustomer', $customer->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    $customer->refresh();

    expect($customer->total_orders)->toBe(3)
        ->and((float) $customer->total_purchases)->toBe(600.0);
});

it('clears the cart after a completed sale', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('completeSale')
        ->assertCount('cart', 0);
});
