<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StockMovementService
{
    public function __construct(private DecimalQuantity $decimal) {}

    public function post(
        string $movementType,
        array $items,
        ?int $sourceLocationId = null,
        ?int $destinationLocationId = null,
        array $options = []
    ): StockMovement {
        if (!in_array($movementType, StockMovement::types(), true)) {
            throw new InvalidArgumentException("Unsupported stock movement type: {$movementType}");
        }

        $items = $this->validateItems($items);
        [$source, $destination] = $this->validateLocations($movementType, $sourceLocationId, $destinationLocationId);
        $allowNegativeSource = $movementType === StockMovement::ORDER_CONSUMPTION
            || (bool) ($options['allow_negative_source'] ?? false);

        return DB::transaction(function () use ($movementType, $items, $source, $destination, $options, $allowNegativeSource) {
            $movement = $this->createMovement($movementType, $source?->id, $destination?->id, $options);

            foreach ($items as $item) {
                $ingredient = Ingredient::query()->findOrFail($item['ingredient_id']);
                if (!$ingredient->track_inventory) {
                    throw ValidationException::withMessages(['ingredient_id' => "Inventory tracking is disabled for {$ingredient->name}."]);
                }

                $quantity = $item['quantity_base'];
                $sourceBefore = $sourceAfter = $destinationBefore = $destinationAfter = null;
                $lockedBalances = $this->lockBalances(array_values(array_filter([$source?->id, $destination?->id])), (int) $ingredient->id);

                if ($source) {
                    $sourceBalance = $lockedBalances->get((int) $source->id);
                    $sourceBefore = (string) $sourceBalance->quantity_base;
                    if (!$allowNegativeSource && $this->decimal->compare($sourceBefore, $quantity) < 0) {
                        throw ValidationException::withMessages(['quantity' => "Insufficient stock for {$ingredient->name} at {$source->name}."]);
                    }
                    DB::update('UPDATE inventory_balances SET quantity_base = quantity_base - ?, updated_at = ? WHERE id = ?', [$quantity, now(), $sourceBalance->id]);
                    $sourceAfter = (string) DB::table('inventory_balances')->where('id', $sourceBalance->id)->value('quantity_base');
                }

                if ($destination) {
                    $destinationBalance = $lockedBalances->get((int) $destination->id);
                    $destinationBefore = (string) $destinationBalance->quantity_base;
                    DB::update('UPDATE inventory_balances SET quantity_base = quantity_base + ?, updated_at = ? WHERE id = ?', [$quantity, now(), $destinationBalance->id]);
                    $destinationAfter = (string) DB::table('inventory_balances')->where('id', $destinationBalance->id)->value('quantity_base');
                }

                StockMovementItem::query()->create([
                    'stock_movement_id' => $movement->id,
                    'ingredient_id' => $ingredient->id,
                    'quantity_base' => $quantity,
                    'source_before' => $sourceBefore,
                    'source_after' => $sourceAfter,
                    'destination_before' => $destinationBefore,
                    'destination_after' => $destinationAfter,
                ]);
            }

            $movement->status = StockMovement::STATUS_POSTED;
            $movement->save();
            return $movement->load(['items.ingredient.baseUnit', 'sourceLocation', 'destinationLocation', 'performer']);
        }, 5);
    }

    public function postOpeningStock(
        int $locationId,
        int $ingredientId,
        string|int $baseQuantity,
        ?string $reason = null,
        ?int $performedBy = null
    ): StockMovement {
        return DB::transaction(function () use ($locationId, $ingredientId, $baseQuantity, $reason, $performedBy) {
            $this->lockBalances([$locationId], $ingredientId);
            $alreadyStarted = StockMovement::query()
                ->where(function ($q) use ($locationId) {
                    $q->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId);
                })
                ->whereHas('items', fn ($q) => $q->where('ingredient_id', $ingredientId))
                ->exists();

            if ($alreadyStarted) {
                throw ValidationException::withMessages(['quantity' => 'Opening stock is allowed only before this ingredient/location has any posted stock history. Use an adjustment for later corrections.']);
            }

            return $this->post(StockMovement::OPENING_STOCK, [['ingredient_id' => $ingredientId, 'quantity_base' => $baseQuantity]], null, $locationId, [
                'reason' => $reason ?: 'Opening stock',
                'performed_by' => $performedBy,
            ]);
        }, 5);
    }

    private function validateItems(array $items): array
    {
        if ($items === []) throw ValidationException::withMessages(['items' => 'At least one stock movement item is required.']);
        $normalized = [];
        foreach ($items as $item) {
            $ingredientId = (int) ($item['ingredient_id'] ?? 0);
            if ($ingredientId < 1) throw ValidationException::withMessages(['ingredient_id' => 'A valid ingredient is required.']);
            if (isset($normalized[$ingredientId])) throw ValidationException::withMessages(['items' => 'The same ingredient cannot appear twice in one stock movement.']);
            $quantity = $this->decimal->normalize((string) ($item['quantity_base'] ?? '0'));
            if (!$this->decimal->isPositive($quantity)) throw ValidationException::withMessages(['quantity' => 'Stock movement quantity must be greater than zero.']);
            $normalized[$ingredientId] = ['ingredient_id' => $ingredientId, 'quantity_base' => $quantity];
        }
        ksort($normalized, SORT_NUMERIC);
        return array_values($normalized);
    }

    private function validateLocations(string $movementType, ?int $sourceLocationId, ?int $destinationLocationId): array
    {
        $source = $sourceLocationId ? $this->location($sourceLocationId) : null;
        $destination = $destinationLocationId ? $this->location($destinationLocationId) : null;
        $incomingOnly = [StockMovement::OPENING_STOCK, StockMovement::PURCHASE_RECEIVE, StockMovement::POSITIVE_ADJUSTMENT];
        $outgoingOnly = [StockMovement::ORDER_CONSUMPTION, StockMovement::WASTAGE, StockMovement::NEGATIVE_ADJUSTMENT, StockMovement::PURCHASE_RETURN];
        $transfer = [StockMovement::MAIN_TO_KITCHEN, StockMovement::KITCHEN_TO_MAIN];

        if (in_array($movementType, $incomingOnly, true) && (!$destination || $source)) throw ValidationException::withMessages(['location' => "{$movementType} requires a destination location only."]);
        if (in_array($movementType, $outgoingOnly, true) && (!$source || $destination)) throw ValidationException::withMessages(['location' => "{$movementType} requires a source location only."]);
        if (in_array($movementType, $transfer, true) && (!$source || !$destination || $source->id === $destination->id)) throw ValidationException::withMessages(['location' => "{$movementType} requires different source and destination locations."]);
        if ($movementType === StockMovement::REVERSAL && !$source && !$destination) throw ValidationException::withMessages(['location' => 'A reversal requires a source or destination location.']);
        if ($movementType === StockMovement::MAIN_TO_KITCHEN && ($source?->type !== StockLocation::TYPE_MAIN || $destination?->type !== StockLocation::TYPE_KITCHEN)) throw ValidationException::withMessages(['location' => 'MAIN_TO_KITCHEN must move stock from Main Stock to Kitchen Stock.']);
        if ($movementType === StockMovement::KITCHEN_TO_MAIN && ($source?->type !== StockLocation::TYPE_KITCHEN || $destination?->type !== StockLocation::TYPE_MAIN)) throw ValidationException::withMessages(['location' => 'KITCHEN_TO_MAIN must move stock from Kitchen Stock to Main Stock.']);
        return [$source, $destination];
    }

    private function location(int $locationId): StockLocation
    {
        return StockLocation::query()->whereKey($locationId)->where('is_active', true)->firstOrFail();
    }

    private function lockBalances(array $locationIds, int $ingredientId)
    {
        $locationIds = array_values(array_unique(array_map('intval', $locationIds)));
        sort($locationIds, SORT_NUMERIC);
        foreach ($locationIds as $locationId) {
            DB::table('inventory_balances')->insertOrIgnore([
                'stock_location_id' => $locationId,
                'ingredient_id' => $ingredientId,
                'quantity_base' => '0.00000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return InventoryBalance::query()
            ->whereIn('stock_location_id', $locationIds)
            ->where('ingredient_id', $ingredientId)
            ->orderBy('stock_location_id')
            ->lockForUpdate()->get()->keyBy('stock_location_id');
    }

    private function createMovement(string $movementType, ?int $sourceLocationId, ?int $destinationLocationId, array $options): StockMovement
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return StockMovement::query()->create([
                    'movement_no' => $this->movementNumber(),
                    'movement_type' => $movementType,
                    'reference_type' => $options['reference_type'] ?? null,
                    'reference_id' => $options['reference_id'] ?? null,
                    'source_location_id' => $sourceLocationId,
                    'destination_location_id' => $destinationLocationId,
                    'status' => StockMovement::STATUS_DRAFT,
                    'occurred_at' => $options['occurred_at'] ?? now(),
                    'performed_by' => $options['performed_by'] ?? auth()->id(),
                    'reason' => $options['reason'] ?? null,
                ]);
            } catch (QueryException $e) {
                if ($attempt === 4) throw $e;
            }
        }
        throw new InvalidArgumentException('Unable to allocate stock movement number.');
    }

    private function movementNumber(): string
    {
        return 'INV-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(5));
    }
}
