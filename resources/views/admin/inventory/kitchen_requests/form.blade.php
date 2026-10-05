@extends('admin.master.master')
@php
    $isEdit = $kitchenRequest->exists;

    $oldFoodItems = old('food_items');
    if ($oldFoodItems === null) {
        $oldFoodItems = $isEdit
            ? $kitchenRequest->foodItems->map(fn($x) => [
                'menu_item_id' => $x->menu_item_id,
                'quantity' => rtrim(rtrim((string)$x->requested_food_qty, '0'), '.'),
            ])->values()->all()
            : [];
    }
    if (count($oldFoodItems) === 0) {
        $oldFoodItems = [['menu_item_id'=>'', 'quantity'=>'']];
    }

    $oldIngredientItems = old('ingredient_items');
    if ($oldIngredientItems === null) {
        $oldIngredientItems = $isEdit
            ? $kitchenRequest->ingredientItems
                ->filter(fn($x) => in_array($x->source_kind, [\App\Models\KitchenRequestIngredientItem::SOURCE_DIRECT, \App\Models\KitchenRequestIngredientItem::SOURCE_MIXED], true))
                ->map(function($x) use ($ingredients) {
                    $choice = $x->package_conversion_id ? 'c:'.$x->package_conversion_id : ($x->display_unit_id ? 'u:'.$x->display_unit_id : '');
                    if (!$x->package_conversion_id && $x->display_unit_id && $x->conversion_factor_snapshot) {
                        $ingredient = $ingredients->firstWhere('id', $x->ingredient_id);
                        $match = $ingredient?->unitConversions?->first(fn($c)=>(int)$c->unit_id===(int)$x->display_unit_id && abs((float)$c->factor_to_base-(float)$x->conversion_factor_snapshot)<0.00000001);
                        if ($match) $choice = 'c:'.$match->id;
                    }
                    return [
                        'ingredient_id'=>$x->ingredient_id,
                        'quantity'=>$x->input_quantity ? rtrim(rtrim((string)$x->input_quantity,'0'),'.') : '',
                        'unit_choice'=>$choice ?: 'u:'.$x->ingredient?->base_unit_id,
                    ];
                })->values()->all()
            : [];
    }
    if (count($oldIngredientItems) === 0) {
        $oldIngredientItems = [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'']];
    }
@endphp
@section('title',$isEdit?'Edit Kitchen Request':'New Kitchen Request')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">{{ $isEdit?'Edit Kitchen Request':'New Kitchen Request' }}</h1>
            <p class="text-muted mb-0">Request by Food + Quantity, Direct Ingredient, or use both methods in the same request.</p>
        </div>
        <a href="{{ $isEdit ? route('inventory.kitchen-requests.show',$kitchenRequest) : route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('inventory.kitchen-requests.update',$kitchenRequest) : route('inventory.kitchen-requests.store') }}" id="kitchenRequestForm">
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

        {{-- Method 1: Food-wise request. Backend always recalculates from active recipe. --}}
        <div class="progga-card mb-4" id="foodRequestCard">
            <div class="progga-card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <strong>Food-wise Request <span class="text-muted fw-normal">(Optional)</span></strong>
                    <div class="small text-muted">Select Food Item + quantity. Required ingredients are calculated automatically from its active recipe version.</div>
                </div>
                <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addFoodRow()"><i class="bi bi-plus-lg"></i> Add Food</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Menu Item</th><th style="width:220px">Food Quantity</th><th style="width:60px"></th></tr></thead>
                    <tbody id="foodRows">
                    @foreach($oldFoodItems as $idx=>$row)
                        <tr class="food-row">
                            <td>
                                <select name="food_items[{{ $idx }}][menu_item_id]" class="progga-form-control food-select" onchange="renderFoodPreview()">
                                    <option value="">Select food item</option>
                                    @foreach($foods as $food)
                                        <option value="{{ $food->id }}" @selected((string)($row['menu_item_id']??'')===(string)$food->id)>{{ $food->name }} — Recipe v{{ $food->activeRecipe?->version_no }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="number" step="0.00000001" min="0.00000001" name="food_items[{{ $idx }}][quantity]" value="{{ $row['quantity']??'' }}" class="progga-form-control food-qty" placeholder="e.g. 10" oninput="renderFoodPreview()"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFoodRow(this)"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top d-none" id="foodIngredientPreviewWrap">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small">Calculated Ingredients from Food</strong>
                    <span class="small text-muted">Preview only — server recalculates on save</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Ingredient</th><th class="text-end">Required</th></tr></thead>
                        <tbody id="foodIngredientPreview"></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Method 2: Direct Ingredient request. Can be used alone or together with Food-wise. --}}
        <div class="progga-card mb-4" id="ingredientRequestCard">
            <div class="progga-card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <strong>Direct Ingredient Request <span class="text-muted fw-normal">(Optional)</span></strong>
                    <div class="small text-muted">Request raw ingredients directly. If the same ingredient is also required by selected Food, both quantities are combined automatically.</div>
                </div>
                <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addIngredientRow()"><i class="bi bi-plus-lg"></i> Add Ingredient</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Ingredient</th><th style="width:180px">Quantity</th><th style="width:240px">Unit</th><th style="width:60px"></th></tr></thead>
                    <tbody id="ingredientRows">
                    @foreach($oldIngredientItems as $idx=>$row)
                        <tr class="ingredient-row">
                            <td><select name="ingredient_items[{{ $idx }}][ingredient_id]" class="progga-form-control ingredient-select" onchange="filterIngredientUnits(this)"><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td>
                            <td><input type="number" step="0.00000001" min="0.00000001" name="ingredient_items[{{ $idx }}][quantity]" value="{{ $row['quantity']??'' }}" class="progga-form-control" placeholder="Quantity"></td>
                            <td><select name="ingredient_items[{{ $idx }}][unit_choice]" class="progga-form-control ingredient-unit" data-selected="{{ $row['unit_choice']??'' }}"><option value="">Select ingredient first</option></select></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeIngredientRow(this)"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            You may send <strong>Food-wise only</strong>, <strong>Direct Ingredient only</strong>, or <strong>both together</strong>. Inventory Manager receives one final ingredient requirement and assigns approved quantities from Store Stock to Kitchen Stock.
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline">Cancel</a>
            <button class="progga-btn progga-btn-primary"><i class="bi bi-send"></i> {{ $isEdit?'Update Request':'Send Request' }}</button>
        </div>
    </form>
</main>
@endsection
@section('script')
<script>
const kitchenFoods = {{ \Illuminate\Support\Js::from($foods->map(fn($food)=>[
    'id'=>(int)$food->id,
    'name'=>$food->name,
    'recipe_version'=>(int)($food->activeRecipe?->version_no ?? 0),
    'yield_quantity'=>(string)($food->activeRecipe?->yield_quantity ?? '1'),
    'items'=>$food->activeRecipe?->items?->map(fn($item)=>[
        'ingredient_id'=>(int)$item->ingredient_id,
        'ingredient_name'=>$item->ingredient?->name,
        'base_symbol'=>$item->ingredient?->baseUnit?->symbol,
        'base_quantity'=>(string)$item->base_quantity,
    ])->values()->all() ?? [],
])->values()) }};
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

let foodRowIndex={{ count($oldFoodItems) }};
let ingredientRowIndex={{ count($oldIngredientItems) }};
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function prettyQty(value){const n=Number(value||0);if(!Number.isFinite(n))return '0';return n.toFixed(8).replace(/0+$/,'').replace(/\.$/,'');}

function foodOptions(){return '<option value="">Select food item</option>'+kitchenFoods.map(f=>`<option value="${f.id}">${esc(f.name)} — Recipe v${f.recipe_version}</option>`).join('');}
function addFoodRow(){const tr=document.createElement('tr');tr.className='food-row';tr.innerHTML=`<td><select name="food_items[${foodRowIndex}][menu_item_id]" class="progga-form-control food-select" onchange="renderFoodPreview()">${foodOptions()}</select></td><td><input type="number" step="0.00000001" min="0.00000001" name="food_items[${foodRowIndex}][quantity]" class="progga-form-control food-qty" placeholder="e.g. 10" oninput="renderFoodPreview()"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFoodRow(this)"><i class="bi bi-trash"></i></button></td>`;document.getElementById('foodRows').appendChild(tr);foodRowIndex++;}
function removeFoodRow(btn){const rows=document.querySelectorAll('.food-row');if(rows.length<=1){const row=btn.closest('tr');row.querySelector('.food-select').value='';row.querySelector('.food-qty').value='';}else btn.closest('tr').remove();renderFoodPreview();}
function renderFoodPreview(){
    const totals=new Map();
    document.querySelectorAll('.food-row').forEach(row=>{
        const foodId=row.querySelector('.food-select').value;
        const qty=parseFloat(row.querySelector('.food-qty').value||'0');
        if(!foodId || !(qty>0)) return;
        const food=kitchenFoods.find(f=>String(f.id)===String(foodId));
        if(!food) return;
        const yieldQty=parseFloat(food.yield_quantity||'1');
        if(!(yieldQty>0)) return;
        food.items.forEach(item=>{
            const amount=(parseFloat(item.base_quantity||'0')*qty)/yieldQty;
            if(!(amount>0)) return;
            const current=totals.get(item.ingredient_id)||{name:item.ingredient_name,symbol:item.base_symbol,qty:0};
            current.qty+=amount;
            totals.set(item.ingredient_id,current);
        });
    });
    const wrap=document.getElementById('foodIngredientPreviewWrap');
    const body=document.getElementById('foodIngredientPreview');
    if(!totals.size){wrap.classList.add('d-none');body.innerHTML='';return;}
    body.innerHTML=[...totals.values()].sort((a,b)=>String(a.name).localeCompare(String(b.name))).map(x=>`<tr><td>${esc(x.name)}</td><td class="text-end fw-semibold">${prettyQty(x.qty)} ${esc(x.symbol)}</td></tr>`).join('');
    wrap.classList.remove('d-none');
}

function ingredientOptions(){return '<option value="">Select ingredient</option>'+kitchenIngredients.map(i=>`<option value="${i.id}">${esc(i.name)} (${esc(i.base)})</option>`).join('');}
function ingredientChoiceOptions(ingredientId){const i=kitchenIngredients.find(x=>String(x.id)===String(ingredientId));if(!i)return '<option value="">Select ingredient first</option>';let html='<option value="">Select unit / package</option>';html+=kitchenUnits.filter(u=>u.dimension===i.dimension).map(u=>`<option value="u:${u.id}">${esc(u.name)} (${esc(u.symbol)})</option>`).join('');if(i.packages.length)html+='<optgroup label="Package variants">'+i.packages.map(c=>`<option value="c:${c.id}">${esc(c.label)}</option>`).join('')+'</optgroup>';return html;}
function setIngredientChoices(select){const row=select.closest('.ingredient-row'),unit=row.querySelector('.ingredient-unit'),wanted=unit.dataset.selected||unit.value;unit.innerHTML=ingredientChoiceOptions(select.value);const i=kitchenIngredients.find(x=>String(x.id)===String(select.value)),fallback=i?`u:${i.base_unit_id}`:'';unit.value=[...unit.options].some(o=>o.value===wanted)?wanted:([...unit.options].some(o=>o.value===fallback)?fallback:'');unit.dataset.selected='';}
function filterIngredientUnits(select){setIngredientChoices(select);}
function addIngredientRow(){const tr=document.createElement('tr');tr.className='ingredient-row';tr.innerHTML=`<td><select name="ingredient_items[${ingredientRowIndex}][ingredient_id]" class="progga-form-control ingredient-select" onchange="filterIngredientUnits(this)">${ingredientOptions()}</select></td><td><input type="number" step="0.00000001" min="0.00000001" name="ingredient_items[${ingredientRowIndex}][quantity]" class="progga-form-control" placeholder="Quantity"></td><td><select name="ingredient_items[${ingredientRowIndex}][unit_choice]" class="progga-form-control ingredient-unit"><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeIngredientRow(this)"><i class="bi bi-trash"></i></button></td>`;document.getElementById('ingredientRows').appendChild(tr);ingredientRowIndex++;}
function removeIngredientRow(btn){const rows=document.querySelectorAll('.ingredient-row');if(rows.length<=1){const row=btn.closest('tr');const select=row.querySelector('.ingredient-select');select.value='';row.querySelector('input').value='';row.querySelector('.ingredient-unit').innerHTML='<option value="">Select ingredient first</option>';}else btn.closest('tr').remove();}

document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.ingredient-select').forEach(setIngredientChoices);
    renderFoodPreview();
    document.getElementById('kitchenRequestForm').addEventListener('submit',function(event){
        const hasFood=[...document.querySelectorAll('.food-row')].some(row=>row.querySelector('.food-select').value && parseFloat(row.querySelector('.food-qty').value||'0')>0);
        const hasIngredient=[...document.querySelectorAll('.ingredient-row')].some(row=>row.querySelector('.ingredient-select').value && parseFloat(row.querySelector('input[type="number"]').value||'0')>0 && row.querySelector('.ingredient-unit').value);
        if(hasFood || hasIngredient) return;
        event.preventDefault();
        const text='Add at least one Food-wise Request or one Direct Ingredient Request.';
        if(window.Swal) Swal.fire({title:'Request item required',text,icon:'info'}); else alert(text);
    });
});
</script>
@endsection
