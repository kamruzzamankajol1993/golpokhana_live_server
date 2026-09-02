@extends('admin.master.master')
@section('title','Inventory Wastage')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Wastage</h1><p class="text-muted mb-0">Spillage, expiry, damage and prep loss are separate audited stock movements.</p></div>@can('inventory-wastage-post')<a href="{{ route('inventory.wastages.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-plus-lg"></i> Post Wastage</a>@endcan</div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="progga-card mb-4 inventory-filter-card"><div class="p-3"><form id="inventoryFilterForm" class="row g-2 align-items-end"><div class="col-md-4"><label class="progga-form-label">Reason</label><select name="reason_code" class="progga-form-control"><option value="">All reasons</option>@foreach($reasons as $reason)<option value="{{ $reason }}" @selected(request('reason_code')===$reason)>{{ ucfirst(strtolower($reason)) }}</option>@endforeach</select></div><div class="col-md-auto"><button class="progga-btn progga-btn-secondary">Apply Filter</button></div><div class="col-md-auto"><a href="{{ route('inventory.wastages.index') }}" class="progga-btn progga-btn-light">Clear</a></div></form></div></div>
    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar"><div class="inventory-result-copy"><span class="fw-semibold">Wastage List</span><small class="text-muted" id="inventoryResultCount">{{ $wastages->total() }} {{ $wastages->total() === 1 ? 'record' : 'records' }}</small></div><div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search wastage number..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button></div></div>
        <div id="inventoryListContent"><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th><th>No.</th><th>Branch</th><th>Location</th><th>Reason</th><th>Items</th><th>Posted</th><th style="width:70px;">Actions</th></tr></thead><tbody>
        @forelse($wastages as $w)<tr><td>{{ ($wastages->firstItem() ?? 1)+$loop->index }}</td><td><strong>{{ $w->wastage_no }}</strong></td><td>{{ $w->branch?->name }}</td><td>{{ $w->location?->name }}</td><td><span class="progga-badge progga-badge-neutral">{{ ucfirst(strtolower($w->reason_code)) }}</span></td><td>{{ $w->items_count }}</td><td>{{ $w->posted_at?->format('d M Y h:i A') }}</td><td><div class="progga-table-actions"><a href="{{ route('inventory.wastages.show',$w) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View"><i class="bi bi-eye"></i></a></div></td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-5">No wastage records found.</td></tr>@endforelse
        </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$wastages,'label'=>'wastage records'])</div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.wastages.index')])
@endsection
