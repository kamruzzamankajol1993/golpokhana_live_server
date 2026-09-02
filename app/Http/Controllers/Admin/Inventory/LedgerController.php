<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view');
    }

    public function index(Request $request)
    {
        $movements = StockMovement::query()
            ->with(['branch', 'sourceLocation', 'destinationLocation', 'performer', 'items.ingredient.baseUnit'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(function ($sub) use ($search) {
                    $sub->where('movement_no', 'like', $search)
                        ->orWhere('reason', 'like', $search)
                        ->orWhereHas('items.ingredient', fn ($ingredient) => $ingredient->where('name', 'like', $search)->orWhere('code', 'like', $search));
                });
            })
            ->when($request->filled('movement_type'), fn ($q) => $q->where('movement_type', $request->movement_type))
            ->when($request->filled('location_id'), function ($q) use ($request) {
                $locationId = (int) $request->location_id;
                $q->where(fn ($sub) => $sub->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId));
            })
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
            'locations' => StockLocation::query()->with('branch')->active()->orderBy('branch_id')->orderBy('type')->get(),
            'ingredients' => Ingredient::query()->orderBy('name')->get(),
        ]);
    }
}
