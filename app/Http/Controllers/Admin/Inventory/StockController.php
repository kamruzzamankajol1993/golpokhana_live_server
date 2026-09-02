<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireSpecificBranch;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\Inventory\DecimalQuantity;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\UnitConversionService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-kitchen-stock-view')->only('index');
        $this->middleware('permission:inventory-adjustment-post')->only('storeOpeningStock');
        $this->middleware(RequireSpecificBranch::class)->only('storeOpeningStock');
    }

    public function index(Request $request, BranchContext $context, DecimalQuantity $decimal)
    {
        $kitchenOnly = (bool) $request->user()?->isKitchenUser();

        $locations = StockLocation::query()->with('branch')->active()
            ->when($kitchenOnly, fn ($q) => $q->where('type', StockLocation::TYPE_KITCHEN))
            ->orderBy('branch_id')->orderBy('type')->get();
        $allowedLocationIds = $locations->pluck('id');

        $balances = InventoryBalance::query()
            ->with(['branch', 'location', 'ingredient.baseUnit'])
            ->when($kitchenOnly, fn ($q) => $q->whereIn('stock_location_id', $allowedLocationIds))
            ->when(!$kitchenOnly && $request->filled('location_id'), fn ($q) => $q->where('stock_location_id', (int) $request->location_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->whereHas('ingredient', fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->orderBy('branch_id')
            ->orderBy('stock_location_id')
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

        $ingredients = Ingredient::query()->active()->where('track_inventory', true)->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])->orderBy('name')->get();
        $allUnits = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();

        return view('admin.inventory.stock.index', [
            'balances' => $balances,
            'locations' => $locations,
            'ingredients' => $ingredients,
            'allUnits' => $allUnits,
            'specificBranch' => !$context->isAllBranches(),
            'kitchenOnly' => $kitchenOnly,
        ]);
    }

    public function storeOpeningStock(
        Request $request,
        BranchContext $context,
        UnitConversionService $conversion,
        StockMovementService $movements
    ) {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_choice' => ['required', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $branchId = $context->requireSpecificBranch();
        $location = StockLocation::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->whereKey((int) $data['location_id'])
            ->first();
        if (!$location) {
            throw ValidationException::withMessages(['location_id' => 'The stock location does not belong to the selected branch.']);
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
            $branchId,
            (int) $location->id,
            (int) $ingredient->id,
            $baseQuantity,
            $data['reason'] ?? null,
            $request->user()?->id
        );

        return back()->with('success', 'Opening stock posted through the immutable inventory ledger.');
    }
}
