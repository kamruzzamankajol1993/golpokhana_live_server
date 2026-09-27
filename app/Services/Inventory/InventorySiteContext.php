<?php

namespace App\Services\Inventory;

use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Single-site inventory context for this branchless inventory build. */
class InventorySiteContext
{
    public function ensureDefaultLocations(): void
    {
        if (!Schema::hasTable('stock_locations')) {
            return;
        }

        $now = now();
        foreach ([
            ['code' => 'MAIN', 'name' => 'Main Stock', 'type' => StockLocation::TYPE_MAIN],
            ['code' => 'KITCHEN', 'name' => 'Kitchen Stock', 'type' => StockLocation::TYPE_KITCHEN],
        ] as $location) {
            DB::table('stock_locations')->updateOrInsert(
                ['code' => $location['code']],
                $location + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
