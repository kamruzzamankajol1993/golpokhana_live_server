<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IngredientController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-ingredients-manage');
    }

    public function index(Request $request)
    {
        $ingredients = Ingredient::query()
            ->with(['baseUnit', 'unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search)
                        ->orWhereHas('unitConversions', fn ($conversion) => $conversion->where('label', 'like', $search));
                });
            })
            ->when($request->filled('dimension'), fn ($q) => $q->where('measurement_dimension', strtoupper((string) $request->dimension)))
            ->orderBy('name')
            ->paginate(10)
            ->appends($request->query());

        return view('admin.inventory.ingredients.index', compact('ingredients'));
    }

    public function create()
    {
        return view('admin.inventory.ingredients.create', $this->formData());
    }

    public function store(Request $request)
    {
        [$data, $conversions] = $this->validated($request);

        DB::transaction(function () use ($data, $conversions) {
            $ingredient = Ingredient::query()->create($data);
            $this->syncConversions($ingredient, $conversions);
        });

        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient created successfully.');
    }

    public function edit(Ingredient $ingredient)
    {
        $ingredient->load(['unitConversions' => fn ($q) => $q->where('is_active', true)->with('unit')]);
        return view('admin.inventory.ingredients.edit', $this->formData() + compact('ingredient'));
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        [$data, $conversions] = $this->validated($request, $ingredient);

        $baseRuleChanged = $ingredient->measurement_dimension !== $data['measurement_dimension']
            || (int) $ingredient->base_unit_id !== (int) $data['base_unit_id'];

        if ($baseRuleChanged && (
            $ingredient->balances()->exists()
            || $ingredient->movementItems()->exists()
            || $ingredient->purchaseItems()->exists()
            || $ingredient->recipeItems()->exists()
        )) {
            throw ValidationException::withMessages([
                'base_unit_id' => 'Base unit cannot be changed after stock, purchase, or recipe history exists for this ingredient.',
            ]);
        }

        DB::transaction(function () use ($ingredient, $data, $conversions) {
            $ingredient->update($data);
            $this->syncConversions($ingredient, $conversions);
        });

        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient updated successfully.');
    }

    public function destroy(Ingredient $ingredient)
    {
        if (
            $ingredient->balances()->exists()
            || $ingredient->movementItems()->exists()
            || $ingredient->purchaseItems()->exists()
            || $ingredient->recipeItems()->exists()
        ) {
            return redirect()->route('inventory.ingredients.index')
                ->with('error', 'This ingredient is already used in inventory, purchase, or recipe history. Set it Inactive instead of deleting it.');
        }

        try {
            $ingredient->delete();
        } catch (QueryException $exception) {
            return redirect()->route('inventory.ingredients.index')
                ->with('error', 'This ingredient is linked to inventory history and cannot be deleted. Set it Inactive instead.');
        }

        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient deleted successfully.');
    }

    private function formData(): array
    {
        return [
            'baseUnits' => Unit::query()
                ->active()
                ->where('is_base', true)
                ->whereIn('dimension', [Unit::DIMENSION_WEIGHT, Unit::DIMENSION_VOLUME, Unit::DIMENSION_COUNT])
                ->orderByRaw("CASE dimension WHEN 'WEIGHT' THEN 1 WHEN 'VOLUME' THEN 2 WHEN 'COUNT' THEN 3 ELSE 4 END")
                ->orderBy('name')
                ->get(),
            'packageUnits' => Unit::query()->active()->where('dimension', Unit::DIMENSION_PACKAGE)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Ingredient $ingredient = null): array
    {
        $request->merge(['name' => trim((string) $request->name)]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('ingredients', 'name')->ignore($ingredient?->id)],
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'low_stock_level_base' => ['required', 'numeric', 'gte:0'],
            'conversions' => ['nullable', 'array'],
            'conversions.*.id' => ['nullable', 'integer', 'exists:ingredient_unit_conversions,id'],
            'conversions.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'conversions.*.label' => ['nullable', 'string', 'max:160'],
            'conversions.*.factor_to_base' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $baseUnit = Unit::query()->findOrFail((int) $validated['base_unit_id']);
        if (!$baseUnit->is_active || !$baseUnit->is_base || !in_array($baseUnit->dimension, [Unit::DIMENSION_WEIGHT, Unit::DIMENSION_VOLUME, Unit::DIMENSION_COUNT], true)) {
            throw ValidationException::withMessages([
                'base_unit_id' => 'Select an active base unit for Weight, Volume or Count.',
            ]);
        }

        $conversions = [];
        $seenVariants = [];
        $seenLabels = [];
        foreach ((array) ($request->input('conversions') ?? []) as $index => $row) {
            $conversionId = (int) ($row['id'] ?? 0);
            $unitId = (int) ($row['unit_id'] ?? 0);
            $factor = $row['factor_to_base'] ?? null;
            $label = trim((string) ($row['label'] ?? ''));

            if ($unitId < 1 && ($factor === null || $factor === '') && $label === '') {
                continue;
            }
            if ($unitId < 1 || $factor === null || $factor === '') {
                throw ValidationException::withMessages([
                    "conversions.{$index}" => 'Each package conversion requires a package unit and package size/conversion.',
                ]);
            }

            $unit = Unit::query()->findOrFail($unitId);
            if (!$unit->is_active || $unit->dimension !== Unit::DIMENSION_PACKAGE) {
                throw ValidationException::withMessages([
                    "conversions.{$index}.unit_id" => 'Select an active package unit.',
                ]);
            }

            if ($conversionId > 0 && (!$ingredient || !$ingredient->unitConversions()->whereKey($conversionId)->exists())) {
                throw ValidationException::withMessages([
                    "conversions.{$index}.id" => 'This package conversion does not belong to the ingredient being edited.',
                ]);
            }

            $factorKey = $this->normalizeFactorForKey((string) $factor);
            $variantKey = $unitId . '|' . $factorKey;
            if (isset($seenVariants[$variantKey])) {
                throw ValidationException::withMessages(['conversions' => 'The same package type and size cannot be added twice.']);
            }
            $seenVariants[$variantKey] = true;

            if ($label === '') {
                $label = $this->automaticConversionLabel($unit, (string) $factor, $baseUnit);
            }
            $labelKey = mb_strtolower($label);
            if (isset($seenLabels[$labelKey])) {
                throw ValidationException::withMessages(['conversions' => 'Each package conversion name must be unique for this ingredient.']);
            }
            $seenLabels[$labelKey] = true;

            $conversions[] = [
                'id' => $conversionId ?: null,
                'unit_id' => $unitId,
                'label' => $label,
                'factor_to_base' => $factor,
                // Advanced flags are intentionally hidden in the simplified UI.
                'purchase_allowed' => true,
                'recipe_allowed' => true,
                'effective_from' => null,
                'is_active' => true,
            ];
        }

        $data = [
            'name' => $validated['name'],
            'code' => $ingredient?->code, // Existing codes remain intact; new ingredients do not require a code.
            'measurement_dimension' => $baseUnit->dimension,
            'base_unit_id' => (int) $baseUnit->id,
            'low_stock_level_base' => $validated['low_stock_level_base'],
            'track_inventory' => true,
            'is_active' => $request->boolean('is_active', true),
        ];

        return [$data, $conversions];
    }

    private function syncConversions(Ingredient $ingredient, array $conversions): void
    {
        $keep = [];
        foreach ($conversions as $conversion) {
            $conversionId = $conversion['id'] ?? null;
            unset($conversion['id']);

            if ($conversionId) {
                $record = $ingredient->unitConversions()->whereKey($conversionId)->firstOrFail();
                $record->update($conversion);
            } else {
                $record = $ingredient->unitConversions()->create($conversion);
            }
            $keep[] = $record->id;
        }

        // Keep old conversions as inactive history instead of deleting rows that may
        // already be referenced by purchase/recipe/transfer snapshots.
        $query = $ingredient->unitConversions()->where('is_active', true);
        if ($keep) {
            $query->whereNotIn('id', $keep);
        }
        $query->update([
            'is_active' => false,
            'purchase_allowed' => false,
            'recipe_allowed' => false,
        ]);
    }

    private function automaticConversionLabel(Unit $packageUnit, string $factor, Unit $baseUnit): string
    {
        return $packageUnit->name . ' (' . $this->normalizeFactorForLabel($factor) . ' ' . $baseUnit->symbol . ')';
    }

    private function normalizeFactorForLabel(string $factor): string
    {
        $factor = trim($factor);
        if (str_contains($factor, '.')) {
            $factor = rtrim(rtrim($factor, '0'), '.');
        }
        return $factor === '' ? '0' : $factor;
    }

    private function normalizeFactorForKey(string $factor): string
    {
        return rtrim(rtrim(number_format((float) $factor, 8, '.', ''), '0'), '.');
    }
}
