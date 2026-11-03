<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_price_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->string('channel_code'); // FR, IT, ES, DE, NL, PT or Mirakl channel id
            $table->string('channel_name')->nullable();
            $table->foreignId('price_column_id')->constrained('price_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['marketplace_id', 'channel_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_price_mappings');
    }
};
