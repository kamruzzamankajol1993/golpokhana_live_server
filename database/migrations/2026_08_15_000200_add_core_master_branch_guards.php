<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tables')) {
            return;
        }

        if (!Schema::hasColumn('tables', 'qr_token')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->string('qr_token', 64)->nullable()->after('table_number');
            });

            DB::table('tables')->select('id')->orderBy('id')->chunkById(250, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('tables')->where('id', $row->id)->update([
                        'qr_token' => (string) Str::uuid(),
                    ]);
                }
            });

            Schema::table('tables', function (Blueprint $table) {
                $table->unique('qr_token', 'tables_qr_token_unique');
            });
        }

        if (Schema::hasColumn('tables', 'branch_id') && Schema::hasColumn('tables', 'table_number')) {
            $duplicate = DB::table('tables')
                ->select('branch_id', 'table_number', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('branch_id', 'table_number')
                ->havingRaw('COUNT(*) > 1')
                ->first();

            if ($duplicate) {
                throw new RuntimeException(
                    'Cannot add branch-wise table number uniqueness. Duplicate table number exists inside branch_id ' .
                    ($duplicate->branch_id ?? 'NULL') . ': ' . $duplicate->table_number
                );
            }

            Schema::table('tables', function (Blueprint $table) {
                $table->unique(['branch_id', 'table_number'], 'tables_branch_table_number_unique');
                $table->index(['branch_id', 'zone_id'], 'tables_branch_zone_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tables')) {
            return;
        }

        if (Schema::hasColumn('tables', 'branch_id') && Schema::hasColumn('tables', 'table_number')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->dropUnique('tables_branch_table_number_unique');
                $table->dropIndex('tables_branch_zone_idx');
            });
        }

        if (Schema::hasColumn('tables', 'qr_token')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->dropUnique('tables_qr_token_unique');
                $table->dropColumn('qr_token');
            });
        }
    }
};
