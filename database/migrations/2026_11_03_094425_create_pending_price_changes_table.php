<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_file_id')->constrained('feed_files')->cascadeOnDelete();
            $table->string('sku');
            $table->string('csv_column');
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->timestamps();

            $table->unique(['user_id', 'feed_file_id', 'sku', 'csv_column'], 'pending_price_unique');
            $table->index(['feed_file_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_price_changes');
    }
};
