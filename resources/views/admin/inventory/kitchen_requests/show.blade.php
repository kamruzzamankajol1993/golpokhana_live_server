@extends('admin.master.master')
@section('title','Kitchen Request '.$kitchenRequest->request_no)
@section('body')
@php
    $kitchenActor = auth()->user()?->isKitchenUser() ?? false;
    $typeLabel = match($kitchenRequest->request_type){'FOOD'=>'Food-wise','INGREDIENT'=>'Ingredient-wise','MIXED'=>'Food + Direct Ingredient',default=>$kitchenRequest->request_type};
    $badge = match($kitchenRequest->status){
        'DRAFT'=>'bg-secondary','SUBMITTED'=>'bg-info text-dark','PARTIALLY_ISSUED'=>'bg-warning text-dark',
        'FULLY_ISSUED'=>'bg-success','CLOSED'=>'bg-dark','CANCELLED'=>'bg-danger',default=>'bg-secondary'};
@endphp
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $kitchenRequest->request_no }}</h1><p class="text-muted mb-0">{{ $kitchenRequest->branch?->name }} · {{ $typeLabel }} · {{ optional($kitchenRequest->request_date)->format('d M Y') }}</p></div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline">Back</a>
            @if($kitchenRequest->isEditable())
                @can('inventory-kitchen-request-create')
                    <a href="{{ route('inventory.kitchen-requests.edit',$kitchenRequest) }}" class="progga-btn progga-btn-outline progga-btn-icon" title="Edit Request" aria-label="Edit Request"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('inventory.kitchen-requests.destroy',$kitchenRequest) }}" class="d-inline">
                        @csrf @method('DELETE')
                        <button type="button" class="progga-btn progga-btn-danger progga-btn-icon" title="Delete Request" aria-label="Delete Request" data-delete-name="{{ $kitchenRequest->request_no }}" onclick="confirmKitchenRequestDelete(this)"><i class="bi bi-trash"></i></button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="progga-card mb-4"><div class="p-4"><div class="row g-3">
        <div class="col-md-2"><small class="text-muted">Status</small><div><span class="badge {{ $badge }}">{{ str_replace('_',' ',$kitchenRequest->status) }}</span></div></div>
        <div class="col-md-2"><small class="text-muted">Requested By</small><div>{{ $kitchenRequest->requester?->name ?: '—' }}</div></div>
        <div class="col-md-2"><small class="text-muted">Reviewed By</small><div>{{ $kitchenRequest->reviewer?->name ?: '—' }}</div></div>
        <div class="col-md-2"><small class="text-muted">Submitted</small><div>{{ $kitchenRequest->submitted_at?->format('d M Y h:i A') ?: '—' }}</div></div>
        <div class="col-md-4"><small class="text-muted">Notes</small><div>{{ $kitchenRequest->notes ?: '—' }}</div></div>
    </div></div></div>

    @if($kitchenRequest->foodItems->isNotEmpty())
    <div class="progga-card mb-4"><div class="progga-card-header"><strong>Food Request Snapshot</strong><div class="small text-muted">The recipe/version below is preserved even if the menu recipe changes later.</div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Menu Item</th><th>Requested Qty</th><th>Recipe Version</th></tr></thead><tbody>@foreach($kitchenRequest->foodItems as $food)<tr><td>{{ $food->foodItem?->name }}</td><td>{{ rtrim(rtrim((string)$food->requested_food_qty,'0'),'.') }}</td><td>v{{ $food->recipe_version_no }} <small class="text-muted">(#{{ $food->recipe_id }})</small></td></tr>@endforeach</tbody></table></div></div>
    @endif

    <div class="progga-card mb-4"><div class="progga-card-header"><strong>Ingredient Requirement vs Issue</strong><div class="small text-muted">Required quantity never gets overwritten. Issued quantity is cumulative across posted transfers.</div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th>Source</th><th>Required</th><th>Approved</th><th>Issued</th><th>Remaining</th>@unless($kitchenActor)<th>Available Main</th>@endunless</tr></thead><tbody>
        @foreach($kitchenRequest->ingredientItems as $item)<tr><td><strong>{{ $item->ingredient?->name }}</strong>@if(in_array($item->source_kind,['DIRECT','MIXED'],true) && $item->input_quantity)<br><small class="text-muted">Direct requested: {{ rtrim(rtrim((string)$item->input_quantity,'0'),'.') }} {{ $item->packageConversion?->label ?: $item->displayUnit?->symbol }}</small>@endif</td><td>{{ match($item->source_kind){'FOOD'=>'Food recipe','DIRECT'=>'Direct','MIXED'=>'Food + Direct',default=>$item->source_kind} }}</td><td>{{ rtrim(rtrim((string)$item->required_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td><td>{{ rtrim(rtrim((string)$item->approved_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td><td>{{ rtrim(rtrim((string)$item->issued_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td><td class="fw-semibold">{{ rtrim(rtrim((string)$item->remaining_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td>@unless($kitchenActor)<td><span class="{{ (float)$item->available_main_base < (float)$item->remaining_base ? 'text-danger fw-semibold' : 'text-success fw-semibold' }}">{{ rtrim(rtrim((string)$item->available_main_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</span></td>@endunless</tr>@endforeach
    </tbody></table></div></div>

    @if($kitchenRequest->status==='DRAFT')
    @can('inventory-kitchen-request-create')
    <div class="progga-card mb-4"><div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><strong>Submit for Store Review</strong><div class="small text-muted">Submission freezes this request document. Stock is still unchanged.</div></div><div class="d-flex gap-2"><form method="POST" action="{{ route('inventory.kitchen-requests.cancel',$kitchenRequest) }}" onsubmit="return confirm('Cancel this draft request?')">@csrf<input type="hidden" name="branch_id" value="{{ $kitchenRequest->branch_id }}"><button class="progga-btn progga-btn-outline">Cancel Request</button></form><form method="POST" action="{{ route('inventory.kitchen-requests.submit',$kitchenRequest) }}">@csrf<input type="hidden" name="branch_id" value="{{ $kitchenRequest->branch_id }}"><button class="progga-btn progga-btn-primary"><i class="bi bi-send"></i> Submit Request</button></form></div></div></div>
    @endcan
    @endif

    @if($kitchenRequest->canIssue())
    @can('inventory-kitchen-request-review')
    @can('inventory-transfer-post')
    <div class="progga-card mb-4">
        <div class="progga-card-header"><strong>Store Issue / Post Main → Kitchen</strong><div class="small text-muted">Enter actual issue quantities. You may issue less than required. More than Main availability is blocked by default.</div></div>
        <form method="POST" action="{{ route('inventory.kitchen-requests.issue',$kitchenRequest) }}" onsubmit="return confirm('Post this stock issue? Main Stock will decrease and Kitchen Stock will increase atomically.')">
            @csrf<input type="hidden" name="branch_id" value="{{ $kitchenRequest->branch_id }}"><input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)\Illuminate\Support\Str::uuid()) }}">
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th>Remaining (Base)</th><th>Available Main</th><th style="width:180px">Issue Qty</th><th style="width:220px">Unit</th></tr></thead><tbody>
            @foreach($kitchenRequest->ingredientItems as $item)
                @if((float)$item->remaining_base > 0)
                <tr><td><strong>{{ $item->ingredient?->name }}</strong></td><td>{{ rtrim(rtrim((string)$item->remaining_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td><td>{{ rtrim(rtrim((string)$item->available_main_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td><td><input type="number" step="0.00000001" min="0" name="items[{{ $item->id }}][quantity]" value="{{ old('items.'.$item->id.'.quantity') }}" class="progga-form-control" placeholder="0"></td><td><select name="items[{{ $item->id }}][unit_choice]" class="progga-form-control"><option value="">Select unit / package</option>@foreach($unitOptionsByIngredient[(int)$item->ingredient_id] ?? [] as $choice)<option value="{{ $choice['value'] }}" @selected((string)old('items.'.$item->id.'.unit_choice','u:'.$item->ingredient?->base_unit_id)===(string)$choice['value'])>{{ $choice['label'] }}</option>@endforeach</select></td></tr>
                @endif
            @endforeach
            </tbody></table></div>
            <div class="p-4 border-top"><div class="row g-3 align-items-end"><div class="col-lg-9"><label class="progga-form-label">Issue Notes</label><input name="notes" value="{{ old('notes') }}" class="progga-form-control" placeholder="Optional Store note"></div><div class="col-lg-3"><button class="progga-btn progga-btn-primary w-100"><i class="bi bi-arrow-right-circle"></i> Post Issue Transfer</button></div></div></div>
        </form>
    </div>
    @endcan
    @endcan
    @endif

    @if(in_array($kitchenRequest->status,['SUBMITTED','PARTIALLY_ISSUED','FULLY_ISSUED'],true))
    @can('inventory-kitchen-request-review')
    <div class="d-flex justify-content-end mb-4"><form method="POST" action="{{ route('inventory.kitchen-requests.close',$kitchenRequest) }}" onsubmit="return confirm('Close this request lifecycle? Further issues will be blocked.')">@csrf<input type="hidden" name="branch_id" value="{{ $kitchenRequest->branch_id }}"><button class="progga-btn progga-btn-outline">Close Request</button></form></div>
    @endcan
    @endif

    @unless($kitchenActor)
    @if($kitchenRequest->transfers->isNotEmpty())
    <div class="progga-card"><div class="progga-card-header"><strong>Issue Transfer History</strong></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Transfer</th><th>Posted</th><th>Items</th><th>Posted By</th><th></th></tr></thead><tbody>@foreach($kitchenRequest->transfers as $transfer)<tr><td class="fw-semibold">{{ $transfer->transfer_no }}</td><td>{{ $transfer->posted_at?->format('d M Y h:i A') }}</td><td>{{ $transfer->items->count() }}</td><td>{{ $transfer->poster?->name ?: '—' }}</td><td class="text-end"><a class="progga-btn progga-btn-outline progga-btn-sm" href="{{ route('inventory.transfers.show',$transfer) }}">View</a></td></tr>@endforeach</tbody></table></div></div>
    @endif
    @endunless
</main>
@endsection
@section('script')
<script>
function confirmKitchenRequestDelete(button) {
    const form = button.closest('form');
    const requestNo = button.dataset.deleteName || 'This request';

    if (window.Swal) {
        Swal.fire({
            title: 'Delete kitchen request?',
            text: requestNo + ' will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
        return;
    }

    if (window.confirm('Delete ' + requestNo + '?')) {
        form.submit();
    }
}
</script>
@endsection
