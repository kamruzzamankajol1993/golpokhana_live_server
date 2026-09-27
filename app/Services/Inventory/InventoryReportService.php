<?php

namespace App\Services\Inventory;

use App\Models\InventoryException;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function __construct(
        private DecimalQuantity $decimal,
        private InventoryReconciliationCalculator $reconciliationCalculator
    ) {}

    public function resolveDateRange(?string $dateFrom, ?string $dateTo, int $defaultDays = 30): array
    {
        $end = $this->parseDate($dateTo) ?? now();
        $start = $this->parseDate($dateFrom) ?? $end->copy()->subDays(max(0, $defaultDays - 1));
        if ($start->gt($end)) [$start, $end] = [$end, $start];
        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
    }

    public function overviewSummary(): array
    {
        $balances = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id');

        return [
            'main_rows' => (clone $balances)->where('sl.type', StockLocation::TYPE_MAIN)->count(),
            'kitchen_rows' => (clone $balances)->where('sl.type', StockLocation::TYPE_KITCHEN)->count(),
            'low_rows' => (clone $balances)->where('ib.quantity_base', '>=', 0)->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base')->count(),
            'negative_rows' => (clone $balances)->where('ib.quantity_base', '<', 0)->count(),
            'open_exceptions' => DB::table('inventory_exceptions')->where('status', InventoryException::STATUS_OPEN)->count(),
        ];
    }

    public function stockRows(array $filters = [], int $perPage = 30)
    {
        $query = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->select([
                'ib.id', 'sl.id as location_id', 'sl.name as location_name', 'sl.type as location_type',
                'i.id as ingredient_id', 'i.name as ingredient_name', 'i.code as ingredient_code',
                'ib.quantity_base', 'i.low_stock_level_base', 'u.symbol as base_unit_symbol',
            ])
            ->selectRaw("CASE WHEN ib.quantity_base < 0 THEN 'NEGATIVE' WHEN ib.quantity_base <= i.low_stock_level_base THEN 'LOW' ELSE 'OK' END AS inventory_state");

        if (!empty($filters['location_type']) && in_array($filters['location_type'], [StockLocation::TYPE_MAIN, StockLocation::TYPE_KITCHEN], true)) {
            $query->where('sl.type', $filters['location_type']);
        }
        if (!empty($filters['state']) && in_array($filters['state'], ['OK', 'LOW', 'NEGATIVE'], true)) {
            if ($filters['state'] === 'NEGATIVE') $query->where('ib.quantity_base', '<', 0);
            elseif ($filters['state'] === 'LOW') $query->where('ib.quantity_base', '>=', 0)->whereColumn('ib.quantity_base', '<=', 'i.low_stock_level_base');
            else $query->whereColumn('ib.quantity_base', '>', 'i.low_stock_level_base');
        }
        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $query->where(fn ($q) => $q->where('i.name', 'like', $search)->orWhere('i.code', 'like', $search));
        }

        return $query->orderBy('sl.type')->orderBy('i.name')->paginate($perPage);
    }

    public function usageRows(Carbon $start, Carbon $end, int $perPage = 30)
    {
        return DB::table('stock_movements as sm')
            ->join('stock_movement_items as smi', 'smi.stock_movement_id', '=', 'sm.id')
            ->join('ingredients as i', 'i.id', '=', 'smi.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->where('sm.status', StockMovement::STATUS_POSTED)
            ->whereBetween('sm.occurred_at', [$start, $end])
            ->whereIn('sm.movement_type', [StockMovement::PURCHASE_RECEIVE, StockMovement::ORDER_CONSUMPTION, StockMovement::WASTAGE])
            ->groupBy('i.id', 'i.name', 'i.code', 'u.symbol')
            ->select(['i.id as ingredient_id', 'i.name as ingredient_name', 'i.code as ingredient_code', 'u.symbol as base_unit_symbol'])
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.movement_type = ? THEN smi.quantity_base ELSE 0 END), 0) AS purchased_base', [StockMovement::PURCHASE_RECEIVE])
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.movement_type = ? THEN smi.quantity_base ELSE 0 END), 0) AS consumed_base', [StockMovement::ORDER_CONSUMPTION])
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.movement_type = ? THEN smi.quantity_base ELSE 0 END), 0) AS wastage_base', [StockMovement::WASTAGE])
            ->orderBy('i.name')->paginate($perPage);
    }

    public function foodUsageRows(Carbon $start, Carbon $end, int $limit = 150): Collection
    {
        return DB::table('order_inventory_consumption_items as oici')
            ->join('order_inventory_consumptions as oic', 'oic.id', '=', 'oici.order_inventory_consumption_id')
            ->join('food_items as f', 'f.id', '=', 'oici.menu_item_id')
            ->join('ingredients as i', 'i.id', '=', 'oici.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->whereBetween('oic.consumed_at', [$start, $end])
            ->groupBy('f.id', 'f.name', 'i.id', 'i.name', 'u.symbol')
            ->select(['f.id as menu_item_id', 'f.name as menu_item_name', 'i.id as ingredient_id', 'i.name as ingredient_name', 'u.symbol as base_unit_symbol'])
            ->selectRaw('SUM(oici.quantity_base) AS consumed_base')
            ->orderByDesc('consumed_base')->limit($limit)->get();
    }

    public function requestVarianceRows(Carbon $start, Carbon $end, int $perPage = 30)
    {
        return DB::table('kitchen_request_ingredient_items as kri')
            ->join('kitchen_requests as kr', 'kr.id', '=', 'kri.kitchen_request_id')
            ->join('ingredients as i', 'i.id', '=', 'kri.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->whereBetween('kr.request_date', [$start->toDateString(), $end->toDateString()])
            ->select([
                'kr.id as kitchen_request_id', 'kr.request_no', 'kr.request_date', 'kr.request_type', 'kr.status',
                'i.id as ingredient_id', 'i.name as ingredient_name', 'u.symbol as base_unit_symbol',
                'kri.source_kind', 'kri.required_base_qty', 'kri.approved_base_qty', 'kri.issued_base_qty',
            ])
            ->selectRaw('(kri.required_base_qty - kri.issued_base_qty) AS shortage_base')
            ->orderByDesc('kr.request_date')->orderByDesc('kr.id')->orderBy('i.name')->paginate($perPage);
    }

    public function reconciliationRows(Carbon $start, Carbon $end, int $perPage = 30)
    {
        $paginator = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->join('ingredients as i', 'i.id', '=', 'ib.ingredient_id')
            ->join('units as u', 'u.id', '=', 'i.base_unit_id')
            ->where('sl.type', StockLocation::TYPE_KITCHEN)
            ->select([
                'sl.id as location_id', 'sl.name as location_name', 'i.id as ingredient_id', 'i.name as ingredient_name',
                'i.code as ingredient_code', 'u.symbol as base_unit_symbol',
            ])
            ->orderBy('i.name')->paginate($perPage);

        foreach ($paginator->items() as $row) {
            $components = $this->reconciliationComponents((int) $row->location_id, (int) $row->ingredient_id, $start, $end);
            foreach ($this->reconciliationCalculator->calculate($components) as $key => $value) $row->{$key} = $value;
            $row->has_variance = $this->decimal->compare((string) $row->variance, '0') !== 0;
        }
        return $paginator;
    }

    public function qaChecks(): Collection
    {
        $checks = collect();

        $missingLocations = 0;
        foreach (['MAIN', 'KITCHEN'] as $code) {
            if (!DB::table('stock_locations')->where('code', $code)->where('is_active', 1)->exists()) $missingLocations++;
        }
        $checks->push($this->check('Default MAIN/KITCHEN locations', $missingLocations === 0, $missingLocations, 'The restaurant is missing a MAIN or KITCHEN stock location.'));

        $requiredPermissions = [
            'inventory-view', 'inventory-units-manage', 'inventory-ingredients-manage', 'inventory-vendors-manage',
            'inventory-purchase-create', 'inventory-purchase-receive', 'inventory-kitchen-request-create',
            'inventory-kitchen-request-review', 'inventory-transfer-post', 'inventory-return-post',
            'inventory-wastage-view', 'inventory-wastage-create', 'inventory-wastage-edit', 'inventory-wastage-delete',
            'inventory-adjustment-post', 'inventory-reports-view',
        ];
        $existingPermissionCount = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $requiredPermissions)->count();
        $missingPermissionCount = count($requiredPermissions) - $existingPermissionCount;
        $checks->push($this->check('Inventory permission set', $missingPermissionCount === 0, $missingPermissionCount, 'Required inventory permissions missing from the web guard.'));

        $receivedWithoutMovement = DB::table('purchases')->where('status', 'RECEIVED')->whereNull('received_stock_movement_id')->count();
        $checks->push($this->check('Received purchase linkage', $receivedWithoutMovement === 0, $receivedWithoutMovement, 'Received purchases missing their immutable receive movement.'));
        $postedTransferWithoutMovement = DB::table('stock_transfers')->where('status', 'POSTED')->whereNull('posted_movement_id')->count();
        $checks->push($this->check('Posted transfer linkage', $postedTransferWithoutMovement === 0, $postedTransferWithoutMovement, 'Posted transfers missing their immutable movement.'));
        $duplicateConsumption = DB::table('order_inventory_consumptions')->select('order_id')->groupBy('order_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $checks->push($this->check('Order consumption idempotency', $duplicateConsumption === 0, $duplicateConsumption, 'Orders with more than one inventory consumption header.'));
        $trackingWithoutRecipe = DB::table('food_items as f')->where('f.inventory_tracking', 1)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('menu_item_recipes as r')->whereColumn('r.menu_item_id', 'f.id')->where('r.is_active', 1))->count();
        $checks->push($this->check('Tracked menu recipe readiness', $trackingWithoutRecipe === 0, $trackingWithoutRecipe, 'Inventory-tracked menu items without an active recipe.'));
        $negativeWithoutException = DB::table('inventory_balances as ib')
            ->join('stock_locations as sl', 'sl.id', '=', 'ib.stock_location_id')
            ->where('sl.type', StockLocation::TYPE_KITCHEN)->where('ib.quantity_base', '<', 0)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('inventory_exceptions as ie')
                    ->whereColumn('ie.location_id', 'ib.stock_location_id')
                    ->whereColumn('ie.ingredient_id', 'ib.ingredient_id')
                    ->where('ie.exception_type', InventoryException::NEGATIVE_KITCHEN_STOCK)
                    ->where('ie.status', InventoryException::STATUS_OPEN);
            })->count();
        $checks->push($this->check('Negative-stock exception coverage', $negativeWithoutException === 0, $negativeWithoutException, 'Negative Kitchen balances without an open exception.'));
        return $checks;
    }

    private function reconciliationComponents(int $locationId, int $ingredientId, Carbon $start, Carbon $end): array
    {
        $opening = DB::table('stock_movements as sm')->join('stock_movement_items as smi', 'smi.stock_movement_id', '=', 'sm.id')
            ->where('smi.ingredient_id', $ingredientId)->where('sm.status', StockMovement::STATUS_POSTED)->where('sm.occurred_at', '<', $start)
            ->where(fn ($q) => $q->where('sm.source_location_id', $locationId)->orWhere('sm.destination_location_id', $locationId))
            ->selectRaw('COALESCE(SUM(CASE WHEN sm.destination_location_id = ? THEN smi.quantity_base WHEN sm.source_location_id = ? THEN -smi.quantity_base ELSE 0 END), 0) AS qty', [$locationId, $locationId])
            ->value('qty') ?? '0';

        $rows = DB::table('stock_movements as sm')->join('stock_movement_items as smi', 'smi.stock_movement_id', '=', 'sm.id')
            ->where('smi.ingredient_id', $ingredientId)->where('sm.status', StockMovement::STATUS_POSTED)->whereBetween('sm.occurred_at', [$start, $end])
            ->where(fn ($q) => $q->where('sm.source_location_id', $locationId)->orWhere('sm.destination_location_id', $locationId))
            ->select('sm.movement_type', 'sm.source_location_id', 'sm.destination_location_id', 'smi.quantity_base')->get();

        $components = ['opening'=>(string)$opening,'transfer_in'=>'0','consumption'=>'0','wastage'=>'0','return_to_main'=>'0','positive_adjustment'=>'0','negative_adjustment'=>'0','other_delta'=>'0'];
        foreach ($rows as $movement) {
            $qty=(string)$movement->quantity_base; $isSource=(int)$movement->source_location_id===$locationId; $isDestination=(int)$movement->destination_location_id===$locationId;
            if ($movement->movement_type===StockMovement::MAIN_TO_KITCHEN && $isDestination) $components['transfer_in']=$this->decimal->add($components['transfer_in'],$qty);
            elseif ($movement->movement_type===StockMovement::ORDER_CONSUMPTION && $isSource) $components['consumption']=$this->decimal->add($components['consumption'],$qty);
            elseif ($movement->movement_type===StockMovement::WASTAGE && $isSource) $components['wastage']=$this->decimal->add($components['wastage'],$qty);
            elseif ($movement->movement_type===StockMovement::KITCHEN_TO_MAIN && $isSource) $components['return_to_main']=$this->decimal->add($components['return_to_main'],$qty);
            elseif ($movement->movement_type===StockMovement::POSITIVE_ADJUSTMENT && $isDestination) $components['positive_adjustment']=$this->decimal->add($components['positive_adjustment'],$qty);
            elseif ($movement->movement_type===StockMovement::NEGATIVE_ADJUSTMENT && $isSource) $components['negative_adjustment']=$this->decimal->add($components['negative_adjustment'],$qty);
            else { $signed=$isDestination?$qty:($isSource?'-'.ltrim($qty,'+-'):'0'); $components['other_delta']=$this->decimal->add($components['other_delta'],$signed); }
        }
        return $components;
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value=trim((string)$value); if ($value==='') return null;
        foreach (['Y-m-d','d-m-Y','d/m/Y'] as $format) { try { $date=Carbon::createFromFormat($format,$value); if ($date!==false) return $date; } catch (\Throwable) {} }
        return null;
    }

    private function check(string $name, bool $passed, int $issues, string $description): array
    {
        return compact('name','passed','issues','description');
    }
}
