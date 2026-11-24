<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sheet_fields', function (Blueprint $table) {
            $table->foreignId('product_sheet_group_id')
                ->nullable()
                ->after('label')
                ->constrained('product_sheet_groups')
                ->nullOnDelete();
        });

        if (Schema::hasColumn('product_sheet_fields', 'group_name')) {
            $rows = DB::table('product_sheet_fields')
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->select('id', 'group_name')
                ->get();

            foreach ($rows as $row) {
                $name = trim((string) $row->group_name);
                if ($name === '') {
                    continue;
                }

                $groupId = DB::table('product_sheet_groups')->where('name', $name)->value('id');
                if (! $groupId) {
                    $groupId = DB::table('product_sheet_groups')->insertGetId([
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('product_sheet_fields')
                    ->where('id', $row->id)
                    ->update(['product_sheet_group_id' => $groupId]);
            }

            Schema::table('product_sheet_fields', function (Blueprint $table) {
                $table->dropColumn('group_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_sheet_fields', function (Blueprint $table) {
            $table->string('group_name', 100)->nullable()->after('label');
        });

        $fields = DB::table('product_sheet_fields')
            ->whereNotNull('product_sheet_group_id')
            ->get(['id', 'product_sheet_group_id']);

        foreach ($fields as $field) {
            $name = DB::table('product_sheet_groups')->where('id', $field->product_sheet_group_id)->value('name');
            if ($name) {
                DB::table('product_sheet_fields')
                    ->where('id', $field->id)
                    ->update(['group_name' => $name]);
            }
        }

        Schema::table('product_sheet_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_sheet_group_id');
        });
    }
};
