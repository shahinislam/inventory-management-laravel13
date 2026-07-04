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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->enum('group', [
                'general',
                'company',
                'invoice',
                'pos',
                'notification',
                'currency',
                'tax',
                'email',
            ])->default('general')->index();
            $table->enum('type', [
                'text',
                'number',
                'boolean',
                'json',
                'image',
            ])->default('text');
            $table->boolean('is_public')->default(false)->index();
            $table->timestamps();

            // Indexes
            $table->index('key');
            $table->index(['group', 'is_public']);
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
