<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireSpecificBranch;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\Inventory\StockLocationService;
use App\Services\Inventory\StockTransferService;
use App\Support\BranchContext;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-transfer-post|inventory-return-post')->only(['index', 'show']);
        $this->middleware('permission:inventory-transfer-post')->only(['create', 'store']);
        $this->middleware('permission:inventory-return-post')->only(['returnCreate', 'returnStore']);
        $this->middleware(RequireSpecificBranch::class)->only(['store', 'returnCreate', 'returnStore']);
    }

    public function index(Request $request)
    {
        $kitchenOnly = (bool) $request->user()?->isKitchenUser();
        $transfers = StockTransfer::query()
            ->when($kitchenOnly, fn ($q) => $q->where('direction', StockTransfer::DIRECTION_KITCHEN_TO_MAIN)->where('created_by', $request->user()->id))
            ->with(['branch', 'kitchenRequest', 'sourceLocation', 'destinationLocation', 'poster'])
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(function ($sub) use ($search) {
                    $sub->where('transfer_no', 'like', $search)
                        ->orWhereHas('kitchenRequest', fn ($q) => $q->where('request_no', 'like', $search));
                });
            })
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->direction))
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.inventory.transfers.index', compact('transfers'));
    }

    public function create(BranchContext $context)
    {
        $ingredients = Ingredient::query()
            ->active()->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')->get();
        $units = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();
        $branches = $context->user()?->isSuperAdmin()
            ? Branch::query()->active()->orderByDesc('is_main')->orderBy('name')->get()
            : collect();

        $balanceQuery = InventoryBalance::query()
            ->withoutGlobalScope(BranchScope::class)
            ->join('stock_locations', 'stock_locations.id', '=', 'inventory_balances.stock_location_id');
        if (!$context->user()?->isSuperAdmin() && $context->branchId()) {
            $balanceQuery->where('inventory_balances.branch_id', $context->branchId());
        }
        $balanceRows = $balanceQuery
            ->where('stock_locations.type', StockLocation::TYPE_MAIN)
            ->select('inventory_balances.branch_id', 'inventory_balances.ingredient_id', 'inventory_balances.quantity_base')
            ->get();
        $availableByBranchIngredient = [];
        foreach ($balanceRows as $row) {
            $availableByBranchIngredient[(int) $row->branch_id][(int) $row->ingredient_id] = (string) $row->quantity_base;
        }

        return view('admin.inventory.transfers.form', [
            'ingredients' => $ingredients,
            'units' => $units,
            'branches' => $branches,
            'currentBranchId' => $context->branchId(),
            'availableByBranchIngredient' => $availableByBranchIngredient,
        ]);
    }

    public function store(Request $request, BranchContext $context, StockTransferService $service)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_choice' => ['required', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);

        $branchId = $context->requireSpecificBranch();
        $transfer = $service->postDirect(
            $branchId,
            $data['items'],
            $data['idempotency_key'],
            $request->user()?->id,
            $data['notes'] ?? null
        );

        return redirect()->route('inventory.transfers.show', $transfer)
            ->with('success', 'Direct Main to Kitchen transfer posted successfully.');
    }

    public function returnCreate(Request $request, BranchContext $context)
    {
        $branchId = $context->requireSpecificBranch();
        $original = null;
        if ($request->filled('transfer_id')) {
            $original = StockTransfer::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)
                ->whereKey((int) $request->transfer_id)
                ->where('status', StockTransfer::STATUS_POSTED)
                ->where('direction', StockTransfer::DIRECTION_MAIN_TO_KITCHEN)
                ->when($request->user()?->isKitchenUser(), fn ($q) => $q->whereHas('kitchenRequest', fn ($kr) => $kr->where('requested_by', $request->user()->id)))
                ->with(['items.ingredient.baseUnit', 'items.ingredient.unitConversions.unit', 'items.unit', 'items.packageConversion'])
                ->firstOrFail();
        }

        $ingredients = Ingredient::query()
            ->active()->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')->get();
        $units = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();
        $kitchen = app(StockLocationService::class)->forBranchAndType($branchId, StockLocation::TYPE_KITCHEN);
        $available = InventoryBalance::query()->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branchId)
            ->where('stock_location_id', $kitchen->id)
            ->pluck('quantity_base', 'ingredient_id')
            ->map(fn ($v) => (string) $v);

        $suggestedItems = $original
            ? $original->items->map(fn ($item) => [
                'ingredient_id' => $item->ingredient_id,
                'quantity' => (string) $item->quantity,
                'unit_choice' => $this->transferUnitChoice($item),
            ])->values()->all()
            : [['ingredient_id' => '', 'quantity' => '', 'unit_choice' => '']];

        return view('admin.inventory.transfers.return_form', compact(
            'ingredients', 'units', 'available', 'original', 'suggestedItems', 'branchId'
        ));
    }

    public function returnStore(Request $request, BranchContext $context, StockTransferService $service)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
            'original_transfer_id' => ['nullable', 'integer', 'exists:stock_transfers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_choice' => ['required', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);

        if ($request->user()?->isKitchenUser()) {
            // Kitchen can return unused stock, but cannot browse/link arbitrary Main-to-Kitchen transfers.
            $data['original_transfer_id'] = null;
        }

        $transfer = $service->postReturn(
            $context->requireSpecificBranch(),
            $data['items'],
            $data['idempotency_key'],
            $request->user()?->id,
            $data['notes'] ?? null,
            isset($data['original_transfer_id']) ? (int) $data['original_transfer_id'] : null
        );

        return redirect()->route('inventory.transfers.show', $transfer)
            ->with('success', 'Unused Kitchen stock returned to Main through an immutable transfer ledger movement.');
    }

    private function transferUnitChoice($item): string
    {
        if ($item->package_conversion_id) {
            return 'c:' . $item->package_conversion_id;
        }
        if (!$item->unit_id) {
            return '';
        }
        if ($item->unit?->dimension !== Unit::DIMENSION_PACKAGE) {
            return 'u:' . $item->unit_id;
        }

        $match = $item->ingredient?->unitConversions?->first(function ($conversion) use ($item) {
            return (int) $conversion->unit_id === (int) $item->unit_id
                && abs((float) $conversion->factor_to_base - (float) $item->conversion_factor_snapshot) < 0.00000001;
        });

        return $match ? 'c:' . $match->id : 'u:' . $item->unit_id;
    }

    public function show(StockTransfer $transfer)
    {
        if (request()->user()?->isKitchenUser()) {
            abort_unless(
                $transfer->direction === StockTransfer::DIRECTION_KITCHEN_TO_MAIN
                    && (int) $transfer->created_by === (int) request()->user()->id,
                403,
                'Kitchen users can view only their own return transfers.'
            );
        }

        $transfer->load([
            'branch',
            'kitchenRequest',
            'originalTransfer',
            'sourceLocation',
            'destinationLocation',
            'postedMovement.items.ingredient.baseUnit',
            'creator',
            'poster',
            'items.ingredient.baseUnit',
            'items.unit',
            'items.packageConversion',
        ]);

        return view('admin.inventory.transfers.show', compact('transfer'));
    }
}
