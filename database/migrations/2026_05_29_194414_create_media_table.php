<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_url');
            $table->string('mime_type', 100);
            $table->enum('file_type', ['image', 'document', 'video', 'other'])->index();
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('file_name');
            $table->index('mime_type');
            $table->index('created_at');
            $table->index(['file_type', 'created_at']);
            $table->index(['uploaded_by', 'file_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
