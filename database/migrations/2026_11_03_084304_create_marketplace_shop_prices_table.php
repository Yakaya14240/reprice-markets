<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_shop_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->string('shop_id');
            $table->foreignId('price_column_id')->constrained('price_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketplace_id', 'shop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_shop_prices');
    }
};
