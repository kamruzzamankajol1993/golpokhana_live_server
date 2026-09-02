<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryException;
use App\Models\InventoryWastage;
use App\Models\KitchenRequest;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;

class InventoryDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-dashboard-view');
    }

    public function index(BranchContext $context)
    {
        $branchId = $context->branchId();
        $today = now()->toDateString();
        $scopeLabel = $branchId
            ? (Branch::query()->find($branchId)?->name ?: 'Assigned Branch')
            : 'All Branches';

        $balanceBase = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->where('sl.is_active', true)
            ->when($branchId, fn ($q) => $q->where('ib.branch_id', $branchId));

        $mainStockItems = (clone $balanceBase)->where('sl.type', 'MAIN')->where('ib.quantity_base', '!=', 0)->count();
        $kitchenStockItems = (clone $balanceBase)->where('sl.type', 'KITCHEN')->where('ib.quantity_base', '!=', 0)->count();
        $lowStockCount = (clone $balanceBase)
            ->where('ib.quantity_base', '>=', 0)
            ->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')
            ->count();
        $negativeStockCount = (clone $balanceBase)->where('ib.quantity_base', '<', 0)->count();

        $pendingRequests = KitchenRequest::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', [KitchenRequest::STATUS_SUBMITTED, KitchenRequest::STATUS_PARTIALLY_ISSUED])
            ->count();

        $purchasesTodayQuery = Purchase::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', Purchase::STATUS_RECEIVED)
            ->whereDate('purchase_date', $today);
        $purchasesToday = (clone $purchasesTodayQuery)->count();
        $purchaseValueToday = (float) (clone $purchasesTodayQuery)->sum('total');

        $transfersToday = StockTransfer::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', StockTransfer::STATUS_POSTED)
            ->whereDate('posted_at', $today)
            ->count();

        $wastagesToday = InventoryWastage::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', InventoryWastage::STATUS_POSTED)
            ->whereDate('posted_at', $today)
            ->count();

        $openExceptions = InventoryException::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', InventoryException::STATUS_OPEN)
            ->count();

        $recentMovements = StockMovement::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['branch', 'sourceLocation', 'destinationLocation', 'performer'])
            ->where('status', StockMovement::STATUS_POSTED)
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(8)->get();

        $lowStocks = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->leftJoin('units as u', 'u.id', '=', 'i.base_unit_id')
            ->leftJoin('branches as b', 'b.id', '=', 'ib.branch_id')
            ->when($branchId, fn ($q) => $q->where('ib.branch_id', $branchId))
            ->where('sl.is_active', true)
            ->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')
            ->select('b.name as branch_name', 'sl.name as location_name', 'i.name as ingredient_name', 'ib.quantity_base', 'i.low_stock_level_base', 'u.symbol as unit_symbol')
            ->orderBy('ib.quantity_base')->limit(8)->get();

        return view('admin.inventory.dashboard.index', compact(
            'scopeLabel', 'branchId', 'mainStockItems', 'kitchenStockItems', 'lowStockCount',
            'negativeStockCount', 'pendingRequests', 'purchasesToday', 'purchaseValueToday',
            'transfersToday', 'wastagesToday', 'openExceptions', 'recentMovements', 'lowStocks'
        ) + ['showBranchColumn' => $branchId === null]);
    }
}
