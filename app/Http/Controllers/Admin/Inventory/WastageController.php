<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\InventoryWastage;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Inventory\InventoryWastageService;
use App\Services\Inventory\StockLocationService;
use App\Services\Inventory\InventorySiteContext;
use Illuminate\Http\Request;

class WastageController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-wastage-view|inventory-wastage-create|inventory-wastage-edit|inventory-wastage-delete')->only(['index', 'show']);
        $this->middleware('permission:inventory-wastage-create')->only(['create', 'store']);
        $this->middleware('permission:inventory-wastage-edit')->only(['edit', 'update']);
        $this->middleware('permission:inventory-wastage-delete')->only(['destroy']);
    }

    public function index(Request $request, InventorySiteContext $site)
    {
        $kitchenOnly = (bool) $request->user()?->isKitchenUser();
        $site->ensureDefaultLocations();
        $wastages = InventoryWastage::query()
            ->when($kitchenOnly, fn ($q) => $q->where('created_by', $request->user()->id)->whereHas('location', fn ($l) => $l->where('type', StockLocation::TYPE_KITCHEN)))
            ->with(['location', 'creator'])
            ->withCount('items')
            ->when($request->filled('search'), fn ($q) => $q->where('wastage_no', 'like', '%' . trim((string) $request->search) . '%'))
            ->when($request->filled('reason_code'), fn ($q) => $q->where('reason_code', $request->reason_code))
            ->orderByRaw("CASE WHEN status = 'DRAFT' THEN 0 ELSE 1 END")
            ->orderByDesc('posted_at')->orderByDesc('id')
            ->paginate(20)->appends($request->query());

        return view('admin.inventory.wastages.index', [
            'wastages' => $wastages,
            'reasons' => InventoryWastage::reasons(),
        ]);
    }

    public function create(InventorySiteContext $site)
    {
        return view('admin.inventory.wastages.form', $this->formData($site));
    }

    public function store(Request $request, InventorySiteContext $site, InventoryWastageService $service)
    {
        $data = $this->validated($request);
        $site->ensureDefaultLocations();
        $data['location_id'] = $this->authorizedLocationId($request, (int) $data['location_id']);
        $action = $data['action'] ?? 'post';

        if ($action === 'draft') {
            $wastage = $service->createDraft(
                (int) $data['location_id'],
                strtoupper((string) $data['reason_code']),
                $data['items'],
                $data['notes'] ?? null,
                $request->user()?->id
            );

            return redirect()->route('inventory.wastages.show', $wastage)
                ->with('success', 'Wastage saved as DRAFT. You can edit or delete it before posting.');
        }

        $wastage = $service->post(
            (int) $data['location_id'],
            strtoupper((string) $data['reason_code']),
            $data['items'],
            $data['notes'] ?? null,
            $request->user()?->id
        );

        return redirect()->route('inventory.wastages.show', $wastage)
            ->with('success', 'Wastage posted and stock reduced through the immutable ledger.');
    }

    public function edit(InventoryWastage $wastage, InventorySiteContext $site, Request $request)
    {
        $this->assertEditableAccess($wastage, $request);
        abort_unless($wastage->status === InventoryWastage::STATUS_DRAFT, 422, 'Only DRAFT wastage can be edited.');

        $wastage->load(['items', 'location']);

        return view('admin.inventory.wastages.form', $this->formData($site, $wastage));
    }

    public function update(
        Request $request,
        InventoryWastage $wastage,
        InventorySiteContext $site,
        InventoryWastageService $service
    ) {
        $this->assertEditableAccess($wastage, $request);
        $data = $this->validated($request);
        $site->ensureDefaultLocations();
        $data['location_id'] = $this->authorizedLocationId($request, (int) $data['location_id']);
        $updated = $service->updateDraft(
            $wastage,
            (int) $data['location_id'],
            strtoupper((string) $data['reason_code']),
            $data['items'],
            $data['notes'] ?? null
        );

        if (($data['action'] ?? 'draft') === 'post') {
            $updated = $service->postDraft($updated, $request->user()?->id);
            return redirect()->route('inventory.wastages.show', $updated)
                ->with('success', 'Wastage updated and posted. Stock has now been reduced.');
        }

        return redirect()->route('inventory.wastages.show', $updated)
            ->with('success', 'Draft wastage updated successfully.');
    }

    public function destroy(
        InventoryWastage $wastage,
        InventorySiteContext $site,
        Request $request,
        InventoryWastageService $service
    ) {
        $this->assertEditableAccess($wastage, $request);
        $site->ensureDefaultLocations();
        $service->deleteDraft($wastage);

        return redirect()->route('inventory.wastages.index')
            ->with('success', 'Draft wastage deleted. No stock movement was posted.');
    }

    public function show(InventoryWastage $wastage, InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $wastage->load('location');
        if (request()->user()?->isKitchenUser()) {
            abort_unless(
                $wastage->location?->type === StockLocation::TYPE_KITCHEN
                && (int) $wastage->created_by === (int) request()->user()->id,
                403,
                'Kitchen users can view only their own Kitchen wastage records.'
            );
        }

        $wastage->load(['location', 'creator', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion']);
        $movement = StockMovement::query()
            ->where('reference_type', InventoryWastage::class)
            ->where('reference_id', $wastage->id)
            ->where('movement_type', StockMovement::WASTAGE)
            ->with('items.ingredient.baseUnit')
            ->first();

        return view('admin.inventory.wastages.show', compact('wastage', 'movement'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'location_id' => ['required', 'integer', 'exists:stock_locations,id'],
            'reason_code' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_choice' => ['required', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
            'action' => ['nullable', 'in:draft,post'],
        ]);
    }

    private function authorizedLocationId(Request $request, int $requestedLocationId): int
    {
        if ($request->user()?->isKitchenUser()) {
            return (int) app(StockLocationService::class)
                ->forType(StockLocation::TYPE_KITCHEN)
                ->id;
        }

        if ($request->user()?->isInventoryManager()) {
            // Main Inventory wastage is recorded by Inventory Manager. Kitchen
            // wastage stays inside the Kitchen role to keep responsibility clear.
            return (int) app(StockLocationService::class)
                ->forType(StockLocation::TYPE_MAIN)
                ->id;
        }

        return $requestedLocationId;
    }

    private function assertEditableAccess(InventoryWastage $wastage, Request $request): void
    {
        $wastage->loadMissing('location');

        if ($request->user()?->isKitchenUser()) {
            abort_unless(
                $wastage->location?->type === StockLocation::TYPE_KITCHEN
                && (int) $wastage->created_by === (int) $request->user()->id,
                403,
                'Kitchen users can edit/delete only their own Kitchen wastage drafts.'
            );
        }

        if ($request->user()?->isInventoryManager()) {
            abort_unless(
                $wastage->location?->type === StockLocation::TYPE_MAIN,
                403,
                'Inventory Manager can edit/delete only Main Stock wastage drafts.'
            );
        }
    }

    private function formData(InventorySiteContext $site, ?InventoryWastage $wastage = null): array
    {
        $site->ensureDefaultLocations();
        $user = auth()->user();

        $locations = StockLocation::query()
            ->where('is_active', true)
            ->when($user?->isKitchenUser(), fn ($q) => $q->where('type', StockLocation::TYPE_KITCHEN))
            ->when($user?->isInventoryManager(), fn ($q) => $q->where('type', StockLocation::TYPE_MAIN))
            ->when(!$user?->isKitchenUser() && !$user?->isInventoryManager(), fn ($q) => $q->whereIn('type', [StockLocation::TYPE_MAIN, StockLocation::TYPE_KITCHEN]))
            ->orderBy('type')->get();

        $ingredients = Ingredient::query()->active()->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')->get();
        $units = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();

        $balances = InventoryBalance::query()
            ->whereIn('stock_location_id', $locations->pluck('id'))
            ->get();
        $available = [];
        foreach ($balances as $balance) {
            $available[(int) $balance->stock_location_id][(int) $balance->ingredient_id] = (string) $balance->quantity_base;
        }

        return [
            'wastage' => $wastage,
            'locations' => $locations,
            'ingredients' => $ingredients,
            'units' => $units,
            'available' => $available,
            'reasons' => InventoryWastage::reasons(),
        ];
    }
}
