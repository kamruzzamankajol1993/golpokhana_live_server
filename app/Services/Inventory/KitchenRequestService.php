<?php

namespace App\Services\Inventory;

use App\Models\FoodItem;
use App\Models\Ingredient;
use App\Models\KitchenRequest;
use App\Models\KitchenRequestIngredientItem;
use App\Models\MenuItemRecipe;
use App\Models\Scopes\BranchScope;
use App\Models\Unit;
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

    public function saveDraft(
        int $branchId,
        array $header,
        ?KitchenRequest $request = null,
        ?int $userId = null
    ): KitchenRequest {
        return DB::transaction(function () use ($branchId, $header, $request, $userId) {
            $statusToKeep = KitchenRequest::STATUS_DRAFT;

            if ($request) {
                $request = KitchenRequest::query()
                    ->withoutGlobalScope(BranchScope::class)
                    ->whereKey($request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $request->branch_id !== $branchId) {
                    throw ValidationException::withMessages(['branch_id' => 'A kitchen request cannot be moved to another branch.']);
                }
                if (!$request->isEditable()) {
                    throw ValidationException::withMessages(['request' => 'Only Draft or Submitted kitchen requests can be edited before stock is issued.']);
                }
                $statusToKeep = $request->status;
            } else {
                $request = new KitchenRequest();
                $request->branch_id = $branchId;
                $request->request_no = $this->nextRequestNumber($branchId);
                $request->requested_by = $userId;
            }

            // The final type is derived from the actual rows below. MIXED is used
            // temporarily so both food and direct ingredient sections can be saved
            // in the same request without requiring a client-facing mode selector.
            $request->fill([
                'request_type' => KitchenRequest::TYPE_MIXED,
                'request_date' => $header['request_date'],
                'status' => $statusToKeep,
                'notes' => $header['notes'] ?? null,
            ]);
            $request->save();

            $request->foodItems()->delete();
            $request->ingredientItems()->delete();

            $foodTotals = $this->saveFoodRows($request, (array) ($header['food_items'] ?? []));
            $directTotals = $this->buildDirectIngredientRows($request, (array) ($header['ingredient_items'] ?? []));

            $hasFood = $foodTotals !== [];
            $hasDirect = $directTotals !== [];
            if (!$hasFood && !$hasDirect) {
                throw ValidationException::withMessages([
                    'request' => 'Add at least one Food item or one Direct Ingredient to the kitchen request.',
                ]);
            }

            $request->request_type = $hasFood && $hasDirect
                ? KitchenRequest::TYPE_MIXED
                : ($hasFood ? KitchenRequest::TYPE_FOOD : KitchenRequest::TYPE_INGREDIENT);
            $request->save();

            foreach ($this->mergeIngredientRequirements($foodTotals, $directTotals) as $item) {
                $request->ingredientItems()->create($item);
            }

            return $request->fresh([
                'branch',
                'requester',
                'foodItems.foodItem',
                'foodItems.recipe',
                'ingredientItems.ingredient.baseUnit',
                'ingredientItems.displayUnit',
                'ingredientItems.packageConversion',
            ]);
        }, 5);
    }

    public function submit(KitchenRequest $request, ?int $userId = null): KitchenRequest
    {
        return DB::transaction(function () use ($request, $userId) {
            $request = $this->lockRequest($request);
            if ($request->status !== KitchenRequest::STATUS_DRAFT) {
                throw ValidationException::withMessages(['request' => 'Only a draft kitchen request can be submitted.']);
            }
            if (!$request->ingredientItems()->exists()) {
                throw ValidationException::withMessages(['request' => 'The request has no ingredient requirement to submit.']);
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
                throw ValidationException::withMessages(['request' => 'Only an unissued draft/submitted request can be cancelled.']);
            }

            $hasIssue = $request->ingredientItems()
                ->where('issued_base_qty', '>', 0)
                ->exists();
            if ($hasIssue || $request->transfers()->where('status', 'POSTED')->exists()) {
                throw ValidationException::withMessages(['request' => 'This request already has issued stock and cannot be cancelled. Close the lifecycle instead.']);
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
                throw ValidationException::withMessages(['request' => 'Only an active submitted/issued request can be closed.']);
            }

            $request->status = KitchenRequest::STATUS_CLOSED;
            $request->reviewed_by = $reviewedBy ?: $request->reviewed_by;
            $request->closed_at = now();
            $request->save();

            return $request->fresh();
        }, 5);
    }

    /**
     * Save food request rows and return the recipe-derived ingredient totals keyed
     * by ingredient id. Empty food rows are allowed because a request may contain
     * only direct ingredients.
     */
    private function saveFoodRows(KitchenRequest $request, array $rows): array
    {
        $seenFoods = [];
        $ingredientTotals = [];

        foreach ($rows as $index => $row) {
            $foodId = (int) ($row['menu_item_id'] ?? 0);
            $qtyRaw = trim((string) ($row['requested_food_qty'] ?? ''));
            if ($foodId < 1 && $qtyRaw === '') {
                continue;
            }
            if ($foodId < 1 || $qtyRaw === '') {
                throw ValidationException::withMessages(["food_items.{$index}" => 'Each food request row requires a menu item and quantity.']);
            }
            if (isset($seenFoods[$foodId])) {
                throw ValidationException::withMessages(['food_items' => 'The same menu item cannot appear twice in one request.']);
            }

            $qty = $this->decimal->normalize($qtyRaw);
            if (!$this->decimal->isPositive($qty)) {
                throw ValidationException::withMessages(["food_items.{$index}.requested_food_qty" => 'Requested food quantity must be greater than zero.']);
            }

            $foodQuery = FoodItem::query();
            $food = $foodQuery
                ->withoutGlobalScopes()
                ->whereKey($foodId)
                ->where('branch_id', $request->branch_id)
                ->first();
            if (!$food) {
                throw ValidationException::withMessages(["food_items.{$index}.menu_item_id" => 'The selected menu item does not belong to this request branch.']);
            }
            if (!$food->inventory_tracking) {
                throw ValidationException::withMessages(["food_items.{$index}.menu_item_id" => "Inventory tracking is OFF for {$food->name}. Configure its recipe first."]);
            }

            $recipe = MenuItemRecipe::query()
                ->where('menu_item_id', $food->id)
                ->where('is_active', true)
                ->with(['items.ingredient.baseUnit'])
                ->first();
            if (!$recipe || $recipe->items->isEmpty()) {
                throw ValidationException::withMessages(["food_items.{$index}.menu_item_id" => "No active inventory recipe exists for {$food->name}."]);
            }
            $yield = $this->decimal->normalize((string) $recipe->yield_quantity);
            if (!$this->decimal->isPositive($yield)) {
                throw ValidationException::withMessages(["food_items.{$index}.menu_item_id" => "Recipe yield is invalid for {$food->name}."]);
            }

            $request->foodItems()->create([
                'menu_item_id' => $food->id,
                'requested_food_qty' => $qty,
                'recipe_id' => $recipe->id,
                'recipe_version_no' => $recipe->version_no,
            ]);

            foreach ($recipe->items as $recipeItem) {
                $ingredient = $recipeItem->ingredient;
                if (!$ingredient || !$ingredient->is_active || !$ingredient->track_inventory || !$ingredient->base_unit_id) {
                    throw ValidationException::withMessages([
                        "food_items.{$index}.menu_item_id" => "Recipe v{$recipe->version_no} contains an unavailable inventory ingredient.",
                    ]);
                }

                $extended = $this->decimal->multiply((string) $recipeItem->base_quantity, $qty);
                $required = $this->decimal->divide($extended, $yield);
                $ingredientId = (int) $ingredient->id;

                if (!isset($ingredientTotals[$ingredientId])) {
                    $ingredientTotals[$ingredientId] = [
                        'ingredient_id' => $ingredientId,
                        'source_kind' => KitchenRequestIngredientItem::SOURCE_FOOD,
                        'input_quantity' => null,
                        'conversion_factor_snapshot' => null,
                        'required_base_qty' => '0.00000000',
                        'approved_base_qty' => '0.00000000',
                        'issued_base_qty' => '0.00000000',
                        'display_unit_id' => (int) $ingredient->base_unit_id,
                        'package_conversion_id' => null,
                    ];
                }

                $ingredientTotals[$ingredientId]['required_base_qty'] = $this->decimal->add(
                    $ingredientTotals[$ingredientId]['required_base_qty'],
                    $required
                );
            }

            $seenFoods[$foodId] = true;
        }

        return $ingredientTotals;
    }

    /**
     * Validate direct ingredient rows and return their normalized base quantities.
     * Empty direct rows are allowed because a request may contain only food items.
     */
    private function buildDirectIngredientRows(KitchenRequest $request, array $rows): array
    {
        $seen = [];
        $items = [];

        foreach ($rows as $index => $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $quantityRaw = trim((string) ($row['quantity'] ?? ''));
            $unitChoice = trim((string) ($row['unit_choice'] ?? ''));
            if ($ingredientId < 1 && $quantityRaw === '' && $unitChoice === '') {
                continue;
            }
            if ($ingredientId < 1 || $quantityRaw === '' || $unitChoice === '') {
                throw ValidationException::withMessages(["ingredient_items.{$index}" => 'Each direct ingredient row requires ingredient, quantity and unit/package variant.']);
            }
            if (isset($seen[$ingredientId])) {
                throw ValidationException::withMessages(['ingredient_items' => 'The same ingredient cannot appear twice in the Direct Ingredient section.']);
            }

            $ingredient = Ingredient::query()->with('unitConversions.unit')->whereKey($ingredientId)->firstOrFail();
            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages(["ingredient_items.{$index}.ingredient_id" => 'Only active inventory-tracked ingredients can be requested.']);
            }

            $resolved = $this->conversion->resolveChoice($ingredient, $unitChoice);
            $unit = $resolved['unit'];
            $packageConversion = $resolved['conversion'];
            $quantity = $this->decimal->normalize($quantityRaw);
            if (!$this->decimal->isPositive($quantity)) {
                throw ValidationException::withMessages(["ingredient_items.{$index}.quantity" => 'Requested ingredient quantity must be greater than zero.']);
            }
            $factor = $resolved['factor'];
            $base = $this->conversion->toBase($ingredient, $quantity, $unit, null, $packageConversion?->id);

            $items[$ingredientId] = [
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

    /**
     * There is one ingredient requirement row per request. If a recipe and the
     * Direct Ingredient section both need the same ingredient, their base
     * quantities are added and the direct input snapshot is kept for display.
     */
    private function mergeIngredientRequirements(array $foodTotals, array $directTotals): array
    {
        $merged = $foodTotals;

        foreach ($directTotals as $ingredientId => $direct) {
            if (isset($merged[$ingredientId])) {
                $direct['source_kind'] = KitchenRequestIngredientItem::SOURCE_MIXED;
                $direct['required_base_qty'] = $this->decimal->add(
                    $merged[$ingredientId]['required_base_qty'],
                    $direct['required_base_qty']
                );
            }
            $merged[$ingredientId] = $direct;
        }

        ksort($merged, SORT_NUMERIC);
        return array_values($merged);
    }

    private function lockRequest(KitchenRequest $request): KitchenRequest
    {
        return KitchenRequest::query()
            ->withoutGlobalScope(BranchScope::class)
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function nextRequestNumber(int $branchId): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = 'KR-' . $branchId . '-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(5));
            if (!KitchenRequest::query()->withoutGlobalScope(BranchScope::class)
                ->where('branch_id', $branchId)->where('request_no', $number)->exists()) {
                return $number;
            }
        }

        throw ValidationException::withMessages(['request_no' => 'Could not allocate a unique kitchen request number. Please try again.']);
    }
}
