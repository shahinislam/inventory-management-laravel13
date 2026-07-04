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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 100)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('method', [
                'cash',
                'card',
                'bank_transfer',
                'cheque',
                'other'
            ])->index();
            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'refunded'
            ])->default('completed')->index();
            $table->string('reference', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('cheque_number', 100)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('payment_number');
            $table->index('payment_date');
            $table->index('reference');
            $table->index(['invoice_id', 'status']);
            $table->index(['method', 'status']);
            $table->index(['created_by', 'payment_date']);
            $table->index(['payment_date', 'method', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
