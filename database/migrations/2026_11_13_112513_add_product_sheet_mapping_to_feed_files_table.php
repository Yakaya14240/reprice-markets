<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_files', function (Blueprint $table) {
            $table->string('description_column')->nullable()->after('name_column');
            $table->json('image_columns')->nullable()->after('description_column');
        });
    }

    public function down(): void
    {
        Schema::table('feed_files', function (Blueprint $table) {
            $table->dropColumn(['description_column', 'image_columns']);
        });
    }
};
