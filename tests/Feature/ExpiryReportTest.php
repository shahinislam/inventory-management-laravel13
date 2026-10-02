<?php

use App\Livewire\Reports\ExpiryReport;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    $this->inventory = app(InventoryService::class);
});

function receiveBatch(Product $product, int $warehouseId, float $qty, string $batch, string $expiry): StockBatch
{
    app(InventoryService::class)->add($product->id, $warehouseId, $qty, 'purchase', [
        'batch_number' => $batch,
        'expiry_date' => $expiry,
        'unit_cost' => 50,
    ]);

    return StockBatch::where('product_id', $product->id)->where('batch_number', $batch)->sole();
}

it('lists expired batches on the expired tab', function () {
    $milk = Product::factory()->create(['quantity' => 0]);
    $bread = Product::factory()->create(['quantity' => 0]);
    receiveBatch($milk, $this->warehouse->id, 5, 'B1', now()->subDay()->toDateString());
    receiveBatch($bread, $this->warehouse->id, 5, 'B2', now()->addDays(5)->toDateString());

    Livewire::test(ExpiryReport::class)
        ->assertSet('tab', 'expired')
        ->assertSee($milk->name)
        ->assertDontSee($bread->name)
        ->assertViewHas('expiredCount', 1)
        ->assertViewHas('soonCount', 1)
        ->call('setTab', 'soon')
        ->assertSee($bread->name)
        ->assertDontSee($milk->name)
        ->call('setTab', 'all')
        ->assertSee($bread->name)
        ->assertSee($milk->name);
});

it('shows a friendly empty state when nothing has expired', function () {
    Livewire::test(ExpiryReport::class)->assertSee('No expired stock');
});

it('writes off an expired batch as an expired movement', function () {
    $milk = Product::factory()->create(['quantity' => 0]);
    $batch = receiveBatch($milk, $this->warehouse->id, 5, 'B1', now()->subDay()->toDateString());
    $this->inventory->add($milk->id, $this->warehouse->id, 3, 'purchase'); // untracked stock stays

    Livewire::test(ExpiryReport::class)->call('writeOff', $batch->id);

    expect($this->inventory->stockIn($milk->id, $this->warehouse->id))->toBe(3.0)
        ->and(StockBatch::find($batch->id))->toBeNull();

    $movement = StockMovement::where('type', 'expired')->sole();
    expect((float) $movement->quantity)->toBe(5.0)
        ->and($movement->notes)->toBe('Expired batch B1 written off');
});

it('does not let a viewer write off stock', function () {
    $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
    $milk = Product::factory()->create(['quantity' => 0]);
    $batch = receiveBatch($milk, $this->warehouse->id, 5, 'B1', now()->subDay()->toDateString());

    Livewire::test(ExpiryReport::class)->call('writeOff', $batch->id)->assertForbidden();

    expect($this->inventory->stockIn($milk->id, $this->warehouse->id))->toBe(5.0);
});
