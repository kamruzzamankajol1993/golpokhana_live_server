<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-units-manage');
    }

    public function index(Request $request)
    {
        $units = Unit::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->search) . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('symbol', 'like', $search));
            })
            ->orderByRaw("CASE dimension WHEN 'WEIGHT' THEN 1 WHEN 'VOLUME' THEN 2 WHEN 'COUNT' THEN 3 WHEN 'PACKAGE' THEN 4 ELSE 5 END")
            ->orderByDesc('is_base')
            ->orderBy('name')
            ->paginate(10)
            ->appends($request->query());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.inventory.units.partials.table', compact('units'))->render(),
                'total' => $units->total(),
            ]);
        }

        return view('admin.inventory.units.index', compact('units'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Unit::query()->create($data);

        return back()->with('success', 'Unit created successfully.');
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $this->validated($request, $unit);

        $usedAsBase = $unit->ingredientsAsBase()->exists();
        $usedInConversions = $unit->ingredientConversions()->exists();
        $dimensionChanged = $unit->dimension !== $data['dimension'];

        if ($dimensionChanged && ($usedAsBase || $usedInConversions)) {
            throw ValidationException::withMessages([
                'dimension' => 'This unit is already used by inventory data, so its measurement dimension cannot be changed.',
            ]);
        }

        if ($usedAsBase && (!$data['is_base'] || !$data['is_active'])) {
            throw ValidationException::withMessages([
                'is_base' => 'A unit used as an ingredient base unit must remain active and marked as a base unit.',
            ]);
        }

        $unit->update($data);
        return back()->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->is_base) {
            return back()->withErrors([
                'unit' => 'A base unit cannot be deleted. Keep it as the reference unit for its measurement dimension.',
            ]);
        }

        try {
            $unit->delete();
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000' || str_contains(strtolower($exception->getMessage()), 'foreign key')) {
                return back()->withErrors([
                    'unit' => 'This unit is already used by inventory records and cannot be deleted. You can mark it inactive instead.',
                ]);
            }

            throw $exception;
        }

        return back()->with('success', 'Unit deleted successfully.');
    }

    private function validated(Request $request, ?Unit $unit = null): array
    {
        $request->merge([
            'name' => trim((string) $request->name),
            'symbol' => trim((string) $request->symbol),
            'dimension' => strtoupper(trim((string) $request->dimension)),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('units', 'name')->ignore($unit?->id)],
            'symbol' => ['required', 'string', 'max:20', Rule::unique('units', 'symbol')->ignore($unit?->id)],
            'dimension' => ['required', Rule::in(Unit::dimensions())],
            'standard_to_base_factor' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $isBase = $request->boolean('is_base');
        $isActive = $request->boolean('is_active');

        if ($data['dimension'] === Unit::DIMENSION_PACKAGE) {
            $isBase = false;
            $data['standard_to_base_factor'] = null;
        } else {
            if ($data['standard_to_base_factor'] === null) {
                throw ValidationException::withMessages([
                    'standard_to_base_factor' => 'Standard units require a conversion factor to the dimension base unit.',
                ]);
            }
            if ($isBase && !preg_match('/^1(?:\.0+)?$/', (string) $data['standard_to_base_factor'])) {
                throw ValidationException::withMessages([
                    'standard_to_base_factor' => 'A base unit must have a factor of 1.',
                ]);
            }
            if ($isBase && Unit::query()
                ->where('dimension', $data['dimension'])
                ->where('is_base', true)
                ->when($unit, fn ($q) => $q->where('id', '<>', $unit->id))
                ->exists()) {
                throw ValidationException::withMessages([
                    'is_base' => 'Each measurement dimension can have only one base unit.',
                ]);
            }
        }

        return $data + [
            'is_base' => $isBase,
            'is_active' => $isActive,
        ];
    }
}
