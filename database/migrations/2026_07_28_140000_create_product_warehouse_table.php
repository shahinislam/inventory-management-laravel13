<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-warehouse stock levels.
 *
 * This table becomes the source of truth for *where* stock is. `products.quantity`
 * is kept as a maintained cache of the total across all warehouses, so the many
 * existing list filters, low-stock scopes and report aggregates keep working off
 * a single indexed column instead of a subquery on every row.
 *
 * The invariant, enforced by InventoryService: for any product,
 *   products.quantity === SUM(product_warehouse.quantity)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            // One stock row per product per warehouse.
            $table->unique(['product_id', 'warehouse_id']);

            // Serves "what is in this warehouse" and low-stock-per-location.
            $table->index(['warehouse_id', 'quantity']);
        });

        $this->seedExistingStockIntoDefaultWarehouse();
    }

    /**
     * Move whatever stock already exists into the default warehouse, so the new
     * invariant holds from the first request after this migration.
     */
    private function seedExistingStockIntoDefaultWarehouse(): void
    {
        $warehouseId = DB::table('warehouses')
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('id')
            ?? DB::table('warehouses')->orderBy('id')->value('id');

        if (! $warehouseId) {
            // No warehouses configured yet; nothing to seed into. Stock rows get
            // created lazily by InventoryService once a warehouse exists.
            return;
        }

        $now = now();

        DB::table('products')
            ->select('id', 'quantity')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(500, function ($products) use ($warehouseId, $now) {
                $rows = $products
                    ->map(fn ($p) => [
                        'product_id' => $p->id,
                        'warehouse_id' => $warehouseId,
                        'quantity' => $p->quantity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($rows !== []) {
                    DB::table('product_warehouse')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warehouse');
    }
};
