<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name', 200);
            $table->string('product_sku', 100);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            // A free member reward: billed at zero, still taken out of stock.
            $table->boolean('is_gift')->default(false);
            // Cost per unit when sold, for profit reporting.
            $table->decimal('unit_cost', 12, 2)->default(0);
            // On a return invoice: the sold line coming back, and whether it went back on the shelf.
            $table->foreignId('parent_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->boolean('restock')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('product_sku');
            $table->index(['invoice_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
