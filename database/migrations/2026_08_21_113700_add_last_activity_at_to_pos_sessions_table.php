<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pos_sessions', 'last_activity_at')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dateTime('last_activity_at')->nullable()->after('start_time');
            });
        }

        // Existing open sessions need a sensible timeout baseline.
        DB::table('pos_sessions')
            ->whereNull('last_activity_at')
            ->update(['last_activity_at' => DB::raw('COALESCE(updated_at, start_time)')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_sessions', 'last_activity_at')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropColumn('last_activity_at');
            });
        }
    }
};
