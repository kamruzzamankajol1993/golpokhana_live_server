@extends('admin.master.master')
@php
    $rows = session()->hasOldInput() ? old('recipe', []) : ($recipeRows ?? []);
    if (empty($rows)) $rows = [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'']];
@endphp
@section('title','Food Recipe - '.$food->name)
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Food Recipe</h1>
            <p class="text-muted mb-0"><strong>{{ $food->name }}</strong> — enter ingredients for <strong>1 sold item / 1 portion</strong>.</p>
        </div>
        <a href="{{ route('inventory.recipes.index') }}" class="progga-btn progga-btn-outline">Back to Recipes</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('inventory.recipes.update',$food) }}">
        @csrf @method('PUT')
        <div class="progga-card mb-4">
            <div class="progga-card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Recipe Ingredients</strong>
                    <div class="small text-muted">One row = one ingredient. Unit conversion and recipe version history are handled automatically.</div>
                </div>
                <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addRecipeRow()"><i class="bi bi-plus-lg"></i> Add Ingredient</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Ingredient</th><th style="width:180px">Quantity</th><th style="width:260px">Unit</th><th style="width:60px"></th></tr></thead>
                    <tbody id="recipeRows">
                    @foreach($rows as $idx=>$row)
                        <tr class="recipe-row">
                            <td><select name="recipe[{{ $idx }}][ingredient_id]" class="progga-form-control recipe-ingredient" onchange="filterRecipeUnits(this)"><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td>
                            <td><input type="number" step="0.01" min="0.01" name="recipe[{{ $idx }}][quantity]" value="{{ ($row['quantity']??'') !== '' && is_numeric($row['quantity']) ? number_format((float)$row['quantity'],2,'.','') : ($row['quantity']??'') }}" class="progga-form-control" placeholder="0.00"></td>
                            <td><select name="recipe[{{ $idx }}][unit_choice]" class="progga-form-control recipe-unit" data-selected="{{ $row['unit_choice']??'' }}"><option value="">Select ingredient first</option></select></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRecipeRow(this)"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info">Saving a recipe automatically enables inventory tracking for this food. If all rows are removed/cleared, the active recipe is closed and inventory tracking is disabled for the food.</div>
        <div class="d-flex justify-content-end gap-2"><a href="{{ route('inventory.recipes.index') }}" class="progga-btn progga-btn-light">Cancel</a><button class="progga-btn progga-btn-primary">Save Recipe</button></div>
    </form>
</main>
@endsection
@section('script')
<script>
const recipeIngredients = {{ \Illuminate\Support\Js::from($recipeIngredientsData) }};
const recipeUnits = {{ \Illuminate\Support\Js::from($recipeUnitsData) }};
let recipeIndex={{ count($rows) }};
function re(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function ingredientOptions(){return '<option value="">Select ingredient</option>'+recipeIngredients.map(i=>`<option value="${i.id}">${re(i.name)} (${re(i.base)})</option>`).join('');}
function unitOptions(id){const i=recipeIngredients.find(x=>String(x.id)===String(id));if(!i)return '<option value="">Select ingredient first</option>';let html='<option value="">Select unit / package</option>';html+=recipeUnits.filter(u=>u.dimension===i.dimension).map(u=>`<option value="u:${u.id}">${re(u.name)} (${re(u.symbol)})</option>`).join('');if(i.packages.length)html+='<optgroup label="Package sizes">'+i.packages.map(p=>`<option value="c:${p.id}">${re(p.label)}</option>`).join('')+'</optgroup>';return html;}
function filterRecipeUnits(select){const row=select.closest('.recipe-row'),unit=row.querySelector('.recipe-unit'),wanted=unit.dataset.selected||unit.value;unit.innerHTML=unitOptions(select.value);const i=recipeIngredients.find(x=>String(x.id)===String(select.value)),fallback=i?`u:${i.base_unit_id}`:'';unit.value=[...unit.options].some(o=>o.value===wanted)?wanted:([...unit.options].some(o=>o.value===fallback)?fallback:'');unit.dataset.selected='';}
function addRecipeRow(){const tr=document.createElement('tr');tr.className='recipe-row';tr.innerHTML=`<td><select name="recipe[${recipeIndex}][ingredient_id]" class="progga-form-control recipe-ingredient" onchange="filterRecipeUnits(this)">${ingredientOptions()}</select></td><td><input type="number" step="0.01" min="0.01" name="recipe[${recipeIndex}][quantity]" class="progga-form-control" placeholder="0.00"></td><td><select name="recipe[${recipeIndex}][unit_choice]" class="progga-form-control recipe-unit"><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRecipeRow(this)"><i class="bi bi-trash"></i></button></td>`;document.getElementById('recipeRows').appendChild(tr);recipeIndex++;}
function removeRecipeRow(btn){const rows=document.querySelectorAll('.recipe-row');if(rows.length<=1){const row=btn.closest('.recipe-row');row.querySelector('.recipe-ingredient').value='';row.querySelector('input').value='';row.querySelector('.recipe-unit').innerHTML='<option value="">Select ingredient first</option>';return;}btn.closest('tr').remove();}
document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('.recipe-ingredient').forEach(filterRecipeUnits));
</script>
@endsection
