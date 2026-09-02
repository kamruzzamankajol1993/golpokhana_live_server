@extends('admin.master.master')
@section('title','Inventory Reports')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Inventory Reports</h1>
            <p class="text-muted mb-0">Branch-scoped stock, usage, reconciliation and audit reporting from the immutable inventory ledger.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('reports.inventory.usage', request()->only('branch_id')) }}" class="progga-btn progga-btn-outline">Usage</a>
            <a href="{{ route('reports.inventory.reconciliation', request()->only('branch_id')) }}" class="progga-btn progga-btn-outline">Reconciliation</a>
            <a href="{{ route('reports.inventory.request-variance', request()->only('branch_id')) }}" class="progga-btn progga-btn-outline">Request vs Issue</a>
            <a href="{{ route('reports.inventory.branch-comparison', request()->only('branch_id')) }}" class="progga-btn progga-btn-secondary">Branch Comparison</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Main Stock Rows</div><div class="fs-4 fw-bold">{{ number_format($summary['main_rows']) }}</div></div></div></div>
        <div class="col-md"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Kitchen Stock Rows</div><div class="fs-4 fw-bold">{{ number_format($summary['kitchen_rows']) }}</div></div></div></div>
        <div class="col-md"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Low Stock</div><div class="fs-4 fw-bold text-warning">{{ number_format($summary['low_rows']) }}</div></div></div></div>
        <div class="col-md"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Negative Stock</div><div class="fs-4 fw-bold text-danger">{{ number_format($summary['negative_rows']) }}</div></div></div></div>
        <div class="col-md"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Open Exceptions</div><div class="fs-4 fw-bold">{{ number_format($summary['open_exceptions']) }}</div></div></div></div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header"><strong>Report Shortcuts</strong></div>
        <div class="p-3 d-flex gap-2 flex-wrap">
            <a class="progga-btn progga-btn-light" href="{{ route('reports.inventory.index', array_filter(['branch_id'=>request('branch_id'),'location_type'=>'MAIN'])) }}">Current Main Stock</a>
            <a class="progga-btn progga-btn-light" href="{{ route('reports.inventory.index', array_filter(['branch_id'=>request('branch_id'),'location_type'=>'KITCHEN'])) }}">Current Kitchen Stock</a>
            <a class="progga-btn progga-btn-light" href="{{ route('reports.inventory.index', array_filter(['branch_id'=>request('branch_id'),'state'=>'LOW'])) }}">Low Stock</a>
            <a class="progga-btn progga-btn-light" href="{{ route('reports.inventory.index', array_filter(['branch_id'=>request('branch_id'),'state'=>'NEGATIVE'])) }}">Negative Stock</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.ledger.index') }}">Ingredient Ledger</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.purchases.index') }}">Purchase History</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.transfers.index') }}">Transfer / Return History</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.kitchen-requests.index') }}">Kitchen Requests</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.consumptions.index') }}">Order Consumption</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.wastages.index') }}">Wastage</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.adjustments.index') }}">Adjustments</a>
            <a class="progga-btn progga-btn-light" href="{{ route('inventory.exceptions.index') }}">Exception Queue</a>
            @if(auth()->user()?->isSuperAdmin())
                <a class="progga-btn progga-btn-light" href="{{ route('reports.inventory.qa') }}">Rollout QA</a>
            @endif
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header">
            <form method="GET" class="row g-2 w-100 align-items-end">
                @if($branches->count() > 1 || $allBranches)
                <div class="col-md-2"><label class="progga-form-label">Branch</label><select name="branch_id" class="progga-form-control"><option value="all" @selected(request('branch_id')==='all' || ($allBranches && !request()->has('branch_id')))>All Branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-2"><label class="progga-form-label">Location</label><select name="location_type" class="progga-form-control"><option value="">Main + Kitchen</option><option value="MAIN" @selected(request('location_type')==='MAIN')>Main Stock</option><option value="KITCHEN" @selected(request('location_type')==='KITCHEN')>Kitchen Stock</option></select></div>
                <div class="col-md-2"><label class="progga-form-label">State</label><select name="state" class="progga-form-control"><option value="">All States</option><option value="OK" @selected(request('state')==='OK')>OK</option><option value="LOW" @selected(request('state')==='LOW')>Low</option><option value="NEGATIVE" @selected(request('state')==='NEGATIVE')>Negative</option></select></div>
                <div class="col-md-4"><label class="progga-form-label">Ingredient</label><input name="search" value="{{ request('search') }}" class="progga-form-control" placeholder="Name or code"></div>
                <div class="col-md-2"><button class="progga-btn progga-btn-primary">Apply</button> <a class="progga-btn progga-btn-light" href="{{ route('reports.inventory.index') }}">Reset</a></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr>@if($allBranches)<th>Branch</th>@endif<th>Location</th><th>Ingredient</th><th class="text-end">Current Qty</th><th class="text-end">Low Level</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        @if($allBranches)<td>{{ $row->branch_name }}</td>@endif
                        <td>{{ $row->location_name }} <span class="text-muted small">({{ $row->location_type }})</span></td>
                        <td><strong>{{ $row->ingredient_name }}</strong>@if($row->ingredient_code)<div class="text-muted small">{{ $row->ingredient_code }}</div>@endif</td>
                        <td class="text-end">{{ rtrim(rtrim((string)$row->quantity_base,'0'),'.') }} {{ $row->base_unit_symbol }}</td>
                        <td class="text-end">{{ rtrim(rtrim((string)$row->low_stock_level_base,'0'),'.') }} {{ $row->base_unit_symbol }}</td>
                        <td>@if($row->inventory_state==='NEGATIVE')<span class="badge bg-danger">Negative</span>@elseif($row->inventory_state==='LOW')<span class="badge bg-warning text-dark">Low</span>@else<span class="badge bg-success">OK</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $allBranches ? 6 : 5 }}" class="text-center text-muted py-5">No stock rows match the selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $rows->links() }}</div>
    </div>
</main>
@endsection
