@extends('admin.master.master')
@section('title','Inventory Adjustments')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">Physical Stock Adjustments</h1><p class="text-muted mb-0">Physical count differences create corrective ledger movements; balances are never overwritten directly.</p></div>
        @can('inventory-adjustment-post')<a href="{{ route('inventory.adjustments.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-plus-lg"></i> New Physical Count</a>@endcan
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar">
            <div class="inventory-result-copy"><span class="fw-semibold">Adjustment List</span><small class="text-muted" id="inventoryResultCount">{{ $adjustments->total() }} {{ $adjustments->total() === 1 ? 'record' : 'records' }}</small></div>
            <div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search adjustment number..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear" aria-label="Clear search"><i class="bi bi-x-lg"></i></button></div>
        </div>
        <div id="inventoryListContent">
            <div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th><th>No.</th><th>Branch</th><th>Location</th><th>Items</th><th>Posted</th><th>Approved By</th><th style="width:70px;">Actions</th></tr></thead><tbody>
            @forelse($adjustments as $a)<tr><td>{{ ($adjustments->firstItem() ?? 1) + $loop->index }}</td><td><strong>{{ $a->adjustment_no }}</strong></td><td>{{ $a->branch?->name }}</td><td>{{ $a->location?->name }}</td><td>{{ $a->items_count }}</td><td>{{ $a->posted_at?->format('d M Y h:i A') }}</td><td>{{ $a->approver?->name ?: '—' }}</td><td><div class="progga-table-actions"><a href="{{ route('inventory.adjustments.show',$a) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View"><i class="bi bi-eye"></i></a></div></td></tr>
            @empty<tr><td colspan="8" class="text-center text-muted py-5">No adjustments found.</td></tr>@endforelse
            </tbody></table></div>
            @include('admin.inventory.partials.pagination',['paginator'=>$adjustments,'label'=>'adjustments'])
        </div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.adjustments.index')])
@endsection
