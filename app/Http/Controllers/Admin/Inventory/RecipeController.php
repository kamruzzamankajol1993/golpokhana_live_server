<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Services\Inventory\RecipeService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-recipes-manage');
    }

    public function index(Request $request)
    {
        $foods = FoodItem::query()
            ->with(['category', 'activeRecipe.items.ingredient.baseUnit', 'activeRecipe.items.inputUnit'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where('name', 'like', $search);
            })
            ->when($request->status === 'configured', fn ($q) => $q->whereHas('activeRecipe'))
            ->when($request->status === 'missing', fn ($q) => $q->whereDoesntHave('activeRecipe'))
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.inventory.recipes.index', compact('foods'));
    }

    public function edit(FoodItem $food)
    {
        $food->load(['activeRecipe.items.ingredient.unitConversions.unit', 'activeRecipe.items.inputUnit']);

        $recipeRows = $food->activeRecipe?->items?->map(fn ($row) => [
            'ingredient_id' => $row->ingredient_id,
            'quantity' => rtrim(rtrim(number_format((float) $row->input_quantity, 2, '.', ''), '0'), '.'),
            'unit_choice' => $this->recipeUnitChoice($row),
        ])->values()->all() ?? [];

        $ingredients = $this->ingredients();
        $units = Unit::query()->active()->orderBy('dimension')->orderBy('name')->get();

        $recipeIngredientsData = $ingredients->map(function ($ingredient) {
            return [
                'id' => (int) $ingredient->id,
                'name' => $ingredient->name,
                'base' => $ingredient->baseUnit?->symbol,
                'base_unit_id' => (int) $ingredient->base_unit_id,
                'dimension' => $ingredient->measurement_dimension,
                'packages' => $ingredient->unitConversions
                    ->filter(fn ($conversion) => $conversion->is_active && $conversion->unit?->is_active)
                    ->map(function ($conversion) use ($ingredient) {
                        $factor = rtrim(rtrim((string) $conversion->factor_to_base, '0'), '.');
                        $label = $conversion->label ?: (($conversion->unit?->name ?? 'Package') . ' (' . $factor . ' ' . ($ingredient->baseUnit?->symbol ?? '') . ')');

                        return [
                            'id' => (int) $conversion->id,
                            'label' => $label,
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        $recipeUnitsData = $units
            ->where('dimension', '!=', Unit::DIMENSION_PACKAGE)
            ->map(fn ($unit) => [
                'id' => (int) $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'dimension' => $unit->dimension,
            ])
            ->values()
            ->all();

        return view('admin.inventory.recipes.edit', [
            'food' => $food,
            'recipeRows' => $recipeRows,
            'ingredients' => $ingredients,
            'units' => $units,
            'recipeIngredientsData' => $recipeIngredientsData,
            'recipeUnitsData' => $recipeUnitsData,
        ]);
    }

    public function update(Request $request, FoodItem $food, RecipeService $recipes)
    {
        $request->validate([
            'recipe' => ['nullable', 'array'],
            'recipe.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'recipe.*.quantity' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'recipe.*.unit_choice' => ['nullable', 'string', 'regex:/^(u|c):[1-9][0-9]*$/'],
        ]);

        $rows = $recipes->normalizeRows((array) $request->input('recipe', []), false);
        $recipes->syncRecipe($food, $rows, $request->user()?->id);
        $food->forceFill(['inventory_tracking' => $rows !== []])->save();

        return redirect()->route('inventory.recipes.index')
            ->with('success', $rows === [] ? 'Recipe removed from active inventory usage.' : 'Food recipe saved successfully.');
    }

    private function ingredients()
    {
        return Ingredient::query()
            ->active()
            ->where('track_inventory', true)
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->orderBy('name')
            ->get();
    }

    private function recipeUnitChoice($row): string
    {
        if (!empty($row->package_conversion_id)) {
            return 'c:' . (int) $row->package_conversion_id;
        }

        if ($row->inputUnit?->dimension !== Unit::DIMENSION_PACKAGE) {
            return 'u:' . (int) $row->input_unit_id;
        }

        $input = (float) $row->input_quantity;
        $base = (float) $row->base_quantity;
        if ($input > 0) {
            $factor = $base / $input;
            $match = $row->ingredient?->unitConversions?->first(function ($conversion) use ($row, $factor) {
                return (int) $conversion->unit_id === (int) $row->input_unit_id
                    && abs((float) $conversion->factor_to_base - $factor) < 0.00000001;
            });
            if ($match) {
                return 'c:' . (int) $match->id;
            }
        }

        return 'u:' . (int) $row->input_unit_id;
    }
}
