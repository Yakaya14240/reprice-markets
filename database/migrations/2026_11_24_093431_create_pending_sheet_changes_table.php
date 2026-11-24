<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_sheet_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_file_id')->constrained('feed_files')->cascadeOnDelete();
            $table->string('sku');
            $table->foreignId('product_sheet_field_id')->constrained('product_sheet_fields')->cascadeOnDelete();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'feed_file_id', 'sku', 'product_sheet_field_id'],
                'pending_sheet_user_feed_sku_field_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_sheet_changes');
    }
};
