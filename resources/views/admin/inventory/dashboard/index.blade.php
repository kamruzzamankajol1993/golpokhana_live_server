@extends('admin.master.master')
@section('title','Inventory Dashboard')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Inventory Dashboard</h1>
            <p class="text-muted mb-0">{{ $scopeLabel }} · stock, requests, purchases, transfers, wastage and exception overview.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('inventory-kitchen-request-review')
                <a href="{{ route('inventory.kitchen-requests.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-clipboard2-check"></i> Requests</a>
            @endcan
            @can('inventory-purchase-create')
                <a href="{{ route('inventory.purchases.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-plus-lg"></i> New Purchase</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['Main Stock Items',$mainStockItems,'bi-box-seam'],
            ['Kitchen Stock Items',$kitchenStockItems,'bi-basket2'],
            ['Low Stock',$lowStockCount,'bi-exclamation-circle'],
            ['Negative Stock',$negativeStockCount,'bi-exclamation-octagon'],
            ['Pending Requests',$pendingRequests,'bi-clipboard2-check'],
            ['Purchases Today',$purchasesToday,'bi-receipt'],
            ['Transfers Today',$transfersToday,'bi-arrow-left-right'],
            ['Wastage Today',$wastagesToday,'bi-trash3'],
            ['Open Exceptions',$openExceptions,'bi-exclamation-triangle'],
        ] as $card)
        <div class="col-6 col-lg-3">
            <div class="progga-card h-100"><div class="p-3 d-flex align-items-center gap-3">
                <div class="fs-3"><i class="bi {{ $card[2] }}"></i></div>
                <div><div class="text-muted small">{{ $card[0] }}</div><div class="fs-4 fw-bold">{{ number_format((float)$card[1],0) }}</div></div>
            </div></div>
        </div>
        @endforeach
        <div class="col-12 col-lg-6">
            <div class="progga-card h-100"><div class="p-3">
                <div class="text-muted small">Received Purchase Value Today</div>
                <div class="fs-3 fw-bold">৳{{ number_format($purchaseValueToday,2) }}</div>
                <div class="small text-muted">Only received/post-to-stock purchases are included.</div>
            </div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="progga-card h-100">
                <div class="progga-card-header"><div><strong>Low / Negative Stock</strong><div class="small text-muted">Lowest balances that need attention.</div></div><a href="{{ route('inventory.stock.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">View Stock</a></div>
                <div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr>@if($showBranchColumn)<th>Branch</th>@endif<th>Location</th><th>Ingredient</th><th class="text-end">Current</th><th class="text-end">Low Level</th></tr></thead><tbody>
                    @forelse($lowStocks as $row)
                    <tr>@if($showBranchColumn)<td>{{ $row->branch_name ?? '—' }}</td>@endif<td>{{ $row->location_name }}</td><td><strong>{{ $row->ingredient_name }}</strong></td><td class="text-end {{ (float)$row->quantity_base < 0 ? 'text-danger fw-bold' : '' }}">{{ number_format((float)$row->quantity_base,2) }} {{ $row->unit_symbol }}</td><td class="text-end">{{ number_format((float)$row->low_stock_level_base,2) }} {{ $row->unit_symbol }}</td></tr>
                    @empty<tr><td colspan="{{ $showBranchColumn ? 5 : 4 }}" class="text-center text-muted py-4">No low or negative stock rows.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="progga-card h-100">
                <div class="progga-card-header"><div><strong>Recent Stock Movements</strong><div class="small text-muted">Latest immutable ledger postings.</div></div><a href="{{ route('inventory.ledger.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">View Ledger</a></div>
                <div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr>@if($showBranchColumn)<th>Branch</th>@endif<th>Movement</th><th>Flow</th><th>When</th></tr></thead><tbody>
                    @forelse($recentMovements as $movement)
                    <tr>@if($showBranchColumn)<td>{{ $movement->branch?->name ?? '—' }}</td>@endif<td><strong>{{ $movement->movement_no }}</strong><div class="small text-muted">{{ str_replace('_',' ',$movement->movement_type) }}</div></td><td>{{ $movement->sourceLocation?->name ?: 'External' }} → {{ $movement->destinationLocation?->name ?: 'External' }}</td><td>{{ $movement->occurred_at?->format('d M Y h:i A') ?: '—' }}</td></tr>
                    @empty<tr><td colspan="{{ $showBranchColumn ? 4 : 3 }}" class="text-center text-muted py-4">No posted movement yet.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
    </div>
</main>
@endsection
