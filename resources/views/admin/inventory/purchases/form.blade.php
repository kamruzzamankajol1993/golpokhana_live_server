@extends('admin.master.master')
@section('title', $purchase->exists ? 'Edit Supplier Receipt' : 'Receive Supplier Delivery')
@section('body')
@php
    $isEdit = $purchase->exists;
    $sourceVoucher = $sourceVoucher ?? $purchase->voucher ?? null;
    $oldItems = old('items');
    if ($oldItems === null) {
        $sourceItems = $isEdit ? $purchase->items : ($sourceVoucher?->items ?? collect());
        $oldItems = $sourceItems->isNotEmpty() ? $sourceItems->map(function($i){
            $choice = $i->package_conversion_id ? 'c:'.$i->package_conversion_id : 'u:'.$i->unit_id;
            if (!$i->package_conversion_id && $i->unit?->dimension === 'PACKAGE') {
                $match = $i->ingredient?->unitConversions?->first(fn($c) => (int)$c->unit_id === (int)$i->unit_id && (string)$c->factor_to_base === (string)$i->conversion_factor_snapshot);
                if ($match) $choice = 'c:'.$match->id;
            }
            return ['ingredient_id'=>$i->ingredient_id,'quantity'=>(string)$i->quantity,'unit_choice'=>$choice,'unit_price'=>(string)(str_starts_with($choice, 'c:') ? $i->unit_price : $i->line_total)];
        })->values()->all() : [['ingredient_id'=>'','quantity'=>'','unit_choice'=>'','unit_price'=>'']];
    }
@endphp
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $isEdit ? 'Edit Supplier Receipt' : 'Receive Supplier Delivery' }}</h1><p class="text-muted mb-0">Enter the actual supplier invoice, delivered quantity and actual price against the approved Purchase Voucher.</p></div>
        <a href="{{ route('inventory.purchases.index') }}" class="progga-btn progga-btn-outline">Back</a>
    </div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" id="purchaseForm" enctype="multipart/form-data" action="{{ $isEdit ? route('inventory.purchases.update',$purchase) : route('inventory.purchases.store') }}">
        @csrf @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="submit_action" id="purchaseSubmitAction" value="draft">
        @if($sourceVoucher)<input type="hidden" name="purchase_voucher_id" value="{{ $sourceVoucher->id }}">@endif
        @if($sourceVoucher)<div class="alert alert-info"><strong>Approved Voucher:</strong> <a href="{{ route('inventory.purchase-vouchers.show',$sourceVoucher) }}">{{ $sourceVoucher->voucher_no }} / R{{ $sourceVoucher->revision_no }}</a> · Approved limit ৳{{ number_format((float)$sourceVoucher->total,2) }}. If actual quantity or value exceeds approval, the system will stop stock receiving and automatically start re-approval.</div>@endif
        <div class="progga-card mb-4"><div class="p-4"><div class="row g-3">
            @if($sourceVoucher)<div class="col-md-4"><label class="progga-form-label">Vendor</label><input type="hidden" name="vendor_id" value="{{ $sourceVoucher->vendor_id }}"><input class="progga-form-control" value="{{ $sourceVoucher->vendor?->name ?? $vendors->firstWhere('id',$sourceVoucher->vendor_id)?->name }}" disabled><small class="text-muted">Locked by approved voucher {{ $sourceVoucher->voucher_no }}</small></div>@else<div class="col-md-4"><label class="progga-form-label">Vendor <span class="progga-required">*</span></label><select name="vendor_id" class="progga-form-control" required><option value="">Select vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}" @selected((string)old('vendor_id',$purchase->vendor_id)===(string)$vendor->id)>{{ $vendor->name }}</option>@endforeach</select></div>@endif
            <div class="col-md-3"><label class="progga-form-label">Purchase Date <span class="progga-required">*</span></label><input type="text" name="purchase_date" value="{{ old('purchase_date',$isEdit ? optional($purchase->purchase_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" class="progga-form-control progga-datepicker" required></div>
            <div class="col-md-3"><label class="progga-form-label">Invoice No.</label><input name="invoice_no" value="{{ old('invoice_no',$purchase->invoice_no) }}" class="progga-form-control"></div>
            <div class="col-md-3"><label class="progga-form-label">Reference</label><input name="reference_no" value="{{ old('reference_no',$purchase->reference_no) }}" class="progga-form-control"></div>
            <div class="col-md-6">
                <label class="progga-form-label">Original Invoice / Receipt</label>
                <input type="file" name="original_invoice_file" class="progga-form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                <small class="text-muted d-block mt-1">PDF or image (JPG/JPEG/PNG/WEBP), maximum 10 MB. Uploading a new file while editing replaces the previous file.</small>
                @if($isEdit && $purchase->original_invoice_path)
                    <small class="d-block mt-1">Current: <strong>{{ $purchase->original_invoice_name ?: 'Original invoice' }}</strong> · <a href="{{ route('inventory.purchases.original-invoice',$purchase) }}">Download</a></small>
                @endif
            </div>
        </div></div></div>

        <div class="progga-card mb-4">
            <div class="progga-card-header d-flex justify-content-between align-items-center"><div><strong>Actual Supplied Items</strong><div class="small text-muted">Enter what the vendor actually supplied. Lower quantity/value is allowed; higher quantity/value triggers re-approval before stock can increase.</div></div><button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addPurchaseRow()"><i class="bi bi-plus-lg"></i> Add Item</button></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="min-width:230px">Ingredient</th><th style="width:130px">Quantity</th><th style="min-width:150px">Unit</th><th style="width:190px">Purchase Price</th><th style="width:150px">Line Total</th><th style="width:60px"></th></tr></thead><tbody id="purchaseRows">
                @foreach($oldItems as $idx=>$row)
                <tr class="purchase-row">
                    <td><select name="items[{{ $idx }}][ingredient_id]" class="progga-form-control purchase-ingredient" onchange="filterPurchaseUnits(this)" required><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)($row['ingredient_id']??'')===(string)$ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->baseUnit?->symbol }})</option>@endforeach</select></td>
                    <td><input type="number" step="0.00000001" min="0.00000001" name="items[{{ $idx }}][quantity]" value="{{ $row['quantity']??'' }}" class="progga-form-control purchase-qty" oninput="recalcPurchase()" required></td>
                    <td><select name="items[{{ $idx }}][unit_choice]" class="progga-form-control purchase-unit" data-selected="{{ $row['unit_choice']??'' }}" onchange="purchaseUnitChanged(this)" required><option value="">Select ingredient first</option></select></td>
                    <td><input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_price]" value="{{ $row['unit_price']??'' }}" class="progga-form-control purchase-price" oninput="recalcPurchase()" required><small class="purchase-price-help text-muted d-block mt-1">Select a unit first</small></td>
                    <td class="purchase-line-total fw-semibold">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removePurchaseRow(this)"><i class="bi bi-trash"></i></button></td>
                </tr>
                @endforeach
            </tbody></table></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7"><div class="progga-card h-100"><div class="p-4"><label class="progga-form-label">Notes</label><textarea name="notes" class="progga-form-control" rows="5">{{ old('notes',$purchase->notes) }}</textarea></div></div></div>
            <div class="col-lg-5"><div class="progga-card"><div class="p-4">
                <div class="d-flex justify-content-between mb-3"><span>Subtotal</span><strong id="subtotalText">৳0.00</strong></div>
                <div class="row g-2 mb-3"><div class="col-6"><label class="progga-form-label">Discount</label><input type="number" step="0.0001" min="0" name="discount" value="{{ old('discount',$purchase->discount ?? 0) }}" class="progga-form-control" id="discountInput" oninput="recalcPurchase()"></div><div class="col-6"><label class="progga-form-label">Tax</label><input type="number" step="0.0001" min="0" name="tax" value="{{ old('tax',$purchase->tax ?? 0) }}" class="progga-form-control" id="taxInput" oninput="recalcPurchase()"></div></div>
                <div class="d-flex justify-content-between border-top pt-3 mb-4"><span class="fw-semibold">Actual Invoice Total</span><strong id="totalText" class="fs-5">৳0.00</strong></div>
                <div class="d-grid gap-2">
                    <button type="submit" class="progga-btn progga-btn-outline w-100" onclick="document.getElementById('purchaseSubmitAction').value='draft'">
                        <i class="bi bi-file-earmark-text"></i> {{ $isEdit ? 'Update Draft' : 'Save Draft' }}
                    </button>
                    @can('inventory-purchase-receive')
                    <button type="button" class="progga-btn progga-btn-primary w-100" onclick="confirmPurchaseAndReceive()">
                        <i class="bi bi-box-arrow-in-down"></i> {{ $isEdit ? 'Update & Receive Supply' : 'Receive Supply & Add Stock' }}
                    </button>
                    @endcan
                </div>
                <div class="small text-muted mt-2 text-center">Save Draft does not change stock. Receive Supply checks the approved voucher first, then increases Store Stock only when approval limits are satisfied.</div>
            </div></div></div>
        </div>
    </form>
</main>
@endsection
@section('script')
<script>
const purchaseIngredients = {{ \Illuminate\Support\Js::from($ingredients->map(fn($i)=>[
    'id'=>$i->id,
    'name'=>$i->name,
    'base'=>$i->baseUnit?->symbol,
    'dimension'=>$i->measurement_dimension,
    'packages'=>$i->unitConversions->map(fn($c)=>['id'=>(int)$c->id,'unit_id'=>(int)$c->unit_id,'label'=>$c->label ?: (($c->unit?->name ?? 'Package').' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')])->values()
])->values()) }};
const purchaseUnits = {{ \Illuminate\Support\Js::from($units->where('dimension','!=','PACKAGE')->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension])->values()) }};
let purchaseRowIndex = {{ count($oldItems) }};
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function ingredientOptions(){return '<option value="">Select ingredient</option>'+purchaseIngredients.map(i=>`<option value="${i.id}">${esc(i.name)} (${esc(i.base)})</option>`).join('');}
function unitOptionsForIngredient(ingredientId){
    const ingredient=purchaseIngredients.find(i=>String(i.id)===String(ingredientId));
    if(!ingredient)return '<option value="">Select ingredient first</option>';
    let html='<option value="">Select unit / package size</option>';
    html+=purchaseUnits.filter(u=>u.dimension===ingredient.dimension).map(u=>`<option value="u:${u.id}">${esc(u.name)} (${esc(u.symbol)})</option>`).join('');
    if(ingredient.packages.length){
        html+='<optgroup label="Package variants">'+ingredient.packages.map(p=>`<option value="c:${p.id}">${esc(p.label)}</option>`).join('')+'</optgroup>';
    }
    return html;
}
function addPurchaseRow(){const tbody=document.getElementById('purchaseRows');const tr=document.createElement('tr');tr.className='purchase-row';tr.innerHTML=`<td><select name="items[${purchaseRowIndex}][ingredient_id]" class="progga-form-control purchase-ingredient" onchange="filterPurchaseUnits(this)" required>${ingredientOptions()}</select></td><td><input type="number" step="0.00000001" min="0.00000001" name="items[${purchaseRowIndex}][quantity]" class="progga-form-control purchase-qty" oninput="recalcPurchase()" required></td><td><select name="items[${purchaseRowIndex}][unit_choice]" class="progga-form-control purchase-unit" onchange="purchaseUnitChanged(this)" required><option value="">Select ingredient first</option></select></td><td><input type="number" step="0.01" min="0" name="items[${purchaseRowIndex}][unit_price]" class="progga-form-control purchase-price" oninput="recalcPurchase()" required><small class="purchase-price-help text-muted d-block mt-1">Select a unit first</small></td><td class="purchase-line-total fw-semibold">0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removePurchaseRow(this)"><i class="bi bi-trash"></i></button></td>`;tbody.appendChild(tr);purchaseRowIndex++;}
function removePurchaseRow(btn){const rows=document.querySelectorAll('.purchase-row');if(rows.length<=1)return;btn.closest('tr').remove();recalcPurchase();}
function filterPurchaseUnits(ingredientSelect){const row=ingredientSelect.closest('.purchase-row');const unit=row.querySelector('.purchase-unit');const wanted=unit.dataset.selected||unit.value;unit.innerHTML=unitOptionsForIngredient(ingredientSelect.value);if(wanted&&[...unit.options].some(o=>o.value===wanted))unit.value=wanted;unit.dataset.selected='';updatePurchasePriceHelp(row);recalcPurchase();}
function purchaseUnitChanged(unitSelect){const row=unitSelect.closest('.purchase-row');updatePurchasePriceHelp(row);recalcPurchase();}
function updatePurchasePriceHelp(row){const choice=row.querySelector('.purchase-unit')?.value||'';const help=row.querySelector('.purchase-price-help');if(!help)return;if(choice.startsWith('c:')){help.textContent='Price of 1 selected package';}else if(choice.startsWith('u:')){help.textContent='Total price paid for this entered quantity';}else{help.textContent='Select a unit first';}}
function recalcPurchase(){let subtotal=0;document.querySelectorAll('.purchase-row').forEach(row=>{const q=parseFloat(row.querySelector('.purchase-qty')?.value||0);const p=parseFloat(row.querySelector('.purchase-price')?.value||0);const choice=row.querySelector('.purchase-unit')?.value||'';const hasValidInput=q>0&&p>=0&&choice!=='';const line=hasValidInput?(choice.startsWith('c:')?q*p:p):0;subtotal+=line;row.querySelector('.purchase-line-total').textContent=line.toFixed(2);});const discount=parseFloat(document.getElementById('discountInput')?.value||0);const tax=parseFloat(document.getElementById('taxInput')?.value||0);document.getElementById('subtotalText').textContent='৳'+subtotal.toFixed(2);document.getElementById('totalText').textContent='৳'+Math.max(0,subtotal-discount+tax).toFixed(2);}
function confirmPurchaseAndReceive(){const form=document.getElementById('purchaseForm');if(!form)return;document.getElementById('purchaseSubmitAction').value='receive';if(typeof Swal==='undefined'){if(confirm('Receive this supplier delivery and add approved stock now?'))form.requestSubmit();else document.getElementById('purchaseSubmitAction').value='draft';return;}Swal.fire({title:'Receive supplier delivery?',text:'Approved quantities and values will be checked before Store Stock is increased. Any overrun starts re-approval instead.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, receive supply',cancelButtonText:'Cancel'}).then(result=>{if(result.isConfirmed){form.requestSubmit();}else{document.getElementById('purchaseSubmitAction').value='draft';}});}
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.purchase-ingredient').forEach(filterPurchaseUnits);document.querySelectorAll('.purchase-row').forEach(updatePurchasePriceHelp);recalcPurchase();});
</script>
@endsection
