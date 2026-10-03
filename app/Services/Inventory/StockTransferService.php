<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\KitchenRequest;
use App\Models\KitchenRequestIngredientItem;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        private StockMovementService $movements,
        private StockLocationService $locations,
        private UnitConversionService $conversion,
        private DecimalQuantity $decimal
    ) {
    }

    public function issueRequest(
        KitchenRequest $request,
        array $rows,
        string $idempotencyKey,
        ?int $userId = null,
        ?string $notes = null
    ): StockTransfer {
        if ($existing = $this->existingByKey($idempotencyKey)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($request, $rows, $idempotencyKey, $userId, $notes) {
                $request = KitchenRequest::query()
                    ->whereKey($request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$request->canIssue()) {
                    throw ValidationException::withMessages(['request' => 'Only submitted or partially assigned requests can receive an ingredient assignment.']);
                }

                $requestItems = KitchenRequestIngredientItem::query()
                    ->where('kitchen_request_id', $request->id)
                    ->with(['ingredient.unitConversions.unit', 'ingredient.baseUnit'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $normalized = [];
                foreach ($rows as $requestItemId => $row) {
                    $requestItemId = (int) $requestItemId;
                    $quantityRaw = trim((string) ($row['quantity'] ?? ''));
                    $unitChoice = trim((string) ($row['unit_choice'] ?? ''));
                    if ($quantityRaw === '' || $this->decimal->compare($quantityRaw, '0') === 0) {
                        continue;
                    }
                    if (!$requestItems->has($requestItemId)) {
                        throw ValidationException::withMessages(['items' => 'An issue row does not belong to this kitchen request.']);
                    }
                    if ($unitChoice === '') {
                        throw ValidationException::withMessages(["items.{$requestItemId}.unit_choice" => 'Select a unit or package variant for each assigned quantity.']);
                    }

                    $requestItem = $requestItems->get($requestItemId);
                    $ingredient = $requestItem->ingredient;
                    $resolved = $this->conversion->resolveChoice($ingredient, $unitChoice);
                    $unit = $resolved['unit'];
                    $packageConversion = $resolved['conversion'];
                    $quantity = $this->decimal->normalize($quantityRaw);
                    if (!$this->decimal->isPositive($quantity)) {
                        throw ValidationException::withMessages(["items.{$requestItemId}.quantity" => 'Assigned quantity must be greater than zero.']);
                    }

                    $factor = $resolved['factor'];
                    $base = $this->conversion->toBase($ingredient, $quantity, $unit, null, $packageConversion?->id);
                    $remaining = $this->decimal->subtract((string) $requestItem->required_base_qty, (string) $requestItem->issued_base_qty);
                    if ($this->decimal->compare($base, $remaining) > 0) {
                        throw ValidationException::withMessages([
                            "items.{$requestItemId}.quantity" => "Assigned quantity for {$ingredient->name} exceeds the remaining requested quantity.",
                        ]);
                    }

                    $normalized[] = [
                        'request_item' => $requestItem,
                        'ingredient_id' => (int) $ingredient->id,
                        'quantity' => $quantity,
                        'unit_id' => $unit->id,
                        'package_conversion_id' => $packageConversion?->id,
                        'conversion_factor_snapshot' => $factor,
                        'base_quantity' => $base,
                    ];
                }

                if ($normalized === []) {
                    throw ValidationException::withMessages(['items' => 'Enter at least one positive issue quantity.']);
                }

                $transfer = $this->createAndPost(
                    $normalized,
                    $idempotencyKey,
                    $userId,
                    $notes,
                    $request
                );

                foreach ($normalized as $item) {
                    /** @var KitchenRequestIngredientItem $requestItem */
                    $requestItem = $item['request_item'];
                    $newIssued = $this->decimal->add((string) $requestItem->issued_base_qty, $item['base_quantity']);
                    $requestItem->issued_base_qty = $newIssued;
                    // In the simplified assignment flow, approval equals the cumulative
                    // quantity the Inventory Manager has actually assigned to Kitchen.
                    $requestItem->approved_base_qty = $newIssued;
                    $requestItem->save();
                }

                $remainingExists = KitchenRequestIngredientItem::query()
                    ->where('kitchen_request_id', $request->id)
                    ->get()
                    ->contains(fn ($item) => $this->decimal->compare((string) $item->issued_base_qty, (string) $item->required_base_qty) < 0);

                $request->status = $remainingExists
                    ? KitchenRequest::STATUS_PARTIALLY_ISSUED
                    : KitchenRequest::STATUS_FULLY_ISSUED;
                $request->reviewed_by = $userId;
                $request->save();

                return $transfer->fresh($this->transferRelations());
            }, 5);
        } catch (QueryException $e) {
            if ($existing = $this->existingByKey($idempotencyKey)) {
                return $existing;
            }
            throw $e;
        }
    }

    public function postDirect(
        array $rows,
        string $idempotencyKey,
        ?int $userId = null,
        ?string $notes = null
    ): StockTransfer {
        if ($existing = $this->existingByKey($idempotencyKey)) {
            return $existing;
        }

        $normalized = $this->normalizeDirectRows($rows);
        try {
            return DB::transaction(function () use ($normalized, $idempotencyKey, $userId, $notes) {
                return $this->createAndPost($normalized, $idempotencyKey, $userId, $notes, null)
                    ->fresh($this->transferRelations());
            }, 5);
        } catch (QueryException $e) {
            if ($existing = $this->existingByKey($idempotencyKey)) {
                return $existing;
            }
            throw $e;
        }
    }

    public function postReturn(
        array $rows,
        string $idempotencyKey,
        ?int $userId = null,
        ?string $notes = null,
        ?int $originalTransferId = null
    ): StockTransfer {
        if ($existing = $this->existingByKey($idempotencyKey)) {
            return $existing;
        }

        $normalized = $this->normalizeDirectRows($rows);
        $original = null;
        if ($originalTransferId) {
            $original = StockTransfer::query()
                ->whereKey($originalTransferId)
                ->where('status', StockTransfer::STATUS_POSTED)
                ->where('direction', StockTransfer::DIRECTION_MAIN_TO_KITCHEN)
                ->firstOrFail();
        }

        try {
            return DB::transaction(function () use ($normalized, $idempotencyKey, $userId, $notes, $original) {
                $main = $this->locations->forType(StockLocation::TYPE_MAIN);
                $kitchen = $this->locations->forType(StockLocation::TYPE_KITCHEN);

                $transfer = StockTransfer::query()->create([
                    'transfer_no' => $this->nextTransferNumber(),
                    'direction' => StockTransfer::DIRECTION_KITCHEN_TO_MAIN,
                    'kitchen_request_id' => $original?->kitchen_request_id,
                    'original_transfer_id' => $original?->id,
                    'source_location_id' => $kitchen->id,
                    'destination_location_id' => $main->id,
                    'status' => StockTransfer::STATUS_DRAFT,
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $userId,
                    'notes' => $notes,
                ]);

                $movementItems = [];
                foreach ($normalized as $item) {
                    $transfer->items()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity' => $item['quantity'],
                        'unit_id' => $item['unit_id'],
                        'package_conversion_id' => $item['package_conversion_id'] ?? null,
                        'conversion_factor_snapshot' => $item['conversion_factor_snapshot'],
                        'base_quantity' => $item['base_quantity'],
                    ]);
                    $movementItems[] = [
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity_base' => $item['base_quantity'],
                    ];
                }

                $movement = $this->movements->post(
                    StockMovement::KITCHEN_TO_MAIN,
                    $movementItems,
                    (int) $kitchen->id,
                    (int) $main->id,
                    [
                        'reference_type' => StockTransfer::class,
                        'reference_id' => $transfer->id,
                        'performed_by' => $userId,
                        'reason' => $notes ?: ($original ? "Unused kitchen stock return from {$original->transfer_no}" : 'Unused Kitchen to Store return'),
                    ]
                );

                $transfer->status = StockTransfer::STATUS_POSTED;
                $transfer->posted_movement_id = $movement->id;
                $transfer->posted_by = $userId;
                $transfer->posted_at = now();
                $transfer->save();

                return $transfer->fresh($this->transferRelations());
            }, 5);
        } catch (QueryException $e) {
            if ($existing = $this->existingByKey($idempotencyKey)) {
                return $existing;
            }
            throw $e;
        }
    }

    private function createAndPost(
        array $items,
        string $idempotencyKey,
        ?int $userId,
        ?string $notes,
        ?KitchenRequest $request
    ): StockTransfer {
        $main = $this->locations->forType(StockLocation::TYPE_MAIN);
        $kitchen = $this->locations->forType(StockLocation::TYPE_KITCHEN);

        $transfer = StockTransfer::query()->create([
            'transfer_no' => $this->nextTransferNumber(),
            'direction' => StockTransfer::DIRECTION_MAIN_TO_KITCHEN,
            'kitchen_request_id' => $request?->id,
            'source_location_id' => $main->id,
            'destination_location_id' => $kitchen->id,
            'status' => StockTransfer::STATUS_DRAFT,
            'idempotency_key' => $idempotencyKey,
            'created_by' => $userId,
            'notes' => $notes,
        ]);

        $movementItems = [];
        foreach ($items as $item) {
            $transfer->items()->create([
                'ingredient_id' => $item['ingredient_id'],
                'quantity' => $item['quantity'],
                'unit_id' => $item['unit_id'],
                'package_conversion_id' => $item['package_conversion_id'] ?? null,
                'conversion_factor_snapshot' => $item['conversion_factor_snapshot'],
                'base_quantity' => $item['base_quantity'],
            ]);
            $movementItems[] = [
                'ingredient_id' => $item['ingredient_id'],
                'quantity_base' => $item['base_quantity'],
            ];
        }

        $movement = $this->movements->post(
            StockMovement::MAIN_TO_KITCHEN,
            $movementItems,
            (int) $main->id,
            (int) $kitchen->id,
            [
                'reference_type' => StockTransfer::class,
                'reference_id' => $transfer->id,
                'performed_by' => $userId,
                'reason' => $request
                    ? "Kitchen request {$request->request_no} ingredient assignment"
                    : ($notes ?: 'Direct Store to Kitchen assignment'),
            ]
        );

        $transfer->status = StockTransfer::STATUS_POSTED;
        $transfer->posted_movement_id = $movement->id;
        $transfer->posted_by = $userId;
        $transfer->posted_at = now();
        $transfer->save();

        return $transfer;
    }

    private function normalizeDirectRows(array $rows): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $quantityRaw = trim((string) ($row['quantity'] ?? ''));
            $unitChoice = trim((string) ($row['unit_choice'] ?? ''));
            if ($ingredientId < 1 && $quantityRaw === '' && $unitChoice === '') {
                continue;
            }
            if ($ingredientId < 1 || $quantityRaw === '' || $unitChoice === '') {
                throw ValidationException::withMessages(["items.{$index}" => 'Each transfer row requires ingredient, quantity and unit/package variant.']);
            }
            if (isset($seen[$ingredientId])) {
                throw ValidationException::withMessages(['items' => 'The same ingredient cannot appear twice in one transfer.']);
            }

            $ingredient = Ingredient::query()->with('unitConversions.unit')->whereKey($ingredientId)->firstOrFail();
            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages(["items.{$index}.ingredient_id" => 'Only active inventory-tracked ingredients can be transferred.']);
            }
            $resolved = $this->conversion->resolveChoice($ingredient, $unitChoice);
            $unit = $resolved['unit'];
            $packageConversion = $resolved['conversion'];
            $quantity = $this->decimal->normalize($quantityRaw);
            $factor = $resolved['factor'];
            $base = $this->conversion->toBase($ingredient, $quantity, $unit, null, $packageConversion?->id);

            $seen[$ingredientId] = true;
            $normalized[] = [
                'ingredient_id' => $ingredientId,
                'quantity' => $quantity,
                'unit_id' => $unit->id,
                'package_conversion_id' => $packageConversion?->id,
                'conversion_factor_snapshot' => $factor,
                'base_quantity' => $base,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one transfer item.']);
        }

        return $normalized;
    }

    private function existingByKey(string $idempotencyKey): ?StockTransfer
    {
        return StockTransfer::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', StockTransfer::STATUS_POSTED)
            ->with($this->transferRelations())
            ->first();
    }

    private function nextTransferNumber(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = 'TRF-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(5));
            if (!StockTransfer::query()
                ->where('transfer_no', $number)->exists()) {
                return $number;
            }
        }

        throw ValidationException::withMessages(['transfer_no' => 'Could not allocate a unique stock transfer number. Please try again.']);
    }

    private function transferRelations(): array
    {
        return [
            'kitchenRequest',
            'originalTransfer',
            'sourceLocation',
            'destinationLocation',
            'postedMovement',
            'creator',
            'poster',
            'items.ingredient.baseUnit',
            'items.unit',
            'items.packageConversion',
        ];
    }
}
