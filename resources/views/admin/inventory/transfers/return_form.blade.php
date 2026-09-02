@extends('admin.master.master')
@php $oldItems=old('items',$suggestedItems); @endphp
@section('title','Kitchen to Main Return')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Kitchen → Main Return</h1><p class="text-muted mb-0">Return unused raw ingredients. Suggested quantities from an original transfer are editable and never forced.</p></div><a href="{{ route('inventory.transfers.index') }}" class="progga-btn progga-btn-outline">Back</a></div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($original)<div class="alert alert-info">Reference transfer: <strong>{{ $original->transfer_no }}</strong>. Edit the suggested quantities to the actual unused stock being returned.</div>@endif
    <form method="POST" action="{{ route('inventory.transfers.return.store') }}" onsubmit="return confirm('Post this Kitchen to Main return?')">@csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)\Illuminate\Support\Str::uuid()) }}">
        <input type="hidden" name="original_transfer_id" value="{{ old('original_transfer_id',$original?->id) }}">
        <div class="progga-card mb-4"><div class="p-4"><label class="progga-form-label">Return Notes</label><textarea name="notes" class="progga-form-control" rows="2" placeholder="Unused prep stock / end-of-shift return note">{{ old('notes') }}</textarea></div></div>
        <div class="progga-card mb-4"><div class="progga-card-header d-flex justify-content-between align-items-center"><div><strong>Return Items</strong><div class="small text-muted">Kitchen availability is shown in base units. The server locks and re-checks stock before posting.</div></div><button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addReturnRow()"><i class="bi bi-plus-lg"></i> Add Item</button></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th style="width:160px">Available Kitchen</th><th style="width:170px">Return Qty</th><th style="width:220px">Unit</th><th style="width:60px"></th></tr></thead><tbody id="returnRows">
        @foreach($oldItems as $idx=>$row)<tr class="return-row"><td><select name="items[{{ $idx }}][ingredient_id]" class="progga-form-control return-ingredient" onchange="filterReturnUnits(this);refreshKitchenAvailable()" required><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" data-base="{{ $ingredient->baseUnit?->symbol }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td><td class="available-kitchen text-muted">—</td><td><input type="number" step="0.00000001" min="0.00000001" name="items[{{ $idx }}][quantity]" value="{{ $row['quantity']??'' }}" class="progga-form-control" required></td><td><select name="items[{{ $idx }}][unit_choice]" class="progga-form-control return-unit" data-selected="{{ $row['unit_choice']??'' }}" required><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeReturnRow(this)"><i class="bi bi-trash"></i></button></td></tr>@endforeach
        </tbody></table></div></div>
        <div class="d-flex justify-content-end"><button class="progga-btn progga-btn-primary"><i class="bi bi-arrow-left-circle"></i> Post Kitchen → Main Return</button></div>
    </form>
</main>
@endsection
@section('script')
<script>
const returnIngredients={{ \Illuminate\Support\Js::from($ingredients->map(fn($i)=>['id'=>(int)$i->id,'name'=>$i->name,'base'=>$i->baseUnit?->symbol,'base_unit_id'=>(int)$i->base_unit_id,'dimension'=>$i->measurement_dimension,'packages'=>$i->unitConversions->filter(fn($c)=>$c->is_active && $c->unit?->is_active)->map(fn($c)=>['id'=>(int)$c->id,'label'=>$c->label ?: ($c->unit?->name.' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')])->values()])->values()) }};
const returnUnits={{ \Illuminate\Support\Js::from($units->filter(fn($u)=>$u->dimension !== \App\Models\Unit::DIMENSION_PACKAGE)->map(fn($u)=>['id'=>(int)$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension])->values()) }};
const kitchenAvailable={{ \Illuminate\Support\Js::from($available) }};
let returnIndex={{ count($oldItems) }};
function rEsc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function rTrimQty(v){const n=String(v??'0');return n.includes('.')?(n.replace(/0+$/,'').replace(/\.$/,'')):(n||'0');}
function rIngredientOptions(){return '<option value="">Select ingredient</option>'+returnIngredients.map(i=>`<option value="${i.id}" data-base="${rEsc(i.base)}">${rEsc(i.name)} (${rEsc(i.base)})</option>`).join('');}
function rChoiceOptions(ingredientId){const i=returnIngredients.find(x=>String(x.id)===String(ingredientId));if(!i)return '<option value="">Select ingredient first</option>';let html='<option value="">Select unit / package</option>';html+=returnUnits.filter(u=>u.dimension===i.dimension).map(u=>`<option value="u:${u.id}">${rEsc(u.name)} (${rEsc(u.symbol)})</option>`).join('');if(i.packages.length)html+='<optgroup label="Package variants">'+i.packages.map(c=>`<option value="c:${c.id}">${rEsc(c.label)}</option>`).join('')+'</optgroup>';return html;}
function setReturnChoices(select){const row=select.closest('.return-row'),unit=row.querySelector('.return-unit'),wanted=unit.dataset.selected||unit.value;unit.innerHTML=rChoiceOptions(select.value);const i=returnIngredients.find(x=>String(x.id)===String(select.value)),fallback=i?`u:${i.base_unit_id}`:'';unit.value=[...unit.options].some(o=>o.value===wanted)?wanted:([...unit.options].some(o=>o.value===fallback)?fallback:'');unit.dataset.selected='';}
function filterReturnUnits(select){setReturnChoices(select);}
function addReturnRow(){const tr=document.createElement('tr');tr.className='return-row';tr.innerHTML=`<td><select name="items[${returnIndex}][ingredient_id]" class="progga-form-control return-ingredient" onchange="filterReturnUnits(this);refreshKitchenAvailable()" required>${rIngredientOptions()}</select></td><td class="available-kitchen text-muted">—</td><td><input type="number" step="0.00000001" min="0.00000001" name="items[${returnIndex}][quantity]" class="progga-form-control" required></td><td><select name="items[${returnIndex}][unit_choice]" class="progga-form-control return-unit" required><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeReturnRow(this)"><i class="bi bi-trash"></i></button></td>`;document.getElementById('returnRows').appendChild(tr);returnIndex++;}
function removeReturnRow(btn){if(document.querySelectorAll('.return-row').length<=1)return;btn.closest('tr').remove();}
function refreshKitchenAvailable(){document.querySelectorAll('.return-row').forEach(row=>{const sel=row.querySelector('.return-ingredient'),id=sel.value,opt=sel.options[sel.selectedIndex],base=opt?.dataset.base||'',qty=kitchenAvailable[id]??'0.00000000';row.querySelector('.available-kitchen').textContent=id?`${rTrimQty(qty)} ${base}`:'—';});}
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.return-ingredient').forEach(setReturnChoices);refreshKitchenAvailable();});
</script>
@endsection
