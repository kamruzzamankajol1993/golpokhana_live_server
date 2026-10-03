<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\StockLocation;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventorySiteContext;
use App\Services\Inventory\StockLocationService;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-adjustment-post')->only(['create', 'store']);
    }

    public function create(InventorySiteContext $site, StockLocationService $locations)
    {
        $site->ensureDefaultLocations();
        $storeLocation = $locations->forType(StockLocation::TYPE_MAIN);

        $ingredients = Ingredient::query()
            ->active()
            ->where('track_inventory', true)
            ->with('baseUnit')
            ->orderBy('name')
            ->get();

        $balances = InventoryBalance::query()
            ->where('stock_location_id', $storeLocation->id)
            ->get()
            ->keyBy('ingredient_id');

        $systemBalances = [];
        foreach ($ingredients as $ingredient) {
            $systemBalances[(int) $ingredient->id] = (string) ($balances->get($ingredient->id)?->quantity_base ?? '0.00000000');
        }

        return view('admin.inventory.adjustments.form', compact('ingredients', 'systemBalances'));
    }

    public function store(
        Request $request,
        InventorySiteContext $site,
        StockLocationService $locations,
        InventoryAdjustmentService $service
    ) {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.physical_qty_base' => ['required', 'numeric', 'gte:0'],
        ]);

        $site->ensureDefaultLocations();
        $storeLocation = $locations->forType(StockLocation::TYPE_MAIN);

        $service->postPhysicalCount(
            (int) $storeLocation->id,
            $data['items'],
            $data['reason'],
            $request->user()?->id
        );

        return redirect()->route('inventory.stock.index')
            ->with('success', 'Store stock physical count adjustment posted successfully. Transaction History keeps the full audit trail.');
    }
}
