<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings')
            && !Schema::hasColumn('pos_settings', 'order_list_random_half_enabled')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('order_list_random_half_enabled')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings')
            && Schema::hasColumn('pos_settings', 'order_list_random_half_enabled')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('order_list_random_half_enabled');
            });
        }
    }
};
