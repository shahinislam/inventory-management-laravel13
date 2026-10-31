<?php

/**
 * Weighted average cost for everything. Batches only decide which units leave
 * first (FEFO); whatever leaves is costed at the product's average, so cost of
 * goods sold and stock value always add up to what was paid.
 */

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    $this->inventory = app(InventoryService::class);
});

it('costs batch and untracked stock alike at the weighted average', function () {
    // 10 untracked at 10, then a batch of 10 at 20: 300 paid for 20, average 15.
    $product = Product::factory()->create(['quantity' => 10, 'cost_price' => 10]);
    $this->inventory->add($product->id, $this->warehouse->id, 10, 'purchase', [
        'batch_number' => 'B1', 'expiry_date' => now()->addMonth()->toDateString(), 'unit_cost' => 20,
    ]);

    expect((float) $product->fresh()->cost_price)->toBe(15.0);

    // FEFO still takes the batch first, but at the average, not the batch's 20.
    $first = $this->inventory->remove($product->id, $this->warehouse->id, 5, 'sale');
    expect(StockBatch::where('batch_number', 'B1')->value('quantity'))->toBe(5.0);

    $rest = $this->inventory->remove($product->id, $this->warehouse->id, 15, 'sale');

    // Selling all 20 costs the 300 paid (it used to come to 350).
    expect($first['unit_cost'])->toBe(15.0)
        ->and($rest['unit_cost'])->toBe(15.0)
        ->and(round(5 * $first['unit_cost'] + 15 * $rest['unit_cost'], 2))->toBe(300.0)
        ->and(StockMovement::where('type', 'sale')->get()->every(fn ($m) => (float) $m->unit_cost === 15.0))->toBeTrue()
        ->and(StockBatch::where('product_id', $product->id)->exists())->toBeFalse()
        ->and((float) $product->fresh()->cost_price)->toBe(15.0);
});

it('re-averages on a restocked sale return, not on transfers or count corrections', function () {
    $product = Product::factory()->create(['quantity' => 20, 'cost_price' => 15]);   // worth 300

    // A sale coming back at the 9 it cost when sold: (20×15 + 5×9) / 25 = 13.8.
    $this->inventory->add($product->id, $this->warehouse->id, 5, 'return', ['unit_cost' => 9]);
    expect((float) $product->fresh()->cost_price)->toBe(13.8);

    // A return with no recorded cost comes back at the average.
    $this->inventory->add($product->id, $this->warehouse->id, 1, 'return');

    $other = Warehouse::factory()->create();
    $this->inventory->transfer($product->id, $this->warehouse->id, $other->id, 10);
    $this->inventory->add($product->id, $this->warehouse->id, 5, 'adjustment');
    $this->inventory->setTo($product->id, $other->id, 4);

    expect((float) $product->fresh()->cost_price)->toBe(13.8)
        ->and((float) StockMovement::where('type', 'transfer_out')->sole()->unit_cost)->toBe(13.8)
        ->and((float) StockMovement::where('type', 'transfer_in')->sole()->unit_cost)->toBe(13.8);
});

it('sends goods back to the supplier at their own cost and re-averages what stays', function () {
    // 10 at 10 plus 10 at 20: 20 on hand worth 300, average 15.
    $product = Product::factory()->create(['quantity' => 10, 'cost_price' => 10]);
    $this->inventory->add($product->id, $this->warehouse->id, 10, 'purchase', ['unit_cost' => 20]);

    $taken = $this->inventory->remove($product->id, $this->warehouse->id, 10, 'purchase_return', ['unit_cost' => 20]);

    // (300 − 10×20) / 10 = 10: what stays is worth what the first 10 cost.
    expect($taken['unit_cost'])->toBe(20.0)
        ->and((float) StockMovement::where('type', 'purchase_return')->sole()->unit_cost)->toBe(20.0)
        ->and((float) $product->fresh()->cost_price)->toBe(10.0);
});

it('keeps the average sane when a supplier return outweighs or empties the stock', function () {
    // 10 on hand worth 100. Sending 5 back at 30 would leave −50 of value: it stops at zero.
    $costly = Product::factory()->create(['quantity' => 10, 'cost_price' => 10]);
    $this->inventory->remove($costly->id, $this->warehouse->id, 5, 'purchase_return', ['unit_cost' => 30]);
    expect((float) $costly->fresh()->cost_price)->toBe(0.0);

    // Sending everything back leaves nothing to divide by: the cost is kept,
    // and the next purchase starts the average again.
    $product = Product::factory()->create(['quantity' => 10, 'cost_price' => 10]);
    $this->inventory->remove($product->id, $this->warehouse->id, 10, 'purchase_return', ['unit_cost' => 12]);
    expect((float) $product->fresh()->cost_price)->toBe(10.0)
        ->and($product->fresh()->quantity)->toBe(0.0);

    $this->inventory->add($product->id, $this->warehouse->id, 5, 'purchase', ['unit_cost' => 8]);
    expect((float) $product->fresh()->cost_price)->toBe(8.0);
});

it('keeps per-piece costs to 4 decimals so a carton sells at what it cost', function () {
    $product = Product::factory()->create(['quantity' => 0, 'cost_price' => 0]);

    // A carton of 24 for 10.00 is 0.4167 a piece. At 2 decimals it was 0.42,
    // so selling the carton cost 10.08.
    $this->inventory->add($product->id, $this->warehouse->id, 24, 'purchase', ['unit_cost' => 10 / 24]);
    $taken = $this->inventory->remove($product->id, $this->warehouse->id, 24, 'sale');

    expect((float) $product->fresh()->cost_price)->toBe(0.4167)
        ->and($taken['unit_cost'])->toBe(0.4167)
        ->and(round(24 * $taken['unit_cost'], 2))->toBe(10.0)
        ->and((float) StockMovement::where('type', 'purchase')->sole()->unit_cost)->toBe(0.4167)
        ->and((float) StockMovement::where('type', 'sale')->sole()->unit_cost)->toBe(0.4167);
});
