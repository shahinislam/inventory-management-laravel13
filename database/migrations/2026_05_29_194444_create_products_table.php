<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('min_stock_level', 12, 3)->default(0);
            $table->string('unit', 50)->default('pcs');
            // Bought in a bigger unit, e.g. carton of 24. Stock is always kept in `unit`.
            $table->string('purchase_unit', 50)->nullable();
            $table->decimal('purchase_unit_factor', 12, 3)->default(1);
            // Require batch / expiry on receipt and sell earliest expiry first.
            $table->boolean('track_expiry')->default(false);
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('dimensions', 100)->nullable();
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('sku');
            $table->index('barcode');
            $table->index('category_id');
            $table->index('supplier_id');
            $table->index('quantity');
            $table->index('selling_price');
            $table->index(['status', 'category_id']);
            $table->index(['quantity', 'min_stock_level']);
            $table->index(['category_id', 'status', 'selling_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
