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
use Illuminate\Validation\ValidationException;

class NegativeStockAdjustmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-negative-stock-adjust');
    }

    public function index(Request $request, InventorySiteContext $site, StockLocationService $locations)
    {
        $this->assertAuthorizedRole($request);
        $site->ensureDefaultLocations();
        $kitchen = $locations->forType(StockLocation::TYPE_KITCHEN);

        $negativeStocks = InventoryBalance::query()
            ->where('stock_location_id', $kitchen->id)
            ->where('quantity_base', '<', 0)
            ->with(['ingredient.baseUnit'])
            ->orderBy('quantity_base')
            ->get();

        return view('admin.inventory.negative_stock_adjustments.index', compact('negativeStocks', 'kitchen'));
    }

    public function store(
        Request $request,
        InventorySiteContext $site,
        StockLocationService $locations,
        InventoryAdjustmentService $service
    ) {
        $this->assertAuthorizedRole($request);

        $data = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'adjust_quantity_base' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);

        $site->ensureDefaultLocations();
        $kitchen = $locations->forType(StockLocation::TYPE_KITCHEN);

        $service->postNegativeKitchenRecovery(
            (int) $kitchen->id,
            (int) $data['ingredient_id'],
            (string) $data['adjust_quantity_base'],
            (string) $data['reason'],
            $request->user()?->id
        );

        return redirect()->route('inventory.negative-stock-adjustments.index')
            ->with('success', 'Negative Kitchen Stock adjustment posted. Inventory Audit keeps the full user/time/reason history.');
    }

    private function assertAuthorizedRole(Request $request): void
    {
        $user = $request->user();
        if (!$user || (!$user->isInventoryManager() && !$user->hasRole('Super Admin'))) {
            throw ValidationException::withMessages([
                'authorization' => 'Negative Kitchen Stock may be adjusted only by an Inventory Manager or Super Admin.',
            ]);
        }
    }
}
