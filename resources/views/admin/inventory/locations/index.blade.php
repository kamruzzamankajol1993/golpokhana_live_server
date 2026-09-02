@extends('admin.master.master')
@section('title','Stock Locations')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Stock Locations</h1><p class="text-muted mb-0">Every branch has isolated MAIN and KITCHEN stock locations.</p></div></div>
    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar"><div class="inventory-result-copy"><span class="fw-semibold">Location List</span><small class="text-muted" id="inventoryResultCount">{{ $locations->total() }} {{ $locations->total() === 1 ? 'record' : 'records' }}</small></div><div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search location or code..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button></div></div>
        <div id="inventoryListContent"><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th><th>Branch</th><th>Code</th><th>Name</th><th>Type</th><th>Status</th></tr></thead><tbody>
        @forelse($locations as $location)<tr><td>{{ ($locations->firstItem() ?? 1)+$loop->index }}</td><td>{{ $location->branch?->name ?? '—' }}</td><td><code>{{ $location->code }}</code></td><td><strong>{{ $location->name }}</strong></td><td><span class="progga-badge progga-badge-info">{{ $location->type }}</span></td><td><span class="progga-badge progga-badge-{{ $location->is_active?'success':'neutral' }}">{{ $location->is_active?'Active':'Inactive' }}</span></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No stock locations found.</td></tr>@endforelse
        </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$locations,'label'=>'locations'])</div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.locations.index')])
@endsection
