<?php

namespace App\Services\Inventory;

use App\Models\Ingredient;
use App\Models\IngredientUnitConversion;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

class UnitConversionService
{
    public function __construct(private DecimalQuantity $decimal)
    {
    }

    public function toBase(
        Ingredient $ingredient,
        string|int $quantity,
        Unit|int $unit,
        ?string $usage = null,
        ?int $packageConversionId = null
    ): string {
        $unit = $unit instanceof Unit ? $unit : Unit::query()->findOrFail($unit);
        $quantity = $this->decimal->normalize($quantity);

        if (!$this->decimal->isPositive($quantity)) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        $factor = $this->factorFor($ingredient, $unit, $usage, $packageConversionId);
        return $this->decimal->multiply($quantity, $factor);
    }

    public function factorFor(
        Ingredient $ingredient,
        Unit $unit,
        ?string $usage = null,
        ?int $packageConversionId = null
    ): string {
        if (!$unit->is_active) {
            throw ValidationException::withMessages(['unit_id' => 'The selected unit is inactive.']);
        }

        if ($unit->dimension === $ingredient->measurement_dimension && $unit->standard_to_base_factor !== null) {
            return $this->decimal->normalize((string) $unit->standard_to_base_factor);
        }

        if ($unit->dimension === Unit::DIMENSION_PACKAGE) {
            $conversion = $this->resolvePackageConversion($ingredient, $unit, $usage, $packageConversionId);
            return $this->decimal->normalize((string) $conversion->factor_to_base);
        }

        throw ValidationException::withMessages([
            'unit_id' => "{$unit->name} is not compatible with {$ingredient->measurement_dimension} ingredients.",
        ]);
    }

    /**
     * Resolve a form unit choice. Standard units use "u:{unit_id}" and package
     * variants use "c:{ingredient_unit_conversion_id}".
     *
     * @return array{unit: Unit, conversion: ?IngredientUnitConversion, factor: string, choice: string}
     */
    public function resolveChoice(Ingredient $ingredient, string|int $choice, ?string $usage = null): array
    {
        $choice = trim((string) $choice);

        if (preg_match('/^c:(\d+)$/', $choice, $matches)) {
            $conversion = IngredientUnitConversion::query()
                ->with('unit')
                ->whereKey((int) $matches[1])
                ->where('ingredient_id', $ingredient->id)
                ->where('is_active', true)
                ->first();

            if (!$conversion || !$conversion->unit || !$conversion->unit->is_active) {
                throw ValidationException::withMessages([
                    'unit_choice' => 'The selected package variant is no longer active for this ingredient.',
                ]);
            }

            $this->assertUsageAllowed($conversion, $usage, $conversion->unit, $ingredient);

            return [
                'unit' => $conversion->unit,
                'conversion' => $conversion,
                'factor' => $this->decimal->normalize((string) $conversion->factor_to_base),
                'choice' => 'c:' . $conversion->id,
            ];
        }

        if (preg_match('/^u:(\d+)$/', $choice, $matches)) {
            $unit = Unit::query()->findOrFail((int) $matches[1]);
            $factor = $this->factorFor($ingredient, $unit, $usage);

            return [
                'unit' => $unit,
                'conversion' => null,
                'factor' => $factor,
                'choice' => 'u:' . $unit->id,
            ];
        }

        // Backward compatibility for forms/posts created before package variant choices.
        if (ctype_digit($choice) && (int) $choice > 0) {
            $unit = Unit::query()->findOrFail((int) $choice);
            $conversion = null;
            if ($unit->dimension === Unit::DIMENSION_PACKAGE) {
                $conversion = $this->resolvePackageConversion($ingredient, $unit, $usage, null);
            }
            $factor = $this->factorFor($ingredient, $unit, $usage, $conversion?->id);

            return [
                'unit' => $unit,
                'conversion' => $conversion,
                'factor' => $factor,
                'choice' => $conversion ? 'c:' . $conversion->id : 'u:' . $unit->id,
            ];
        }

        throw ValidationException::withMessages([
            'unit_choice' => 'Select a valid unit or package variant.',
        ]);
    }

    private function resolvePackageConversion(
        Ingredient $ingredient,
        Unit $unit,
        ?string $usage,
        ?int $packageConversionId
    ): IngredientUnitConversion {
        $query = $ingredient->unitConversions()
            ->where('unit_id', $unit->id)
            ->where('is_active', true);

        if ($usage === 'purchase') {
            $query->where('purchase_allowed', true);
        } elseif ($usage === 'recipe') {
            $query->where('recipe_allowed', true);
        }

        if ($packageConversionId) {
            $conversion = (clone $query)->whereKey($packageConversionId)->first();
            if (!$conversion) {
                throw ValidationException::withMessages([
                    'unit_id' => "The selected {$unit->name} variant is not available for {$ingredient->name}.",
                ]);
            }
            return $conversion;
        }

        $matches = $query->limit(2)->get();
        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'unit_id' => "No active {$unit->name} conversion is defined for {$ingredient->name}.",
            ]);
        }
        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'unit_id' => "Multiple {$unit->name} package variants exist for {$ingredient->name}. Select the specific package label (for example 500 g / 1 kg) instead of the generic package unit.",
            ]);
        }

        return $matches->first();
    }

    private function assertUsageAllowed(IngredientUnitConversion $conversion, ?string $usage, Unit $unit, Ingredient $ingredient): void
    {
        if ($usage === 'purchase' && !$conversion->purchase_allowed) {
            throw ValidationException::withMessages([
                'unit_id' => "{$unit->name} is not allowed for purchasing {$ingredient->name}.",
            ]);
        }
        if ($usage === 'recipe' && !$conversion->recipe_allowed) {
            throw ValidationException::withMessages([
                'unit_id' => "{$unit->name} is not allowed in recipes for {$ingredient->name}.",
            ]);
        }
    }
}
