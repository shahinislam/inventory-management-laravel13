<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', [
                'purchase',
                'sale',
                'return',
                'adjustment',
                'transfer_in',
                'transfer_out',
                'damaged',
                'expired',
                'purchase_return',
            ])->index();
            $table->decimal('quantity', 12, 3);
            $table->decimal('before_quantity', 12, 3);
            $table->decimal('after_quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('created_at');
            $table->index('batch_number');
            $table->index('expiry_date');
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['product_id', 'type']);
            $table->index(['warehouse_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['product_id', 'warehouse_id', 'type']);
            $table->index(['product_id', 'created_at']);
            $table->index(['expiry_date', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
