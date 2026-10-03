@php
    $recipeInputRows = session()->hasOldInput() ? old('recipe', []) : ($recipeRows ?? []);
    if (empty($recipeInputRows)) $recipeInputRows = [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'']];
@endphp
<div class="af-card" id="inventory-recipe" style="margin-top:20px;">
    <div class="af-card-head"><div class="af-card-num">06</div><div class="af-card-title">Food Recipe</div></div>
    <div class="af-card-body">
        <div class="alert alert-info py-2 px-3 small mb-3">
            Add the ingredients used for <strong>1 sold item / 1 portion</strong>. Inventory tracking turns on automatically when a recipe is saved. Recipe history/versioning is maintained automatically in the background.
        </div>
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
