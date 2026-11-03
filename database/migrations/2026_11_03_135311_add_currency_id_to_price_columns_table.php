<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_columns', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->nullable()
                ->after('alias')
                ->constrained('currencies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_columns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
