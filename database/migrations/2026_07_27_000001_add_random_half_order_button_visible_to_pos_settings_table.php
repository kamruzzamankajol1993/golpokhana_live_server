<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings')
            && !Schema::hasColumn('pos_settings', 'random_half_order_button_visible')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('random_half_order_button_visible')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings')
            && Schema::hasColumn('pos_settings', 'random_half_order_button_visible')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('random_half_order_button_visible');
            });
        }
    }
};
