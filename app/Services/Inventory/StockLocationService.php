<?php

namespace App\Services\Inventory;

use App\Models\StockLocation;
use App\Models\Scopes\BranchScope;

class StockLocationService
{
    public function ensureDefaultLocations(int $branchId): array
    {
        $locations = [];
        foreach ([
            StockLocation::TYPE_MAIN => 'Main Stock',
            StockLocation::TYPE_KITCHEN => 'Kitchen Stock',
        ] as $code => $name) {
            $locations[$code] = StockLocation::query()
                ->withoutGlobalScope(BranchScope::class)
                ->updateOrCreate(
                    ['branch_id' => $branchId, 'code' => $code],
                    ['name' => $name, 'type' => $code, 'is_active' => true]
                );
        }

        return $locations;
    }

    public function forBranchAndType(int $branchId, string $type): StockLocation
    {
        $this->ensureDefaultLocations($branchId);

        return StockLocation::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('type', $type)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
