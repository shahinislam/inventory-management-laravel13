<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A settled trading period. The report computes live figures for any
        // date range; closing one freezes those numbers here so that later
        // edits to the underlying invoices cannot change what was settled.
        Schema::create('partner_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('total_purchases', 14, 2)->default(0);
            $table->decimal('courier_margin', 14, 2)->default(0);
            $table->decimal('profit', 14, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
        });

        Schema::create('partner_period_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_period_id')->constrained('partner_periods')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            // Snapshotted, because a partner's share may be changed afterwards.
            $table->decimal('share_percentage', 5, 2)->default(0);
            $table->decimal('profit_share', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['partner_period_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_period_shares');
        Schema::dropIfExists('partner_periods');
    }
};
