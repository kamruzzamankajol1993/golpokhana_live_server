<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\Inventory\DecimalQuantity;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\UnitConversionService;
use App\Services\Inventory\InventorySiteContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-kitchen-stock-view')->only('index');
        $this->middleware('permission:inventory-adjustment-post')->only('storeOpeningStock');
    }

    public function index(Request $request, InventorySiteContext $site, DecimalQuantity $decimal)
    {
        $site->ensureDefaultLocations();
        $kitchenOnly = (bool) $request->user()?->isKitchenManager();
        $stockType = $kitchenOnly ? StockLocation::TYPE_KITCHEN : StockLocation::TYPE_MAIN;
        $location = StockLocation::query()->where('type', $stockType)->where('is_active', true)->first();

        $balances = InventoryBalance::query()
            ->with(['location', 'ingredient.baseUnit'])
            ->when($location, fn ($q) => $q->where('stock_location_id', $location->id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->whereHas('ingredient', fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->orderBy('ingredient_id')
            ->paginate(30)
            ->appends($request->query());

        $balances->getCollection()->transform(function (InventoryBalance $balance) use ($decimal) {
            $quantity = (string) $balance->quantity_base;
            $low = (string) ($balance->ingredient?->low_stock_level_base ?? '0');
            $balance->setAttribute(
                'inventory_state',
                $decimal->compare($quantity, '0') < 0
                    ? 'NEGATIVE'
                    : ($decimal->compare($quantity, $low) <= 0 ? 'LOW' : 'OK')
            );
            return $balance;
        });

        $ingredients = Ingredient::query()
            ->active()
            ->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')
            ->get();
        $allUnits = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();

        return view('admin.inventory.stock.index', [
            'balances' => $balances,
            'ingredients' => $ingredients,
            'allUnits' => $allUnits,
            'kitchenOnly' => $kitchenOnly,
            'stockLocation' => $location,
        ]);
    }

    public function storeOpeningStock(
        Request $request,
        InventorySiteContext $site,
        UnitConversionService $conversion,
        StockMovementService $movements
    ) {
        $data = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_choice' => ['required', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $site->ensureDefaultLocations();
        $location = StockLocation::query()
            ->where('type', StockLocation::TYPE_MAIN)
            ->where('is_active', true)
            ->first();
        if (!$location) {
            throw ValidationException::withMessages(['ingredient_id' => 'Store stock is not initialized. Run the inventory setup migration first.']);
        }

        $ingredient = Ingredient::query()->with('unitConversions')->findOrFail((int) $data['ingredient_id']);
        $selection = $conversion->resolveChoice($ingredient, (string) $data['unit_choice']);
        $baseQuantity = $conversion->toBase(
            $ingredient,
            (string) $data['quantity'],
            $selection['unit'],
            null,
            $selection['conversion']?->id
        );

        $movements->postOpeningStock(
            (int) $location->id,
            (int) $ingredient->id,
            $baseQuantity,
            $data['reason'] ?? null,
            $request->user()?->id
        );

        return back()->with('success', 'Opening stock added to Store Stock successfully.');
    }
}
