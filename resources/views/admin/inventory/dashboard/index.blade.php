@extends('admin.master.master')
@section('title','Inventory Dashboard')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Inventory Dashboard</h1>
            <p class="text-muted mb-0">Store stock, kitchen requests, purchases and wastage — simplified for daily operation.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('inventory-kitchen-request-assign')<a href="{{ route('inventory.kitchen-requests.index',['tab'=>'assign']) }}" class="progga-btn progga-btn-outline"><i class="bi bi-box-arrow-right"></i> Assign Ingredient</a>@endcan
            @can('inventory-purchase-voucher-manage')<a href="{{ route('inventory.purchase-vouchers.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-plus-lg"></i> New Purchase Voucher</a>@endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['Store Stock Items',$storeStockItems,'bi-box-seam'],
            ['Kitchen Stock Items',$kitchenStockItems,'bi-basket2'],
            ['Low Store Stock',$lowStockCount,'bi-exclamation-circle'],
            ['Negative Kitchen Stock',$negativeStockCount,'bi-exclamation-octagon'],
            ['Pending Ingredient Requests',$pendingRequests,'bi-clipboard2-check'],
            ['Purchase Vouchers Pending Approval',$pendingPurchaseVouchers,'bi-person-check'],
            ['Approved Vouchers / Send Vendor',$approvedPurchaseVouchers,'bi-send-check'],
            ['Sent to Vendor / Awaiting Supply',$awaitingSupplyVouchers,'bi-truck'],
            ['Purchases Received Today',$purchasesToday,'bi-receipt'],
            ['Wastage Today',$wastagesToday,'bi-trash3'],
        ] as $card)
        <div class="col-6 col-lg-3">
            <div class="progga-card h-100"><div class="p-3 d-flex align-items-center gap-3">
                <div class="fs-3"><i class="bi {{ $card[2] }}"></i></div>
                <div><div class="text-muted small">{{ $card[0] }}</div><div class="fs-4 fw-bold">{{ number_format((float)$card[1],0) }}</div></div>
            </div></div>
        </div>
        @endforeach
        <div class="col-12 col-lg-6">
            <div class="progga-card h-100"><div class="p-3"><div class="text-muted small">Received Purchase Value Today</div><div class="fs-3 fw-bold">৳{{ number_format($purchaseValueToday,2) }}</div><div class="small text-muted">Only received purchases that actually increased Store Stock.</div></div></div>
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header"><strong>Quick Actions</strong></div>
        <div class="p-3 d-flex gap-2 flex-wrap">
            @can('inventory-ingredients-manage')<a class="progga-btn progga-btn-light" href="{{ route('inventory.ingredients.create') }}"><i class="bi bi-plus-lg"></i> Add Ingredient</a>@endcan
            @can('inventory-recipes-manage')<a class="progga-btn progga-btn-light" href="{{ route('inventory.recipes.index') }}"><i class="bi bi-card-checklist"></i> Food Recipes</a>@endcan
            @can('inventory-kitchen-request-assign')<a class="progga-btn progga-btn-light" href="{{ route('inventory.kitchen-requests.index',['tab'=>'assign']) }}"><i class="bi bi-box-arrow-right"></i> Assign Ingredient to Kitchen</a>@endcan
            @can('inventory-purchase-voucher-manage')<a class="progga-btn progga-btn-light" href="{{ route('inventory.purchase-vouchers.index') }}"><i class="bi bi-file-earmark-check"></i> Purchase Vouchers</a>@endcan
            @can('inventory-transaction-history-view')<a class="progga-btn progga-btn-light" href="{{ route('inventory.ledger.index') }}"><i class="bi bi-clock-history"></i> Inventory Audit</a>@endcan
            @can('inventory-adjustment-post')<a class="progga-btn progga-btn-light" href="{{ route('inventory.adjustments.create') }}"><i class="bi bi-sliders"></i> Adjust Stock</a>@endcan
            @can('inventory-negative-stock-adjust')<a class="progga-btn progga-btn-light" href="{{ route('inventory.negative-stock-adjustments.index') }}"><i class="bi bi-exclamation-triangle"></i> Negative Stock Adjustment</a>@endcan
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="progga-card h-100">
                <div class="progga-card-header"><div><strong>Low / Negative Store Stock</strong><div class="small text-muted">Ingredients that need attention.</div></div><a href="{{ route('inventory.stock.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">View Stock</a></div>
                <div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th>Ingredient</th><th class="text-end">Current</th><th class="text-end">Low Alert</th></tr></thead><tbody>
                    @forelse($lowStocks as $row)<tr><td><strong>{{ $row->ingredient_name }}</strong></td><td class="text-end {{ (float)$row->quantity_base < 0 ? 'text-danger fw-bold' : '' }}">{{ rtrim(rtrim(number_format((float)$row->quantity_base,2,'.',''),'0'),'.') }} {{ $row->unit_symbol }}</td><td class="text-end">{{ rtrim(rtrim(number_format((float)$row->low_stock_level_base,2,'.',''),'0'),'.') }} {{ $row->unit_symbol }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No low or negative Store Stock.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="progga-card h-100">
                <div class="progga-card-header"><div><strong>Recent Transactions</strong><div class="small text-muted">Latest stock changes across Store and Kitchen.</div></div><a href="{{ route('inventory.ledger.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">View History</a></div>
                <div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th>Reference</th><th>Type</th><th>When</th></tr></thead><tbody>
                    @forelse($recentMovements as $movement)<tr><td><strong>{{ $movement->movement_no }}</strong></td><td>{{ ucwords(strtolower(str_replace('_',' ',$movement->movement_type))) }}</td><td>{{ $movement->occurred_at?->format('d M Y h:i A') ?: '—' }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No stock transaction yet.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
    </div>
</main>
@endsection
