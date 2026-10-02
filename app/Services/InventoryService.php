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
 */
class InventoryService
{
    /** Quantities are compared with this tolerance, never with ===. */
    public const EPSILON = 0.0005;

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
     * Pass batch_number / expiry_date in $extra to record which batch it is.
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
     * ['unit_cost' => average cost of the units removed, 'batches' => [...]].
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

            if ($change > 0) {
                $taken = ['unit_cost' => (float) ($extra['unit_cost'] ?? $product->cost_price), 'batches' => []];
                $this->addToBatches($product, $warehouseId, $change, $extra);

                // A purchase moves the product's cost to the weighted average.
                if ($type === 'purchase' && isset($extra['unit_cost'])) {
                    $this->averageCost($product, $change, (float) $extra['unit_cost']);
                }
            } else {
                $taken = $this->takeFromBatches($product, $warehouseId, abs($change));
            }

            $this->writeWarehouseStock($productId, $warehouseId, $after);
            $this->refreshProductTotal($product);

            $this->recordMovement($product, $warehouseId, $type, $change, $before, $after, $extra + [
                'unit_cost' => $taken['unit_cost'],
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
     * batches last), then from untracked stock. Returns the batches used and
     * the average cost of everything taken.
     */
    private function takeFromBatches(Product $product, int $warehouseId, float $quantity): array
    {
        $remaining = $quantity;
        $used = [];
        $cost = 0.0;

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
            $cost += $take * (float) $batch->unit_cost;
            $remaining = round($remaining - $take, 3);
        }

        // Whatever is left came from untracked stock, at the product's cost.
        $cost += max(0, $remaining) * (float) $product->cost_price;

        return [
            'unit_cost' => $quantity > 0 ? round($cost / $quantity, 2) : (float) $product->cost_price,
            'batches' => $used,
        ];
    }

    /** Weighted moving average: old stock at old cost plus new stock at new cost. */
    private function averageCost(Product $product, float $added, float $unitCost): void
    {
        $onHand = max(0, (float) DB::table('product_warehouse')->where('product_id', $product->id)->sum('quantity'));
        $old = (float) $product->cost_price;

        $average = $onHand + $added > 0
            ? (($onHand * $old) + ($added * $unitCost)) / ($onHand + $added)
            : $unitCost;

        $product->cost_price = round($average, 2);
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

        // save() rather than update(): averageCost() may have set cost_price too.
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
