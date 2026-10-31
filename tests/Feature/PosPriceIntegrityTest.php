<?php

use App\Livewire\Pos\PosTerminal;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

/*
 * The POS cart is a public Livewire property: the browser can send back any
 * figure in it. Only the quantity is the cashier's to change; everything that
 * sets the price must come from the catalogue.
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    openShift();

    $this->product = Product::factory()->create([
        'selling_price' => 100, 'tax_rate' => 10, 'discount' => 0, 'quantity' => 20,
    ]);
});

it('restores catalogue figures the browser changed before payment', function (string $field, mixed $forged) {
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $this->product->id)
        ->set("cart.0.{$field}", $forged)
        ->call('openPaymentModal')
        ->assertSet('cart.0.price', 100.0)
        ->assertSet('cart.0.discount', 0.0)
        ->assertSet('cart.0.tax_rate', 10.0)
        ->assertSet('amountReceived', '110.00')
        ->call('completeSale');

    expect((float) Invoice::sole()->total)->toBe(110.0);
})->with([
    'price' => ['price', 1],
    'discount' => ['discount', 99],
    'tax rate' => ['tax_rate', 0],
]);

it('refuses to charge when the cart was changed after the payment modal opened', function () {
    $pos = Livewire::test(PosTerminal::class)
        ->call('addToCart', $this->product->id)
        ->call('openPaymentModal')
        ->set('cart.0.price', 1)
        ->call('completeSale');

    expect(Invoice::count())->toBe(0);
    $pos->assertSet('cart.0.price', 100.0)
        ->assertSet('showPaymentModal', false);

    $pos->call('openPaymentModal')->call('completeSale');

    expect((float) Invoice::sole()->total)->toBe(110.0);
});

it('charges a line the browser marked as a gift', function () {
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $this->product->id)
        ->set('cart.0.is_gift', true)
        ->set('cart.0.price', 0)
        ->call('openPaymentModal')
        ->assertSet('cart.0.price', 100.0)
        ->call('completeSale');

    $invoice = Invoice::with('items')->sole();

    expect((float) $invoice->total)->toBe(110.0)
        ->and($invoice->items->sole()->is_gift)->toBeFalse();
});

it('keeps packaged goods in whole units', function () {
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $this->product->id)
        ->set('cart.0.quantity', 2.5)
        ->call('openPaymentModal')
        ->assertSet('cart.0.quantity', 2.0);
});

it('does not sell more than is in stock when the browser raises the quantity', function () {
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $this->product->id)
        ->set('cart.0.quantity', 999)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(Invoice::count())->toBe(0)
        ->and($this->product->fresh()->quantity)->toBe(20.0);
});

it('sells only from an active warehouse whatever id the browser sends', function (?string $forged) {
    $closed = Warehouse::factory()->create(['is_active' => false]);
    $closed->products()->attach($this->product->id, ['quantity' => 50]);

    $pos = Livewire::test(PosTerminal::class)
        ->set('warehouse_id', match ($forged) {
            'inactive' => $closed->id,
            'missing' => 999999,
            default => null,
        })
        ->assertSet('warehouse_id', $this->warehouse->id)
        ->call('addToCart', $this->product->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(Invoice::sole()->warehouse_id)->toBe($this->warehouse->id)
        ->and($this->product->stockIn($closed->id))->toBe(50.0);
})->with(['inactive', 'missing', 'empty' => [null]]);

it('takes payment as usual when nothing was tampered with', function () {
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $this->product->id)
        ->call('updateQty', 0, 3)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect((float) Invoice::sole()->total)->toBe(330.0);
});
