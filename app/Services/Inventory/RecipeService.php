<?php

namespace App\Services\Inventory;

use App\Models\FoodItem;
use App\Models\Ingredient;
use App\Models\MenuItemRecipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeService
{
    public function __construct(
        private UnitConversionService $conversion,
        private DecimalQuantity $decimal
    ) {
    }

    public function normalizeRows(array $rows, bool $trackingEnabled): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $quantity = trim((string) ($row['quantity'] ?? ''));
            $unitChoice = trim((string) ($row['unit_choice'] ?? ''));

            if ($ingredientId < 1 && $quantity === '' && $unitChoice === '') {
                continue;
            }
            if ($ingredientId < 1 || $quantity === '' || $unitChoice === '') {
                throw ValidationException::withMessages([
                    "recipe.{$index}" => 'Each recipe row requires ingredient, quantity and unit/package variant.',
                ]);
            }
            if (isset($seen[$ingredientId])) {
                throw ValidationException::withMessages([
                    'recipe' => 'The same ingredient cannot appear twice in one recipe version.',
                ]);
            }

            $ingredient = Ingredient::query()
                ->with(['unitConversions' => fn ($q) => $q->where('is_active', true)])
                ->whereKey($ingredientId)
                ->firstOrFail();
            if (!$ingredient->is_active || !$ingredient->track_inventory) {
                throw ValidationException::withMessages([
                    "recipe.{$index}.ingredient_id" => 'Only active inventory-tracked ingredients can be used in a new recipe.',
                ]);
            }

            $selection = $this->conversion->resolveChoice($ingredient, $unitChoice, 'recipe');
            $unit = $selection['unit'];
            $packageConversion = $selection['conversion'];
            $quantityNormalized = $this->decimal->normalize($quantity);
            if (!$this->decimal->isPositive($quantityNormalized)) {
                throw ValidationException::withMessages([
                    "recipe.{$index}.quantity" => 'Recipe quantity must be greater than zero.',
                ]);
            }
            $base = $this->decimal->multiply($quantityNormalized, $selection['factor']);

            $seen[$ingredientId] = true;
            $normalized[] = [
                'ingredient_id' => $ingredientId,
                'input_quantity' => $quantityNormalized,
                'input_unit_id' => $unit->id,
                'package_conversion_id' => $packageConversion?->id,
                'base_quantity' => $base,
            ];
        }

        if ($trackingEnabled && $normalized === []) {
            throw ValidationException::withMessages([
                'inventory_tracking' => 'Add at least one recipe ingredient before enabling inventory tracking.',
            ]);
        }

        usort($normalized, fn ($a, $b) => $a['ingredient_id'] <=> $b['ingredient_id']);
        return $normalized;
    }

    public function syncRecipe(FoodItem $food, array $normalizedRows, ?int $userId = null): ?MenuItemRecipe
    {
        if ($normalizedRows === []) {
            return $food->activeRecipe()->with('items')->first();
        }

        return DB::transaction(function () use ($food, $normalizedRows, $userId) {
            FoodItem::query()->withoutGlobalScopes()->whereKey($food->id)->lockForUpdate()->firstOrFail();

            $active = MenuItemRecipe::query()
                ->where('menu_item_id', $food->id)
                ->where('is_active', true)
                ->with('items')
                ->lockForUpdate()
                ->first();

            if ($active && $this->sameRows($active, $normalizedRows)) {
                return $active;
            }

            $nextVersion = ((int) MenuItemRecipe::query()->where('menu_item_id', $food->id)->max('version_no')) + 1;

            if ($active) {
                $active->forceFill(['is_active' => false])->save();
            }

            $recipe = MenuItemRecipe::query()->create([
                'menu_item_id' => $food->id,
                'version_no' => $nextVersion,
                'yield_quantity' => '1.00000000',
                'is_active' => true,
                'effective_from' => now(),
                'created_by' => $userId,
            ]);

            foreach ($normalizedRows as $row) {
                $recipe->items()->create($row);
            }

            return $recipe->load(['items.ingredient.baseUnit', 'items.inputUnit', 'items.packageConversion']);
        }, 5);
    }

    private function sameRows(MenuItemRecipe $recipe, array $normalizedRows): bool
    {
        $current = $recipe->items
            ->map(fn ($row) => [
                'ingredient_id' => (int) $row->ingredient_id,
                'input_quantity' => $this->decimal->normalize((string) $row->input_quantity),
                'input_unit_id' => (int) $row->input_unit_id,
                'package_conversion_id' => $row->package_conversion_id ? (int) $row->package_conversion_id : null,
            ])
            ->sortBy('ingredient_id')
            ->values()
            ->all();

        $submitted = array_map(fn ($row) => [
            'ingredient_id' => (int) $row['ingredient_id'],
            'input_quantity' => $this->decimal->normalize((string) $row['input_quantity']),
            'input_unit_id' => (int) $row['input_unit_id'],
            'package_conversion_id' => !empty($row['package_conversion_id']) ? (int) $row['package_conversion_id'] : null,
        ], array_values($normalizedRows));

        return $current === $submitted;
    }
}
