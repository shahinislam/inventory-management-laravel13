<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money leaving (or, for cash_in, entering) the business outside a sale or
     * purchase. Only type 'expense' is a cost in the profit & loss; cash_in and
     * cash_out are drawer movements during a shift (float top-up, bank drop).
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 100)->unique();
            $table->enum('type', ['expense', 'cash_in', 'cash_out'])->default('expense')->index();
            $table->string('category', 100)->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date')->index();
            $table->enum('method', ['cash', 'card', 'bank_transfer', 'mobile_banking', 'cheque', 'other'])->default('cash');
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            // Set when the cash came out of (or went into) a till during a shift.
            $table->foreignId('shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $table->string('paid_to', 150)->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'expense_date']);
            $table->index(['shift_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
