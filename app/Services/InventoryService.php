<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place stock is ever changed.
 *
 * Stock lives in two places and they must always agree:
 *
 *   product_warehouse.quantity  = how much is in ONE warehouse (source of truth)
 *   products.quantity           = the total across ALL warehouses (a cache)
 *
 * Every method here updates both, inside a transaction, with the row locked.
 * Do not write to either column directly from a component — call these instead.
 *
 * Quantities are decimals (3 places) so loose goods can be sold by weight.
 *
 * stock_batches records which part of the warehouse stock expires when. It is
 * never more than product_warehouse; the rest is untracked stock. Removals take
 * from batches earliest-expiry first (FEFO), then from untracked stock.
 *
 * Cost is a weighted average per product (products.cost_price, 4 decimals per
 * base unit). Batches only say which units leave first; whatever leaves is
 * costed at the average, batch or not. Goods arriving with a cost of their own
 * (a purchase, a sale coming back) move the average; transfers and count
 * corrections do not. A return to the supplier leaves at its own (landed) cost
 * and the stock left behind carries the rest of the value.
 */
class InventoryService
{
    /** Quantities are compared with this tolerance, never with ===. */
    public const EPSILON = 0.0005;

    /** Arrivals that carry a real cost and so move the weighted average. */
    private const COSTED_ARRIVALS = ['purchase', 'return'];

    /**
     * How much of this product is in this warehouse.
     */
    public function stockIn(int $productId, ?int $warehouseId): float
    {
        if (! $warehouseId) {
            return 0.0;
        }

        return round((float) DB::table('product_warehouse')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity'), 3);
    }

    /**
     * Add stock to a warehouse. Used by purchase receiving, returns and transfers in.
     *
     * Pass batch_number / expiry_date in $extra to record which batch it is, and
     * unit_cost (per base unit) on a purchase or sales return to re-average.
     */
    public function add(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $type,
        array $extra = []
    ): array {
        return $this->move($productId, $warehouseId, $quantity, $type, $extra);
    }

    /**
     * Remove stock from a warehouse. Used by sales and transfers out.
     *
     * Throws if the warehouse does not hold enough. Returns what was taken:
     * ['unit_cost' => the weighted average cost per unit, 'batches' => [...]].
     * A purchase_return passes its own unit_cost and is taken out at that.
     */
    public function remove(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $type,
        array $extra = []
    ): array {
        return $this->move($productId, $warehouseId, -$quantity, $type, $extra);
    }

    /**
     * Move stock between two warehouses. The total on hand does not change,
     * and the batches travel with the goods.
     */
    public function transfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $notes) {
            $taken = $this->remove($productId, $fromWarehouseId, $quantity, 'transfer_out', ['notes' => $notes]);
            $this->add($productId, $toWarehouseId, $quantity, 'transfer_in', [
                'notes' => $notes,
                'unit_cost' => $taken['unit_cost'],
                'batches' => $taken['batches'],
            ]);
        });
    }

    /**
     * Set a warehouse's stock to an exact number (a stock count correction).
     */
    public function setTo(
        int $productId,
        int $warehouseId,
        float $newQuantity,
        array $extra = []
    ): void {
        $difference = round($newQuantity - $this->stockIn($productId, $warehouseId), 3);

        if (abs($difference) < self::EPSILON) {
            throw ValidationException::withMessages([
                'quantity' => 'No change in quantity.',
            ]);
        }

        $this->move($productId, $warehouseId, $difference, $extra['type'] ?? 'adjustment', $extra);
    }

    /**
     * The one method that actually changes stock.
     *
     * $change is positive to add, negative to remove.
     */
    private function move(int $productId, int $warehouseId, float $change, string $type, array $extra): array
    {
        $change = round($change, 3);

        return DB::transaction(function () use ($productId, $warehouseId, $change, $type, $extra) {
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            $before = $this->lockedStockIn($productId, $warehouseId);
            $after = round($before + $change, 3);

            if ($after < -self::EPSILON) {
                $warehouse = Warehouse::find($warehouseId);

                throw ValidationException::withMessages([
                    'quantity' => 'Only '.format_qty($before, $product->unit)." of {$product->name} available"
                        .($warehouse ? " in {$warehouse->name}." : '.'),
                ]);
            }
            $after = max(0.0, $after);

            // Averages read the stock on hand before this change, so they run
            // before writeWarehouseStock().
            if ($change > 0) {
                $unitCost = isset($extra['unit_cost']) ? (float) $extra['unit_cost'] : (float) $product->cost_price;
                $taken = ['unit_cost' => round($unitCost, 4), 'batches' => []];
                $this->addToBatches($product, $warehouseId, $change, $extra);

                // A purchase, or a sale coming back at what it cost, moves the
                // product's cost to the weighted average.
                if (in_array($type, self::COSTED_ARRIVALS, true) && isset($extra['unit_cost'])) {
                    $this->averageCost($product, $change, $unitCost);
                }
            } else {
                $taken = $this->takeFromBatches($product, $warehouseId, abs($change));

                if ($type === 'purchase_return' && isset($extra['unit_cost'])) {
                    // Sent back at what it cost; what stays keeps the rest of the value.
                    $taken['unit_cost'] = round((float) $extra['unit_cost'], 4);
                    $this->averageOut($product, abs($change), $taken['unit_cost']);
                } else {
                    $taken['unit_cost'] = round((float) $product->cost_price, 4);
                }
            }

            $this->writeWarehouseStock($productId, $warehouseId, $after);
            $this->refreshProductTotal($product);

            $this->recordMovement($product, $warehouseId, $type, $change, $before, $after, ['unit_cost' => $taken['unit_cost']] + $extra + [
                'batch_number' => count($taken['batches']) === 1 ? $taken['batches'][0]['batch_number'] : null,
                'expiry_date' => count($taken['batches']) === 1 ? $taken['batches'][0]['expiry_date'] : null,
            ]);

            return $taken;
        });
    }

    /**
     * Record incoming stock against its batch, when one is known. A transfer
     * passes the batches it took from the other warehouse.
     */
    private function addToBatches(Product $product, int $warehouseId, float $quantity, array $extra): void
    {
        $batches = $extra['batches'] ?? [];

        if ($batches === [] && (filled($extra['batch_number'] ?? null) || filled($extra['expiry_date'] ?? null))) {
            $batches = [[
                'batch_number' => $extra['batch_number'] ?? null,
                'expiry_date' => $extra['expiry_date'] ?? null,
                'quantity' => $quantity,
                'unit_cost' => (float) ($extra['unit_cost'] ?? $product->cost_price),
            ]];
        }

        foreach ($batches as $batch) {
            $expiry = $batch['expiry_date'] ? date('Y-m-d', strtotime((string) $batch['expiry_date'])) : null;

            $row = StockBatch::where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->where('batch_number', $batch['batch_number'])
                ->where('expiry_date', $expiry)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $row->update(['quantity' => round($row->quantity + $batch['quantity'], 3)]);
            } else {
                StockBatch::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'batch_number' => $batch['batch_number'],
                    'expiry_date' => $expiry,
                    'quantity' => round($batch['quantity'], 3),
                    'unit_cost' => $batch['unit_cost'],
                    'received_at' => now(),
                ]);
            }
        }
    }

    /**
     * Take $quantity out of the batches, earliest expiry first (no-expiry
     * batches last), then from untracked stock. Returns the batches used; the
     * caller prices the units (batches decide which units go, not their cost).
     */
    private function takeFromBatches(Product $product, int $warehouseId, float $quantity): array
    {
        $remaining = $quantity;
        $used = [];

        $batches = StockBatch::where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining < self::EPSILON) {
                break;
            }

            $take = round(min($remaining, (float) $batch->quantity), 3);
            $left = round($batch->quantity - $take, 3);
            $left < self::EPSILON ? $batch->delete() : $batch->update(['quantity' => $left]);

            $used[] = [
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'quantity' => $take,
                'unit_cost' => (float) $batch->unit_cost,
            ];
            $remaining = round($remaining - $take, 3);
        }

        // Whatever is left came from untracked stock.
        return ['batches' => $used];
    }

    /** Total on hand across every warehouse, before the change being made. */
    private function onHand(Product $product): float
    {
        return max(0, (float) DB::table('product_warehouse')->where('product_id', $product->id)->sum('quantity'));
    }

    /** Weighted moving average: old stock at old cost plus new stock at new cost. */
    private function averageCost(Product $product, float $added, float $unitCost): void
    {
        $onHand = $this->onHand($product);
        $old = (float) $product->cost_price;

        $average = $onHand + $added > 0
            ? (($onHand * $old) + ($added * $unitCost)) / ($onHand + $added)
            : $unitCost;

        $product->cost_price = round($average, 4);
    }

    /**
     * Goods leaving at their own cost instead of the average (sent back to the
     * supplier at landed cost): the stock left carries what remains of the value.
     */
    private function averageOut(Product $product, float $removed, float $unitCost): void
    {
        $onHand = $this->onHand($product);
        $left = round($onHand - $removed, 3);

        // Nothing left to value: keep the last average until stock arrives again.
        if ($left < self::EPSILON) {
            return;
        }

        $value = ($onHand * (float) $product->cost_price) - ($removed * $unitCost);

        // Never below zero, even if what went back cost more than all of it was worth.
        $product->cost_price = round(max(0, $value) / $left, 4);
    }

    /**
     * Read a warehouse's stock with the row locked, creating it if missing.
     */
    private function lockedStockIn(int $productId, int $warehouseId): float
    {
        $row = DB::table('product_warehouse')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($row) {
            return round((float) $row->quantity, 3);
        }

        DB::table('product_warehouse')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 0.0;
    }

    private function writeWarehouseStock(int $productId, int $warehouseId, float $quantity): void
    {
        DB::table('product_warehouse')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->update(['quantity' => $quantity, 'updated_at' => now()]);
    }

    /**
     * Recalculate products.quantity from the warehouse rows, so the cached
     * total can never drift away from the per-warehouse truth.
     */
    private function refreshProductTotal(Product $product): void
    {
        $total = round((float) DB::table('product_warehouse')
            ->where('product_id', $product->id)
            ->sum('quantity'), 3);

        // save() rather than update(): averageCost() / averageOut() may have set cost_price too.
        $product->quantity = $total;
        $product->save();
    }

    private function recordMovement(
        Product $product,
        int $warehouseId,
        string $type,
        float $change,
        float $before,
        float $after,
        array $extra
    ): void {
        $reference = $extra['reference'] ?? null;

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'created_by' => auth()->id(),
            'type' => $type,
            'quantity' => abs($change),
            'before_quantity' => $before,
            'after_quantity' => $after,
            'unit_cost' => $extra['unit_cost'] ?? $product->cost_price,
            'reference_type' => $reference instanceof Model ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'batch_number' => $extra['batch_number'] ?? null,
            'expiry_date' => $extra['expiry_date'] ?? null,
            'notes' => $extra['notes'] ?? null,
        ]);
    }
}
