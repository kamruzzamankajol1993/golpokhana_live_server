<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(
        private StockMovementService $movements,
        private DecimalQuantity $decimal
    ) {
    }

    public function postPhysicalCount(int $branchId, int $locationId, array $rows, string $reason, ?int $userId): InventoryAdjustment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for physical stock adjustment.']);
        }

        return DB::transaction(function () use ($branchId, $locationId, $rows, $reason, $userId) {
            $location = StockLocation::query()->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)->whereKey($locationId)->where('is_active', true)->firstOrFail();

            $physicalByIngredient = $this->normalizeRows($rows);
            $ingredientIds = array_keys($physicalByIngredient);

            foreach ($ingredientIds as $ingredientId) {
                DB::table('inventory_balances')->insertOrIgnore([
                    'branch_id' => $branchId,
                    'stock_location_id' => $location->id,
                    'ingredient_id' => $ingredientId,
                    'quantity_base' => '0.00000000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $balances = InventoryBalance::query()->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)
                ->where('stock_location_id', $location->id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->orderBy('ingredient_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('ingredient_id');

            $adjustment = InventoryAdjustment::query()->withoutGlobalScope(BranchScope::class)->create([
                'branch_id' => $branchId,
                'location_id' => $location->id,
                'adjustment_no' => $this->number($branchId),
                'reason' => $reason,
                'status' => InventoryAdjustment::STATUS_DRAFT,
                'created_by' => $userId,
                'approved_by' => $userId,
            ]);

            $positive = [];
            $negative = [];
            foreach ($physicalByIngredient as $ingredientId => $physical) {
                $system = $this->decimal->normalize((string) ($balances->get($ingredientId)?->quantity_base ?? '0'));
                $difference = $this->decimal->subtract($physical, $system);

                $adjustment->items()->create([
                    'ingredient_id' => $ingredientId,
                    'system_qty_base' => $system,
                    'physical_qty_base' => $physical,
                    'difference_base' => $difference,
                ]);

                $cmp = $this->decimal->compare($difference, '0');
                if ($cmp > 0) {
                    $positive[] = ['ingredient_id' => $ingredientId, 'quantity_base' => $difference];
                } elseif ($cmp < 0) {
                    $negative[] = ['ingredient_id' => $ingredientId, 'quantity_base' => $this->decimal->subtract('0', $difference)];
                }
            }

            if ($positive !== []) {
                $this->movements->post(
                    $branchId,
                    StockMovement::POSITIVE_ADJUSTMENT,
                    $positive,
                    null,
                    (int) $location->id,
                    [
                        'reference_type' => InventoryAdjustment::class,
                        'reference_id' => $adjustment->id,
                        'performed_by' => $userId,
                        'reason' => $reason,
                    ]
                );
            }
            if ($negative !== []) {
                $this->movements->post(
                    $branchId,
                    StockMovement::NEGATIVE_ADJUSTMENT,
                    $negative,
                    (int) $location->id,
                    null,
                    [
                        'reference_type' => InventoryAdjustment::class,
                        'reference_id' => $adjustment->id,
                        'performed_by' => $userId,
                        'reason' => $reason,
                    ]
                );
            }

            $adjustment->status = InventoryAdjustment::STATUS_POSTED;
            $adjustment->posted_at = now();
            $adjustment->save();

            return $adjustment->fresh(['branch', 'location', 'creator', 'approver', 'items.ingredient.baseUnit']);
        }, 5);
    }

    private function normalizeRows(array $rows): array
    {
        $result = [];
        foreach ($rows as $index => $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $physical = trim((string) ($row['physical_qty_base'] ?? ''));
            if ($ingredientId < 1 && $physical === '') {
                continue;
            }
            if ($ingredientId < 1 || $physical === '') {
                throw ValidationException::withMessages(["items.{$index}" => 'Each adjustment row requires ingredient and physical quantity.']);
            }
            if (isset($result[$ingredientId])) {
                throw ValidationException::withMessages(['items' => 'The same ingredient cannot appear twice in one adjustment.']);
            }
            $ingredient = Ingredient::query()->whereKey($ingredientId)->firstOrFail();
            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages(["items.{$index}.ingredient_id" => 'Only active inventory-tracked ingredients can be adjusted.']);
            }
            $physical = $this->decimal->normalize($physical);
            if ($this->decimal->compare($physical, '0') < 0) {
                throw ValidationException::withMessages(["items.{$index}.physical_qty_base" => 'Physical count cannot be negative.']);
            }
            $result[$ingredientId] = $physical;
        }
        if ($result === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one physical count row.']);
        }
        ksort($result, SORT_NUMERIC);
        return $result;
    }

    private function number(int $branchId): string
    {
        return 'ADJ-' . $branchId . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
    }
}
