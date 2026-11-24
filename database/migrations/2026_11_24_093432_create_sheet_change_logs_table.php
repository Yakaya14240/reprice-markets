<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_file_id')->constrained('feed_files')->cascadeOnDelete();
            $table->string('sku');
            $table->foreignId('product_sheet_field_id')->nullable()->constrained('product_sheet_fields')->nullOnDelete();
            $table->string('csv_column');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index(['feed_file_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_change_logs');
    }
};
