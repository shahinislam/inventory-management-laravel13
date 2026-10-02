<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every reward actually handed to a member. Also what stops a cumulative
        // reward from being offered to the same member twice.
        Schema::create('membership_reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('membership_reward_id')->nullable()->constrained('membership_rewards')->nullOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Snapshots, so the record still reads correctly if the rule changes.
            $table->string('reward_name', 150);
            $table->string('customer_phone', 20);
            $table->enum('reward_type', ['percent', 'fixed', 'gift']);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->foreignId('gift_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('gift_quantity')->default(0);
            $table->timestamps();

            // Explicit names: the generated ones exceed MySQL's 64-character limit.
            $table->index(['customer_id', 'membership_reward_id'], 'mrr_customer_reward_index');
            $table->index('customer_phone', 'mrr_customer_phone_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_reward_redemptions');
    }
};
