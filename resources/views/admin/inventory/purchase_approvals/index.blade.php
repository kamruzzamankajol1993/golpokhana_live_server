@extends('admin.master.master')
@section('title','My Purchase Approvals')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">My Purchase Approvals</h1><p class="text-muted mb-0">Vouchers selectively sent to you. Each send keeps its own note and history; no earlier-level wait is required.</p></div></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="progga-card mb-4"><div class="p-3"><form id="inventoryFilterForm" class="row g-2 align-items-end"><div class="col-md-3"><label class="progga-form-label">My Status</label><select name="status" class="progga-form-control"><option value="">All</option>@foreach(['PENDING','APPROVED','REJECTED','CANCELLED'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div><div class="col-md-auto"><button class="progga-btn progga-btn-secondary">Filter</button></div><div class="col-md-auto"><a href="{{ route('inventory.purchase-approvals.index') }}" class="progga-btn progga-btn-light">Clear</a></div></form></div></div>
    <div class="progga-card"><div class="progga-card-header"><strong>Approval List</strong><span class="text-muted small" id="inventoryResultCount">{{ $approvals->total() }} {{ $approvals->total()===1?'record':'records' }}</span></div><div id="inventoryListContent"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Voucher</th><th>Vendor</th><th>Revision / Batch</th><th>Sent By / Note</th><th>Total</th><th>Voucher Status</th><th>My Status</th><th style="width:110px">Action</th></tr></thead><tbody>
        @forelse($approvals as $approval)
        <tr><td><strong>{{ $approval->voucher?->voucher_no }}</strong><br><small class="text-muted">{{ optional($approval->voucher?->voucher_date)->format('d M Y') }}</small></td><td>{{ $approval->voucher?->vendor?->name }}</td><td>R{{ $approval->revision_no }}<br><small class="text-muted">Batch #{{ $approval->batch_no ?: 1 }}</small></td><td>{{ $approval->assigner?->name ?: 'System / Legacy' }}<br><small class="text-muted">{{ $approval->dispatch_note ?: '—' }}</small></td><td>৳{{ number_format((float)($approval->voucher?->total ?? 0),2) }}</td><td><span class="badge bg-secondary">{{ str_replace('_',' ',$approval->voucher?->status ?? '') }}</span></td><td><span class="badge {{ $approval->status==='APPROVED'?'bg-success':($approval->status==='REJECTED'?'bg-danger':'bg-warning text-dark') }}">{{ $approval->status }}</span></td><td>@if($approval->voucher)<a href="{{ route('inventory.purchase-vouchers.show',$approval->voucher) }}" class="progga-btn progga-btn-outline progga-btn-sm">Open</a>@endif</td></tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-5">No purchase approval tasks found.</td></tr>@endforelse
    </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$approvals,'label'=>'approval tasks'])</div></div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.purchase-approvals.index')])
@endsection
