<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('remote_path');
            $table->string('delimiter', 8)->default("\t");
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('remote_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_files');
    }
};
