<?php

namespace App\Services\Inventory;

use App\Models\FoodItem;
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
     * A Kitchen Request may be created in either or both ways:
     * 1) Food-wise: Food + quantity => ingredient requirement is calculated from
     *    the food's active recipe and recipe yield.
     * 2) Direct Ingredient: Ingredient + quantity/unit.
     *
     * Both sources are merged into one ingredient requirement list so Inventory
     * Manager always approves/issues raw ingredients. The food rows are retained
     * as recipe/version snapshots for audit and display.
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

            [$foodRows, $foodIngredientTotals] = $this->buildFoodRows((array) ($header['food_items'] ?? []));
            $directRows = $this->buildDirectIngredientRows((array) ($header['ingredient_items'] ?? []));

            if ($foodRows === [] && $directRows === []) {
                throw ValidationException::withMessages([
                    'request' => 'Add at least one Food-wise Request or one Direct Ingredient Request.',
                ]);
            }

            $requestType = $foodRows !== [] && $directRows !== []
                ? KitchenRequest::TYPE_MIXED
                : ($foodRows !== [] ? KitchenRequest::TYPE_FOOD : KitchenRequest::TYPE_INGREDIENT);

            $request->fill([
                'request_type' => $requestType,
                'request_date' => $header['request_date'],
                'status' => $statusToKeep,
                'notes' => $header['notes'] ?? null,
            ]);
            $request->save();

            // Request is editable only before any assignment starts, therefore the
            // saved snapshot can be safely rebuilt while editing a Draft/Submitted request.
            $request->foodItems()->delete();
            $request->ingredientItems()->delete();

            foreach ($foodRows as $foodRow) {
                $request->foodItems()->create($foodRow);
            }

            $finalIngredientRows = $this->mergeIngredientSources($foodIngredientTotals, $directRows);
            foreach ($finalIngredientRows as $item) {
                $request->ingredientItems()->create($item);
            }

            return $request->fresh([
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
        return DB::transaction(function () use ($request) {
            $request = $this->lockRequest($request);
            if ($request->status !== KitchenRequest::STATUS_DRAFT) {
                throw ValidationException::withMessages(['request' => 'Only a draft kitchen request can be sent.']);
            }
            if (!$request->ingredientItems()->exists()) {
                throw ValidationException::withMessages(['request' => 'The request has no calculated/requested ingredients to send.']);
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
                    'request' => 'Only an unassigned draft/submitted kitchen request can be cancelled.',
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

    /**
     * Build Food-wise request snapshots and aggregate their raw ingredient needs.
     * Recipe quantity formula intentionally matches completed-order consumption:
     * recipe_item.base_quantity * requested_food_qty / recipe.yield_quantity.
     */
    private function buildFoodRows(array $rows): array
    {
        $seenFoods = [];
        $foodRows = [];
        $ingredientTotals = [];

        foreach ($rows as $index => $row) {
            $foodIdRaw = trim((string) ($row['menu_item_id'] ?? ''));
            $quantityRaw = trim((string) ($row['quantity'] ?? ''));

            // Empty placeholder rows are allowed because Food-wise Request is optional.
            if ($foodIdRaw === '' && $quantityRaw === '') {
                continue;
            }
            if ($foodIdRaw === '' || $quantityRaw === '') {
                throw ValidationException::withMessages([
                    "food_items.{$index}" => 'Each Food-wise Request row requires both Food Item and Food Quantity.',
                ]);
            }

            $foodId = (int) $foodIdRaw;
            if ($foodId < 1) {
                throw ValidationException::withMessages([
                    "food_items.{$index}.menu_item_id" => 'Select a valid food item.',
                ]);
            }
            if (isset($seenFoods[$foodId])) {
                throw ValidationException::withMessages([
                    'food_items' => 'The same food item cannot appear twice in one kitchen request. Increase its quantity instead.',
                ]);
            }

            $quantity = $this->decimal->normalize($quantityRaw);
            if (!$this->decimal->isPositive($quantity)) {
                throw ValidationException::withMessages([
                    "food_items.{$index}.quantity" => 'Food quantity must be greater than zero.',
                ]);
            }

            $food = FoodItem::query()
                ->with(['activeRecipe.items.ingredient.baseUnit'])
                ->whereKey($foodId)
                ->firstOrFail();
            $recipe = $food->activeRecipe;

            if (!$recipe || $recipe->items->isEmpty() || !$this->decimal->isPositive((string) $recipe->yield_quantity)) {
                throw ValidationException::withMessages([
                    "food_items.{$index}.menu_item_id" => "{$food->name} has no usable active recipe. Configure its recipe first.",
                ]);
            }

            $foodRows[] = [
                'menu_item_id' => $food->id,
                'requested_food_qty' => $quantity,
                'recipe_id' => $recipe->id,
                'recipe_version_no' => $recipe->version_no,
            ];

            foreach ($recipe->items as $recipeItem) {
                $ingredient = $recipeItem->ingredient;
                if (!$ingredient || !$ingredient->is_active || !$ingredient->track_inventory) {
                    throw ValidationException::withMessages([
                        "food_items.{$index}.menu_item_id" => "{$food->name} recipe contains an inactive/non-inventory ingredient. Update the recipe before requesting it.",
                    ]);
                }

                $scaled = $this->decimal->divide(
                    $this->decimal->multiply((string) $recipeItem->base_quantity, $quantity),
                    (string) $recipe->yield_quantity
                );
                if (!$this->decimal->isPositive($scaled)) {
                    continue;
                }

                $ingredientId = (int) $ingredient->id;
                if (!isset($ingredientTotals[$ingredientId])) {
                    $ingredientTotals[$ingredientId] = [
                        'ingredient' => $ingredient,
                        'base_quantity' => '0.00000000',
                    ];
                }
                $ingredientTotals[$ingredientId]['base_quantity'] = $this->decimal->add(
                    $ingredientTotals[$ingredientId]['base_quantity'],
                    $scaled
                );
            }

            $seenFoods[$foodId] = true;
        }

        return [$foodRows, $ingredientTotals];
    }

    /**
     * Normalize optional direct ingredient rows but do not persist them yet. They
     * are merged with recipe-derived quantities before KitchenRequestIngredientItem
     * rows are created.
     */
    private function buildDirectIngredientRows(array $rows): array
    {
        $seen = [];
        $items = [];

        foreach ($rows as $index => $row) {
            $ingredientIdRaw = trim((string) ($row['ingredient_id'] ?? ''));
            $quantityRaw = trim((string) ($row['quantity'] ?? ''));
            $unitChoice = trim((string) ($row['unit_choice'] ?? ''));

            // Empty placeholder rows are allowed because Direct Ingredient is optional.
            if ($ingredientIdRaw === '' && $quantityRaw === '' && $unitChoice === '') {
                continue;
            }
            if ($ingredientIdRaw === '' || $quantityRaw === '' || $unitChoice === '') {
                throw ValidationException::withMessages([
                    "ingredient_items.{$index}" => 'Each Direct Ingredient row requires ingredient, quantity and unit/package variant.',
                ]);
            }

            $ingredientId = (int) $ingredientIdRaw;
            if ($ingredientId < 1) {
                throw ValidationException::withMessages([
                    "ingredient_items.{$index}.ingredient_id" => 'Select a valid ingredient.',
                ]);
            }
            if (isset($seen[$ingredientId])) {
                throw ValidationException::withMessages([
                    'ingredient_items' => 'The same direct ingredient cannot appear twice. Increase its quantity instead.',
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

            $base = $this->conversion->toBase(
                $ingredient,
                $quantity,
                $unit,
                null,
                $packageConversion?->id
            );

            $items[$ingredientId] = [
                'ingredient' => $ingredient,
                'input_quantity' => $quantity,
                'conversion_factor_snapshot' => $resolved['factor'],
                'base_quantity' => $base,
                'display_unit_id' => $unit->id,
                'package_conversion_id' => $packageConversion?->id,
            ];
            $seen[$ingredientId] = true;
        }

        return $items;
    }

    /**
     * Merge recipe-derived and direct quantities by ingredient. If the same
     * ingredient appears in both methods, one MIXED row is created with the sum.
     */
    private function mergeIngredientSources(array $foodTotals, array $directRows): array
    {
        $ingredientIds = array_values(array_unique(array_merge(array_keys($foodTotals), array_keys($directRows))));
        sort($ingredientIds, SORT_NUMERIC);

        $items = [];
        foreach ($ingredientIds as $ingredientId) {
            $food = $foodTotals[$ingredientId] ?? null;
            $direct = $directRows[$ingredientId] ?? null;
            $ingredient = $direct['ingredient'] ?? $food['ingredient'] ?? null;
            if (!$ingredient) {
                continue;
            }

            $foodBase = $food['base_quantity'] ?? '0.00000000';
            $directBase = $direct['base_quantity'] ?? '0.00000000';
            $required = $this->decimal->add($foodBase, $directBase);
            if (!$this->decimal->isPositive($required)) {
                continue;
            }

            if ($food && $direct) {
                $sourceKind = KitchenRequestIngredientItem::SOURCE_MIXED;
                // Keep the Direct component snapshot so an editable Mixed request can
                // faithfully rebuild its Direct Ingredient row. required_base_qty still
                // stores the Food-derived + Direct grand total used for assignment.
                $inputQuantity = $direct['input_quantity'];
                $factor = $direct['conversion_factor_snapshot'];
                $displayUnitId = $direct['display_unit_id'];
                $packageConversionId = $direct['package_conversion_id'];
            } elseif ($food) {
                $sourceKind = KitchenRequestIngredientItem::SOURCE_FOOD;
                $inputQuantity = null;
                $factor = '1.00000000';
                $displayUnitId = $ingredient->base_unit_id;
                $packageConversionId = null;
            } else {
                $sourceKind = KitchenRequestIngredientItem::SOURCE_DIRECT;
                $inputQuantity = $direct['input_quantity'];
                $factor = $direct['conversion_factor_snapshot'];
                $displayUnitId = $direct['display_unit_id'];
                $packageConversionId = $direct['package_conversion_id'];
            }

            $items[] = [
                'ingredient_id' => $ingredient->id,
                'source_kind' => $sourceKind,
                'input_quantity' => $inputQuantity,
                'conversion_factor_snapshot' => $factor,
                'required_base_qty' => $required,
                'approved_base_qty' => '0.00000000',
                'issued_base_qty' => '0.00000000',
                'display_unit_id' => $displayUnitId,
                'package_conversion_id' => $packageConversionId,
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'request' => 'The request did not produce any valid ingredient quantity.',
            ]);
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
