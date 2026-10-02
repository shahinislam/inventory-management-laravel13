<?php

use App\Livewire\Pos\PosTerminal;
use App\Livewire\Reports\StockReport;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->dhaka = Warehouse::factory()->default()->create(['name' => 'Dhaka']);
    $this->ctg = Warehouse::factory()->create(['name' => 'Chittagong']);
    $this->inventory = app(InventoryService::class);
    $this->actingAs($this->user);
    openShift();
});

/** products.quantity must always equal the sum of its warehouse rows. */
function invariantHolds(Product $product): bool
{
    $sum = (float) DB::table('product_warehouse')
        ->where('product_id', $product->id)
        ->sum('quantity');

    return abs($product->fresh()->quantity - $sum) < 0.0005;
}

it('tracks stock separately per warehouse', function () {
    $product = Product::factory()->create(['quantity' => 0]);

    $this->inventory->add($product->id, $this->dhaka->id, 30, 'purchase');
    $this->inventory->add($product->id, $this->ctg->id, 70, 'purchase');

    expect($this->inventory->stockIn($product->id, $this->dhaka->id))->toBe(30.0)
        ->and($this->inventory->stockIn($product->id, $this->ctg->id))->toBe(70.0)
        ->and($product->fresh()->quantity)->toBe(100.0)
        ->and(invariantHolds($product))->toBeTrue();
});

it('keeps products.quantity in step with the warehouse rows', function () {
    $product = Product::factory()->create(['quantity' => 0]);

    $this->inventory->add($product->id, $this->dhaka->id, 50, 'purchase');
    expect(invariantHolds($product))->toBeTrue();

    $this->inventory->remove($product->id, $this->dhaka->id, 20, 'sale');
    expect(invariantHolds($product))->toBeTrue();

    $this->inventory->transfer($product->id, $this->dhaka->id, $this->ctg->id, 10);
    expect(invariantHolds($product))->toBeTrue()
        ->and($product->fresh()->quantity)->toBe(30.0);
});

it('refuses to remove more than a warehouse holds', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $this->inventory->add($product->id, $this->dhaka->id, 5, 'purchase');

    expect(fn () => $this->inventory->remove($product->id, $this->dhaka->id, 10, 'sale'))
        ->toThrow(ValidationException::class);

    // Nothing changed.
    expect($this->inventory->stockIn($product->id, $this->dhaka->id))->toBe(5.0);
});

it('will not sell stock that is in another warehouse', function () {
    // All stock is in Chittagong; the terminal is selling from Dhaka.
    $product = Product::factory()->create(['quantity' => 0, 'selling_price' => 100]);
    $this->inventory->add($product->id, $this->ctg->id, 50, 'purchase');

    Livewire::test(PosTerminal::class)
        ->set('warehouse_id', $this->dhaka->id)
        ->call('addToCart', $product->id)
        ->assertCount('cart', 0);

    expect(Invoice::count())->toBe(0);
});

it('sells from the warehouse the terminal is set to', function () {
    $product = Product::factory()->create(['quantity' => 0, 'selling_price' => 100]);
    $this->inventory->add($product->id, $this->dhaka->id, 10, 'purchase');
    $this->inventory->add($product->id, $this->ctg->id, 10, 'purchase');

    Livewire::test(PosTerminal::class)
        ->set('warehouse_id', $this->ctg->id)
        ->call('addToCart', $product->id)
        ->call('updateQty', 0, 4)
        ->call('openPaymentModal')
        ->call('completeSale');

    // Only Chittagong was drawn down.
    expect($this->inventory->stockIn($product->id, $this->ctg->id))->toBe(6.0)
        ->and($this->inventory->stockIn($product->id, $this->dhaka->id))->toBe(10.0)
        ->and($product->fresh()->quantity)->toBe(16.0);
});

it('caps the cart at what the selected warehouse holds', function () {
    $product = Product::factory()->create(['quantity' => 0, 'selling_price' => 100]);
    $this->inventory->add($product->id, $this->dhaka->id, 2, 'purchase');
    $this->inventory->add($product->id, $this->ctg->id, 99, 'purchase');

    Livewire::test(PosTerminal::class)
        ->set('warehouse_id', $this->dhaka->id)
        ->call('addToCart', $product->id)
        ->call('incrementQty', 0)
        ->call('incrementQty', 0)   // would be 3; Dhaka only has 2
        ->assertSet('cart.0.quantity', 2);
});

it('clears the cart when the warehouse is switched', function () {
    $product = Product::factory()->create(['quantity' => 0, 'selling_price' => 100]);
    $this->inventory->add($product->id, $this->dhaka->id, 10, 'purchase');

    Livewire::test(PosTerminal::class)
        ->set('warehouse_id', $this->dhaka->id)
        ->call('addToCart', $product->id)
        ->assertCount('cart', 1)
        ->set('warehouse_id', $this->ctg->id)
        ->assertCount('cart', 0);
});

it('records movements against the warehouse the stock moved in', function () {
    $product = Product::factory()->create(['quantity' => 0]);

    $this->inventory->add($product->id, $this->ctg->id, 10, 'purchase');

    $movement = StockMovement::sole();

    expect($movement->warehouse_id)->toBe($this->ctg->id)
        ->and($movement->before_quantity)->toBe(0.0)
        ->and($movement->after_quantity)->toBe(10.0);
});

it('creates the warehouse stock row on first use', function () {
    $product = Product::factory()->create(['quantity' => 0]);

    expect(DB::table('product_warehouse')
        ->where('product_id', $product->id)
        ->where('warehouse_id', $this->ctg->id)
        ->exists())->toBeFalse();

    $this->inventory->add($product->id, $this->ctg->id, 7, 'purchase');

    expect($this->inventory->stockIn($product->id, $this->ctg->id))->toBe(7.0);
});

it('reports zero for a warehouse holding none of the product', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $this->inventory->add($product->id, $this->dhaka->id, 10, 'purchase');

    expect($this->inventory->stockIn($product->id, $this->ctg->id))->toBe(0.0)
        ->and($this->inventory->stockIn($product->id, null))->toBe(0.0);
});

it('adjusts one warehouse without touching the other', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $this->inventory->add($product->id, $this->dhaka->id, 10, 'purchase');
    $this->inventory->add($product->id, $this->ctg->id, 10, 'purchase');

    $this->inventory->setTo($product->id, $this->dhaka->id, 3);

    expect($this->inventory->stockIn($product->id, $this->dhaka->id))->toBe(3.0)
        ->and($this->inventory->stockIn($product->id, $this->ctg->id))->toBe(10.0)
        ->and($product->fresh()->quantity)->toBe(13.0);
});

it('exposes per-warehouse quantities through the relationship', function () {
    $product = Product::factory()->create(['quantity' => 0]);
    $this->inventory->add($product->id, $this->dhaka->id, 8, 'purchase');

    expect($product->stockIn($this->dhaka->id))->toBe(8.0)
        ->and($product->stockIn($this->ctg->id))->toBe(0.0)
        ->and($this->dhaka->products()->count())->toBe(1);
});

it('filters the stock report to one warehouse', function () {
    $inDhaka = Product::factory()->create(['name' => 'Dhaka Only', 'quantity' => 0]);
    $inCtg = Product::factory()->create(['name' => 'Ctg Only', 'quantity' => 0]);

    $this->inventory->add($inDhaka->id, $this->dhaka->id, 10, 'purchase');
    $this->inventory->add($inCtg->id, $this->ctg->id, 10, 'purchase');

    Livewire::test(StockReport::class)
        ->set('warehouseFilter', (string) $this->dhaka->id)
        ->assertSee('Dhaka Only')
        ->assertDontSee('Ctg Only');
});

it('values the stock report per warehouse', function () {
    $product = Product::factory()->create(['quantity' => 0, 'cost_price' => 100]);

    $this->inventory->add($product->id, $this->dhaka->id, 3, 'purchase');
    $this->inventory->add($product->id, $this->ctg->id, 7, 'purchase');

    $dhakaOnly = Livewire::test(StockReport::class)
        ->set('warehouseFilter', (string) $this->dhaka->id)
        ->viewData('summary');

    $everywhere = Livewire::test(StockReport::class)
        ->viewData('summary');

    expect((float) $dhakaOnly['total_value'])->toBe(300.0)
        ->and((float) $everywhere['total_value'])->toBe(1000.0);
});
