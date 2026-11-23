<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('channel_price_mappings');
        Schema::dropIfExists('marketplace_shop_prices');

        Schema::table('marketplaces', function (Blueprint $table) {
            $table->dropColumn(['supports_channels', 'supports_multi_shop']);
        });
    }

    public function down(): void
    {
        Schema::table('marketplaces', function (Blueprint $table) {
            $table->boolean('supports_channels')->default(false)->after('shop_ids');
            $table->boolean('supports_multi_shop')->default(false)->after('supports_channels');
        });

        Schema::create('marketplace_shop_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->string('shop_id');
            $table->foreignId('price_column_id')->constrained('price_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketplace_id', 'shop_id']);
        });

        Schema::create('channel_price_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->string('channel_code');
            $table->string('channel_name')->nullable();
            $table->foreignId('price_column_id')->constrained('price_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketplace_id', 'channel_code']);
        });
    }
};
