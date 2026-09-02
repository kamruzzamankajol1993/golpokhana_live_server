<?php

namespace App\Services\Inventory;

use App\Models\InventoryException;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function __construct(
        private DecimalQuantity $decimal,
        private InventoryReconciliationCalculator $reconciliationCalculator
    ) {
    }

    public function resolveDateRange(?string $dateFrom, ?string $dateTo, int $defaultDays = 30): array
    {
        $end = $this->parseDate($dateTo) ?? now();
        $start = $this->parseDate($dateFrom) ?? $end->copy()->subDays(max(0, $defaultDays - 1));

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
    }

    public function overviewSummary(?int $branchId): array
    {
        $balances = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id');
        $this->branch($balances, $branchId, 'ib.branch_id');

        $mainRows = (clone $balances)->where('sl.type', StockLocation::TYPE_MAIN)->count();
        $kitchenRows = (clone $balances)->where('sl.type', StockLocation::TYPE_KITCHEN)->count();
        $negativeRows = (clone $balances)->where('ib.quantity_base', '<', 0)->count();
        $lowRows = (clone $balances)
            ->where('ib.quantity_base', '>=', 0)
            ->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')
            ->count();

        $exceptions = DB::table('inventory_exceptions')->where('status', InventoryException::STATUS_OPEN);
        $this->branch($exceptions, $branchId);

        return [
            'main_rows' => $mainRows,
            'kitchen_rows' => $kitchenRows,
            'low_rows' => $lowRows,
            'negative_rows' => $negativeRows,
            'open_exceptions' => $exceptions->count(),
        ];
    }

    public function stockRows(?int $branchId, array $filters = [], int $perPage = 30)
    {
        $query = DB::table('inventory_balances as ib')
            ->join('branches as b', 'b.id', '=', 'ib.branch_id')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->select([
                'ib.id', 'ib.branch_id', 'b.name as branch_name', 'sl.id as location_id', 'sl.name as location_name',
                'sl.type as location_type', 'i.id as ingredient_id', 'i.name as ingredient_name', 'i.code as ingredient_code',
                'ib.quantity_base', 'i.low_stock_level_base', 'u.symbol as base_unit_symbol',
            ])
            ->selectRaw("CASE WHEN ib.quantity_base < 0 THEN 'NEGATIVE' WHEN ib.quantity_base <= i.low_stock_level_base THEN 'LOW' ELSE 'OK' END AS inventory_state");

        $this->branch($query, $branchId, 'ib.branch_id');

        if (!empty($filters['location_type']) && in_array($filters['location_type'], [StockLocation::TYPE_MAIN, StockLocation::TYPE_KITCHEN], true)) {
            $query->where('sl.type', $filters['location_type']);
        }
        if (!empty($filters['state']) && in_array($filters['state'], ['OK', 'LOW', 'NEGATIVE'], true)) {
            if ($filters['state'] === 'NEGATIVE') {
                $query->where('ib.quantity_base', '<', 0);
            } elseif ($filters['state'] === 'LOW') {
                $query->where('ib.quantity_base', '>=', 0)->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base');
            } else {
                $query->whereColumn('ib.quantity_base', '>', 'i.low_stock_level_base');
            }
        }
        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('i.name', 'like', $search)->orWhere('i.code', 'like', $search);
            });
        }

        return $query->orderBy('b.name')->orderBy('sl.type')->orderBy('i.name')->paginate($perPage);
    }

    public function usageRows(?int $branchId, Carbon $start, Carbon $end, int $perPage = 30)
    {
        $query = DB::table('stock_movements as sm')
            ->join('stock_movement_items as smi', 'smi.stock_movement_id', '=', 'sm.id')
            ->join('branches as b', 'b.id', '=', 'sm.branch_id')
            ->join('ingredients as i', 'i.id', '=', 'smi.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->where('sm.status', StockMovement::STATUS_POSTED)
            ->whereBetween('sm.occurred_at', [$start, $end])
            ->whereIn('sm.movement_type', [
                StockMovement::PURCHASE_RECEIVE,
                StockMovement::ORDER_CONSUMPTION,
                StockMovement::WASTAGE,
            ]);
        $this->branch($query, $branchId, 'sm.branch_id');

        return $query
            ->groupBy('sm.branch_id', 'b.name', 'i.id', 'i.name', 'i.code', 'u.symbol')
            ->select([
                'sm.branch_id', 'b.name as branch_name', 'i.id as ingredient_id', 'i.name as ingredient_name',
                'i.code as ingredient_code', 'u.symbol as base_unit_symbol',
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.movement_type = ? THEN smi.quantity_base ELSE 0 END), 0) AS purchased_base', [StockMovement::PURCHASE_RECEIVE])
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.movement_type = ? THEN smi.quantity_base ELSE 0 END), 0) AS consumed_base', [StockMovement::ORDER_CONSUMPTION])
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.movement_type = ? THEN smi.quantity_base ELSE 0 END), 0) AS wastage_base', [StockMovement::WASTAGE])
            ->orderBy('b.name')->orderBy('i.name')
            ->paginate($perPage);
    }

    public function foodUsageRows(?int $branchId, Carbon $start, Carbon $end, int $limit = 150): Collection
    {
        $query = DB::table('order_inventory_consumption_items as oici')
            ->join('order_inventory_consumptions as oic', 'oic.id', '=', 'oici.order_inventory_consumption_id')
            ->join('branches as b', 'b.id', '=', 'oic.branch_id')
            ->join('food_items as f', 'f.id', '=', 'oici.menu_item_id')
            ->join('ingredients as i', 'i.id', '=', 'oici.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->whereBetween('oic.consumed_at', [$start, $end]);
        $this->branch($query, $branchId, 'oic.branch_id');

        return $query
            ->groupBy('oic.branch_id', 'b.name', 'f.id', 'f.name', 'i.id', 'i.name', 'u.symbol')
            ->select([
                'oic.branch_id', 'b.name as branch_name', 'f.id as menu_item_id', 'f.name as menu_item_name',
                'i.id as ingredient_id', 'i.name as ingredient_name', 'u.symbol as base_unit_symbol',
            ])
            ->selectRaw('SUM(oici.quantity_base) AS consumed_base')
            ->orderByDesc('consumed_base')
            ->limit($limit)
            ->get();
    }

    public function requestVarianceRows(?int $branchId, Carbon $start, Carbon $end, int $perPage = 30)
    {
        $query = DB::table('kitchen_request_ingredient_items as kri')
            ->join('kitchen_requests as kr', 'kr.id', '=', 'kri.kitchen_request_id')
            ->join('branches as b', 'b.id', '=', 'kr.branch_id')
            ->join('ingredients as i', 'i.id', '=', 'kri.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->whereBetween('kr.request_date', [$start->toDateString(), $end->toDateString()]);
        $this->branch($query, $branchId, 'kr.branch_id');

        return $query
            ->select([
                'kr.id as kitchen_request_id', 'kr.request_no', 'kr.request_date', 'kr.request_type', 'kr.status',
                'kr.branch_id', 'b.name as branch_name', 'i.id as ingredient_id', 'i.name as ingredient_name',
                'u.symbol as base_unit_symbol', 'kri.source_kind', 'kri.required_base_qty', 'kri.approved_base_qty', 'kri.issued_base_qty',
            ])
            ->selectRaw('(kri.required_base_qty - kri.issued_base_qty) AS shortage_base')
            ->orderByDesc('kr.request_date')->orderByDesc('kr.id')->orderBy('i.name')
            ->paginate($perPage);
    }

    public function reconciliationRows(?int $branchId, Carbon $start, Carbon $end, int $perPage = 30)
    {
        // Query builder cannot safely correlate the stock location alias from the outer
        // query into a joinSub across all supported Laravel/database versions. Build one
        // aggregate per concrete Kitchen location and merge the rows after pagination.
        $base = DB::table('inventory_balances as ib')
            ->join('branches as b', 'b.id', '=', 'ib.branch_id')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->where('sl.type', StockLocation::TYPE_KITCHEN)
            ->select([
                'ib.branch_id', 'b.name as branch_name', 'sl.id as location_id', 'sl.name as location_name',
                'i.id as ingredient_id', 'i.name as ingredient_name', 'i.code as ingredient_code', 'u.symbol as base_unit_symbol',
            ]);
        $this->branch($base, $branchId, 'ib.branch_id');

        $paginator = $base->orderBy('b.name')->orderBy('i.name')->paginate($perPage);

        foreach ($paginator->items() as $row) {
            $components = $this->reconciliationComponents(
                (int) $row->branch_id,
                (int) $row->location_id,
                (int) $row->ingredient_id,
                $start,
                $end
            );
            foreach ($this->reconciliationCalculator->calculate($components) as $key => $value) {
                $row->{$key} = $value;
            }
            $row->has_variance = $this->decimal->compare((string) $row->variance, '0') !== 0;
        }

        return $paginator;
    }

    public function branchComparisonRows(Carbon $start, Carbon $end, ?int $branchId = null): Collection
    {
        $branches = DB::table('branches')->where('status', 1);
        if ($branchId !== null) {
            $branches->where('id', $branchId);
        }

        return $branches->orderBy('name')->get()->map(function ($branch) use ($start, $end) {
            $id = (int) $branch->id;

            $purchase = DB::table('purchases')
                ->where('branch_id', $id)->where('status', 'RECEIVED')->whereBetween('received_at', [$start, $end]);
            $consumptions = DB::table('order_inventory_consumptions')
                ->where('branch_id', $id)->whereBetween('consumed_at', [$start, $end]);
            $movements = DB::table('stock_movements')
                ->where('branch_id', $id)->where('status', StockMovement::STATUS_POSTED)->whereBetween('occurred_at', [$start, $end]);
            $balance = DB::table('inventory_balances as ib')
                ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
                ->where('ib.branch_id', $id);

            return (object) [
                'branch_id' => $id,
                'branch_name' => $branch->name,
                'purchase_count' => (clone $purchase)->count(),
                'purchase_value' => (string) ((clone $purchase)->sum('total') ?: '0'),
                'consumed_orders' => (clone $consumptions)->count(),
                'movement_count' => (clone $movements)->count(),
                'transfer_count' => (clone $movements)->whereIn('movement_type', [StockMovement::MAIN_TO_KITCHEN, StockMovement::KITCHEN_TO_MAIN])->count(),
                'wastage_count' => (clone $movements)->where('movement_type', StockMovement::WASTAGE)->count(),
                'low_stock_rows' => (clone $balance)->where('ib.quantity_base', '>=', 0)->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')->count(),
                'negative_stock_rows' => (clone $balance)->where('ib.quantity_base', '<', 0)->count(),
                'open_exceptions' => DB::table('inventory_exceptions')->where('branch_id', $id)->where('status', InventoryException::STATUS_OPEN)->count(),
            ];
        });
    }

    public function qaChecks(): Collection
    {
        $checks = collect();

        $missingLocations = DB::table('branches as b')
            ->where('b.status', 1)
            ->where(function ($q) {
                $q->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')->from('stock_locations as sl')->whereColumn('sl.branch_id', 'b.id')->where('sl.code', 'MAIN')->where('sl.is_active', 1);
                })->orWhereNotExists(function ($sub) {
                    $sub->selectRaw('1')->from('stock_locations as sl')->whereColumn('sl.branch_id', 'b.id')->where('sl.code', 'KITCHEN')->where('sl.is_active', 1);
                });
            })->count();
        $checks->push($this->check('Default MAIN/KITCHEN locations', $missingLocations === 0, $missingLocations, 'Active branches missing MAIN or KITCHEN stock location.'));

        $requiredPermissions = [
            'inventory-view', 'inventory-units-manage', 'inventory-ingredients-manage', 'inventory-vendors-manage',
            'inventory-purchase-create', 'inventory-purchase-receive', 'inventory-kitchen-request-create',
            'inventory-kitchen-request-review', 'inventory-transfer-post', 'inventory-return-post',
            'inventory-wastage-post', 'inventory-adjustment-post', 'inventory-reports-view', 'inventory-branch-all-view',
        ];
        $existingPermissionCount = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $requiredPermissions)->count();
        $missingPermissionCount = count($requiredPermissions) - $existingPermissionCount;
        $checks->push($this->check('Inventory permission set', $missingPermissionCount === 0, $missingPermissionCount, 'Required inventory permissions missing from the web guard.'));

        $superRoleIds = DB::table('roles')->where('guard_name', 'web')->whereIn('name', ['Super Admin', 'Super Admin Limited'])->pluck('id');
        $inventoryPermissionIds = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $requiredPermissions)->pluck('id');
        $expectedAssignments = $superRoleIds->count() * $inventoryPermissionIds->count();
        $actualAssignments = $expectedAssignments === 0 ? 0 : DB::table('role_has_permissions')
            ->whereIn('role_id', $superRoleIds)->whereIn('permission_id', $inventoryPermissionIds)->count();
        $missingAssignments = max(0, $expectedAssignments - $actualAssignments);
        $checks->push($this->check('Super Admin inventory permissions', $missingAssignments === 0, $missingAssignments, 'Missing inventory permission assignments on Super Admin roles.'));

        $balanceBranchMismatch = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->whereColumn('ib.branch_id', '<>', 'sl.branch_id')->count();
        $checks->push($this->check('Balance branch isolation', $balanceBranchMismatch === 0, $balanceBranchMismatch, 'Balance rows whose location belongs to another branch.'));

        $movementSourceMismatch = DB::table('stock_movements as sm')
            ->join('stock_locations as sl', 'sl.id', '=', 'sm.source_location_id')
            ->whereColumn('sm.branch_id', '<>', 'sl.branch_id')->count();
        $movementDestinationMismatch = DB::table('stock_movements as sm')
            ->join('stock_locations as sl', 'sl.id', '=', 'sm.destination_location_id')
            ->whereColumn('sm.branch_id', '<>', 'sl.branch_id')->count();
        $movementMismatch = $movementSourceMismatch + $movementDestinationMismatch;
        $checks->push($this->check('Ledger branch isolation', $movementMismatch === 0, $movementMismatch, 'Movement source/destination location branch mismatch.'));

        $receivedWithoutMovement = DB::table('purchases')->where('status', 'RECEIVED')->whereNull('received_stock_movement_id')->count();
        $checks->push($this->check('Received purchase linkage', $receivedWithoutMovement === 0, $receivedWithoutMovement, 'Received purchases missing their immutable receive movement.'));

        $draftPurchaseWithMovement = DB::table('purchases')->where('status', 'DRAFT')->whereNotNull('received_stock_movement_id')->count();
        $checks->push($this->check('Draft purchase stock isolation', $draftPurchaseWithMovement === 0, $draftPurchaseWithMovement, 'Draft purchases that already point to a stock receive movement.'));

        $postedTransferWithoutMovement = DB::table('stock_transfers')->where('status', 'POSTED')->whereNull('posted_movement_id')->count();
        $checks->push($this->check('Posted transfer linkage', $postedTransferWithoutMovement === 0, $postedTransferWithoutMovement, 'Posted transfers missing their immutable movement.'));

        $draftTransferWithMovement = DB::table('stock_transfers')->where('status', 'DRAFT')->whereNotNull('posted_movement_id')->count();
        $checks->push($this->check('Draft transfer stock isolation', $draftTransferWithMovement === 0, $draftTransferWithMovement, 'Draft transfers that already point to a posted stock movement.'));

        $consumptionBranchMismatch = DB::table('order_inventory_consumptions as oic')
            ->join('orders as o', 'o.id', '=', 'oic.order_id')
            ->whereColumn('oic.branch_id', '<>', 'o.branch_id')->count();
        $checks->push($this->check('Order consumption branch mapping', $consumptionBranchMismatch === 0, $consumptionBranchMismatch, 'Consumed order branch does not match inventory consumption branch.'));

        $duplicateConsumption = DB::table('order_inventory_consumptions')
            ->select('order_id')->groupBy('order_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $checks->push($this->check('Order consumption idempotency', $duplicateConsumption === 0, $duplicateConsumption, 'Orders with more than one inventory consumption header.'));

        $trackingWithoutRecipe = DB::table('food_items as f')
            ->where('f.inventory_tracking', 1)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('menu_item_recipes as r')->whereColumn('r.menu_item_id', 'f.id')->where('r.is_active', 1);
            })->count();
        $checks->push($this->check('Tracked menu recipe readiness', $trackingWithoutRecipe === 0, $trackingWithoutRecipe, 'Inventory-tracked menu items without an active recipe.'));

        $negativeWithoutException = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->where('sl.type', StockLocation::TYPE_KITCHEN)
            ->where('ib.quantity_base', '<', 0)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('inventory_exceptions as ie')
                    ->whereColumn('ie.branch_id', 'ib.branch_id')
                    ->whereColumn('ie.location_id', 'ib.stock_location_id')
                    ->whereColumn('ie.ingredient_id', 'ib.ingredient_id')
                    ->where('ie.exception_type', InventoryException::NEGATIVE_KITCHEN_STOCK)
                    ->where('ie.status', InventoryException::STATUS_OPEN);
            })->count();
        $checks->push($this->check('Negative-stock exception coverage', $negativeWithoutException === 0, $negativeWithoutException, 'Negative Kitchen balances without an open exception.'));

        return $checks;
    }

    private function reconciliationComponents(int $branchId, int $locationId, int $ingredientId, Carbon $start, Carbon $end): array
    {
        $opening = DB::table('stock_movements as sm')
            ->join('stock_movement_items as smi', 'smi.stock_movement_id', '=', 'sm.id')
            ->where('sm.branch_id', $branchId)
            ->where('smi.ingredient_id', $ingredientId)
            ->where('sm.status', StockMovement::STATUS_POSTED)
            ->where('sm.occurred_at', '<', $start)
            ->where(function ($q) use ($locationId) {
                $q->where('sm.source_location_id', $locationId)->orWhere('sm.destination_location_id', $locationId);
            })
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.destination_location_id = ? THEN smi.quantity_base WHEN sm.source_location_id = ? THEN -smi.quantity_base ELSE 0 END), 0) AS qty', [$locationId, $locationId])
            ->value('qty') ?? '0';

        $rows = DB::table('stock_movements as sm')
            ->join('stock_movement_items as smi', 'smi.stock_movement_id', '=', 'sm.id')
            ->where('sm.branch_id', $branchId)
            ->where('smi.ingredient_id', $ingredientId)
            ->where('sm.status', StockMovement::STATUS_POSTED)
            ->whereBetween('sm.occurred_at', [$start, $end])
            ->where(function ($q) use ($locationId) {
                $q->where('sm.source_location_id', $locationId)->orWhere('sm.destination_location_id', $locationId);
            })
            ->select('sm.movement_type', 'sm.source_location_id', 'sm.destination_location_id', 'smi.quantity_base')
            ->get();

        $components = [
            'opening' => (string) $opening,
            'transfer_in' => '0',
            'consumption' => '0',
            'wastage' => '0',
            'return_to_main' => '0',
            'positive_adjustment' => '0',
            'negative_adjustment' => '0',
            'other_delta' => '0',
        ];

        foreach ($rows as $movement) {
            $qty = (string) $movement->quantity_base;
            $isSource = (int) $movement->source_location_id === $locationId;
            $isDestination = (int) $movement->destination_location_id === $locationId;

            if ($movement->movement_type === StockMovement::MAIN_TO_KITCHEN && $isDestination) {
                $components['transfer_in'] = $this->decimal->add($components['transfer_in'], $qty);
            } elseif ($movement->movement_type === StockMovement::ORDER_CONSUMPTION && $isSource) {
                $components['consumption'] = $this->decimal->add($components['consumption'], $qty);
            } elseif ($movement->movement_type === StockMovement::WASTAGE && $isSource) {
                $components['wastage'] = $this->decimal->add($components['wastage'], $qty);
            } elseif ($movement->movement_type === StockMovement::KITCHEN_TO_MAIN && $isSource) {
                $components['return_to_main'] = $this->decimal->add($components['return_to_main'], $qty);
            } elseif ($movement->movement_type === StockMovement::POSITIVE_ADJUSTMENT && $isDestination) {
                $components['positive_adjustment'] = $this->decimal->add($components['positive_adjustment'], $qty);
            } elseif ($movement->movement_type === StockMovement::NEGATIVE_ADJUSTMENT && $isSource) {
                $components['negative_adjustment'] = $this->decimal->add($components['negative_adjustment'], $qty);
            } else {
                $signed = $isDestination ? $qty : ($isSource ? '-' . ltrim($qty, '+-') : '0');
                $components['other_delta'] = $this->decimal->add($components['other_delta'], $signed);
            }
        }

        return $components;
    }

    private function branch(Builder $query, ?int $branchId, string $column = 'branch_id'): void
    {
        if ($branchId !== null) {
            $query->where($column, $branchId);
        }
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function check(string $name, bool $passed, int $issues, string $description): array
    {
        return compact('name', 'passed', 'issues', 'description');
    }
}
