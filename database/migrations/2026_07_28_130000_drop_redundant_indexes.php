<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops indexes that MySQL can never choose, because another index already
 * covers the same lookups.
 *
 * Two cases only, both provable from the index list rather than guesswork:
 *
 *   1. A single-column index on a column that already has a UNIQUE index.
 *   2. A composite index that is an exact left-prefix of a wider composite —
 *      (a, b) is redundant when (a, b, c) exists, since MySQL can use any
 *      leftmost prefix of a composite index.
 *
 * Every dropped index costs write throughput and buffer-pool space while
 * contributing nothing to reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Duplicates of the unique indexes on the same columns.
            $table->dropIndex('products_sku_index');
            $table->dropIndex('products_barcode_index');

            // products_category_id_index is also a left-prefix of a wider
            // composite, but a foreign key depends on it — MySQL needs an index
            // on the FK column, so it stays.
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Duplicate of invoices_invoice_number_unique.
            $table->dropIndex('invoices_invoice_number_index');

            // Left-prefix of invoices_customer_phone_status_index.
            $table->dropIndex('invoices_customer_phone_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            // Left-prefix of stock_movements_product_id_warehouse_id_type_index.
            // Safe despite the FK on product_id: the wider composite starts with
            // the same column, so the foreign key remains indexed.
            $table->dropIndex('stock_movements_product_id_warehouse_id_index');

            // Left-prefix of stock_movements_expiry_date_product_id_index.
            $table->dropIndex('stock_movements_expiry_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('sku');
            $table->index('barcode');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('invoice_number');
            $table->index('customer_phone');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'warehouse_id'], 'stock_movements_product_id_warehouse_id_index');
            $table->index('expiry_date');
        });
    }
};
