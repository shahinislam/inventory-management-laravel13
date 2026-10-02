<?php

use App\Livewire\Stock\StockCount;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

it('loads active products with their system quantity when a count starts', function () {
    $a = Product::factory()->create(['quantity' => 10]);
    $b = Product::factory()->create(['quantity' => 4]);
    Product::factory()->inactive()->create(['quantity' => 3]);

    Livewire::test(StockCount::class)
        ->assertSet('warehouse_id', $this->warehouse->id)
        ->call('start')
        ->assertSet('started', true)
        ->assertCount('rows', 2)
        ->assertSet("rows.{$a->id}.system", 10.0)
        ->assertSet("rows.{$b->id}.counted", '')
        ->assertSee("it isn't saved until you post", false);
});

it('only loads the chosen category', function () {
    $category = Category::factory()->create();
    $in = Product::factory()->create(['quantity' => 5, 'category_id' => $category->id]);
    Product::factory()->create(['quantity' => 5]);

    Livewire::test(StockCount::class)
        ->set('category_id', (string) $category->id)
        ->call('start')
        ->assertCount('rows', 1)
        ->assertSet("rows.{$in->id}.name", $in->name);
});

it('corrects counted rows and leaves blank rows alone when a manager posts', function () {
    $short = Product::factory()->create(['quantity' => 10]);
    $extra = Product::factory()->create(['quantity' => 5]);
    $same = Product::factory()->create(['quantity' => 7]);
    $blank = Product::factory()->create(['quantity' => 8]);

    Livewire::test(StockCount::class)
        ->call('start')
        ->set("rows.{$short->id}.counted", '8')
        ->set("rows.{$extra->id}.counted", '6')
        ->set("rows.{$same->id}.counted", '7')
        ->call('post')
        ->assertHasNoErrors()
        ->assertSet('started', false)
        ->assertSet('rows', []);

    $inventory = app(InventoryService::class);
    expect($inventory->stockIn($short->id, $this->warehouse->id))->toBe(8.0)
        ->and($inventory->stockIn($extra->id, $this->warehouse->id))->toBe(6.0)
        ->and($inventory->stockIn($same->id, $this->warehouse->id))->toBe(7.0)
        ->and($inventory->stockIn($blank->id, $this->warehouse->id))->toBe(8.0);

    $movements = StockMovement::all();
    expect($movements)->toHaveCount(2)
        ->and($movements->pluck('type')->unique()->all())->toBe(['adjustment'])
        ->and($movements->first()->notes)->toStartWith('Stock count SC-');
});

it('accepts decimal counts for loose products', function () {
    $rice = Product::factory()->create(['quantity' => 10, 'unit' => 'kg']);

    Livewire::test(StockCount::class)
        ->call('start')
        ->assertSet("rows.{$rice->id}.loose", true)
        ->set("rows.{$rice->id}.counted", '9.375')
        ->call('post');

    expect(app(InventoryService::class)->stockIn($rice->id, $this->warehouse->id))->toBe(9.375);
});

it('adds one per scan, but not for loose products', function () {
    $soap = Product::factory()->create(['quantity' => 10, 'barcode' => '1111111111111']);
    $rice = Product::factory()->create(['quantity' => 10, 'unit' => 'kg', 'barcode' => '2222222222222']);

    Livewire::test(StockCount::class)
        ->call('start')
        ->call('onBarcodeScanned', '1111111111111')
        ->call('onBarcodeScanned', '1111111111111')
        ->assertSet("rows.{$soap->id}.counted", '2')
        ->call('onBarcodeScanned', '2222222222222')
        ->assertDispatched('scan-result', found: false, message: "{$rice->name} is sold by weight. Weigh it and type the amount.")
        ->assertSet("rows.{$rice->id}.counted", '');
});

it('does not let staff post a count', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));
    $product = Product::factory()->create(['quantity' => 10]);

    Livewire::test(StockCount::class)
        ->call('start')
        ->assertSee('Ask a manager to post')
        ->set("rows.{$product->id}.counted", '3')
        ->call('post')
        ->assertForbidden();

    expect(app(InventoryService::class)->stockIn($product->id, $this->warehouse->id))->toBe(10.0)
        ->and(StockMovement::count())->toBe(0);
});
