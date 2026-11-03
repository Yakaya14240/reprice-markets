<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_files', function (Blueprint $table) {
            $table->string('source_type', 16)->default('sftp')->after('name');
            $table->string('location')->nullable()->after('source_type');
            $table->string('write_source_type', 16)->nullable()->after('location');
            $table->string('write_location')->nullable()->after('write_source_type');
            $table->json('connection_config')->nullable()->after('write_location');
            $table->string('sku_column')->default('SKU')->after('delimiter');
            $table->string('name_column')->default('Titre')->after('sku_column');
        });

        if (Schema::hasColumn('feed_files', 'remote_path')) {
            \Illuminate\Support\Facades\DB::table('feed_files')->orderBy('id')->each(function ($feed) {
                \Illuminate\Support\Facades\DB::table('feed_files')->where('id', $feed->id)->update([
                    'source_type' => 'sftp',
                    'location' => $feed->remote_path,
                    'write_source_type' => 'sftp',
                    'write_location' => $feed->remote_path,
                ]);
            });

            Schema::table('feed_files', function (Blueprint $table) {
                $table->dropUnique(['remote_path']);
                $table->dropColumn('remote_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('feed_files', function (Blueprint $table) {
            $table->string('remote_path')->nullable()->after('name');
        });

        foreach (\App\Models\FeedFile::query()->get() as $feed) {
            $feed->forceFill([
                'remote_path' => $feed->location,
            ])->save();
        }

        Schema::table('feed_files', function (Blueprint $table) {
            $table->unique('remote_path');
            $table->dropColumn([
                'source_type',
                'location',
                'write_source_type',
                'write_location',
                'connection_config',
                'sku_column',
                'name_column',
            ]);
        });
    }
};
