<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_columns', function (Blueprint $table) {
            $table->boolean('is_public_price')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('price_columns', function (Blueprint $table) {
            $table->dropColumn('is_public_price');
        });
    }
};
