<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_sessions')) {
            return;
        }

        if (!Schema::hasColumn('pos_sessions', 'started_by_user_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('started_by_user_id')->nullable()->after('user_id');
                $table->index('started_by_user_id', 'pos_sessions_started_by_idx');
                $table->foreign('started_by_user_id', 'pos_sessions_started_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('pos_sessions', 'ended_by_user_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('ended_by_user_id')->nullable()->after('started_by_user_id');
                $table->index('ended_by_user_id', 'pos_sessions_ended_by_idx');
                $table->foreign('ended_by_user_id', 'pos_sessions_ended_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        // Before this migration user_id meant the actual starter. Preserve that
        // historical information before new sessions start using user_id as Manager owner.
        DB::table('pos_sessions')
            ->whereNull('started_by_user_id')
            ->update(['started_by_user_id' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('pos_sessions')) {
            return;
        }

        if (Schema::hasColumn('pos_sessions', 'ended_by_user_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropForeign('pos_sessions_ended_by_fk');
                $table->dropIndex('pos_sessions_ended_by_idx');
                $table->dropColumn('ended_by_user_id');
            });
        }

        if (Schema::hasColumn('pos_sessions', 'started_by_user_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropForeign('pos_sessions_started_by_fk');
                $table->dropIndex('pos_sessions_started_by_idx');
                $table->dropColumn('started_by_user_id');
            });
        }
    }
};
