<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_files', function (Blueprint $table) {
            $table->string('price_decimal_separator', 1)->default('.')->after('delimiter');
        });
    }

    public function down(): void
    {
        Schema::table('feed_files', function (Blueprint $table) {
            $table->dropColumn('price_decimal_separator');
        });
    }
};
