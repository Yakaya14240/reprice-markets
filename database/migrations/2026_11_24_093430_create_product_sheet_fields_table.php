<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sheet_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_file_id')->constrained('feed_files')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->string('type', 32);
            $table->string('label');
            $table->string('csv_column');
            $table->boolean('is_editable')->default(false);
            $table->unsignedInteger('max_length')->nullable();
            $table->string('help_text', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['feed_file_id', 'language_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sheet_fields');
    }
};
