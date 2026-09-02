<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-view');
    }

    public function index(Request $request)
    {
        $locations = StockLocation::query()
            ->with('branch')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->orderBy('branch_id')
            ->orderByRaw("CASE type WHEN 'MAIN' THEN 1 WHEN 'KITCHEN' THEN 2 ELSE 3 END")
            ->paginate(30)
            ->appends($request->query());

        return view('admin.inventory.locations.index', compact('locations'));
    }
}
