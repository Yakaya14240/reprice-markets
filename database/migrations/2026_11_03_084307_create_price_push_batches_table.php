<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_push_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->foreignId('feed_file_id')->nullable()->constrained('feed_files')->nullOnDelete();
            $table->string('status')->default('pending'); // pending|queued|running|succeeded|failed|blocked
            $table->unsignedInteger('offers_count')->default(0);
            $table->json('payload')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['marketplace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_push_batches');
    }
};
