<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_file_id')->constrained('feed_files')->cascadeOnDelete();
            $table->string('status')->default('idle');
            $table->unsignedInteger('products_count')->default(0);
            $table->string('storage_path')->nullable();
            $table->json('headers')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_label')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->timestamps();

            $table->unique('feed_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_snapshots');
    }
};
