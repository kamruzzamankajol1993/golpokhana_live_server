<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryWastage;
use App\Models\Scopes\BranchScope;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryWastageService
{
    public function __construct(
        private StockMovementService $movements,
        private UnitConversionService $conversion,
        private DecimalQuantity $decimal
    ) {
    }

    public function post(int $branchId, int $locationId, string $reasonCode, array $rows, ?string $notes, ?int $userId): InventoryWastage
    {
        if (!in_array($reasonCode, InventoryWastage::reasons(), true)) {
            throw ValidationException::withMessages(['reason_code' => 'Select a valid wastage reason.']);
        }
        if ($reasonCode === InventoryWastage::REASON_OTHER && trim((string) $notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Notes are required when the wastage reason is Other.']);
        }

        return DB::transaction(function () use ($branchId, $locationId, $reasonCode, $rows, $notes, $userId) {
            $location = StockLocation::query()->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)->whereKey($locationId)->where('is_active', true)->firstOrFail();
            $normalized = $this->normalizeRows($rows);

            $wastage = InventoryWastage::query()->withoutGlobalScope(BranchScope::class)->create([
                'branch_id' => $branchId,
                'location_id' => $location->id,
                'wastage_no' => $this->number($branchId),
                'reason_code' => $reasonCode,
                'notes' => $notes,
                'status' => InventoryWastage::STATUS_DRAFT,
                'created_by' => $userId,
            ]);

            foreach ($normalized as $item) {
                $wastage->items()->create($item);
            }

            $movement = $this->movements->post(
                $branchId,
                StockMovement::WASTAGE,
                array_map(fn ($item) => [
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity_base' => $item['base_quantity'],
                ], $normalized),
                (int) $location->id,
                null,
                [
                    'reference_type' => InventoryWastage::class,
                    'reference_id' => $wastage->id,
                    'performed_by' => $userId,
                    'reason' => $reasonCode . ($notes ? ': ' . $notes : ''),
                ]
            );

            $wastage->status = InventoryWastage::STATUS_POSTED;
            $wastage->posted_at = now();
            $wastage->save();

            return $wastage->fresh(['branch', 'location', 'creator', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion']);
        }, 5);
    }

    private function normalizeRows(array $rows): array
    {
        $result = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $quantity = trim((string) ($row['quantity'] ?? ''));
            $unitChoice = trim((string) ($row['unit_choice'] ?? ''));
            if ($ingredientId < 1 && $quantity === '' && $unitChoice === '') {
                continue;
            }
            if ($ingredientId < 1 || $quantity === '' || $unitChoice === '') {
                throw ValidationException::withMessages(["items.{$index}" => 'Each wastage row requires ingredient, quantity and unit/package variant.']);
            }
            if (isset($seen[$ingredientId])) {
                throw ValidationException::withMessages(['items' => 'The same ingredient cannot appear twice in one wastage record.']);
            }

            $ingredient = Ingredient::query()->with('unitConversions')->whereKey($ingredientId)->firstOrFail();
            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages(["items.{$index}.ingredient_id" => 'Only active inventory-tracked ingredients can be wasted.']);
            }
            $selection = $this->conversion->resolveChoice($ingredient, $unitChoice);
            $normalizedQty = $this->decimal->normalize($quantity);
            $factor = $selection['factor'];
            $base = $this->decimal->multiply($normalizedQty, $factor);

            $seen[$ingredientId] = true;
            $result[] = [
                'ingredient_id' => $ingredientId,
                'quantity' => $normalizedQty,
                'unit_id' => $selection['unit']->id,
                'package_conversion_id' => $selection['conversion']?->id,
                'conversion_factor_snapshot' => $factor,
                'base_quantity' => $base,
            ];
        }
        if ($result === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one wastage item.']);
        }
        return $result;
    }

    private function number(int $branchId): string
    {
        return 'WST-' . $branchId . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
    }
}
