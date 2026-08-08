<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings')
            && !Schema::hasColumn('pos_settings', 'random_order_hide_percentage')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('random_order_hide_percentage')
                    ->default(50)
                    ->after('order_list_random_half_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings')
            && Schema::hasColumn('pos_settings', 'random_order_hide_percentage')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('random_order_hide_percentage');
            });
        }
    }
};
