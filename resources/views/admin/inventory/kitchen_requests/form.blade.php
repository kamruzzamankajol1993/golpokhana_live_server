@extends('admin.master.master')
@php
    $isEdit = $kitchenRequest->exists;
    $oldIngredientItems = old('ingredient_items');
    if ($oldIngredientItems === null) {
        $oldIngredientItems = $isEdit
            ? $kitchenRequest->ingredientItems->map(function($x) use ($ingredients) {
                $choice = $x->package_conversion_id ? 'c:'.$x->package_conversion_id : ($x->display_unit_id ? 'u:'.$x->display_unit_id : '');
                if (!$x->package_conversion_id && $x->display_unit_id && $x->conversion_factor_snapshot) {
                    $ingredient = $ingredients->firstWhere('id', $x->ingredient_id);
                    $match = $ingredient?->unitConversions?->first(fn($c)=>(int)$c->unit_id===(int)$x->display_unit_id && abs((float)$c->factor_to_base-(float)$x->conversion_factor_snapshot)<0.00000001);
                    if ($match) $choice = 'c:'.$match->id;
                }
                return [
                    'ingredient_id'=>$x->ingredient_id,
                    'quantity'=>number_format((float)($x->input_quantity ?: $x->required_base_qty),2,'.',''),
                    'unit_choice'=>$choice ?: 'u:'.$x->ingredient?->base_unit_id,
                ];
            })->values()->all()
            : [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'']];
    }
@endphp
@section('title',$isEdit?'Edit Ingredient Request':'New Ingredient Request')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">{{ $isEdit?'Edit Ingredient Request':'New Ingredient Request' }}</h1>
            <p class="text-muted mb-0">Request only the raw ingredients needed by Kitchen. Inventory Manager will assign the available quantity from Store Stock.</p>
        </div>
        <a href="{{ $isEdit ? route('inventory.kitchen-requests.show',$kitchenRequest) : route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('inventory.kitchen-requests.update',$kitchenRequest) : route('inventory.kitchen-requests.store') }}" id="ingredientRequestForm">
        @csrf @if($isEdit) @method('PUT') @endif

        <div class="progga-card mb-4">
            <div class="p-4"><div class="row g-3">
                <div class="col-md-3">
                    <label class="progga-form-label">Request Date <span class="progga-required">*</span></label>
                    <input type="text" name="request_date" value="{{ old('request_date',$isEdit?optional($kitchenRequest->request_date)->format('Y-m-d'):now()->format('Y-m-d')) }}" class="progga-form-control progga-datepicker" required>
                </div>
                <div class="col-md-9">
                    <label class="progga-form-label">Notes</label>
                    <input name="notes" value="{{ old('notes',$kitchenRequest->notes) }}" class="progga-form-control" placeholder="Optional note for Inventory Manager">
                </div>
            </div></div>
        </div>

        <div class="progga-card mb-4" id="ingredientRequestCard">
            <div class="progga-card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <strong>Ingredients Required</strong>
                    <div class="small text-muted">One ingredient per row. Quantity can be entered in the configured unit or package conversion.</div>
                </div>
                <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addIngredientRow()"><i class="bi bi-plus-lg"></i> Add Ingredient</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Ingredient</th><th style="width:180px">Quantity</th><th style="width:240px">Unit</th><th style="width:60px"></th></tr></thead>
                    <tbody id="ingredientRows">
                    @foreach($oldIngredientItems as $idx=>$row)
                        <tr class="ingredient-row">
                            <td><select name="ingredient_items[{{ $idx }}][ingredient_id]" class="progga-form-control ingredient-select" onchange="filterIngredientUnits(this)" required><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td>
                            <td><input type="number" step="0.00000001" min="0.00000001" name="ingredient_items[{{ $idx }}][quantity]" value="{{ $row['quantity']??'' }}" class="progga-form-control" required></td>
                            <td><select name="ingredient_items[{{ $idx }}][unit_choice]" class="progga-form-control ingredient-unit" data-selected="{{ $row['unit_choice']??'' }}" required><option value="">Select ingredient first</option></select></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeIngredientRow(this)"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i> {{ $isEdit ? 'Update keeps the request in the Inventory Manager queue until assignment starts.' : 'Click Send Request to place it directly in the Inventory Manager assignment queue.' }}</div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline">Cancel</a>
            <button class="progga-btn progga-btn-primary"><i class="bi bi-send"></i> {{ $isEdit?'Update Request':'Send Request' }}</button>
        </div>
    </form>
</main>
@endsection
@section('script')
<script>
const kitchenIngredients = {{ \Illuminate\Support\Js::from($ingredients->map(fn($i)=>[
    'id'=>(int)$i->id,
    'name'=>$i->name,
    'base'=>$i->baseUnit?->symbol,
    'base_unit_id'=>(int)$i->base_unit_id,
    'dimension'=>$i->measurement_dimension,
    'packages'=>$i->unitConversions->filter(fn($c)=>$c->is_active && $c->unit?->is_active)->map(fn($c)=>[
        'id'=>(int)$c->id,
        'label'=>$c->label ?: ($c->unit?->name.' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')
    ])->values()
])->values()) }};
const kitchenUnits = {{ \Illuminate\Support\Js::from($units->filter(fn($u)=>$u->dimension !== \App\Models\Unit::DIMENSION_PACKAGE)->map(fn($u)=>[
    'id'=>(int)$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension
])->values()) }};
let ingredientRowIndex={{ count($oldIngredientItems) }};
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function ingredientOptions(){return '<option value="">Select ingredient</option>'+kitchenIngredients.map(i=>`<option value="${i.id}">${esc(i.name)} (${esc(i.base)})</option>`).join('');}
function ingredientChoiceOptions(ingredientId){const i=kitchenIngredients.find(x=>String(x.id)===String(ingredientId));if(!i)return '<option value="">Select ingredient first</option>';let html='<option value="">Select unit / package</option>';html+=kitchenUnits.filter(u=>u.dimension===i.dimension).map(u=>`<option value="u:${u.id}">${esc(u.name)} (${esc(u.symbol)})</option>`).join('');if(i.packages.length)html+='<optgroup label="Package variants">'+i.packages.map(c=>`<option value="c:${c.id}">${esc(c.label)}</option>`).join('')+'</optgroup>';return html;}
function setIngredientChoices(select){const row=select.closest('.ingredient-row'),unit=row.querySelector('.ingredient-unit'),wanted=unit.dataset.selected||unit.value;unit.innerHTML=ingredientChoiceOptions(select.value);const i=kitchenIngredients.find(x=>String(x.id)===String(select.value)),fallback=i?`u:${i.base_unit_id}`:'';unit.value=[...unit.options].some(o=>o.value===wanted)?wanted:([...unit.options].some(o=>o.value===fallback)?fallback:'');unit.dataset.selected='';}
function filterIngredientUnits(select){setIngredientChoices(select);}
function addIngredientRow(){const tr=document.createElement('tr');tr.className='ingredient-row';tr.innerHTML=`<td><select name="ingredient_items[${ingredientRowIndex}][ingredient_id]" class="progga-form-control ingredient-select" onchange="filterIngredientUnits(this)" required>${ingredientOptions()}</select></td><td><input type="number" step="0.00000001" min="0.00000001" name="ingredient_items[${ingredientRowIndex}][quantity]" class="progga-form-control" required></td><td><select name="ingredient_items[${ingredientRowIndex}][unit_choice]" class="progga-form-control ingredient-unit" required><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeIngredientRow(this)"><i class="bi bi-trash"></i></button></td>`;document.getElementById('ingredientRows').appendChild(tr);ingredientRowIndex++;}
function removeIngredientRow(btn){if(document.querySelectorAll('.ingredient-row').length<=1)return;btn.closest('tr').remove();}
document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('.ingredient-select').forEach(setIngredientChoices));
</script>
@endsection
