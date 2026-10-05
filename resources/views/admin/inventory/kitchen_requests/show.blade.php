@extends('admin.master.master')
@php
    $statusText = match($kitchenRequest->status){
        'PARTIALLY_ISSUED'=>'Partially Assigned',
        'FULLY_ISSUED'=>'Assigned',
        default=>ucwords(strtolower(str_replace('_',' ',$kitchenRequest->status)))
    };
    $badge=match($kitchenRequest->status){'DRAFT'=>'neutral','SUBMITTED'=>'info','PARTIALLY_ISSUED'=>'warning','FULLY_ISSUED'=>'success','CLOSED'=>'primary','CANCELLED'=>'danger',default=>'neutral'};
@endphp
@section('title',$kitchenRequest->request_no)
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $kitchenRequest->request_no }}</h1><p class="text-muted mb-0">Kitchen request details, recipe-calculated ingredients and assignment progress.</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('inventory.kitchen-requests.index',['tab'=>'requests']) }}" class="progga-btn progga-btn-outline">Back to Request List</a>
            @can('inventory-kitchen-request-assign')
                @if($kitchenRequest->canIssue())<a href="{{ route('inventory.kitchen-requests.assign',$kitchenRequest) }}" class="progga-btn progga-btn-primary"><i class="bi bi-box-arrow-right"></i> Assign Ingredient</a>@endif
            @endcan
            @if($kitchenRequest->isEditable())
                @can('inventory-kitchen-request-create')<a href="{{ route('inventory.kitchen-requests.edit',$kitchenRequest) }}" class="progga-btn progga-btn-secondary"><i class="bi bi-pencil"></i> Edit Request</a>@endcan
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="progga-card mb-4"><div class="p-4"><div class="row g-3">
        <div class="col-md-2"><small class="text-muted">Status</small><div><span class="progga-badge progga-badge-{{ $badge }}">{{ $statusText }}</span></div></div>
        <div class="col-md-2"><small class="text-muted">Request Type</small><div>{{ match($kitchenRequest->request_type){'FOOD'=>'Food-wise','MIXED'=>'Food + Direct Ingredient',default=>'Direct Ingredient'} }}</div></div>
        <div class="col-md-2"><small class="text-muted">Request Date</small><div>{{ optional($kitchenRequest->request_date)->format('d M Y') }}</div></div>
        <div class="col-md-2"><small class="text-muted">Requested By</small><div>{{ $kitchenRequest->requester?->name ?: '—' }}</div></div>
        <div class="col-md-2"><small class="text-muted">Assigned/Reviewed By</small><div>{{ $kitchenRequest->reviewer?->name ?: '—' }}</div></div>
        <div class="col-md-2"><small class="text-muted">Sent At</small><div>{{ $kitchenRequest->submitted_at?->format('d M Y h:i A') ?: '—' }}</div></div>
        <div class="col-md-2"><small class="text-muted">Notes</small><div>{{ $kitchenRequest->notes ?: '—' }}</div></div>
    </div></div></div>

    @if($kitchenRequest->foodItems->isNotEmpty())
    <div class="progga-card mb-4">
        <div class="progga-card-header">
            <div><strong>Food-wise Request</strong><div class="small text-muted">Recipe/version is snapshotted when the request is sent. Ingredient quantities below were calculated from these rows.</div></div>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Food Item</th><th>Food Quantity</th><th>Recipe Version</th></tr></thead>
            <tbody>
            @foreach($kitchenRequest->foodItems as $foodRow)
                <tr>
                    <td><strong>{{ $foodRow->foodItem?->name ?: 'Deleted/Unavailable Food' }}</strong></td>
                    <td>{{ rtrim(rtrim((string)$foodRow->requested_food_qty,'0'),'.') }}</td>
                    <td>v{{ $foodRow->recipe_version_no }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    @endif

    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>Requested Ingredients</strong><div class="small text-muted">Required quantity remains unchanged; assigned quantity accumulates from each Inventory Manager assignment.</div></div></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th>Source</th><th>Requested</th><th>Assigned</th><th>Remaining</th></tr></thead><tbody>
        @foreach($kitchenRequest->ingredientItems as $item)
            <tr>
                <td><strong>{{ $item->ingredient?->name }}</strong></td>
                <td><span class="progga-badge {{ $item->source_kind==='MIXED' ? 'progga-badge-warning' : ($item->source_kind==='FOOD' ? 'progga-badge-info' : 'progga-badge-neutral') }}">{{ $item->source_kind==='MIXED' ? 'Food + Direct' : ($item->source_kind==='FOOD' ? 'Food Recipe' : 'Direct') }}</span></td>
                <td>
                    @if($item->source_kind === \App\Models\KitchenRequestIngredientItem::SOURCE_MIXED)
                        <span class="fw-semibold">{{ rtrim(rtrim((string)$item->required_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</span>
                        <br><small class="text-muted">Includes direct: {{ rtrim(rtrim((string)$item->input_quantity,'0'),'.') }} {{ $item->packageConversion?->label ?: $item->displayUnit?->symbol }}</small>
                    @elseif($item->input_quantity)
                        {{ rtrim(rtrim((string)$item->input_quantity,'0'),'.') }} {{ $item->packageConversion?->label ?: $item->displayUnit?->symbol }}
                        <br><small class="text-muted">{{ rtrim(rtrim((string)$item->required_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }} base</small>
                    @else
                        {{ rtrim(rtrim((string)$item->required_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}
                    @endif
                </td>
                <td>{{ rtrim(rtrim((string)$item->issued_base_qty,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td>
                <td class="fw-semibold {{ (float)$item->remaining_base > 0 ? 'text-warning' : 'text-success' }}">{{ rtrim(rtrim((string)$item->remaining_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td>
            </tr>
        @endforeach
        </tbody></table></div>
    </div>

    @if($kitchenRequest->status==='DRAFT')
        @can('inventory-kitchen-request-create')
        <div class="progga-card mb-4"><div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><strong>Legacy Draft</strong><div class="small text-muted">New Step 2 requests are sent immediately. This old draft can still be sent.</div></div><form method="POST" action="{{ route('inventory.kitchen-requests.submit',$kitchenRequest) }}">@csrf<button class="progga-btn progga-btn-primary"><i class="bi bi-send"></i> Send Request</button></form></div></div>
        @endcan
    @endif

    @if(in_array($kitchenRequest->status,['DRAFT','SUBMITTED'],true))
        @can('inventory-kitchen-request-create')
        <div class="d-flex justify-content-end mb-4"><form method="POST" action="{{ route('inventory.kitchen-requests.cancel',$kitchenRequest) }}" id="cancelRequestForm">@csrf<button type="button" class="progga-btn progga-btn-danger" onclick="confirmCancelRequest()">Cancel Request</button></form></div>
        @endcan
    @endif

    @if(in_array($kitchenRequest->status,['SUBMITTED','PARTIALLY_ISSUED','FULLY_ISSUED'],true))
        @can('inventory-kitchen-request-review')
        <div class="d-flex justify-content-end mb-4"><form method="POST" action="{{ route('inventory.kitchen-requests.close',$kitchenRequest) }}" id="closeRequestForm">@csrf<button type="button" class="progga-btn progga-btn-outline" onclick="confirmCloseRequest()">Close Request</button></form></div>
        @endcan
    @endif

    @if($kitchenRequest->transfers->isNotEmpty())
    <div class="progga-card">
        <div class="progga-card-header"><strong>Assignment History</strong></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Assignment</th><th>Posted</th><th>Items</th><th>Assigned By</th>@unless(auth()->user()?->isKitchenManager())<th></th>@endunless</tr></thead><tbody>
        @foreach($kitchenRequest->transfers as $transfer)
            <tr><td class="fw-semibold">{{ $transfer->transfer_no }}</td><td>{{ $transfer->posted_at?->format('d M Y h:i A') }}</td><td>{{ $transfer->items->count() }}</td><td>{{ $transfer->poster?->name ?: '—' }}</td>@unless(auth()->user()?->isKitchenManager())<td class="text-end"><a class="progga-btn progga-btn-outline progga-btn-sm" href="{{ route('inventory.transfers.show',$transfer) }}">View Transaction</a></td>@endunless</tr>
        @endforeach
        </tbody></table></div>
    </div>
    @endif
</main>
@endsection
@section('script')
<script>
function swalConfirm(title,text,confirmText,formId){const form=document.getElementById(formId);if(window.Swal){Swal.fire({title,text,icon:'warning',showCancelButton:true,confirmButtonText:confirmText,cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});}else if(window.confirm(text)){form.submit();}}
function confirmCancelRequest(){swalConfirm('Cancel ingredient request?','No stock will be changed.','Yes, cancel it','cancelRequestForm');}
function confirmCloseRequest(){swalConfirm('Close ingredient request?','Further ingredient assignments will be blocked.','Yes, close it','closeRequestForm');}
</script>
@endsection
