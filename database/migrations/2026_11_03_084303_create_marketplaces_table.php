<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver'); // mirakl | kaufland
            $table->string('api_url');
            $table->text('api_key')->nullable(); // encrypted via cast
            $table->text('client_key')->nullable(); // kaufland
            $table->text('client_secret')->nullable(); // kaufland
            $table->json('shop_ids')->nullable();
            $table->boolean('supports_channels')->default(false);
            $table->boolean('supports_multi_shop')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplaces');
    }
};
