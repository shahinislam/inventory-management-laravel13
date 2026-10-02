<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock on hand per batch / expiry, so the earliest expiry is sold first.
     *
     * product_warehouse stays the source of truth for how much is on hand; the
     * batches only say which of it expires when. Their sum per product and
     * warehouse never exceeds product_warehouse.quantity; any remainder is
     * untracked stock (no batch or expiry recorded).
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'expiry_date'], 'stock_batches_fefo_index');
            $table->index(['expiry_date', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
