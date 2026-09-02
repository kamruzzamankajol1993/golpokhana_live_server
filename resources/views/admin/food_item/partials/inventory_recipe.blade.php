@php
    $recipeInputRows = session()->hasOldInput() ? old('recipe', []) : ($recipeRows ?? []);
    if (empty($recipeInputRows)) $recipeInputRows = [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'']];
    $trackingChecked = session()->hasOldInput() ? (bool)old('inventory_tracking') : (bool)($foodItem->inventory_tracking ?? false);
@endphp
<div class="af-card" style="margin-top:20px;">
    <div class="af-card-head"><div class="af-card-num">06</div><div class="af-card-title">Inventory Recipe</div></div>
    <div class="af-card-body">
        <div class="af-toggle-row" style="padding-top:0;margin-bottom:14px;">
            <div class="af-toggle-info"><div class="af-toggle-name">Inventory Tracking</div><div class="af-toggle-hint">Existing items stay OFF until a recipe is configured. You can save a recipe while tracking is OFF.</div></div>
            <label class="progga-toggle"><input type="checkbox" name="inventory_tracking" value="1" {{ $trackingChecked ? 'checked' : '' }}><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span></label>
        </div>
        @if(isset($foodItem) && $foodItem->activeRecipe)
            <div class="alert alert-light border py-2 px-3 small mb-3"><strong>Active recipe:</strong> Version {{ $foodItem->activeRecipe->version_no }}. Changing ingredient, quantity or unit creates a new version; the old version remains historical.</div>
        @endif
        <div class="small text-muted mb-2">Quantities are saved in the selected input unit and also normalized to each ingredient's base unit. Package units require an ingredient-specific recipe conversion.</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2">
                <thead><tr><th style="min-width:210px;">Ingredient</th><th style="width:130px;">Quantity</th><th style="min-width:150px;">Unit</th><th style="width:54px;"></th></tr></thead>
                <tbody id="inventoryRecipeRows">
                    @foreach($recipeInputRows as $idx => $row)
                    <tr class="inventory-recipe-row">
                        <td><select name="recipe[{{ $idx }}][ingredient_id]" class="progga-form-control recipe-ingredient" onchange="filterRecipeUnits(this)"><option value="">Select ingredient</option>@foreach($inventoryIngredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td>
                        <td><input type="number" step="0.01" min="0.01" name="recipe[{{ $idx }}][quantity]" value="{{ ($row['quantity'] ?? '') !== '' && is_numeric($row['quantity']) ? number_format((float)$row['quantity'], 2, '.', '') : ($row['quantity'] ?? '') }}" class="progga-form-control" placeholder="0.00"></td>
                        <td><select name="recipe[{{ $idx }}][unit_choice]" class="progga-form-control recipe-unit" data-selected="{{ $row['unit_choice']??'' }}"><option value="">Select ingredient first</option></select></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRecipeRow(this)" title="Remove"><i class="bi bi-trash"></i></button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" onclick="addRecipeRow()"><i class="bi bi-plus-lg"></i> Add Ingredient</button>
    </div>
</div>
