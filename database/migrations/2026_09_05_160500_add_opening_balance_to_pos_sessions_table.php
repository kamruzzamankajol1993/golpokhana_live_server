<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_sessions') || Schema::hasColumn('pos_sessions', 'opening_balance')) {
            return;
        }

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->decimal('opening_balance', 12, 2)->default(0)->after('status');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pos_sessions') || !Schema::hasColumn('pos_sessions', 'opening_balance')) {
            return;
        }

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
