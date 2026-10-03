@extends('admin.master.master')
@section('title','Assign Ingredient to Kitchen')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">Assign Ingredient to Kitchen</h1><p class="text-muted mb-0">{{ $kitchenRequest->request_no }} — assign full or partial quantities from Store Stock.</p></div>
        <div class="d-flex gap-2"><a href="{{ route('inventory.kitchen-requests.index',['tab'=>'assign']) }}" class="progga-btn progga-btn-outline">Back to Assign Tab</a><a href="{{ route('inventory.kitchen-requests.show',$kitchenRequest) }}" class="progga-btn progga-btn-secondary">Request Details</a></div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="progga-card mb-4"><div class="p-4"><div class="row g-3">
        <div class="col-md-3"><small class="text-muted">Requested By</small><div class="fw-semibold">{{ $kitchenRequest->requester?->name ?: '—' }}</div></div>
        <div class="col-md-3"><small class="text-muted">Request Date</small><div>{{ optional($kitchenRequest->request_date)->format('d M Y') }}</div></div>
        <div class="col-md-3"><small class="text-muted">Status</small><div>{{ $kitchenRequest->status===\App\Models\KitchenRequest::STATUS_PARTIALLY_ISSUED ? 'Partially Assigned' : 'Waiting Assignment' }}</div></div>
        <div class="col-md-3"><small class="text-muted">Notes</small><div>{{ $kitchenRequest->notes ?: '—' }}</div></div>
    </div></div></div>

    <form method="POST" action="{{ route('inventory.kitchen-requests.assign.store',$kitchenRequest) }}" id="assignIngredientForm">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)\Illuminate\Support\Str::uuid()) }}">
        <div class="progga-card mb-4">
            <div class="progga-card-header d-flex justify-content-between align-items-center gap-3">
                <div><strong>Ingredient Assignment</strong><div class="small text-muted">Assignment immediately decreases Store Stock and increases Kitchen Stock in one audited transaction.</div></div>
                <button type="button" class="progga-btn progga-btn-light progga-btn-sm" onclick="fillAllRemaining()"><i class="bi bi-check2-all"></i> Fill All Remaining</button>
            </div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Ingredient</th><th>Requested</th><th>Already Assigned</th><th>Remaining</th><th>Available Store</th><th style="width:170px">Assign Qty</th><th style="width:230px">Unit</th></tr></thead>
                <tbody>
                @foreach($kitchenRequest->ingredientItems as $item)
                    @if((float)$item->remaining_base > 0)
                    <tr class="assign-row" data-remaining="{{ $item->remaining_base }}" data-base-unit="u:{{ $item->ingredient?->base_unit_id }}">
                        <td><strong>{{ $item->ingredient?->name }}</strong></td>
                        <td>{{ rtrim(rtrim((string)$item->required_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td>
                        <td>{{ rtrim(rtrim((string)$item->issued_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td>
                        <td class="fw-semibold">{{ rtrim(rtrim((string)$item->remaining_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td>
                        <td><span class="{{ (float)$item->available_main_base < (float)$item->remaining_base ? 'text-danger fw-semibold' : 'text-success fw-semibold' }}">{{ rtrim(rtrim((string)$item->available_main_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</span></td>
                        <td><input type="number" step="0.00000001" min="0" name="items[{{ $item->id }}][quantity]" value="{{ old('items.'.$item->id.'.quantity') }}" class="progga-form-control assign-qty" placeholder="0"></td>
                        <td><select name="items[{{ $item->id }}][unit_choice]" class="progga-form-control assign-unit"><option value="">Select unit / package</option>@foreach($unitOptionsByIngredient[(int)$item->ingredient_id] ?? [] as $choice)<option value="{{ $choice['value'] }}" @selected((string)old('items.'.$item->id.'.unit_choice','u:'.$item->ingredient?->base_unit_id)===(string)$choice['value'])>{{ $choice['label'] }}</option>@endforeach</select></td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table></div>
            <div class="p-4 border-top"><div class="row g-3 align-items-end"><div class="col-lg-9"><label class="progga-form-label">Assignment Notes</label><input name="notes" value="{{ old('notes') }}" class="progga-form-control" placeholder="Optional inventory note"></div><div class="col-lg-3"><button type="button" class="progga-btn progga-btn-primary w-100" onclick="confirmAssignment()"><i class="bi bi-box-arrow-right"></i> Assign Ingredient</button></div></div></div>
        </div>
    </form>

    @if($kitchenRequest->transfers->isNotEmpty())
    <div class="progga-card">
        <div class="progga-card-header"><strong>Previous Assignments</strong></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Transaction</th><th>Posted</th><th>Items</th><th>Assigned By</th><th></th></tr></thead><tbody>@foreach($kitchenRequest->transfers as $transfer)<tr><td>{{ $transfer->transfer_no }}</td><td>{{ $transfer->posted_at?->format('d M Y h:i A') }}</td><td>{{ $transfer->items->count() }}</td><td>{{ $transfer->poster?->name ?: '—' }}</td><td class="text-end"><a href="{{ route('inventory.transfers.show',$transfer) }}" class="progga-btn progga-btn-outline progga-btn-sm">View</a></td></tr>@endforeach</tbody></table></div>
    </div>
    @endif
</main>
@endsection
@section('script')
<script>
function fillAllRemaining(){document.querySelectorAll('.assign-row').forEach(row=>{const qty=row.querySelector('.assign-qty'),unit=row.querySelector('.assign-unit');qty.value=row.dataset.remaining||'';if([...unit.options].some(o=>o.value===row.dataset.baseUnit))unit.value=row.dataset.baseUnit;});}
function confirmAssignment(){
    const hasQty=[...document.querySelectorAll('.assign-qty')].some(i=>parseFloat(i.value||'0')>0);
    if(!hasQty){if(window.Swal){Swal.fire({title:'No quantity entered',text:'Enter at least one ingredient quantity to assign.',icon:'info'});}else{alert('Enter at least one ingredient quantity to assign.');}return;}
    const form=document.getElementById('assignIngredientForm');
    if(window.Swal){Swal.fire({title:'Assign ingredients to Kitchen?',text:'Store Stock will decrease and Kitchen Stock will increase immediately.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, assign now',cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});}else if(window.confirm('Assign these ingredients to Kitchen?')){form.submit();}
}
</script>
@endsection
