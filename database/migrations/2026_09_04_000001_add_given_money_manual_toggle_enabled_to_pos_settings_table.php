<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings') && !Schema::hasColumn('pos_settings', 'given_money_manual_toggle_enabled')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('given_money_manual_toggle_enabled')
                    ->default(true)
                    ->after('show_out_of_stock')
                    ->comment('Show the POS Given Money Auto/Reset button and start Given Money at zero.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'given_money_manual_toggle_enabled')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('given_money_manual_toggle_enabled');
            });
        }
    }
};
