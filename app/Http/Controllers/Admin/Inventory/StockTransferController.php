<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\Inventory\InventorySiteContext;
use App\Services\Inventory\StockLocationService;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view|inventory-transfer-post|inventory-return-post')->only(['show']);
        $this->middleware('permission:inventory-return-post')->only(['returnCreate', 'returnStore']);
    }

    /**
     * Step 1 keeps transfers as an internal stock engine only.
     * The only user-facing transfer action is returning unused Kitchen stock.
     */
    public function returnCreate(Request $request, InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $original = null;

        if ($request->filled('transfer_id')) {
            $original = StockTransfer::query()
                ->whereKey((int) $request->transfer_id)
                ->where('status', StockTransfer::STATUS_POSTED)
                ->where('direction', StockTransfer::DIRECTION_MAIN_TO_KITCHEN)
                ->when($request->user()?->isKitchenManager(), fn ($q) => $q->whereHas(
                    'kitchenRequest',
                    fn ($kr) => $kr->where('requested_by', $request->user()->id)
                ))
                ->with(['items.ingredient.baseUnit', 'items.ingredient.unitConversions.unit', 'items.unit', 'items.packageConversion'])
                ->firstOrFail();
        }

        $ingredients = Ingredient::query()
            ->active()
            ->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')
            ->get();

        $units = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();
        $kitchen = app(StockLocationService::class)->forType(StockLocation::TYPE_KITCHEN);
        $available = InventoryBalance::query()
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
            'ingredients', 'units', 'available', 'original', 'suggestedItems'
        ));
    }

    public function returnStore(Request $request, InventorySiteContext $site, StockTransferService $service)
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

        if ($request->user()?->isKitchenManager()) {
            // Kitchen Manager may return unused ingredients but may not link an arbitrary historical issue.
            $data['original_transfer_id'] = null;
        }

        $site->ensureDefaultLocations();
        $transfer = $service->postReturn(
            $data['items'],
            $data['idempotency_key'],
            $request->user()?->id,
            $data['notes'] ?? null,
            isset($data['original_transfer_id']) ? (int) $data['original_transfer_id'] : null
        );

        return redirect()->route('inventory.transfers.show', $transfer)
            ->with('success', 'Unused Kitchen ingredients returned to Store Stock successfully.');
    }

    public function show(StockTransfer $transfer, InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();

        if (request()->user()?->isKitchenManager()) {
            abort_unless(
                $transfer->direction === StockTransfer::DIRECTION_KITCHEN_TO_MAIN
                    && (int) $transfer->created_by === (int) request()->user()->id,
                403,
                'Kitchen Manager can view only their own ingredient return transactions.'
            );
        }

        $transfer->load([
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
}
