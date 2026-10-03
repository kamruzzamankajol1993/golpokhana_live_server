<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Inventory\InventorySiteContext;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-transaction-history-view|inventory-view');
    }

    public function index(Request $request, InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $kitchenOnly = (bool) $request->user()?->isKitchenManager();
        $kitchen = $kitchenOnly
            ? StockLocation::query()->where('type', StockLocation::TYPE_KITCHEN)->where('is_active', true)->first()
            : null;

        $movements = StockMovement::query()
            ->with(['sourceLocation', 'destinationLocation', 'performer', 'items.ingredient.baseUnit'])
            ->where('status', StockMovement::STATUS_POSTED)
            ->when($kitchen, function ($q) use ($kitchen) {
                $q->where(fn ($movement) => $movement
                    ->where('source_location_id', $kitchen->id)
                    ->orWhere('destination_location_id', $kitchen->id));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(function ($sub) use ($search) {
                    $sub->where('movement_no', 'like', $search)
                        ->orWhere('reason', 'like', $search)
                        ->orWhereHas('items.ingredient', fn ($ingredient) => $ingredient->where('name', 'like', $search)->orWhere('code', 'like', $search));
                });
            })
            ->when($request->filled('movement_type'), fn ($q) => $q->where('movement_type', $request->movement_type))
            ->when($request->filled('ingredient_id'), fn ($q) => $q->whereHas('items', fn ($item) => $item->where('ingredient_id', (int) $request->ingredient_id)))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('occurred_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('occurred_at', '<=', $request->date_to))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.inventory.ledger.index', [
            'movements' => $movements,
            'movementTypes' => StockMovement::types(),
            'ingredients' => Ingredient::query()->orderBy('name')->get(),
            'kitchenOnly' => $kitchenOnly,
        ]);
    }
}
