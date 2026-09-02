<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branch_mode_settings')) {
            return;
        }

        $existing = DB::table('branch_mode_settings')->where('id', 1)->first();

        if ($existing) {
            DB::table('branch_mode_settings')
                ->where('id', 1)
                ->update([
                    'mode' => 'multiple',
                    'multi_branch_activated_at' => $existing->multi_branch_activated_at ?: now(),
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('branch_mode_settings')->insert([
            'id' => 1,
            'mode' => 'multiple',
            'multi_branch_activated_at' => now(),
            'multi_branch_locked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Deliberately do not force Multiple -> Single during rollback.
        // A live installation may already contain secondary-branch business data,
        // where reverting the mode would violate the product's irreversible lock rule.
    }
};
