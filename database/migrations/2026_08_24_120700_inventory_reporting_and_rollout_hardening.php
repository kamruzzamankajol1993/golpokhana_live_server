<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_locations')) {
            $now = now();
            foreach ([
                ['code' => 'MAIN', 'name' => 'Main Stock', 'type' => 'MAIN'],
                ['code' => 'KITCHEN', 'name' => 'Kitchen Stock', 'type' => 'KITCHEN'],
            ] as $location) {
                DB::table('stock_locations')->updateOrInsert(
                    ['code' => $location['code']],
                    $location + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(['status', 'occurred_at'], 'stock_movements_status_time_idx');
            });
        }
        if (Schema::hasTable('inventory_balances')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->index('quantity_base', 'inventory_balances_quantity_idx');
            });
        }
        if (Schema::hasTable('kitchen_requests')) {
            Schema::table('kitchen_requests', function (Blueprint $table) {
                $table->index('request_date', 'kitchen_requests_date_idx');
            });
        }
        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->index(['status', 'posted_at'], 'stock_transfers_status_posted_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfers')) Schema::table('stock_transfers', fn (Blueprint $t) => $t->dropIndex('stock_transfers_status_posted_idx'));
        if (Schema::hasTable('kitchen_requests')) Schema::table('kitchen_requests', fn (Blueprint $t) => $t->dropIndex('kitchen_requests_date_idx'));
        if (Schema::hasTable('inventory_balances')) Schema::table('inventory_balances', fn (Blueprint $t) => $t->dropIndex('inventory_balances_quantity_idx'));
        if (Schema::hasTable('stock_movements')) Schema::table('stock_movements', fn (Blueprint $t) => $t->dropIndex('stock_movements_status_time_idx'));
    }
};
