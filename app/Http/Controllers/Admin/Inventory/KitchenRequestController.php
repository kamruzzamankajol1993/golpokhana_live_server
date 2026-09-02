<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireSpecificBranch;
use App\Models\Branch;
use App\Models\FoodItem;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\KitchenRequest;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\Inventory\DecimalQuantity;
use App\Services\Inventory\KitchenRequestService;
use App\Services\Inventory\StockLocationService;
use App\Services\Inventory\StockTransferService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KitchenRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-kitchen-request-create|inventory-kitchen-request-review|inventory-transfer-post')->only(['index', 'show']);
        $this->middleware('permission:inventory-kitchen-request-create')->only(['create', 'store', 'edit', 'update', 'destroy', 'submit', 'cancel']);
        $this->middleware('permission:inventory-kitchen-request-review')->only(['close', 'issue']);
        $this->middleware('permission:inventory-transfer-post')->only('issue');
        $this->middleware(RequireSpecificBranch::class)->only(['store', 'update', 'destroy', 'submit', 'cancel', 'close', 'issue']);
    }

    public function index(Request $request)
    {
        $kitchenOnly = (bool) $request->user()?->isKitchenUser();
        $requests = KitchenRequest::query()
            ->when($kitchenOnly, fn ($q) => $q->where('requested_by', $request->user()->id))
            ->with(['branch', 'requester', 'reviewer'])
            ->withCount(['foodItems', 'ingredientItems', 'transfers'])
            ->withCount(['ingredientItems as direct_ingredient_items_count' => fn ($q) => $q->whereIn('source_kind', ['DIRECT', 'MIXED'])])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where('request_no', 'like', $search);
            })
            ->when($request->filled('request_type'), fn ($q) => $q->where('request_type', strtoupper((string) $request->request_type)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('request_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('request_date', '<=', $request->date_to))
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.inventory.kitchen_requests.index', [
            'requests' => $requests,
            'statuses' => KitchenRequest::statuses(),
            'types' => KitchenRequest::types(),
        ]);
    }

    public function create(BranchContext $context)
    {
        return view('admin.inventory.kitchen_requests.form', $this->formData($context) + [
            'kitchenRequest' => new KitchenRequest(),
        ]);
    }

    public function store(Request $request, BranchContext $context, KitchenRequestService $service)
    {
        $data = $this->validated($request);
        $branchId = $context->requireSpecificBranch();
        $kitchenRequest = $service->saveDraft($branchId, $data, null, $request->user()?->id);

        return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
            ->with('success', 'Kitchen request saved as Draft. Submit it when ready for Store review.');
    }

    public function show(
        KitchenRequest $kitchenRequest,
        StockLocationService $locationService,
        DecimalQuantity $decimal
    ) {
        $this->assertKitchenOwnership($kitchenRequest);
        $kitchenActor = (bool) request()->user()?->isKitchenUser();

        $kitchenRequest->load([
            'branch',
            'requester',
            'reviewer',
            'foodItems.foodItem',
            'foodItems.recipe',
            'ingredientItems.ingredient.baseUnit',
            'ingredientItems.ingredient.unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit'),
            'ingredientItems.displayUnit',
            'ingredientItems.packageConversion',
            'transfers' => fn ($q) => $q->with(['poster', 'items'])->orderByDesc('id'),
        ]);

        $main = null;
        $balances = collect();
        if (!$kitchenActor) {
            $main = $locationService->forBranchAndType((int) $kitchenRequest->branch_id, StockLocation::TYPE_MAIN);
            $balances = InventoryBalance::query()
                ->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $kitchenRequest->branch_id)
                ->where('stock_location_id', $main->id)
                ->whereIn('ingredient_id', $kitchenRequest->ingredientItems->pluck('ingredient_id'))
                ->pluck('quantity_base', 'ingredient_id');
        }

        $activeUnits = $kitchenActor
            ? collect()
            : Unit::query()->active()->where('dimension', '!=', Unit::DIMENSION_PACKAGE)
                ->orderBy('dimension')->orderBy('name')->get();
        $unitOptionsByIngredient = [];
        foreach ($kitchenRequest->ingredientItems as $item) {
            $ingredient = $item->ingredient;
            $choices = $activeUnits->where('dimension', $ingredient->measurement_dimension)
                ->map(fn ($unit) => ['value' => 'u:' . $unit->id, 'label' => $unit->name . ' (' . $unit->symbol . ')'])
                ->values()->all();
            foreach ($ingredient->unitConversions as $conversion) {
                if ($conversion->unit?->is_active) {
                    $choices[] = ['value' => 'c:' . $conversion->id, 'label' => $conversion->label ?: ($conversion->unit->name . ' (' . $conversion->factor_to_base . ' ' . $ingredient->baseUnit?->symbol . ')')];
                }
            }
            $unitOptionsByIngredient[(int) $ingredient->id] = $choices;

            $available = (string) ($balances[(int) $ingredient->id] ?? '0');
            $item->setAttribute('available_main_base', $available);
            $item->setAttribute('remaining_base', $decimal->subtract((string) $item->required_base_qty, (string) $item->issued_base_qty));
        }

        return view('admin.inventory.kitchen_requests.show', compact(
            'kitchenRequest',
            'main',
            'unitOptionsByIngredient'
        ));
    }

    public function edit(KitchenRequest $kitchenRequest, BranchContext $context)
    {
        $this->assertKitchenOwnership($kitchenRequest);
        if (!$kitchenRequest->isEditable()) {
            return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
                ->with('error', 'Only Draft or Submitted kitchen requests can be edited before stock is issued.');
        }
        $kitchenRequest->load(['foodItems', 'ingredientItems']);

        return view('admin.inventory.kitchen_requests.form', $this->formData($context) + compact('kitchenRequest'));
    }

    public function update(
        Request $request,
        KitchenRequest $kitchenRequest,
        BranchContext $context,
        KitchenRequestService $service
    ) {
        $this->assertKitchenOwnership($kitchenRequest);
        $data = $this->validated($request);
        $branchId = $context->requireSpecificBranch();
        if ((int) $kitchenRequest->branch_id !== $branchId) {
            throw ValidationException::withMessages(['branch_id' => 'The selected branch does not match this kitchen request.']);
        }

        $kitchenRequest = $service->saveDraft($branchId, $data, $kitchenRequest, $request->user()?->id);
        return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
            ->with('success', 'Kitchen request updated successfully.');
    }

    public function submit(
        Request $request,
        KitchenRequest $kitchenRequest,
        BranchContext $context,
        KitchenRequestService $service
    ) {
        $this->assertSelectedBranch($context, $kitchenRequest);
        $service->submit($kitchenRequest, $request->user()?->id);

        return back()->with('success', 'Kitchen request submitted for Store review.');
    }

    public function cancel(
        KitchenRequest $kitchenRequest,
        BranchContext $context,
        KitchenRequestService $service
    ) {
        $this->assertSelectedBranch($context, $kitchenRequest);
        $service->cancel($kitchenRequest);

        return back()->with('success', 'Kitchen request cancelled. No stock was changed.');
    }

    public function close(
        Request $request,
        KitchenRequest $kitchenRequest,
        BranchContext $context,
        KitchenRequestService $service
    ) {
        $this->assertSelectedBranch($context, $kitchenRequest);
        $service->close($kitchenRequest, $request->user()?->id);

        return back()->with('success', 'Kitchen request lifecycle closed.');
    }

    public function issue(
        Request $request,
        KitchenRequest $kitchenRequest,
        BranchContext $context,
        StockTransferService $service
    ) {
        $this->assertSelectedBranch($context, $kitchenRequest);
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'numeric', 'gte:0'],
            'items.*.unit_choice' => ['nullable', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);

        $transfer = $service->issueRequest(
            $kitchenRequest,
            $data['items'],
            $data['idempotency_key'],
            $request->user()?->id,
            $data['notes'] ?? null
        );

        return redirect()->route('inventory.transfers.show', $transfer)
            ->with('success', 'Stock issued: Main Stock decreased and Kitchen Stock increased in one ledger transaction.');
    }

    public function destroy(KitchenRequest $kitchenRequest, BranchContext $context)
    {
        $this->assertKitchenOwnership($kitchenRequest);
        $this->assertSelectedBranch($context, $kitchenRequest);
        if (!$kitchenRequest->isEditable()) {
            throw ValidationException::withMessages(['request' => 'Only Draft or Submitted kitchen requests can be deleted before stock is issued.']);
        }
        $kitchenRequest->delete();

        return redirect()->route('inventory.kitchen-requests.index')->with('success', 'Kitchen request deleted successfully.');
    }

    private function formData(BranchContext $context): array
    {
        $foods = FoodItem::query()
            ->where('inventory_tracking', true)
            ->whereHas('activeRecipe')
            ->with(['activeRecipe.items', 'category'])
            ->orderBy('name')
            ->get();

        $ingredients = Ingredient::query()
            ->active()
            ->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')
            ->get();

        return [
            'foods' => $foods,
            'ingredients' => $ingredients,
            'units' => Unit::query()->active()->orderBy('dimension')->orderBy('name')->get(),
            'branches' => $context->user()?->isSuperAdmin()
                ? Branch::query()->active()->orderByDesc('is_main')->orderBy('name')->get()
                : collect(),
            'currentBranchId' => $context->branchId(),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'request_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'food_items' => ['nullable', 'array'],
            'food_items.*.menu_item_id' => ['nullable', 'integer', 'exists:food_items,id'],
            'food_items.*.requested_food_qty' => ['nullable', 'numeric', 'gt:0'],
            'ingredient_items' => ['nullable', 'array'],
            'ingredient_items.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'ingredient_items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'ingredient_items.*.unit_choice' => ['nullable', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);

        return $data;
    }

    private function assertKitchenOwnership(KitchenRequest $kitchenRequest): void
    {
        $user = request()->user();
        if ($user?->isKitchenUser()) {
            abort_unless(
                (int) $kitchenRequest->requested_by === (int) $user->id,
                403,
                'Kitchen users can access and change only their own kitchen requests.'
            );
        }
    }

    private function assertSelectedBranch(BranchContext $context, KitchenRequest $request): void
    {
        $this->assertKitchenOwnership($request);
        $branchId = $context->requireSpecificBranch();
        if ((int) $request->branch_id !== $branchId) {
            throw ValidationException::withMessages(['branch_id' => 'The selected branch does not match this kitchen request.']);
        }
    }
}
