<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryWastage;
use App\Models\StockLocation;
use App\Models\StockMovement;
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

    /**
     * Existing one-click flow: create the document and post it immediately.
     */
    public function post(int $locationId, string $reasonCode, array $rows, ?string $notes, ?int $userId): InventoryWastage
    {
        return DB::transaction(function () use ($locationId, $reasonCode, $rows, $notes, $userId) {
            $draft = $this->createDraft($locationId, $reasonCode, $rows, $notes, $userId);
            return $this->postDraft($draft, $userId);
        }, 5);
    }

    /**
     * Save a wastage document without touching stock. Drafts may be edited or
     * deleted by users who have the corresponding permissions.
     */
    public function createDraft(int $locationId, string $reasonCode, array $rows, ?string $notes, ?int $userId): InventoryWastage
    {
        $this->validateReason($reasonCode, $notes);

        return DB::transaction(function () use ($locationId, $reasonCode, $rows, $notes, $userId) {
            $location = $this->location($locationId);
            $normalized = $this->normalizeRows($rows);

            $wastage = InventoryWastage::query()->create([
                'location_id' => $location->id,
                'wastage_no' => $this->number(),
                'reason_code' => $reasonCode,
                'notes' => $notes,
                'status' => InventoryWastage::STATUS_DRAFT,
                'created_by' => $userId,
            ]);

            foreach ($normalized as $item) {
                $wastage->items()->create($item);
            }

            return $wastage->fresh(['location', 'creator', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion']);
        }, 5);
    }

    /**
     * Update a draft only. Posted wastage stays immutable and must be corrected
     * with an adjustment/reversal workflow, preserving the stock audit trail.
     */
    public function updateDraft(
        InventoryWastage $wastage,
        int $locationId,
        string $reasonCode,
        array $rows,
        ?string $notes
    ): InventoryWastage {
        $this->assertDraft($wastage);
        $this->validateReason($reasonCode, $notes);

        return DB::transaction(function () use ($wastage, $locationId, $reasonCode, $rows, $notes) {
            $locked = InventoryWastage::query()
                
                ->whereKey($wastage->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraft($locked);
            $location = $this->location($locationId);
            $normalized = $this->normalizeRows($rows);

            $locked->location_id = $location->id;
            $locked->reason_code = $reasonCode;
            $locked->notes = $notes;
            $locked->save();

            $locked->items()->delete();
            foreach ($normalized as $item) {
                $locked->items()->create($item);
            }

            return $locked->fresh(['location', 'creator', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion']);
        }, 5);
    }

    /**
     * Post an existing draft and deduct stock exactly once.
     */
    public function postDraft(InventoryWastage $wastage, ?int $userId): InventoryWastage
    {
        return DB::transaction(function () use ($wastage, $userId) {
            $locked = InventoryWastage::query()
                
                ->whereKey($wastage->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraft($locked);
            $location = $this->location((int) $locked->location_id);
            $items = $locked->items()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one wastage item before posting.']);
            }

            $this->movements->post(
                StockMovement::WASTAGE,
                $items->map(fn ($item) => [
                    'ingredient_id' => (int) $item->ingredient_id,
                    'quantity_base' => (string) $item->base_quantity,
                ])->all(),
                (int) $location->id,
                null,
                [
                    'reference_type' => InventoryWastage::class,
                    'reference_id' => $locked->id,
                    'performed_by' => $userId,
                    'reason' => $locked->reason_code . ($locked->notes ? ': ' . $locked->notes : ''),
                ]
            );

            $locked->status = InventoryWastage::STATUS_POSTED;
            $locked->posted_at = now();
            $locked->save();

            return $locked->fresh(['location', 'creator', 'items.ingredient.baseUnit', 'items.unit', 'items.packageConversion']);
        }, 5);
    }

    public function deleteDraft(InventoryWastage $wastage): void
    {
        $this->assertDraft($wastage);

        DB::transaction(function () use ($wastage) {
            $locked = InventoryWastage::query()
                
                ->whereKey($wastage->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraft($locked);
            $locked->delete();
        }, 5);
    }

    private function validateReason(string $reasonCode, ?string $notes): void
    {
        if (!in_array($reasonCode, InventoryWastage::reasons(), true)) {
            throw ValidationException::withMessages(['reason_code' => 'Select a valid wastage reason.']);
        }
        if ($reasonCode === InventoryWastage::REASON_OTHER && trim((string) $notes) === '') {
            throw ValidationException::withMessages(['notes' => 'Notes are required when the wastage reason is Other.']);
        }
    }

    private function assertDraft(InventoryWastage $wastage): void
    {
        if ($wastage->status !== InventoryWastage::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'wastage' => 'Posted wastage is immutable. Only DRAFT wastage can be edited or deleted.',
            ]);
        }
    }

    private function location(int $locationId): StockLocation
    {
        return StockLocation::query()
            ->whereKey($locationId)
            ->where('is_active', true)
            ->firstOrFail();
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

    private function number(): string
    {
        return 'WST-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
    }
}
