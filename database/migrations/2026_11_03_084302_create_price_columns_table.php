<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_file_id')->constrained('feed_files')->cascadeOnDelete();
            $table->string('name');
            $table->string('csv_column');
            $table->string('alias')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['feed_file_id', 'name']);
            $table->unique(['feed_file_id', 'csv_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_columns');
    }
};
