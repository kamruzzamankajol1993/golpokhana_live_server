<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireSpecificBranch;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\InventoryWastage;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\InventoryWastageService;
use App\Services\Inventory\StockLocationService;
use App\Support\BranchContext;
use Illuminate\Http\Request;

class WastageController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-wastage-post')->only(['index', 'show']);
        $this->middleware('permission:inventory-wastage-post')->only(['create', 'store']);
        $this->middleware(RequireSpecificBranch::class)->only(['create', 'store']);
    }

    public function index(Request $request)
    {
        $kitchenOnly = (bool) $request->user()?->isKitchenUser();
        $wastages = InventoryWastage::query()
            ->when($kitchenOnly, fn ($q) => $q->where('created_by', $request->user()->id)->whereHas('location', fn ($l) => $l->where('type', StockLocation::TYPE_KITCHEN)))
            ->with(['branch', 'location', 'creator'])
            ->withCount('items')
            ->when($request->filled('search'), fn ($q) => $q->where('wastage_no', 'like', '%' . trim((string) $request->search) . '%'))
            ->when($request->filled('reason_code'), fn ($q) => $q->where('reason_code', $request->reason_code))
            ->orderByDesc('posted_at')->orderByDesc('id')
            ->paginate(20)->appends($request->query());

        return view('admin.inventory.wastages.index', [
            'wastages' => $wastages,
            'reasons' => InventoryWastage::reasons(),
        ]);
    }

    public function create(BranchContext $context)
    {
        $branchId = $context->requireSpecificBranch();
        $locations = StockLocation::query()->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)->where('is_active', true)
            ->when($context->user()?->isKitchenUser(), fn ($q) => $q->where('type', StockLocation::TYPE_KITCHEN))
            ->when(!$context->user()?->isKitchenUser(), fn ($q) => $q->whereIn('type', [StockLocation::TYPE_MAIN, StockLocation::TYPE_KITCHEN]))
            ->orderBy('type')->get();
        $ingredients = Ingredient::query()->active()->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')->get();
        $units = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();

        $balances = InventoryBalance::query()->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->whereIn('stock_location_id', $locations->pluck('id'))
            ->get();
        $available = [];
        foreach ($balances as $balance) {
            $available[(int) $balance->stock_location_id][(int) $balance->ingredient_id] = (string) $balance->quantity_base;
        }

        return view('admin.inventory.wastages.form', [
            'locations' => $locations,
            'ingredients' => $ingredients,
            'units' => $units,
            'available' => $available,
            'reasons' => InventoryWastage::reasons(),
        ]);
    }

    public function store(Request $request, BranchContext $context, InventoryWastageService $service)
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'reason_code' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_choice' => ['required', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);

        $branchId = $context->requireSpecificBranch();
        if ($request->user()?->isKitchenUser()) {
            $kitchen = app(StockLocationService::class)->forBranchAndType($branchId, StockLocation::TYPE_KITCHEN);
            $data['location_id'] = (int) $kitchen->id;
        }

        $wastage = $service->post(
            $branchId,
            (int) $data['location_id'],
            strtoupper((string) $data['reason_code']),
            $data['items'],
            $data['notes'] ?? null,
            $request->user()?->id
        );

        return redirect()->route('inventory.wastages.show', $wastage)
            ->with('success', 'Wastage posted and stock reduced through the immutable ledger.');
    }

    public function show(InventoryWastage $wastage)
    {
        $wastage->load('location');
        if (request()->user()?->isKitchenUser()) {
            abort_unless($wastage->location?->type === StockLocation::TYPE_KITCHEN && (int) $wastage->created_by === (int) request()->user()->id, 403, 'Kitchen users can view only their own Kitchen wastage records.');
        }

        $wastage->load(['branch', 'location', 'creator', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion']);
        $movement = StockMovement::query()
            ->where('reference_type', InventoryWastage::class)
            ->where('reference_id', $wastage->id)
            ->where('movement_type', StockMovement::WASTAGE)
            ->with('items.ingredient.baseUnit')
            ->first();

        return view('admin.inventory.wastages.show', compact('wastage', 'movement'));
    }
}
