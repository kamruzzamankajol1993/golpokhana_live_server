<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 10 rollout safety: every existing branch must have its own physical
        // MAIN and KITCHEN locations before stock posting is enabled.
        if (Schema::hasTable('branches') && Schema::hasTable('stock_locations')) {
            $now = now();
            foreach (DB::table('branches')->pluck('id') as $branchId) {
                foreach ([
                    ['code' => 'MAIN', 'name' => 'Main Stock', 'type' => 'MAIN'],
                    ['code' => 'KITCHEN', 'name' => 'Kitchen Stock', 'type' => 'KITCHEN'],
                ] as $location) {
                    $existing = DB::table('stock_locations')
                        ->where('branch_id', (int) $branchId)
                        ->where('code', $location['code'])
                        ->first();

                    if ($existing) {
                        DB::table('stock_locations')->where('id', $existing->id)->update([
                            'name' => $location['name'],
                            'type' => $location['type'],
                            'is_active' => 1,
                            'updated_at' => $now,
                        ]);
                    } else {
                        DB::table('stock_locations')->insert($location + [
                            'branch_id' => (int) $branchId,
                            'is_active' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }

        // Reporting indexes are additive only; no old migration/table is renamed.
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(['branch_id', 'status', 'occurred_at'], 'stock_movements_branch_status_time_idx');
            });
        }

        if (Schema::hasTable('inventory_balances')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->index(['branch_id', 'quantity_base'], 'inventory_balances_branch_quantity_idx');
            });
        }

        if (Schema::hasTable('kitchen_requests')) {
            Schema::table('kitchen_requests', function (Blueprint $table) {
                $table->index(['branch_id', 'request_date'], 'kitchen_requests_branch_date_idx');
            });
        }

        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->index(['branch_id', 'status', 'posted_at'], 'stock_transfers_branch_status_posted_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->dropIndex('stock_transfers_branch_status_posted_idx');
            });
        }
        if (Schema::hasTable('kitchen_requests')) {
            Schema::table('kitchen_requests', function (Blueprint $table) {
                $table->dropIndex('kitchen_requests_branch_date_idx');
            });
        }
        if (Schema::hasTable('inventory_balances')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->dropIndex('inventory_balances_branch_quantity_idx');
            });
        }
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropIndex('stock_movements_branch_status_time_idx');
            });
        }
    }
};
