<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\OrderInventoryConsumption;
use Illuminate\Http\Request;

class OrderConsumptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view');
    }

    public function index(Request $request)
    {
        $consumptions = OrderInventoryConsumption::query()
            ->with(['branch', 'order', 'creator'])
            ->withCount('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->whereHas('order', fn ($q) => $q->where('order_number', 'like', $search));
            })
            ->when($request->filled('trigger_source'), fn ($q) => $q->where('trigger_source', $request->trigger_source))
            ->orderByDesc('consumed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.inventory.consumptions.index', compact('consumptions'));
    }

    public function show(OrderInventoryConsumption $consumption)
    {
        $consumption->load([
            'branch', 'order', 'creator',
            'stockMovement.items.ingredient.baseUnit',
            'items.ingredient.baseUnit', 'items.foodItem', 'items.recipe',
        ]);

        return view('admin.inventory.consumptions.show', compact('consumption'));
    }
}
