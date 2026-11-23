<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_price_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->string('shop_id');
            // Empty string = shop-only mapping (no Mirakl channel).
            $table->string('channel_code')->default('');
            $table->string('channel_name')->nullable();
            $table->foreignId('price_column_id')->constrained('price_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketplace_id', 'shop_id', 'channel_code'], 'mkp_price_map_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_price_mappings');
    }
};
