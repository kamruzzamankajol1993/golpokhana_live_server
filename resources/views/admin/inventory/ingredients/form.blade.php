@php
    $editing = isset($ingredient);
    $selectedDimension = old('measurement_dimension', $ingredient->measurement_dimension ?? 'WEIGHT');
    $selectedBaseUnit = old('base_unit_id', $ingredient->base_unit_id ?? '');
    $existingConversions = old('conversions');
    if ($existingConversions === null && $editing) {
        $existingConversions = $ingredient->unitConversions->map(fn($c) => [
            'id' => $c->id,
            'unit_id' => $c->unit_id,
            'label' => $c->label,
            'factor_to_base' => number_format((float) $c->factor_to_base, 2, '.', ''),
            'purchase_allowed' => $c->purchase_allowed ? 1 : 0,
            'recipe_allowed' => $c->recipe_allowed ? 1 : 0,
            'effective_from' => optional($c->effective_from)->format('Y-m-d'),
            'is_active' => $c->is_active ? 1 : 0,
        ])->values()->all();
    }
    $existingConversions = collect($existingConversions ?: [])->map(function ($row) {
        if (isset($row['factor_to_base']) && is_numeric($row['factor_to_base'])) {
            $row['factor_to_base'] = number_format((float) $row['factor_to_base'], 2, '.', '');
        }
        return $row;
    })->values()->all();
    $lowStockRaw = old('low_stock_level_base', $ingredient->low_stock_level_base ?? 0);
    $lowStockDisplay = is_numeric($lowStockRaw) ? number_format((float) $lowStockRaw, 2, '.', '') : $lowStockRaw;
@endphp
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="progga-card mb-4">
    <div class="progga-card-header"><strong>Ingredient Master</strong></div>
    <div class="p-4">
        <div class="row g-3">
            <div class="col-md-6"><label class="progga-form-label">Ingredient Name *</label><input class="progga-form-control" name="name" value="{{ old('name',$ingredient->name ?? '') }}" maxlength="160" required></div>
            <div class="col-md-3"><label class="progga-form-label">Code / SKU</label><input class="progga-form-control" name="code" value="{{ old('code',$ingredient->code ?? '') }}" maxlength="80"></div>
            <div class="col-md-3"><label class="progga-form-label">Measurement Type *</label><select class="progga-form-control" id="measurementDimension" name="measurement_dimension" required onchange="filterBaseUnits();refreshAllAutoLabels()">@foreach($dimensions as $dimension)<option value="{{ $dimension }}" @selected($selectedDimension===$dimension)>{{ $dimension }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="progga-form-label">Base Unit *</label><select class="progga-form-control" id="baseUnitId" name="base_unit_id" required onchange="refreshAllAutoLabels()">@foreach($baseUnits as $unit)<option value="{{ $unit->id }}" data-dimension="{{ $unit->dimension }}" data-symbol="{{ $unit->symbol }}" @selected((string)$selectedBaseUnit===(string)$unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select></div>
            <div class="col-md-4"><label class="progga-form-label">Low Stock Level (Base Unit)</label><input type="number" step="0.01" min="0" class="progga-form-control" name="low_stock_level_base" value="{{ $lowStockDisplay }}" onblur="formatDecimalInput(this)" required></div>
            <div class="col-md-2 d-flex align-items-end"><input type="hidden" name="track_inventory" value="0"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="track_inventory" value="1" id="trackInventory" @checked(old('track_inventory',$ingredient->track_inventory ?? true))><label class="form-check-label" for="trackInventory">Track inventory</label></div></div>
            <div class="col-md-2 d-flex align-items-end"><input type="hidden" name="is_active" value="0"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ingredientActive" @checked(old('is_active',$ingredient->is_active ?? true))><label class="form-check-label" for="ingredientActive">Active</label></div></div>
        </div>
    </div>
</div>

<div class="progga-card mb-4">
    <div class="progga-card-header d-flex justify-content-between align-items-center"><div><strong>Package Conversions</strong><div class="text-muted small">Multiple sizes of the same package are supported. Labels are created automatically and can be edited.</div></div><button type="button" class="progga-btn progga-btn-sm progga-btn-secondary" onclick="addConversionRow()"><i class="bi bi-plus-lg"></i> Add Conversion</button></div>
    <div class="p-3">
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th style="min-width:160px">Package Unit</th><th style="min-width:150px">Factor to Base</th><th style="min-width:230px">Label</th><th>Purchase</th><th>Recipe</th><th>Effective From</th><th>Active</th><th></th></tr></thead><tbody id="conversionRows"></tbody></table></div>
        <div class="alert alert-warning mb-0 py-2"><strong>Example:</strong> Salt can have Packet (500 g) and Packet (1000 g). Oil can have Bottle (500 ml) and Bottle (1000 ml). The generated label can be renamed, for example Bottle (1 L).</div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2"><a href="{{ route('inventory.ingredients.index') }}" class="progga-btn progga-btn-light">Cancel</a><button class="progga-btn progga-btn-primary">{{ $editing ? 'Update Ingredient' : 'Save Ingredient' }}</button></div>

<template id="conversionTemplate">
<tr data-conversion-row>
    <td>
        <input type="hidden" data-field="id">
        <select class="progga-form-control" data-field="unit_id" onchange="refreshConversionLabel(this.closest('tr'))"><option value="">Select package</option>@foreach($packageUnits as $unit)<option value="{{ $unit->id }}" data-name="{{ $unit->name }}">{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select>
    </td>
    <td><input class="progga-form-control" type="number" step="0.01" min="0.01" data-field="factor_to_base" placeholder="e.g. 500.00" oninput="refreshConversionLabel(this.closest('tr'))" onblur="formatDecimalInput(this);refreshConversionLabel(this.closest('tr'))"></td>
    <td><div class="d-flex gap-2"><input class="progga-form-control" type="text" maxlength="160" data-field="label" placeholder="Auto generated" oninput="markConversionLabelManual(this)"><button type="button" class="progga-btn progga-btn-light progga-btn-sm" title="Reset to automatic label" onclick="resetConversionLabel(this.closest('tr'))"><i class="bi bi-arrow-clockwise"></i></button></div></td>
    <td><input type="hidden" data-field="purchase_allowed" value="0"><input class="form-check-input" type="checkbox" data-checkbox="purchase_allowed" value="1" checked></td>
    <td><input type="hidden" data-field="recipe_allowed" value="0"><input class="form-check-input" type="checkbox" data-checkbox="recipe_allowed" value="1" checked></td>
    <td><input class="progga-form-control progga-datepicker" type="text" data-field="effective_from"></td>
    <td><input type="hidden" data-field="is_active" value="0"><input class="form-check-input" type="checkbox" data-checkbox="is_active" value="1" checked></td>
    <td class="text-end"><button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick="this.closest('tr').remove();reindexConversions()" title="Remove"><i class="bi bi-trash"></i></button></td>
</tr>
</template>

<script>
const initialConversions={{ \Illuminate\Support\Js::from($existingConversions) }};
function filterBaseUnits(){
    const dimension=document.getElementById('measurementDimension').value;
    const select=document.getElementById('baseUnitId'); let first=null, current=select.value, currentVisible=false;
    [...select.options].forEach(opt=>{ const show=opt.dataset.dimension===dimension; opt.hidden=!show; opt.disabled=!show; if(show&&!first)first=opt.value; if(show&&opt.value===current)currentVisible=true; });
    if(!currentVisible && first) select.value=first;
}
function selectedBaseSymbol(){
    const select=document.getElementById('baseUnitId');
    return select?.selectedOptions?.[0]?.dataset?.symbol || '';
}
function formatTwoDecimals(value){
    if(value===null || value===undefined || String(value).trim()==='') return '';
    const number=Number(value);
    return Number.isFinite(number) ? number.toFixed(2) : String(value);
}
function formatDecimalInput(input){
    if(!input || input.value.trim()==='') return;
    input.value=formatTwoDecimals(input.value);
}
function cleanFactor(value){
    let text=String(value??'').trim();
    if(!text) return '';
    if(text.includes('.')) text=text.replace(/0+$/,'').replace(/\.$/,'');
    return text;
}
function generatedConversionLabel(row){
    const unitSelect=row.querySelector('[data-field="unit_id"]');
    const factor=cleanFactor(row.querySelector('[data-field="factor_to_base"]').value);
    const packageName=unitSelect?.selectedOptions?.[0]?.dataset?.name || '';
    const baseSymbol=selectedBaseSymbol();
    if(!packageName || !factor || !baseSymbol) return '';
    return `${packageName} (${factor} ${baseSymbol})`;
}
function refreshConversionLabel(row,force=false){
    if(!row) return;
    const input=row.querySelector('[data-field="label"]');
    if(!input || (!force && input.dataset.manual==='1')) return;
    input.value=generatedConversionLabel(row);
    input.dataset.manual='0';
}
function refreshAllAutoLabels(){document.querySelectorAll('[data-conversion-row]').forEach(row=>refreshConversionLabel(row));}
function markConversionLabelManual(input){input.dataset.manual=input.value.trim()===''?'0':'1';if(input.dataset.manual==='0')refreshConversionLabel(input.closest('tr'),true);}
function resetConversionLabel(row){const input=row.querySelector('[data-field="label"]');input.dataset.manual='0';refreshConversionLabel(row,true);}
function initConversionDatePicker(input){
    if(!input || typeof flatpickr!=='function' || input._flatpickr) return;
    flatpickr(input,{dateFormat:'Y-m-d',allowInput:true});
}
function addConversionRow(data={}){
    const clone=document.getElementById('conversionTemplate').content.cloneNode(true); const row=clone.querySelector('tr'); document.getElementById('conversionRows').appendChild(row);
    row.querySelector('[data-field="id"]').value=data.id ?? '';
    row.querySelector('[data-field="unit_id"]').value=data.unit_id ?? '';
    row.querySelector('[data-field="factor_to_base"]').value=formatTwoDecimals(data.factor_to_base ?? '');
    const labelInput=row.querySelector('[data-field="label"]');
    const expected=generatedConversionLabel(row);
    labelInput.value=data.label || expected;
    labelInput.dataset.manual=(data.label && data.label!==expected)?'1':'0';
    const effectiveFrom=row.querySelector('[data-field="effective_from"]');
    effectiveFrom.value=data.effective_from ?? '';
    initConversionDatePicker(effectiveFrom);
    ['purchase_allowed','recipe_allowed','is_active'].forEach(key=>row.querySelector('[data-checkbox="'+key+'"]').checked=String(data[key] ?? 1)!=='0');
    reindexConversions();
}
function reindexConversions(){
    document.querySelectorAll('[data-conversion-row]').forEach((row,index)=>{
        row.querySelectorAll('[data-field]').forEach(el=>el.name='conversions['+index+']['+el.dataset.field+']');
        row.querySelectorAll('[data-checkbox]').forEach(el=>el.name='conversions['+index+']['+el.dataset.checkbox+']');
    });
}
document.addEventListener('DOMContentLoaded',()=>{filterBaseUnits();initialConversions.forEach(row=>addConversionRow(row));});
</script>
