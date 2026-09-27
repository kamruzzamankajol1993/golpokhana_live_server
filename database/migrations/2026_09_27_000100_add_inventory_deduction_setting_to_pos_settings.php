<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_settings') && !Schema::hasColumn('pos_settings', 'deduct_inventory_on_order_complete')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->boolean('deduct_inventory_on_order_complete')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_settings') && Schema::hasColumn('pos_settings', 'deduct_inventory_on_order_complete')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('deduct_inventory_on_order_complete');
            });
        }
    }
};
