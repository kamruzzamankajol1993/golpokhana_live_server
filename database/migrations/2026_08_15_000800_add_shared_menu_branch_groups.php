<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [
        'food_categories',
        'cuisine_types',
        'allergens',
        'course_types',
        'food_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'shared_group_uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('shared_group_uuid')->nullable()->after('branch_id');
                });
            }

            DB::table($tableName)
                ->whereNull('shared_group_uuid')
                ->select('id')
                ->orderBy('id')
                ->chunkById(250, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        DB::table($tableName)->where('id', $row->id)->update([
                            'shared_group_uuid' => (string) Str::uuid(),
                        ]);
                    }
                });

            if (!$this->indexExists($tableName, $tableName . '_shared_group_branch_unique')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->unique(['shared_group_uuid', 'branch_id'], $tableName . '_shared_group_branch_unique');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'shared_group_uuid')) {
                continue;
            }

            if ($this->indexExists($tableName, $tableName . '_shared_group_branch_unique')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropUnique($tableName . '_shared_group_branch_unique');
                });
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('shared_group_uuid');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
