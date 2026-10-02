<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 100)->unique();
            // 'return' rows are credit notes against parent_invoice_id; they are not sales.
            $table->enum('type', ['sale', 'return'])->default('sale')->index();
            $table->foreignId('parent_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            // A POS sale parked for later: a draft with no stock moved.
            $table->boolean('is_held')->default(false)->index();
            $table->string('held_label', 100)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 200);
            $table->string('customer_email', 150)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->text('customer_address')->nullable();
            $table->enum('status', [
                'draft',
                'sent',
                'paid',
                'partial',
                'overdue',
                'cancelled',
                'returned',
            ])->default('draft')->index();
            $table->enum('payment_method', [
                'cash',
                'card',
                'bank_transfer',
                'mobile_banking',
                'split',
            ])->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            // Member reward discount, kept apart from the manual discount. Part of `total`.
            $table->decimal('membership_discount', 12, 2)->default(0);
            // On a sale: value of the goods brought back since. Net sale = total - returned_amount.
            $table->decimal('returned_amount', 12, 2)->default(0);
            // What the customer is billed for delivery. Part of `total`.
            $table->decimal('courier_charge', 12, 2)->default(0);
            // What the courier actually costs the shop. Internal only. May exceed
            // courier_charge when delivery is free, in which case the shop absorbs
            // the difference.
            $table->decimal('courier_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('invoice_number');
            $table->index('customer_name');
            $table->index('customer_phone');
            $table->index('invoice_date');
            $table->index('due_date');
            $table->index('paid_date');
            $table->index(['status', 'invoice_date']);
            $table->index(['status', 'due_date']);
            $table->index(['created_by', 'status']);
            $table->index(['warehouse_id', 'status']);
            $table->index(['customer_phone', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
