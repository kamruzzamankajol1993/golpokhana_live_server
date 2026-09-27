<?php

namespace App\Services\Inventory;

use App\Models\StockLocation;

class StockLocationService
{
    public function ensureDefaultLocations(mixed $legacyContext = null): array
    {
        $locations = [];
        foreach ([
            StockLocation::TYPE_MAIN => 'Main Stock',
            StockLocation::TYPE_KITCHEN => 'Kitchen Stock',
        ] as $code => $name) {
            $locations[$code] = StockLocation::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $code, 'is_active' => true]
            );
        }
        return $locations;
    }

    public function forType(string $type): StockLocation
    {
        $this->ensureDefaultLocations();
        return StockLocation::query()->where('type', $type)->where('is_active', true)->firstOrFail();
    }
}
