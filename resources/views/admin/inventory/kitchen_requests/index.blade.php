@extends('admin.master.master')
@section('title','Kitchen Requests')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Kitchen Requests</h1>
            <p class="text-muted mb-0">Kitchen Manager can request by Food + Quantity, Direct Ingredient, or both. Inventory Manager assigns the final ingredients from Store Stock.</p>
        </div>
        @can('inventory-kitchen-request-create')
            <a href="{{ route('inventory.kitchen-requests.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-plus-lg"></i> New Kitchen Request</a>
        @endcan
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if($canAssign)
    <div class="progga-card mb-4">
        <div class="p-2 d-flex gap-2 flex-wrap">
            <a href="{{ route('inventory.kitchen-requests.index',['tab'=>'requests']) }}" class="progga-btn {{ $tab==='requests' ? 'progga-btn-primary' : 'progga-btn-light' }}">
                <i class="bi bi-list-check"></i> Request List
            </a>
            <a href="{{ route('inventory.kitchen-requests.index',['tab'=>'assign']) }}" class="progga-btn {{ $tab==='assign' ? 'progga-btn-primary' : 'progga-btn-light' }}">
                <i class="bi bi-box-arrow-right"></i> Assign Ingredient to Kitchen
                @if($pendingAssignmentCount > 0)<span class="badge bg-danger ms-1">{{ $pendingAssignmentCount }}</span>@endif
            </a>
        </div>
    </div>
    @endif

    @if($tab === 'assign' && $canAssign)
        <div class="progga-card mb-4 inventory-filter-card">
            <div class="p-3">
                <form id="inventoryFilterForm" class="row g-2 align-items-end" method="GET">
                    <input type="hidden" name="tab" value="assign">
                    <div class="col-lg-3"><label class="progga-form-label">Search</label><input type="search" name="search" value="{{ request('search') }}" class="progga-form-control" placeholder="Request no / requester"></div>
                    <div class="col-lg-2"><label class="progga-form-label">From</label><input type="text" name="date_from" value="{{ request('date_from') }}" class="progga-form-control progga-datepicker"></div>
                    <div class="col-lg-2"><label class="progga-form-label">To</label><input type="text" name="date_to" value="{{ request('date_to') }}" class="progga-form-control progga-datepicker"></div>
                    <div class="col-lg-auto"><button class="progga-btn progga-btn-secondary">Apply Filter</button></div>
                    <div class="col-lg-auto"><a href="{{ route('inventory.kitchen-requests.index',['tab'=>'assign']) }}" class="progga-btn progga-btn-light">Clear</a></div>
                </form>
            </div>
        </div>

        <div class="progga-card">
            <div class="progga-card-header">
                <div><strong>Assign Ingredient to Kitchen</strong><div class="small text-muted">Only Submitted and Partially Assigned requests appear here.</div></div>
                <span class="text-muted small" id="inventoryResultCount">{{ $assignmentRequests->total() }} pending {{ $assignmentRequests->total()===1?'request':'requests' }}</span>
            </div>
            <div id="inventoryListContent">
            <div class="progga-table-wrapper" style="border:none;border-radius:0;">
                <table class="progga-table">
                    <thead><tr><th style="width:70px">SL</th><th>Request</th><th>Date</th><th>Ingredients</th><th>Progress</th><th>Store Availability</th><th>Status</th><th style="width:160px">Action</th></tr></thead>
                    <tbody>
                    @forelse($assignmentRequests as $item)
                        @php
                            $statusText = $item->status === \App\Models\KitchenRequest::STATUS_PARTIALLY_ISSUED ? 'Partially Assigned' : 'Waiting Assignment';
                        @endphp
                        <tr>
                            <td>{{ ($assignmentRequests->firstItem() ?? 1)+$loop->index }}</td>
                            <td><strong>{{ $item->request_no }}</strong><br><small class="text-muted">{{ match($item->request_type){'FOOD'=>'Food-wise','MIXED'=>'Food + Direct','INGREDIENT'=>'Direct Ingredient',default=>$item->request_type} }} · By {{ $item->requester?->name ?: '—' }}</small></td>
                            <td>{{ optional($item->request_date)->format('d M Y') }}</td>
                            <td>{{ $item->ingredient_items_count }} item(s)<br><small class="text-muted">{{ $item->remaining_lines }} remaining line(s)</small></td>
                            <td>{{ $item->completed_lines }} / {{ $item->ingredient_items_count }} ingredient line(s)</td>
                            <td>
                                @if($item->shortage_lines > 0)
                                    <span class="progga-badge progga-badge-warning">{{ $item->shortage_lines }} shortage line(s)</span>
                                @else
                                    <span class="progga-badge progga-badge-success">Available</span>
                                @endif
                            </td>
                            <td><span class="progga-badge {{ $item->status===\App\Models\KitchenRequest::STATUS_PARTIALLY_ISSUED ? 'progga-badge-warning' : 'progga-badge-info' }}">{{ $statusText }}</span></td>
                            <td><a href="{{ route('inventory.kitchen-requests.assign',$item) }}" class="progga-btn progga-btn-primary progga-btn-sm"><i class="bi bi-box-arrow-right"></i> Assign</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">No kitchen request is waiting for ingredient assignment.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.inventory.partials.pagination',['paginator'=>$assignmentRequests,'label'=>'requests'])
            </div>
        </div>
    @else
        <div class="progga-card mb-4 inventory-filter-card">
            <div class="p-3">
                <form id="inventoryFilterForm" class="row g-2 align-items-end" method="GET">
                    <input type="hidden" name="tab" value="requests">
                    <div class="col-lg-3"><label class="progga-form-label">Search</label><input type="search" name="search" value="{{ request('search') }}" class="progga-form-control" placeholder="Request no / requester"></div>
                    <div class="col-lg-2"><label class="progga-form-label">Status</label><select name="status" class="progga-form-control"><option value="">All status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ match($status){'PARTIALLY_ISSUED'=>'Partially Assigned','FULLY_ISSUED'=>'Assigned',default=>ucwords(strtolower(str_replace('_',' ',$status)))} }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><label class="progga-form-label">From</label><input type="text" name="date_from" value="{{ request('date_from') }}" class="progga-form-control progga-datepicker"></div>
                    <div class="col-lg-2"><label class="progga-form-label">To</label><input type="text" name="date_to" value="{{ request('date_to') }}" class="progga-form-control progga-datepicker"></div>
                    <div class="col-lg-auto"><button class="progga-btn progga-btn-secondary">Apply Filter</button></div>
                    <div class="col-lg-auto"><a href="{{ route('inventory.kitchen-requests.index',['tab'=>'requests']) }}" class="progga-btn progga-btn-light">Clear</a></div>
                </form>
            </div>
        </div>

        <div class="progga-card">
            <div class="progga-card-header"><div><strong>Request List</strong><div class="small text-muted">Food-wise, Direct Ingredient and Mixed requests with their assignment status.</div></div><span class="text-muted small" id="inventoryResultCount">{{ $requests->total() }} {{ $requests->total()===1?'record':'records' }}</span></div>
            <div id="inventoryListContent">
            <div class="progga-table-wrapper" style="border:none;border-radius:0;">
                <table class="progga-table">
                    <thead><tr><th style="width:70px">SL</th><th>Request</th><th>Date</th><th>Ingredients</th><th>Assignments</th><th>Status</th><th style="width:150px">Actions</th></tr></thead>
                    <tbody>
                    @forelse($requests as $item)
                        @php
                            $badge=match($item->status){'DRAFT'=>'neutral','SUBMITTED'=>'info','PARTIALLY_ISSUED'=>'warning','FULLY_ISSUED'=>'success','CLOSED'=>'primary','CANCELLED'=>'danger',default=>'neutral'};
                            $statusText=match($item->status){'PARTIALLY_ISSUED'=>'Partially Assigned','FULLY_ISSUED'=>'Assigned',default=>ucwords(strtolower(str_replace('_',' ',$item->status)))};
                        @endphp
                        <tr>
                            <td>{{ ($requests->firstItem() ?? 1)+$loop->index }}</td>
                            <td><a href="{{ route('inventory.kitchen-requests.show',$item) }}"><strong>{{ $item->request_no }}</strong></a><br><small class="text-muted">{{ match($item->request_type){'FOOD'=>'Food-wise','MIXED'=>'Food + Direct','INGREDIENT'=>'Direct Ingredient',default=>$item->request_type} }} · By {{ $item->requester?->name ?: '—' }}</small></td>
                            <td>{{ optional($item->request_date)->format('d M Y') }}</td>
                            <td>{{ $item->ingredient_items_count }} item(s)</td>
                            <td>{{ $item->transfers_count }}</td>
                            <td><span class="progga-badge progga-badge-{{ $badge }}">{{ $statusText }}</span></td>
                            <td><div class="progga-table-actions">
                                <a href="{{ route('inventory.kitchen-requests.show',$item) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View"><i class="bi bi-eye"></i></a>
                                @if($canAssign && $item->canIssue())<a href="{{ route('inventory.kitchen-requests.assign',$item) }}" class="progga-btn progga-btn-primary progga-btn-icon progga-btn-sm" title="Assign Ingredient"><i class="bi bi-box-arrow-right"></i></a>@endif
                                @if($item->isEditable())
                                    @can('inventory-kitchen-request-create')
                                        <a href="{{ route('inventory.kitchen-requests.edit',$item) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('inventory.kitchen-requests.destroy',$item) }}" class="d-inline">@csrf @method('DELETE')<button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" title="Delete" data-delete-name="{{ $item->request_no }}" onclick="confirmKitchenRequestDelete(this)"><i class="bi bi-trash"></i></button></form>
                                    @endcan
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No kitchen requests found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.inventory.partials.pagination',['paginator'=>$requests,'label'=>'requests'])
            </div>
        </div>
    @endif
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.kitchen-requests.index')])
<script>
function confirmKitchenRequestDelete(button){
    const form=button.closest('form');
    const requestNo=button.dataset.deleteName||'This request';
    if(window.Swal){Swal.fire({title:'Delete kitchen request?',text:requestNo+' will be permanently deleted.',icon:'warning',showCancelButton:true,confirmButtonText:'Yes, delete it',cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});return;}
    if(window.confirm('Delete '+requestNo+'?')) form.submit();
}
</script>
@endsection
