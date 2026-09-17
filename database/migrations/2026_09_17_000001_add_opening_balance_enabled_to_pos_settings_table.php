<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings') && !Schema::hasColumn('pos_settings', 'opening_balance_enabled')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('opening_balance_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'opening_balance_enabled')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('opening_balance_enabled');
            });
        }
    }
};
