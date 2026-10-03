<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\KitchenRequest;
use App\Models\KitchenRequestIngredientItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KitchenRequestService
{
    public function __construct(
        private UnitConversionService $conversion,
        private DecimalQuantity $decimal
    ) {
    }

    /**
     * Step 2 kitchen requisition is intentionally ingredient-only.
     * Recipes remain responsible for sale consumption; Kitchen Managers request
     * the raw ingredients they actually need for operation/preparation.
     */
    public function saveDraft(
        array $header,
        ?KitchenRequest $request = null,
        ?int $userId = null
    ): KitchenRequest {
        return DB::transaction(function () use ($header, $request, $userId) {
            $statusToKeep = KitchenRequest::STATUS_DRAFT;

            if ($request) {
                $request = KitchenRequest::query()
                    ->whereKey($request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$request->isEditable()) {
                    throw ValidationException::withMessages([
                        'request' => 'A kitchen request cannot be edited after ingredient assignment has started.',
                    ]);
                }
                $statusToKeep = $request->status;
            } else {
                $request = new KitchenRequest();
                $request->request_no = $this->nextRequestNumber();
                $request->requested_by = $userId;
            }

            $request->fill([
                'request_type' => KitchenRequest::TYPE_INGREDIENT,
                'request_date' => $header['request_date'],
                'status' => $statusToKeep,
                'notes' => $header['notes'] ?? null,
            ]);
            $request->save();

            // Food-wise request rows are legacy only after Step 2. If an old request
            // is edited before assignment, it is normalized into ingredient rows.
            $request->foodItems()->delete();
            $request->ingredientItems()->delete();

            $items = $this->buildIngredientRows((array) ($header['ingredient_items'] ?? []));
            if ($items === []) {
                throw ValidationException::withMessages([
                    'ingredient_items' => 'Add at least one ingredient to the kitchen request.',
                ]);
            }

            foreach ($items as $item) {
                $request->ingredientItems()->create($item);
            }

            return $request->fresh([
                'requester',
                'ingredientItems.ingredient.baseUnit',
                'ingredientItems.displayUnit',
                'ingredientItems.packageConversion',
            ]);
        }, 5);
    }

    public function submit(KitchenRequest $request, ?int $userId = null): KitchenRequest
    {
        return DB::transaction(function () use ($request) {
            $request = $this->lockRequest($request);
            if ($request->status !== KitchenRequest::STATUS_DRAFT) {
                throw ValidationException::withMessages(['request' => 'Only a draft ingredient request can be sent.']);
            }
            if (!$request->ingredientItems()->exists()) {
                throw ValidationException::withMessages(['request' => 'The request has no ingredients to send.']);
            }

            $request->status = KitchenRequest::STATUS_SUBMITTED;
            $request->submitted_at = now();
            $request->save();

            return $request->fresh();
        }, 5);
    }

    public function cancel(KitchenRequest $request): KitchenRequest
    {
        return DB::transaction(function () use ($request) {
            $request = $this->lockRequest($request);
            if (!in_array($request->status, [KitchenRequest::STATUS_DRAFT, KitchenRequest::STATUS_SUBMITTED], true)) {
                throw ValidationException::withMessages([
                    'request' => 'Only an unassigned draft/submitted ingredient request can be cancelled.',
                ]);
            }

            $hasAssignment = $request->ingredientItems()->where('issued_base_qty', '>', 0)->exists()
                || $request->transfers()->where('status', 'POSTED')->exists();
            if ($hasAssignment) {
                throw ValidationException::withMessages([
                    'request' => 'This request already has assigned ingredients and cannot be cancelled. Close the request instead.',
                ]);
            }

            $request->status = KitchenRequest::STATUS_CANCELLED;
            $request->closed_at = now();
            $request->save();

            return $request->fresh();
        }, 5);
    }

    public function close(KitchenRequest $request, ?int $reviewedBy = null): KitchenRequest
    {
        return DB::transaction(function () use ($request, $reviewedBy) {
            $request = $this->lockRequest($request);
            if (!in_array($request->status, [
                KitchenRequest::STATUS_SUBMITTED,
                KitchenRequest::STATUS_PARTIALLY_ISSUED,
                KitchenRequest::STATUS_FULLY_ISSUED,
            ], true)) {
                throw ValidationException::withMessages([
                    'request' => 'Only an active submitted/assigned request can be closed.',
                ]);
            }

            $request->status = KitchenRequest::STATUS_CLOSED;
            $request->reviewed_by = $reviewedBy ?: $request->reviewed_by;
            $request->closed_at = now();
            $request->save();

            return $request->fresh();
        }, 5);
    }

    private function buildIngredientRows(array $rows): array
    {
        $seen = [];
        $items = [];

        foreach ($rows as $index => $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $quantityRaw = trim((string) ($row['quantity'] ?? ''));
            $unitChoice = trim((string) ($row['unit_choice'] ?? ''));

            if ($ingredientId < 1 || $quantityRaw === '' || $unitChoice === '') {
                throw ValidationException::withMessages([
                    "ingredient_items.{$index}" => 'Each ingredient row requires ingredient, quantity and unit/package variant.',
                ]);
            }
            if (isset($seen[$ingredientId])) {
                throw ValidationException::withMessages([
                    'ingredient_items' => 'The same ingredient cannot appear twice in one kitchen request.',
                ]);
            }

            $ingredient = Ingredient::query()
                ->with('unitConversions.unit')
                ->whereKey($ingredientId)
                ->firstOrFail();

            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages([
                    "ingredient_items.{$index}.ingredient_id" => 'Only active inventory ingredients can be requested.',
                ]);
            }

            $resolved = $this->conversion->resolveChoice($ingredient, $unitChoice);
            $unit = $resolved['unit'];
            $packageConversion = $resolved['conversion'];
            $quantity = $this->decimal->normalize($quantityRaw);
            if (!$this->decimal->isPositive($quantity)) {
                throw ValidationException::withMessages([
                    "ingredient_items.{$index}.quantity" => 'Requested ingredient quantity must be greater than zero.',
                ]);
            }

            $factor = $resolved['factor'];
            $base = $this->conversion->toBase(
                $ingredient,
                $quantity,
                $unit,
                null,
                $packageConversion?->id
            );

            $items[] = [
                'ingredient_id' => $ingredient->id,
                'source_kind' => KitchenRequestIngredientItem::SOURCE_DIRECT,
                'input_quantity' => $quantity,
                'conversion_factor_snapshot' => $factor,
                'required_base_qty' => $base,
                'approved_base_qty' => '0.00000000',
                'issued_base_qty' => '0.00000000',
                'display_unit_id' => $unit->id,
                'package_conversion_id' => $packageConversion?->id,
            ];
            $seen[$ingredientId] = true;
        }

        return $items;
    }

    private function lockRequest(KitchenRequest $request): KitchenRequest
    {
        return KitchenRequest::query()
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function nextRequestNumber(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = 'KR-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(5));
            if (!KitchenRequest::query()->where('request_no', $number)->exists()) {
                return $number;
            }
        }

        throw ValidationException::withMessages([
            'request_no' => 'Could not allocate a unique kitchen request number. Please try again.',
        ]);
    }
}
