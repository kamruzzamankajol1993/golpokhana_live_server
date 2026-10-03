@extends('admin.master.master')
@section('title', $voucher->exists ? 'Edit Purchase Voucher' : 'New Purchase Voucher')
@section('body')
@php
    $isEdit = $voucher->exists;
    $oldItems = old('items');
    if ($oldItems === null) {
        $oldItems = $isEdit ? $voucher->items->map(function($i){
            $choice = $i->package_conversion_id ? 'c:'.$i->package_conversion_id : 'u:'.$i->unit_id;
            return ['ingredient_id'=>$i->ingredient_id,'quantity'=>(string)$i->quantity,'unit_choice'=>$choice,'unit_price'=>(string)(str_starts_with($choice,'c:') ? $i->unit_price : $i->line_total)];
        })->values()->all() : [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'','unit_price'=>'']];
    }
@endphp
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $isEdit ? 'Edit Purchase Voucher' : 'New Purchase Voucher' }}</h1><p class="text-muted mb-0">Prepare the estimated vendor purchase before any stock is received. Approval is required before sending it to the vendor.</p></div>
        <a href="{{ route('inventory.purchase-vouchers.index') }}" class="progga-btn progga-btn-outline">Back</a>
    </div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" id="voucherForm" action="{{ $isEdit ? route('inventory.purchase-vouchers.update',$voucher) : route('inventory.purchase-vouchers.store') }}">
        @csrf @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="submit_action" id="voucherSubmitAction" value="draft">
        <div class="progga-card mb-4"><div class="p-4"><div class="row g-3">
            <div class="col-md-5"><label class="progga-form-label">Vendor <span class="progga-required">*</span></label><select name="vendor_id" class="progga-form-control" required><option value="">Select vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((string)old('vendor_id',$voucher->vendor_id)===(string)$vendor->id)>{{ $vendor->name }}</option>@endforeach</select>@can('inventory-vendors-manage')<small><a href="{{ route('inventory.vendors.create') }}">Add vendor</a></small>@endcan</div>
            <div class="col-md-3"><label class="progga-form-label">Voucher Date <span class="progga-required">*</span></label><input type="text" name="voucher_date" value="{{ old('voucher_date',$isEdit ? optional($voucher->voucher_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" class="progga-form-control progga-datepicker" required></div>
            @if($isEdit)<div class="col-md-4"><label class="progga-form-label">Voucher No.</label><input class="progga-form-control" value="{{ $voucher->voucher_no }}" disabled></div>@endif
        </div></div></div>

        <div class="progga-card mb-4">
            <div class="progga-card-header d-flex justify-content-between align-items-center"><div><strong>Estimated Ingredient Purchase</strong><div class="small text-muted">Use the expected quantity and expected vendor price. These values become the approval limit.</div></div><button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addVoucherRow()"><i class="bi bi-plus-lg"></i> Add Item</button></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="min-width:230px">Ingredient</th><th style="width:130px">Quantity</th><th style="min-width:160px">Unit</th><th style="width:190px">Estimated Price</th><th style="width:150px">Line Total</th><th style="width:60px"></th></tr></thead><tbody id="voucherRows">
                @foreach($oldItems as $idx=>$row)
                <tr class="voucher-row">
                    <td><select name="items[{{ $idx }}][ingredient_id]" class="progga-form-control voucher-ingredient" onchange="filterVoucherUnits(this)" required><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td>
                    <td><input type="number" step="0.00000001" min="0.00000001" name="items[{{ $idx }}][quantity]" value="{{ $row['quantity']??'' }}" class="progga-form-control voucher-qty" oninput="recalcVoucher()" required></td>
                    <td><select name="items[{{ $idx }}][unit_choice]" class="progga-form-control voucher-unit" data-selected="{{ $row['unit_choice']??'' }}" onchange="voucherUnitChanged(this)" required><option value="">Select ingredient first</option></select></td>
                    <td><input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_price]" value="{{ $row['unit_price']??'' }}" class="progga-form-control voucher-price" oninput="recalcVoucher()" required><small class="voucher-price-help text-muted d-block mt-1">Select a unit first</small></td>
                    <td class="voucher-line-total fw-semibold">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVoucherRow(this)"><i class="bi bi-trash"></i></button></td>
                </tr>
                @endforeach
            </tbody></table></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7"><div class="progga-card h-100"><div class="p-4"><label class="progga-form-label">Purpose / Notes</label><textarea name="notes" class="progga-form-control" rows="6" placeholder="Why this purchase is required, special vendor instruction, etc.">{{ old('notes',$voucher->notes) }}</textarea></div></div></div>
            <div class="col-lg-5"><div class="progga-card"><div class="p-4">
                <div class="d-flex justify-content-between mb-3"><span>Subtotal</span><strong id="voucherSubtotalText">৳0.00</strong></div>
                <div class="row g-2 mb-3"><div class="col-6"><label class="progga-form-label">Discount</label><input type="number" step="0.0001" min="0" name="discount" value="{{ old('discount',$voucher->discount ?? 0) }}" class="progga-form-control" id="voucherDiscountInput" oninput="recalcVoucher()"></div><div class="col-6"><label class="progga-form-label">Tax</label><input type="number" step="0.0001" min="0" name="tax" value="{{ old('tax',$voucher->tax ?? 0) }}" class="progga-form-control" id="voucherTaxInput" oninput="recalcVoucher()"></div></div>
                <div class="d-flex justify-content-between border-top pt-3 mb-4"><span class="fw-semibold">Approval Amount</span><strong id="voucherTotalText" class="fs-5">৳0.00</strong></div>
                <div class="d-grid gap-2">
                    <button type="submit" class="progga-btn progga-btn-outline w-100" onclick="document.getElementById('voucherSubmitAction').value='draft'"><i class="bi bi-file-earmark-text"></i> {{ $isEdit ? 'Update Draft' : 'Save Draft' }}</button>
                    <button type="button" class="progga-btn progga-btn-primary w-100" onclick="confirmVoucherSubmit()"><i class="bi bi-send-check"></i> Save & Send for Approval</button>
                </div>
                <div class="small text-muted mt-2 text-center">No stock changes here. Purchase receiving is available only after all configured approvals and vendor dispatch.</div>
            </div></div></div>
        </div>
    </form>
</main>
@endsection
@section('script')
<script>
const voucherIngredients = {{ \Illuminate\Support\Js::from($ingredients->map(fn($i)=>[
    'id'=>$i->id,'name'=>$i->name,'base'=>$i->baseUnit?->symbol,'dimension'=>$i->measurement_dimension,
    'packages'=>$i->unitConversions->map(fn($c)=>['id'=>(int)$c->id,'unit_id'=>(int)$c->unit_id,'label'=>$c->label ?: (($c->unit?->name ?? 'Package').' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')])->values()
])->values()) }};
const voucherUnits = {{ \Illuminate\Support\Js::from($units->where('dimension','!=','PACKAGE')->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension])->values()) }};
let voucherRowIndex={{ count($oldItems) }};
function vEsc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function voucherIngredientOptions(){return '<option value="">Select ingredient</option>'+voucherIngredients.map(i=>`<option value="${i.id}">${vEsc(i.name)} (${vEsc(i.base)})</option>`).join('');}
function voucherUnitOptions(id){const i=voucherIngredients.find(x=>String(x.id)===String(id));if(!i)return '<option value="">Select ingredient first</option>';let html='<option value="">Select unit / package size</option>';html+=voucherUnits.filter(u=>u.dimension===i.dimension).map(u=>`<option value="u:${u.id}">${vEsc(u.name)} (${vEsc(u.symbol)})</option>`).join('');if(i.packages.length)html+='<optgroup label="Package variants">'+i.packages.map(p=>`<option value="c:${p.id}">${vEsc(p.label)}</option>`).join('')+'</optgroup>';return html;}
function addVoucherRow(){const tbody=document.getElementById('voucherRows');const tr=document.createElement('tr');tr.className='voucher-row';tr.innerHTML=`<td><select name="items[${voucherRowIndex}][ingredient_id]" class="progga-form-control voucher-ingredient" onchange="filterVoucherUnits(this)" required>${voucherIngredientOptions()}</select></td><td><input type="number" step="0.00000001" min="0.00000001" name="items[${voucherRowIndex}][quantity]" class="progga-form-control voucher-qty" oninput="recalcVoucher()" required></td><td><select name="items[${voucherRowIndex}][unit_choice]" class="progga-form-control voucher-unit" onchange="voucherUnitChanged(this)" required><option value="">Select ingredient first</option></select></td><td><input type="number" step="0.01" min="0" name="items[${voucherRowIndex}][unit_price]" class="progga-form-control voucher-price" oninput="recalcVoucher()" required><small class="voucher-price-help text-muted d-block mt-1">Select a unit first</small></td><td class="voucher-line-total fw-semibold">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVoucherRow(this)"><i class="bi bi-trash"></i></button></td>`;tbody.appendChild(tr);voucherRowIndex++;}
function removeVoucherRow(btn){if(document.querySelectorAll('.voucher-row').length<=1)return;btn.closest('tr').remove();recalcVoucher();}
function filterVoucherUnits(sel){const row=sel.closest('.voucher-row');const unit=row.querySelector('.voucher-unit');const wanted=unit.dataset.selected||unit.value;unit.innerHTML=voucherUnitOptions(sel.value);if(wanted&&[...unit.options].some(o=>o.value===wanted))unit.value=wanted;unit.dataset.selected='';updateVoucherPriceHelp(row);recalcVoucher();}
function voucherUnitChanged(sel){updateVoucherPriceHelp(sel.closest('.voucher-row'));recalcVoucher();}
function updateVoucherPriceHelp(row){const choice=row.querySelector('.voucher-unit')?.value||'';const h=row.querySelector('.voucher-price-help');if(!h)return;h.textContent=choice.startsWith('c:')?'Price of 1 selected package':(choice.startsWith('u:')?'Total expected price for this entered quantity':'Select a unit first');}
function recalcVoucher(){let subtotal=0;document.querySelectorAll('.voucher-row').forEach(row=>{const q=parseFloat(row.querySelector('.voucher-qty')?.value||0),p=parseFloat(row.querySelector('.voucher-price')?.value||0),choice=row.querySelector('.voucher-unit')?.value||'';const line=q>0&&p>=0&&choice?(choice.startsWith('c:')?q*p:p):0;subtotal+=line;row.querySelector('.voucher-line-total').textContent=line.toFixed(2);});const discount=parseFloat(document.getElementById('voucherDiscountInput')?.value||0),tax=parseFloat(document.getElementById('voucherTaxInput')?.value||0);document.getElementById('voucherSubtotalText').textContent='৳'+subtotal.toFixed(2);document.getElementById('voucherTotalText').textContent='৳'+Math.max(0,subtotal-discount+tax).toFixed(2);}
function confirmVoucherSubmit(){const form=document.getElementById('voucherForm');document.getElementById('voucherSubmitAction').value='submit';if(typeof Swal==='undefined'){if(confirm('Send this voucher to the configured approval officers?'))form.requestSubmit();else document.getElementById('voucherSubmitAction').value='draft';return;}Swal.fire({title:'Send for approval?',text:'The voucher will be locked while the approval chain is in progress.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, send for approval'}).then(r=>{if(r.isConfirmed)form.requestSubmit();else document.getElementById('voucherSubmitAction').value='draft';});}
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.voucher-ingredient').forEach(filterVoucherUnits);document.querySelectorAll('.voucher-row').forEach(updateVoucherPriceHelp);recalcVoucher();});
</script>
@endsection
