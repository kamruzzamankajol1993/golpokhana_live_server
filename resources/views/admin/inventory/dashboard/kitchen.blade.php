@extends('admin.master.master')
@section('title','Kitchen Inventory Dashboard')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Kitchen Inventory Dashboard</h1>
            <p class="text-muted mb-0">Kitchen Board, ingredient stock, requests, returns and wastage in one place.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('kitchen-view')
                <a href="{{ route('kitchen.index') }}" class="progga-btn progga-btn-primary"><i class="bi bi-fire"></i> Open Kitchen Board</a>
            @endcan
            @can('inventory-kitchen-request-create')
                <a href="{{ route('inventory.kitchen-requests.create') }}" class="progga-btn progga-btn-outline"><i class="bi bi-plus-lg"></i> New Request</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['Pending KOT',$pendingKot,'bi-hourglass-split'],
            ['Cooking',$cookingKot,'bi-fire'],
            ['Ready',$readyKot,'bi-check2-circle'],
            ['Kitchen Ingredients',$kitchenStockItems,'bi-basket2'],
            ['Low Kitchen Stock',$kitchenLowStock,'bi-exclamation-circle'],
            ['Wastage Today',$wastageToday,'bi-trash3'],
        ] as $card)
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="progga-card h-100"><div class="p-3 d-flex align-items-center gap-3">
                <div class="fs-3"><i class="bi {{ $card[2] }}"></i></div>
                <div><div class="text-muted small">{{ $card[0] }}</div><div class="fs-4 fw-bold">{{ number_format((float)$card[1],0) }}</div></div>
            </div></div>
        </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="progga-card h-100">
                <div class="progga-card-header"><strong>My Ingredient Requests</strong><a href="{{ route('inventory.kitchen-requests.index',['tab'=>'requests']) }}" class="progga-btn progga-btn-outline progga-btn-sm">Request List</a></div>
                <div class="p-3">
                    <div class="row g-3 text-center">
                        @foreach([
                            ['Draft',$requestCounts['draft']],
                            ['Submitted',$requestCounts['submitted']],
                            ['Partially Assigned',$requestCounts['partial']],
                            ['Assigned',$requestCounts['assigned']],
                        ] as $row)
                        <div class="col-6"><div class="border rounded p-3"><div class="text-muted small">{{ $row[0] }}</div><div class="fs-4 fw-bold">{{ number_format($row[1]) }}</div></div></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="progga-card h-100">
                <div class="progga-card-header"><strong>Quick Actions</strong></div>
                <div class="p-3 d-flex flex-wrap gap-2">
                    @can('inventory-kitchen-stock-view')<a class="progga-btn progga-btn-light" href="{{ route('inventory.stock.index') }}"><i class="bi bi-boxes"></i> Kitchen Stock</a>@endcan
                    @can('inventory-kitchen-request-create')<a class="progga-btn progga-btn-light" href="{{ route('inventory.kitchen-requests.create') }}"><i class="bi bi-clipboard-plus"></i> New Ingredient Request</a>@endcan
                    @can('inventory-return-post')<a class="progga-btn progga-btn-light" href="{{ route('inventory.transfers.return.create') }}"><i class="bi bi-arrow-return-left"></i> Return Extra Ingredient</a>@endcan
                    @can('inventory-wastage-create')<a class="progga-btn progga-btn-light" href="{{ route('inventory.wastages.create') }}"><i class="bi bi-trash3"></i> Add Wastage</a>@endcan
                    @can('inventory-transaction-history-view')<a class="progga-btn progga-btn-light" href="{{ route('inventory.ledger.index') }}"><i class="bi bi-clock-history"></i> Inventory Audit</a>@endcan
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header"><div><strong>Recent Kitchen Transactions</strong><div class="small text-muted">Latest stock changes that affected Kitchen inventory.</div></div><a href="{{ route('inventory.ledger.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">View All</a></div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;">
            <table class="progga-table"><thead><tr><th>Transaction</th><th>Type</th><th>Ingredients</th><th>When</th></tr></thead><tbody>
            @forelse($recentMovements as $movement)
                <tr>
                    <td><strong>{{ $movement->movement_no }}</strong></td>
                    <td>{{ ucwords(strtolower(str_replace('_',' ',$movement->movement_type))) }}</td>
                    <td>@foreach($movement->items as $item)<div>{{ $item->ingredient?->name }}: {{ rtrim(rtrim((string)$item->quantity_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</div>@endforeach</td>
                    <td>{{ $movement->occurred_at?->format('d M Y h:i A') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No kitchen inventory transactions yet.</td></tr>
            @endforelse
            </tbody></table>
        </div>
    </div>
</main>
@endsection
