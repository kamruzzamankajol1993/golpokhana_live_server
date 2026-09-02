@extends('admin.master.master')
@section('title','Inventory Vendors')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Vendors</h1><p class="text-muted mb-0">Global vendor master for inventory purchasing.</p></div><a href="{{ route('inventory.vendors.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-plus-lg"></i> Add Vendor</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar"><div class="inventory-result-copy"><span class="fw-semibold">Vendor List</span><small class="text-muted" id="inventoryResultCount">{{ $vendors->total() }} {{ $vendors->total() === 1 ? 'record' : 'records' }}</small></div><div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search vendor, phone or email..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button></div></div>
        <div id="inventoryListContent"><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th><th>Vendor</th><th>Contact</th><th>Purchases</th><th>Status</th><th style="width:100px;">Actions</th></tr></thead><tbody>
        @forelse($vendors as $vendor)<tr><td>{{ ($vendors->firstItem() ?? 1)+$loop->index }}</td><td><strong>{{ $vendor->name }}</strong><br><small class="text-muted">{{ \Illuminate\Support\Str::limit($vendor->address,70) }}</small></td><td>{{ $vendor->phone ?: '—' }}<br><small class="text-muted">{{ $vendor->email ?: '—' }}</small></td><td>{{ $vendor->purchases_count }}</td><td><span class="progga-badge progga-badge-{{ $vendor->is_active?'success':'neutral' }}">{{ $vendor->is_active?'Active':'Inactive' }}</span></td><td><div class="progga-table-actions"><a href="{{ route('inventory.vendors.edit',$vendor) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Edit"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('inventory.vendors.destroy',$vendor) }}" class="d-inline">@csrf @method('DELETE')<button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" title="Delete" data-delete-name="{{ $vendor->name }}" onclick="inventoryConfirmDelete(this)"><i class="bi bi-trash"></i></button></form></div></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-5">No vendors found.</td></tr>@endforelse
        </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$vendors,'label'=>'vendors'])</div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.vendors.index')])
@endsection
