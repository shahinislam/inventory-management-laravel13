<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Owner-defined reward rules for member customers. The POS suggests the
        // rules a member qualifies for; the cashier decides whether to give them.
        Schema::create('membership_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);

            // cumulative: the member's lifetime spend crosses min_amount (given once).
            // single_invoice: this bill falls within [min_amount, max_amount].
            $table->enum('basis', ['cumulative', 'single_invoice'])->index();
            $table->decimal('min_amount', 12, 2);
            $table->decimal('max_amount', 12, 2)->nullable();

            $table->enum('reward_type', ['percent', 'fixed', 'gift']);
            $table->decimal('value', 12, 2)->default(0);
            $table->foreignId('gift_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('gift_quantity')->default(1);

            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['basis', 'is_active', 'min_amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_rewards');
    }
};
