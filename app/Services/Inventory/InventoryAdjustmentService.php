<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
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

    public function postPhysicalCount(int $locationId, array $rows, string $reason, ?int $userId): InventoryAdjustment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for physical stock adjustment.']);
        }

        return DB::transaction(function () use ($locationId, $rows, $reason, $userId) {
            $location = StockLocation::query()->whereKey($locationId)->where('is_active', true)->firstOrFail();

            $physicalByIngredient = $this->normalizeRows($rows);
            $ingredientIds = array_keys($physicalByIngredient);

            foreach ($ingredientIds as $ingredientId) {
                DB::table('inventory_balances')->insertOrIgnore([
                    'stock_location_id' => $location->id,
                    'ingredient_id' => $ingredientId,
                    'quantity_base' => '0.00000000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $balances = InventoryBalance::query()
                ->where('stock_location_id', $location->id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->orderBy('ingredient_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('ingredient_id');

            $adjustment = InventoryAdjustment::query()->create([
                'location_id' => $location->id,
                'adjustment_no' => $this->number(),
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

            return $adjustment->fresh(['location', 'creator', 'approver', 'items.ingredient.baseUnit']);
        }, 5);
    }


    public function postNegativeKitchenRecovery(
        int $locationId,
        int $ingredientId,
        string|int|float $adjustQuantityBase,
        string $reason,
        ?int $userId
    ): InventoryAdjustment {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for negative Kitchen Stock adjustment.']);
        }

        $adjustQuantity = $this->decimal->normalize((string) $adjustQuantityBase);
        if (!$this->decimal->isPositive($adjustQuantity)) {
            throw ValidationException::withMessages(['adjust_quantity_base' => 'Adjustment quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($locationId, $ingredientId, $adjustQuantity, $reason, $userId) {
            $location = StockLocation::query()->whereKey($locationId)->where('is_active', true)->firstOrFail();
            if ($location->type !== StockLocation::TYPE_KITCHEN) {
                throw ValidationException::withMessages(['location' => 'Negative stock recovery is allowed only for Kitchen Stock.']);
            }

            $ingredient = Ingredient::query()->whereKey($ingredientId)->firstOrFail();
            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages(['ingredient_id' => 'Only active inventory-tracked ingredients can be adjusted.']);
            }

            DB::table('inventory_balances')->insertOrIgnore([
                'stock_location_id' => $location->id,
                'ingredient_id' => $ingredientId,
                'quantity_base' => '0.00000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $balance = InventoryBalance::query()
                ->where('stock_location_id', $location->id)
                ->where('ingredient_id', $ingredientId)
                ->lockForUpdate()
                ->firstOrFail();

            $system = $this->decimal->normalize((string) $balance->quantity_base);
            if ($this->decimal->compare($system, '0') >= 0) {
                throw ValidationException::withMessages(['ingredient_id' => 'This ingredient no longer has negative Kitchen Stock.']);
            }

            $shortage = $this->decimal->subtract('0', $system);
            if ($this->decimal->compare($adjustQuantity, $shortage) > 0) {
                throw ValidationException::withMessages(['adjust_quantity_base' => 'Adjustment quantity cannot exceed the current negative shortage.']);
            }

            $newBalance = $this->decimal->add($system, $adjustQuantity);
            $adjustment = InventoryAdjustment::query()->create([
                'location_id' => $location->id,
                'adjustment_no' => $this->number(),
                'reason' => $reason,
                'status' => InventoryAdjustment::STATUS_DRAFT,
                'created_by' => $userId,
                'approved_by' => $userId,
            ]);

            $adjustment->items()->create([
                'ingredient_id' => $ingredientId,
                'system_qty_base' => $system,
                'physical_qty_base' => $newBalance,
                'difference_base' => $adjustQuantity,
            ]);

            $this->movements->post(
                StockMovement::POSITIVE_ADJUSTMENT,
                [['ingredient_id' => $ingredientId, 'quantity_base' => $adjustQuantity]],
                null,
                (int) $location->id,
                [
                    'reference_type' => InventoryAdjustment::class,
                    'reference_id' => $adjustment->id,
                    'performed_by' => $userId,
                    'reason' => 'Negative Kitchen Stock adjustment: ' . $reason,
                ]
            );

            $adjustment->status = InventoryAdjustment::STATUS_POSTED;
            $adjustment->posted_at = now();
            $adjustment->save();

            $after = $this->decimal->normalize((string) DB::table('inventory_balances')
                ->where('stock_location_id', $location->id)
                ->where('ingredient_id', $ingredientId)
                ->value('quantity_base'));

            $exceptions = DB::table('inventory_exceptions')
                ->where('exception_type', 'NEGATIVE_KITCHEN_STOCK')
                ->where('ingredient_id', $ingredientId)
                ->where('location_id', $location->id)
                ->where('status', 'OPEN');

            if ($this->decimal->compare($after, '0') >= 0) {
                $exceptions->update([
                    'status' => 'RESOLVED',
                    'resolved_at' => now(),
                    'resolved_by' => $userId,
                    'resolution_note' => DB::raw("CONCAT(COALESCE(resolution_note, ''), CASE WHEN COALESCE(resolution_note, '') = '' THEN '' ELSE '\n' END, 'Resolved by negative Kitchen Stock adjustment {$adjustment->adjustment_no}.')"),
                    'updated_at' => now(),
                ]);
            } else {
                $exceptions->update([
                    'shortage_base' => $this->decimal->subtract('0', $after),
                    'updated_at' => now(),
                ]);
            }

            return $adjustment->fresh(['location', 'creator', 'approver', 'items.ingredient.baseUnit']);
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

    private function number(): string
    {
        return 'ADJ-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
    }
}
