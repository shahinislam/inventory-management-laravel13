<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            // Optional link to a login, so a partner may also be a system user.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 200);
            $table->string('email', 200)->nullable();
            $table->string('phone', 20)->nullable();
            // Ownership share. Active partners must sum to 100 — enforced in
            // PartnerForm rather than in the schema.
            $table->decimal('share_percentage', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
