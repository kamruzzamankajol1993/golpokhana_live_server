<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\KitchenRequest;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Services\Inventory\DecimalQuantity;
use App\Services\Inventory\InventorySiteContext;
use App\Services\Inventory\KitchenRequestService;
use App\Services\Inventory\StockLocationService;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KitchenRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-kitchen-request-create|inventory-kitchen-request-review|inventory-kitchen-request-assign')->only(['index', 'show']);
        $this->middleware('permission:inventory-kitchen-request-create')->only(['create', 'store', 'edit', 'update', 'destroy', 'submit', 'cancel']);
        $this->middleware('permission:inventory-kitchen-request-review')->only(['close']);
        $this->middleware('permission:inventory-kitchen-request-assign')->only(['assign', 'issue']);
    }

    public function index(
        Request $request,
        InventorySiteContext $site,
        StockLocationService $locationService,
        DecimalQuantity $decimal
    ) {
        $site->ensureDefaultLocations();
        $user = $request->user();
        $kitchenOnly = (bool) $user?->isKitchenManager();
        $canAssign = (bool) $user?->can('inventory-kitchen-request-assign');
        $tab = $request->string('tab')->toString();
        $tab = ($tab === 'assign' && $canAssign) ? 'assign' : 'requests';
        $pendingAssignmentCount = $canAssign
            ? KitchenRequest::query()->whereIn('status', [KitchenRequest::STATUS_SUBMITTED, KitchenRequest::STATUS_PARTIALLY_ISSUED])->count()
            : 0;

        $requests = null;
        $assignmentRequests = null;

        if ($tab === 'assign') {
            $assignmentRequests = KitchenRequest::query()
                ->whereIn('status', [KitchenRequest::STATUS_SUBMITTED, KitchenRequest::STATUS_PARTIALLY_ISSUED])
                ->with([
                    'requester',
                    'ingredientItems.ingredient.baseUnit',
                    'ingredientItems.displayUnit',
                    'ingredientItems.packageConversion',
                ])
                ->withCount(['ingredientItems', 'transfers'])
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = '%' . trim((string) $request->search) . '%';
                    $query->where(function ($q) use ($search) {
                        $q->where('request_no', 'like', $search)
                            ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', $search));
                    });
                })
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('request_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('request_date', '<=', $request->date_to))
                ->orderByRaw("CASE WHEN status = 'PARTIALLY_ISSUED' THEN 0 ELSE 1 END")
                ->orderBy('request_date')
                ->orderBy('id')
                ->paginate(20)
                ->appends($request->query());

            $main = $locationService->forType(StockLocation::TYPE_MAIN);
            $ingredientIds = $assignmentRequests->getCollection()
                ->flatMap(fn (KitchenRequest $item) => $item->ingredientItems->pluck('ingredient_id'))
                ->unique()
                ->values();

            $balances = InventoryBalance::query()
                ->where('stock_location_id', $main->id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->pluck('quantity_base', 'ingredient_id');

            $assignmentRequests->getCollection()->transform(function (KitchenRequest $item) use ($balances, $decimal) {
                $remainingLines = 0;
                $shortageLines = 0;
                $completedLines = 0;

                foreach ($item->ingredientItems as $ingredientItem) {
                    $remaining = $decimal->subtract((string) $ingredientItem->required_base_qty, (string) $ingredientItem->issued_base_qty);
                    if ($decimal->compare($remaining, '0') > 0) {
                        $remainingLines++;
                        $available = (string) ($balances[(int) $ingredientItem->ingredient_id] ?? '0');
                        if ($decimal->compare($available, $remaining) < 0) {
                            $shortageLines++;
                        }
                    } else {
                        $completedLines++;
                    }
                }

                $item->setAttribute('remaining_lines', $remainingLines);
                $item->setAttribute('shortage_lines', $shortageLines);
                $item->setAttribute('completed_lines', $completedLines);
                return $item;
            });
        } else {
            $requests = KitchenRequest::query()
                ->when($kitchenOnly, fn ($q) => $q->where('requested_by', $user->id))
                ->with(['requester', 'reviewer'])
                ->withCount(['ingredientItems', 'transfers'])
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = '%' . trim((string) $request->search) . '%';
                    $query->where(function ($q) use ($search) {
                        $q->where('request_no', 'like', $search)
                            ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', $search));
                    });
                })
                ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper((string) $request->status)))
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('request_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('request_date', '<=', $request->date_to))
                ->orderByDesc('request_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->appends($request->query());
        }

        return view('admin.inventory.kitchen_requests.index', [
            'tab' => $tab,
            'canAssign' => $canAssign,
            'pendingAssignmentCount' => $pendingAssignmentCount,
            'requests' => $requests,
            'assignmentRequests' => $assignmentRequests,
            'statuses' => KitchenRequest::statuses(),
        ]);
    }

    public function create(InventorySiteContext $site)
    {
        return view('admin.inventory.kitchen_requests.form', $this->formData($site) + [
            'kitchenRequest' => new KitchenRequest(),
        ]);
    }

    public function store(Request $request, InventorySiteContext $site, KitchenRequestService $service)
    {
        $data = $this->validated($request);
        $site->ensureDefaultLocations();
        $kitchenRequest = $service->saveDraft($data, null, $request->user()?->id);
        $service->submit($kitchenRequest, $request->user()?->id);

        return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
            ->with('success', 'Kitchen request sent to Inventory Manager successfully.');
    }

    public function show(
        KitchenRequest $kitchenRequest,
        DecimalQuantity $decimal,
        InventorySiteContext $site
    ) {
        $this->assertSiteRequest($site, $kitchenRequest);
        $this->prepareRequestForDisplay($kitchenRequest, $decimal, false);

        return view('admin.inventory.kitchen_requests.show', compact('kitchenRequest'));
    }

    public function assign(
        KitchenRequest $kitchenRequest,
        DecimalQuantity $decimal,
        InventorySiteContext $site
    ) {
        $this->assertSiteRequest($site, $kitchenRequest);
        if (!$kitchenRequest->canIssue()) {
            return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
                ->with('error', 'This request has no remaining ingredient assignment.');
        }

        $unitOptionsByIngredient = $this->prepareRequestForDisplay($kitchenRequest, $decimal, true);

        return view('admin.inventory.kitchen_requests.assign', compact('kitchenRequest', 'unitOptionsByIngredient'));
    }

    public function edit(KitchenRequest $kitchenRequest, InventorySiteContext $site)
    {
        $this->assertSiteRequest($site, $kitchenRequest);
        $this->assertKitchenOwnership($kitchenRequest);
        if (!$kitchenRequest->isEditable()) {
            return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
                ->with('error', 'A request cannot be edited after ingredient assignment has started.');
        }
        $kitchenRequest->load([
            'foodItems.foodItem',
            'foodItems.recipe',
            'ingredientItems.displayUnit',
            'ingredientItems.packageConversion',
        ]);

        return view('admin.inventory.kitchen_requests.form', $this->formData($site) + compact('kitchenRequest'));
    }

    public function update(
        Request $request,
        KitchenRequest $kitchenRequest,
        InventorySiteContext $site,
        KitchenRequestService $service
    ) {
        $this->assertKitchenOwnership($kitchenRequest);
        $data = $this->validated($request);
        $site->ensureDefaultLocations();
        $kitchenRequest = $service->saveDraft($data, $kitchenRequest, $request->user()?->id);

        return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
            ->with('success', 'Kitchen request updated successfully.');
    }

    /** Legacy Draft compatibility. New Step 2 requests are submitted immediately from store(). */
    public function submit(
        Request $request,
        KitchenRequest $kitchenRequest,
        InventorySiteContext $site,
        KitchenRequestService $service
    ) {
        $this->assertSiteRequest($site, $kitchenRequest);
        $this->assertKitchenOwnership($kitchenRequest);
        $service->submit($kitchenRequest, $request->user()?->id);

        return back()->with('success', 'Kitchen request sent to Inventory Manager.');
    }

    public function cancel(
        KitchenRequest $kitchenRequest,
        InventorySiteContext $site,
        KitchenRequestService $service
    ) {
        $this->assertSiteRequest($site, $kitchenRequest);
        $this->assertKitchenOwnership($kitchenRequest);
        $service->cancel($kitchenRequest);

        return back()->with('success', 'Kitchen request cancelled. No stock was changed.');
    }

    public function close(
        Request $request,
        KitchenRequest $kitchenRequest,
        InventorySiteContext $site,
        KitchenRequestService $service
    ) {
        $this->assertSiteRequest($site, $kitchenRequest);
        $service->close($kitchenRequest, $request->user()?->id);

        return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
            ->with('success', 'Ingredient request closed.');
    }

    public function issue(
        Request $request,
        KitchenRequest $kitchenRequest,
        InventorySiteContext $site,
        StockTransferService $service
    ) {
        $this->assertSiteRequest($site, $kitchenRequest);
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'numeric', 'gte:0'],
            'items.*.unit_choice' => ['nullable', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);

        $service->issueRequest(
            $kitchenRequest,
            $data['items'],
            $data['idempotency_key'],
            $request->user()?->id,
            $data['notes'] ?? null
        );

        $kitchenRequest->refresh();
        if ($kitchenRequest->canIssue()) {
            return redirect()->route('inventory.kitchen-requests.assign', $kitchenRequest)
                ->with('success', 'Ingredients partially assigned. Remaining quantity is ready for the next assignment.');
        }

        return redirect()->route('inventory.kitchen-requests.show', $kitchenRequest)
            ->with('success', 'All requested ingredients assigned to Kitchen successfully.');
    }

    public function destroy(KitchenRequest $kitchenRequest, InventorySiteContext $site)
    {
        $this->assertKitchenOwnership($kitchenRequest);
        $this->assertSiteRequest($site, $kitchenRequest);
        if (!$kitchenRequest->isEditable()) {
            throw ValidationException::withMessages(['request' => 'A request cannot be deleted after ingredient assignment has started.']);
        }
        if ($kitchenRequest->ingredientItems()->where('issued_base_qty', '>', 0)->exists()) {
            throw ValidationException::withMessages(['request' => 'This request already has assigned stock and cannot be deleted.']);
        }
        $kitchenRequest->delete();

        return redirect()->route('inventory.kitchen-requests.index')->with('success', 'Kitchen request deleted successfully.');
    }

    private function formData(InventorySiteContext $site): array
    {
        $site->ensureDefaultLocations();
        $ingredients = Ingredient::query()
            ->active()
            ->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')
            ->get();

        $foods = FoodItem::query()
            ->whereHas('activeRecipe')
            ->with([
                'activeRecipe.items.ingredient.baseUnit',
            ])
            ->orderBy('name')
            ->get();

        return [
            'ingredients' => $ingredients,
            'foods' => $foods,
            'units' => Unit::query()->active()->orderBy('dimension')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'request_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],

            // Both request methods are optional individually. KitchenRequestService
            // enforces that at least one complete Food-wise or Direct Ingredient row exists.
            'food_items' => ['nullable', 'array'],
            'food_items.*.menu_item_id' => ['nullable', 'integer', 'exists:food_items,id'],
            'food_items.*.quantity' => ['nullable', 'numeric', 'gt:0'],

            'ingredient_items' => ['nullable', 'array'],
            'ingredient_items.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'ingredient_items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'ingredient_items.*.unit_choice' => ['nullable', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);
    }

    private function prepareRequestForDisplay(
        KitchenRequest $kitchenRequest,
        DecimalQuantity $decimal,
        bool $forAssignment
    ): array {
        $kitchenRequest->load([
            'requester',
            'reviewer',
            'foodItems.foodItem', // Legacy Step 1 requests remain readable.
            'foodItems.recipe',
            'ingredientItems.ingredient.baseUnit',
            'ingredientItems.ingredient.unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit'),
            'ingredientItems.displayUnit',
            'ingredientItems.packageConversion',
            'transfers' => fn ($q) => $q->with(['poster', 'items'])->orderByDesc('id'),
        ]);

        $balances = collect();
        $activeUnits = collect();
        if ($forAssignment) {
            $main = app(StockLocationService::class)->forType(StockLocation::TYPE_MAIN);
            $balances = InventoryBalance::query()
                ->where('stock_location_id', $main->id)
                ->whereIn('ingredient_id', $kitchenRequest->ingredientItems->pluck('ingredient_id'))
                ->pluck('quantity_base', 'ingredient_id');
            $activeUnits = Unit::query()
                ->active()
                ->where('dimension', '!=', Unit::DIMENSION_PACKAGE)
                ->orderBy('dimension')
                ->orderBy('name')
                ->get();
        }

        $unitOptionsByIngredient = [];
        foreach ($kitchenRequest->ingredientItems as $item) {
            $remaining = $decimal->subtract((string) $item->required_base_qty, (string) $item->issued_base_qty);
            $item->setAttribute('remaining_base', $remaining);
            $item->setAttribute('available_main_base', (string) ($balances[(int) $item->ingredient_id] ?? '0'));

            if (!$forAssignment) {
                continue;
            }

            $ingredient = $item->ingredient;
            $choices = $activeUnits->where('dimension', $ingredient->measurement_dimension)
                ->map(fn ($unit) => [
                    'value' => 'u:' . $unit->id,
                    'label' => $unit->name . ' (' . $unit->symbol . ')',
                    'is_base' => (int) $unit->id === (int) $ingredient->base_unit_id,
                ])
                ->values()
                ->all();

            foreach ($ingredient->unitConversions as $conversion) {
                if ($conversion->unit?->is_active) {
                    $choices[] = [
                        'value' => 'c:' . $conversion->id,
                        'label' => $conversion->label ?: ($conversion->unit->name . ' (' . $conversion->factor_to_base . ' ' . $ingredient->baseUnit?->symbol . ')'),
                        'is_base' => false,
                    ];
                }
            }
            $unitOptionsByIngredient[(int) $ingredient->id] = $choices;
        }

        return $unitOptionsByIngredient;
    }

    private function assertKitchenOwnership(KitchenRequest $kitchenRequest): void
    {
        $user = request()->user();
        if ($user?->isKitchenManager()) {
            abort_unless(
                (int) $kitchenRequest->requested_by === (int) $user->id,
                403,
                'Kitchen Manager can access and change only their own ingredient requests.'
            );
        }
    }

    private function assertSiteRequest(InventorySiteContext $site, KitchenRequest $request): void
    {
        $site->ensureDefaultLocations();
        $this->assertKitchenOwnership($request);
    }
}
