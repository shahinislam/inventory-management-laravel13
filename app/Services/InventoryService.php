<?php

namespace App\Services;

use App\Models\Product;
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
 */
class InventoryService
{
    /**
     * How much of this product is in this warehouse.
     */
    public function stockIn(int $productId, ?int $warehouseId): int
    {
        if (! $warehouseId) {
            return 0;
        }

        return (int) DB::table('product_warehouse')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');
    }

    /**
     * Add stock to a warehouse. Used by purchase receiving and transfers in.
     */
    public function add(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $type,
        array $extra = []
    ): void {
        $this->move($productId, $warehouseId, $quantity, $type, $extra);
    }

    /**
     * Remove stock from a warehouse. Used by sales and transfers out.
     *
     * Throws if the warehouse does not hold enough.
     */
    public function remove(
        int $productId,
        int $warehouseId,
        int $quantity,
        string $type,
        array $extra = []
    ): void {
        $this->move($productId, $warehouseId, -$quantity, $type, $extra);
    }

    /**
     * Move stock between two warehouses. The total on hand does not change.
     */
    public function transfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $notes) {
            $this->remove($productId, $fromWarehouseId, $quantity, 'transfer_out', ['notes' => $notes]);
            $this->add($productId, $toWarehouseId, $quantity, 'transfer_in', ['notes' => $notes]);
        });
    }

    /**
     * Set a warehouse's stock to an exact number (a stock count correction).
     */
    public function setTo(
        int $productId,
        int $warehouseId,
        int $newQuantity,
        array $extra = []
    ): void {
        $difference = $newQuantity - $this->stockIn($productId, $warehouseId);

        if ($difference === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'No change in quantity.',
            ]);
        }

        $this->move($productId, $warehouseId, $difference, 'adjustment', $extra);
    }

    /**
     * The one method that actually changes stock.
     *
     * $change is positive to add, negative to remove.
     */
    private function move(int $productId, int $warehouseId, int $change, string $type, array $extra): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $change, $type, $extra) {
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            $before = $this->lockedStockIn($productId, $warehouseId);
            $after = $before + $change;

            if ($after < 0) {
                $warehouse = Warehouse::find($warehouseId);

                throw ValidationException::withMessages([
                    'quantity' => "Only {$before} {$product->unit} of {$product->name} available"
                        .($warehouse ? " in {$warehouse->name}." : '.'),
                ]);
            }

            $this->writeWarehouseStock($productId, $warehouseId, $after);
            $this->refreshProductTotal($product);

            $this->recordMovement($product, $warehouseId, $type, $change, $before, $after, $extra);
        });
    }

    /**
     * Read a warehouse's stock with the row locked, creating it if missing.
     */
    private function lockedStockIn(int $productId, int $warehouseId): int
    {
        $row = DB::table('product_warehouse')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($row) {
            return (int) $row->quantity;
        }

        DB::table('product_warehouse')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 0;
    }

    private function writeWarehouseStock(int $productId, int $warehouseId, int $quantity): void
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
        $total = (int) DB::table('product_warehouse')
            ->where('product_id', $product->id)
            ->sum('quantity');

        $product->update(['quantity' => $total]);
    }

    private function recordMovement(
        Product $product,
        int $warehouseId,
        string $type,
        int $change,
        int $before,
        int $after,
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
