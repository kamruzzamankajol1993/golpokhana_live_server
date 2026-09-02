@extends('admin.master.master')
@php
    $isEdit = $kitchenRequest->exists;
    $selectedBranch = old('branch_id', $kitchenRequest->branch_id ?: $currentBranchId);
    $oldFoodItems = old('food_items');
    if ($oldFoodItems === null) {
        $oldFoodItems = $isEdit ? $kitchenRequest->foodItems->map(fn($x)=>['menu_item_id'=>$x->menu_item_id,'requested_food_qty'=>number_format((float)$x->requested_food_qty,2,'.','')])->values()->all() : [['menu_item_id'=>'','requested_food_qty'=>'']];
    }
    $oldIngredientItems = old('ingredient_items');
    if ($oldIngredientItems === null) {
        $oldIngredientItems = $isEdit ? $kitchenRequest->ingredientItems->whereIn('source_kind', ['DIRECT', 'MIXED'])->map(function($x) use ($ingredients) {
            $choice = $x->package_conversion_id ? 'c:'.$x->package_conversion_id : ($x->display_unit_id ? 'u:'.$x->display_unit_id : '');
            if (!$x->package_conversion_id && $x->display_unit_id && $x->conversion_factor_snapshot) {
                $ingredient = $ingredients->firstWhere('id', $x->ingredient_id);
                $match = $ingredient?->unitConversions?->first(fn($c)=>(int)$c->unit_id===(int)$x->display_unit_id && abs((float)$c->factor_to_base-(float)$x->conversion_factor_snapshot)<0.00000001);
                if ($match) $choice = 'c:'.$match->id;
            }
            return ['ingredient_id'=>$x->ingredient_id,'quantity'=>number_format((float)($x->input_quantity ?: $x->required_base_qty),2,'.',''),'unit_choice'=>$choice];
        })->values()->all() : [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'']];
    }
@endphp
@section('title',$isEdit?'Edit Kitchen Request':'New Kitchen Request')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $isEdit?'Edit Kitchen Request':'New Kitchen Request' }}</h1><p class="text-muted mb-0">Add Food items, Direct Ingredients, or both in the same request. Food quantities are converted from the active recipe; editing Draft/Submitted requests does not change stock until an issue is posted.</p></div>
        <a href="{{ $isEdit ? route('inventory.kitchen-requests.show',$kitchenRequest) : route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline">Back</a>
    </div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $isEdit ? route('inventory.kitchen-requests.update',$kitchenRequest) : route('inventory.kitchen-requests.store') }}">
        @csrf @if($isEdit) @method('PUT') @endif
        <div class="progga-card mb-4"><div class="p-4"><div class="row g-3">
            @if(auth()->user()?->isSuperAdmin())
            <div class="col-md-3"><label class="progga-form-label">Branch <span class="progga-required">*</span></label>
                @if($isEdit)<input type="hidden" id="kitchenRequestBranch" name="branch_id" value="{{ $kitchenRequest->branch_id }}"><input class="progga-form-control" value="{{ $kitchenRequest->branch?->name ?? 'Selected branch' }}" disabled>
                @else<select id="kitchenRequestBranch" name="branch_id" class="progga-form-control" onchange="filterFoodBranches()" required><option value="">Select branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)$selectedBranch===(int)$branch->id)>{{ $branch->name }}</option>@endforeach</select>@endif
            </div>
            @else<input type="hidden" id="kitchenRequestBranch" name="branch_id" value="{{ $selectedBranch }}">@endif
            <div class="col-md-3"><label class="progga-form-label">Request Date <span class="progga-required">*</span></label><input type="text" name="request_date" value="{{ old('request_date',$isEdit?optional($kitchenRequest->request_date)->format('Y-m-d'):now()->format('Y-m-d')) }}" class="progga-form-control progga-datepicker" required></div>
            <div class="col-md-{{ auth()->user()?->isSuperAdmin() ? '6' : '9' }}"><label class="progga-form-label">Notes</label><input name="notes" value="{{ old('notes',$kitchenRequest->notes) }}" class="progga-form-control" placeholder="Optional note"></div>
        </div></div></div>

        <div class="alert alert-info mb-4"><i class="bi bi-info-circle me-1"></i> Use either section or both. If the same ingredient is required by a Food recipe and also added directly, the system combines both quantities into one inventory requirement.</div>

        <div class="progga-card mb-4" id="foodRequestCard">
            <div class="progga-card-header d-flex justify-content-between align-items-center"><div><strong>Food-wise Request <span class="text-muted fw-normal">(Optional)</span></strong><div class="small text-muted">Select Food Item + quantity. Required ingredients are calculated automatically from its active recipe version.</div></div><button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addFoodRow()"><i class="bi bi-plus-lg"></i> Add Food</button></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Menu Item</th><th style="width:220px">Food Quantity</th><th style="width:60px"></th></tr></thead><tbody id="foodRows">
                @foreach($oldFoodItems as $idx=>$row)<tr class="food-row"><td><select name="food_items[{{ $idx }}][menu_item_id]" class="progga-form-control"><option value="">Select menu item</option>@foreach($foods as $food)<option value="{{ $food->id }}" data-branch="{{ $food->branch_id }}" @selected((string)($row['menu_item_id']??'')===(string)$food->id)>{{ $food->name }} — Recipe v{{ $food->activeRecipe?->version_no }}</option>@endforeach</select></td><td><input type="number" step="0.01" min="0.01" name="food_items[{{ $idx }}][requested_food_qty]" value="{{ $row['requested_food_qty']??'' }}" class="progga-form-control" placeholder="e.g. 10"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this,'.food-row')"><i class="bi bi-trash"></i></button></td></tr>@endforeach
            </tbody></table></div>
        </div>

        <div class="progga-card mb-4" id="ingredientRequestCard">
            <div class="progga-card-header d-flex justify-content-between align-items-center"><div><strong>Direct Ingredient Request <span class="text-muted fw-normal">(Optional)</span></strong><div class="small text-muted">Add extra/raw ingredients directly using standard units or ingredient-specific package variants.</div></div><button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addIngredientRow()"><i class="bi bi-plus-lg"></i> Add Ingredient</button></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th style="width:180px">Quantity</th><th style="width:220px">Unit</th><th style="width:60px"></th></tr></thead><tbody id="ingredientRows">
                @foreach($oldIngredientItems as $idx=>$row)<tr class="ingredient-row"><td><select name="ingredient_items[{{ $idx }}][ingredient_id]" class="progga-form-control ingredient-select" onchange="filterIngredientUnits(this)"><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td><td><input type="number" step="0.01" min="0.01" name="ingredient_items[{{ $idx }}][quantity]" value="{{ $row['quantity']??'' }}" class="progga-form-control"></td><td><select name="ingredient_items[{{ $idx }}][unit_choice]" class="progga-form-control ingredient-unit" data-selected="{{ $row['unit_choice']??'' }}"><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this,'.ingredient-row')"><i class="bi bi-trash"></i></button></td></tr>@endforeach
            </tbody></table></div>
        </div>

        <div class="d-flex justify-content-end gap-2"><a href="{{ route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline">Cancel</a><button class="progga-btn progga-btn-primary"><i class="bi bi-save"></i> {{ $isEdit?'Update Request':'Save Draft' }}</button></div>
    </form>
</main>
@endsection
@section('script')
<script>
const kitchenFoods = {{ \Illuminate\Support\Js::from($foods->map(fn($f)=>['id'=>(int)$f->id,'name'=>$f->name,'version'=>$f->activeRecipe?->version_no,'branch_id'=>(int)$f->branch_id])->values()) }};
const kitchenIngredients = {{ \Illuminate\Support\Js::from($ingredients->map(fn($i)=>['id'=>(int)$i->id,'name'=>$i->name,'base'=>$i->baseUnit?->symbol,'base_unit_id'=>(int)$i->base_unit_id,'dimension'=>$i->measurement_dimension,'packages'=>$i->unitConversions->filter(fn($c)=>$c->is_active && $c->unit?->is_active)->map(fn($c)=>['id'=>(int)$c->id,'label'=>$c->label ?: ($c->unit?->name.' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')])->values()])->values()) }};
const kitchenUnits = {{ \Illuminate\Support\Js::from($units->filter(fn($u)=>$u->dimension !== \App\Models\Unit::DIMENSION_PACKAGE)->map(fn($u)=>['id'=>(int)$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension])->values()) }};
let foodRowIndex={{ count($oldFoodItems) }}, ingredientRowIndex={{ count($oldIngredientItems) }};
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function filterFoodBranches(){const branch=document.getElementById('kitchenRequestBranch')?.value||'';document.querySelectorAll('#foodRows select').forEach(sel=>{[...sel.options].forEach(o=>{if(!o.value)return;const show=!branch||o.dataset.branch===branch;o.hidden=!show;o.disabled=!show;});if(sel.value&&sel.options[sel.selectedIndex]?.disabled)sel.value='';});}
function addFoodRow(){const tr=document.createElement('tr');tr.className='food-row';tr.innerHTML=`<td><select name="food_items[${foodRowIndex}][menu_item_id]" class="progga-form-control"><option value="">Select menu item</option>${kitchenFoods.map(f=>`<option value="${f.id}" data-branch="${f.branch_id}">${esc(f.name)} — Recipe v${f.version}</option>`).join('')}</select></td><td><input type="number" step="0.01" min="0.01" name="food_items[${foodRowIndex}][requested_food_qty]" class="progga-form-control" placeholder="e.g. 10"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this,'.food-row')"><i class="bi bi-trash"></i></button></td>`;document.getElementById('foodRows').appendChild(tr);foodRowIndex++;filterFoodBranches();}
function ingredientOptions(){return '<option value="">Select ingredient</option>'+kitchenIngredients.map(i=>`<option value="${i.id}">${esc(i.name)} (${esc(i.base)})</option>`).join('');}
function ingredientChoiceOptions(ingredientId){const i=kitchenIngredients.find(x=>String(x.id)===String(ingredientId));if(!i)return '<option value="">Select ingredient first</option>';let html='<option value="">Select unit / package</option>';html+=kitchenUnits.filter(u=>u.dimension===i.dimension).map(u=>`<option value="u:${u.id}">${esc(u.name)} (${esc(u.symbol)})</option>`).join('');if(i.packages.length)html+='<optgroup label="Package variants">'+i.packages.map(c=>`<option value="c:${c.id}">${esc(c.label)}</option>`).join('')+'</optgroup>';return html;}
function filterIngredientUnits(select){const row=select.closest('.ingredient-row'),unit=row.querySelector('.ingredient-unit'),wanted=unit.dataset.selected||unit.value;unit.innerHTML=ingredientChoiceOptions(select.value);const i=kitchenIngredients.find(x=>String(x.id)===String(select.value)),fallback=i?`u:${i.base_unit_id}`:'';unit.value=[...unit.options].some(o=>o.value===wanted)?wanted:([...unit.options].some(o=>o.value===fallback)?fallback:'');unit.dataset.selected='';}
function addIngredientRow(){const tr=document.createElement('tr');tr.className='ingredient-row';tr.innerHTML=`<td><select name="ingredient_items[${ingredientRowIndex}][ingredient_id]" class="progga-form-control ingredient-select" onchange="filterIngredientUnits(this)">${ingredientOptions()}</select></td><td><input type="number" step="0.01" min="0.01" name="ingredient_items[${ingredientRowIndex}][quantity]" class="progga-form-control"></td><td><select name="ingredient_items[${ingredientRowIndex}][unit_choice]" class="progga-form-control ingredient-unit"><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this,'.ingredient-row')"><i class="bi bi-trash"></i></button></td>`;document.getElementById('ingredientRows').appendChild(tr);ingredientRowIndex++;}
function removeRow(btn,selector){if(document.querySelectorAll(selector).length<=1)return;btn.closest('tr').remove();}
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.ingredient-select').forEach(filterIngredientUnits);filterFoodBranches();});
</script>
@endsection
