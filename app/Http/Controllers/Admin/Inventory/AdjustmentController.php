<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventorySiteContext;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-adjustment-post')->only(['index', 'show']);
        $this->middleware('permission:inventory-adjustment-post')->only(['create', 'store']);
    }

    public function index(Request $request, InventorySiteContext $site)
    {
        $adjustments = InventoryAdjustment::query()
            ->with(['location', 'creator', 'approver'])
            ->withCount('items')
            ->when($request->filled('search'), fn ($q) => $q->where('adjustment_no', 'like', '%' . trim((string) $request->search) . '%'))
            ->orderByDesc('posted_at')->orderByDesc('id')
            ->paginate(20)->appends($request->query());

        return view('admin.inventory.adjustments.index', compact('adjustments'));
    }

    public function create(InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $locations = StockLocation::query()
            ->where('is_active', true)
            ->whereIn('type', [StockLocation::TYPE_MAIN, StockLocation::TYPE_KITCHEN])
            ->orderBy('type')->get();
        $ingredients = Ingredient::query()->active()->where('track_inventory', true)
            ->with('baseUnit')->orderBy('name')->get();

        $balances = InventoryBalance::query()
            ->whereIn('stock_location_id', $locations->pluck('id'))->get();
        $systemBalances = [];
        foreach ($balances as $balance) {
            $systemBalances[(int) $balance->stock_location_id][(int) $balance->ingredient_id] = (string) $balance->quantity_base;
        }

        return view('admin.inventory.adjustments.form', compact('locations', 'ingredients', 'systemBalances'));
    }

    public function store(Request $request, InventorySiteContext $site, InventoryAdjustmentService $service)
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'reason' => ['required', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.physical_qty_base' => ['required', 'numeric', 'gte:0'],
        ]);

        $site->ensureDefaultLocations();
        $adjustment = $service->postPhysicalCount(
            (int) $data['location_id'],
            $data['items'],
            $data['reason'],
            $request->user()?->id
        );

        return redirect()->route('inventory.adjustments.show', $adjustment)
            ->with('success', 'Physical count adjustment posted through corrective ledger movements.');
    }

    public function show(InventoryAdjustment $adjustment, InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $adjustment->load(['location', 'creator', 'approver', 'items.ingredient.baseUnit']);
        $movements = StockMovement::query()
            ->where('reference_type', InventoryAdjustment::class)
            ->where('reference_id', $adjustment->id)
            ->with('items.ingredient.baseUnit')
            ->orderBy('id')->get();

        return view('admin.inventory.adjustments.show', compact('adjustment', 'movements'));
    }
}
