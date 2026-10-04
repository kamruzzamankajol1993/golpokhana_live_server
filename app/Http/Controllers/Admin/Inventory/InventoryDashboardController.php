<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryWastage;
use App\Models\KitchenRequest;
use App\Models\OrderKot;
use App\Models\Purchase;
use App\Models\PurchaseVoucher;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\Inventory\InventorySiteContext;
use Illuminate\Support\Facades\DB;

class InventoryDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-dashboard-view')->only('index');
        $this->middleware('permission:inventory-kitchen-dashboard-view')->only('kitchen');
    }

    public function index(InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $today = now()->toDateString();

        $balanceBase = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->where('sl.is_active', true);

        $storeStockItems = (clone $balanceBase)
            ->where('sl.type', StockLocation::TYPE_MAIN)
            ->where('ib.quantity_base', '!=', 0)
            ->count();
        $kitchenStockItems = (clone $balanceBase)
            ->where('sl.type', StockLocation::TYPE_KITCHEN)
            ->where('ib.quantity_base', '!=', 0)
            ->count();
        $lowStockCount = (clone $balanceBase)
            ->where('sl.type', StockLocation::TYPE_MAIN)
            ->where('ib.quantity_base', '>=', 0)
            ->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')
            ->count();
        $negativeStockCount = (clone $balanceBase)
            ->where('sl.type', StockLocation::TYPE_KITCHEN)
            ->where('ib.quantity_base', '<', 0)
            ->count();

        $pendingRequests = KitchenRequest::query()
            ->whereIn('status', [KitchenRequest::STATUS_SUBMITTED, KitchenRequest::STATUS_PARTIALLY_ISSUED])
            ->count();

        $pendingPurchaseVouchers = PurchaseVoucher::query()->where('status', PurchaseVoucher::STATUS_PENDING_APPROVAL)->count();
        $approvedPurchaseVouchers = PurchaseVoucher::query()->where('status', PurchaseVoucher::STATUS_APPROVED)->count();
        $awaitingSupplyVouchers = PurchaseVoucher::query()->where('status', PurchaseVoucher::STATUS_SENT_TO_VENDOR)->count();

        $purchasesTodayQuery = Purchase::query()
            ->where('status', Purchase::STATUS_RECEIVED)
            ->whereDate('purchase_date', $today);
        $purchasesToday = (clone $purchasesTodayQuery)->count();
        $purchaseValueToday = (float) (clone $purchasesTodayQuery)->sum('total');

        $wastagesToday = InventoryWastage::query()
            ->where('status', InventoryWastage::STATUS_POSTED)
            ->whereDate('posted_at', $today)
            ->count();

        $recentMovements = StockMovement::query()
            ->with(['sourceLocation', 'destinationLocation', 'performer'])
            ->where('status', StockMovement::STATUS_POSTED)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $lowStocks = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->leftJoin('units as u', 'u.id', '=', 'i.base_unit_id')
            ->where('sl.is_active', true)
            ->where('sl.type', StockLocation::TYPE_MAIN)
            ->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')
            ->select('i.name as ingredient_name', 'ib.quantity_base', 'i.low_stock_level_base', 'u.symbol as unit_symbol')
            ->orderBy('ib.quantity_base')
            ->limit(8)
            ->get();

        return view('admin.inventory.dashboard.index', compact(
            'storeStockItems', 'kitchenStockItems', 'lowStockCount', 'negativeStockCount',
            'pendingRequests', 'pendingPurchaseVouchers', 'approvedPurchaseVouchers', 'awaitingSupplyVouchers',
            'purchasesToday', 'purchaseValueToday', 'wastagesToday',
            'recentMovements', 'lowStocks'
        ));
    }

    public function kitchen(InventorySiteContext $site)
    {
        $site->ensureDefaultLocations();
        $user = auth()->user();
        $kitchen = StockLocation::query()
            ->where('type', StockLocation::TYPE_KITCHEN)
            ->where('is_active', true)
            ->first();

        $kotCounts = OrderKot::query()
            ->whereIn('kitchen_status', ['Pending', 'Cooking', 'Ready'])
            ->selectRaw("SUM(CASE WHEN kitchen_status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN kitchen_status = 'Cooking' THEN 1 ELSE 0 END) as cooking_count")
            ->selectRaw("SUM(CASE WHEN kitchen_status = 'Ready' THEN 1 ELSE 0 END) as ready_count")
            ->first();

        $kitchenBalanceBase = DB::table('inventory_balances as ib')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->when($kitchen, fn ($q) => $q->where('ib.stock_location_id', $kitchen->id));

        $kitchenStockItems = (clone $kitchenBalanceBase)->where('ib.quantity_base', '!=', 0)->count();
        $kitchenLowStock = (clone $kitchenBalanceBase)
            ->where('ib.quantity_base', '>=', 0)
            ->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')
            ->count();

        $requestBase = KitchenRequest::query()->where('requested_by', $user?->id);
        $requestCounts = [
            'draft' => (clone $requestBase)->where('status', KitchenRequest::STATUS_DRAFT)->count(),
            'submitted' => (clone $requestBase)->where('status', KitchenRequest::STATUS_SUBMITTED)->count(),
            'partial' => (clone $requestBase)->where('status', KitchenRequest::STATUS_PARTIALLY_ISSUED)->count(),
            'assigned' => (clone $requestBase)->where('status', KitchenRequest::STATUS_FULLY_ISSUED)->count(),
        ];

        $wastageToday = InventoryWastage::query()
            ->when($kitchen, fn ($q) => $q->where('location_id', $kitchen->id))
            ->where('status', InventoryWastage::STATUS_POSTED)
            ->whereDate('posted_at', now()->toDateString())
            ->count();

        $recentMovements = StockMovement::query()
            ->with(['sourceLocation', 'destinationLocation', 'items.ingredient.baseUnit'])
            ->where('status', StockMovement::STATUS_POSTED)
            ->when($kitchen, function ($q) use ($kitchen) {
                $q->where(fn ($movement) => $movement
                    ->where('source_location_id', $kitchen->id)
                    ->orWhere('destination_location_id', $kitchen->id));
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('admin.inventory.dashboard.kitchen', [
            'pendingKot' => (int) ($kotCounts->pending_count ?? 0),
            'cookingKot' => (int) ($kotCounts->cooking_count ?? 0),
            'readyKot' => (int) ($kotCounts->ready_count ?? 0),
            'kitchenStockItems' => $kitchenStockItems,
            'kitchenLowStock' => $kitchenLowStock,
            'requestCounts' => $requestCounts,
            'wastageToday' => $wastageToday,
            'recentMovements' => $recentMovements,
        ]);
    }
}
