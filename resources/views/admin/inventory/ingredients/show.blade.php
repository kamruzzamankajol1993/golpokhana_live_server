@extends('admin.master.master')
@section('title', $ingredient->name . ' - Ingredient Details')
@section('body')
@php
    $unit = $ingredient->baseUnit?->symbol ?? '';
    $formatQty = fn($qty) => rtrim(rtrim(number_format((float)$qty, 8, '.', ''), '0'), '.');
    $stockStateClass = fn($qty) => (float)$qty < 0 ? 'danger' : ((float)$qty <= (float)$ingredient->low_stock_level_base ? 'warning' : 'success');
@endphp
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="progga-page-title mb-0">{{ $ingredient->name }}</h1>
                <span class="progga-badge progga-badge-{{ $ingredient->is_active ? 'success' : 'neutral' }}">{{ $ingredient->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">Current stock, purchase history, order/food consumption and wastage history.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            @canany(['inventory-transaction-history-view', 'inventory-view'])
                <a href="{{ route('inventory.ledger.index', ['ingredient_id' => $ingredient->id]) }}" class="progga-btn progga-btn-secondary"><i class="bi bi-clipboard-data"></i> Inventory Audit</a>
            @endcanany
            <a href="{{ route('inventory.ingredients.edit', $ingredient) }}" class="progga-btn progga-btn-outline"><i class="bi bi-pencil"></i> Edit</a>
            <a href="{{ route('inventory.ingredients.index') }}" class="progga-btn progga-btn-light"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="progga-card h-100 p-3">
                <div class="small text-muted mb-1">Store Stock</div>
                <div class="fs-4 fw-bold text-{{ $stockStateClass($summary['store_stock']) }}">{{ $formatQty($summary['store_stock']) }} {{ $unit }}</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="progga-card h-100 p-3">
                <div class="small text-muted mb-1">Kitchen Stock</div>
                <div class="fs-4 fw-bold text-{{ $stockStateClass($summary['kitchen_stock']) }}">{{ $formatQty($summary['kitchen_stock']) }} {{ $unit }}</div>
                @if($summary['kitchen_stock'] < 0)<div class="small text-danger mt-1">Negative kitchen stock needs adjustment.</div>@endif
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="progga-card h-100 p-3">
                <div class="small text-muted mb-1">Total Current Stock</div>
                <div class="fs-4 fw-bold {{ $summary['total_stock'] < 0 ? 'text-danger' : '' }}">{{ $formatQty($summary['total_stock']) }} {{ $unit }}</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="progga-card h-100 p-3">
                <div class="small text-muted mb-1">Low Stock Alert</div>
                <div class="fs-4 fw-bold">{{ $formatQty($ingredient->low_stock_level_base) }} {{ $unit }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="progga-card p-3 h-100"><div class="small text-muted">Received Purchase Total</div><div class="fs-5 fw-bold mt-1">{{ $formatQty($summary['purchased']) }} {{ $unit }}</div></div></div>
        <div class="col-md-4"><div class="progga-card p-3 h-100"><div class="small text-muted">Used in Completed Orders</div><div class="fs-5 fw-bold mt-1">{{ $formatQty($summary['used']) }} {{ $unit }}</div></div></div>
        <div class="col-md-4"><div class="progga-card p-3 h-100"><div class="small text-muted">Posted Wastage</div><div class="fs-5 fw-bold mt-1">{{ $formatQty($summary['wastage']) }} {{ $unit }}</div></div></div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div><strong>Purchase History</strong><div class="small text-muted">When and how much of this ingredient was purchased.</div></div>
            <span class="progga-badge progga-badge-neutral">{{ $purchaseHistory->count() }} record(s)</span>
        </div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;">
            <table class="progga-table">
                <thead><tr><th>SL</th><th>Date</th><th>Purchase No</th><th>Vendor</th><th>Purchased Qty</th><th>Base Qty</th><th>Line Total</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($purchaseHistory as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->purchase?->purchase_date?->format('d M Y') ?? 'N/A' }}</td>
                        <td><strong>{{ $item->purchase?->purchase_no ?? 'N/A' }}</strong></td>
                        <td>{{ $item->purchase?->vendor?->name ?? 'N/A' }}</td>
                        <td>{{ $formatQty($item->quantity) }} {{ $item->unit?->symbol }}</td>
                        <td>{{ $formatQty($item->base_quantity) }} {{ $unit }}</td>
                        <td>{{ number_format((float)$item->line_total, 2) }}</td>
                        <td><span class="progga-badge {{ $item->purchase?->status === \App\Models\Purchase::STATUS_RECEIVED ? 'progga-badge-success' : ($item->purchase?->status === \App\Models\Purchase::STATUS_CANCELLED ? 'progga-badge-danger' : 'progga-badge-warning') }}">{{ $item->purchase?->status ?? 'N/A' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchase history found for this ingredient.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div><strong>Order / Food Usage</strong><div class="small text-muted">Which order and which food consumed this ingredient.</div></div>
            <span class="progga-badge progga-badge-neutral">{{ $usageHistory->count() }} record(s)</span>
        </div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;">
            <table class="progga-table">
                <thead><tr><th>SL</th><th>Consumed At</th><th>Order</th><th>Food</th><th>Recipe Version</th><th>Ingredient Used</th></tr></thead>
                <tbody>
                @forelse($usageHistory as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->consumption?->consumed_at?->format('d M Y h:i A') ?? 'N/A' }}</td>
                        <td>
                            @if($item->consumption?->order)
                                @can('order-view')<a href="{{ route('order.show', $item->consumption->order->id) }}" class="fw-semibold text-decoration-none">#{{ $item->consumption->order->order_number }}</a>@else<strong>#{{ $item->consumption->order->order_number }}</strong>@endcan
                            @else N/A @endif
                        </td>
                        <td><strong>{{ $item->foodItem?->name ?? 'N/A' }}</strong></td>
                        <td>v{{ $item->recipe_version_no }}</td>
                        <td><strong>{{ $formatQty($item->quantity_base) }} {{ $unit }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No order consumption history found for this ingredient.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div><strong>Wastage History</strong><div class="small text-muted">All recorded wastage entries for this ingredient.</div></div>
            <span class="progga-badge progga-badge-neutral">{{ $wastageHistory->count() }} record(s)</span>
        </div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;">
            <table class="progga-table">
                <thead><tr><th>SL</th><th>Date</th><th>Wastage No</th><th>Stock</th><th>Reason</th><th>Qty</th><th>Base Qty</th><th>Created By</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($wastageHistory as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ ($item->wastage?->posted_at ?? $item->wastage?->created_at)?->format('d M Y h:i A') ?? 'N/A' }}</td>
                        <td><strong>{{ $item->wastage?->wastage_no ?? 'N/A' }}</strong></td>
                        <td>{{ $item->wastage?->location?->name ?? 'N/A' }}</td>
                        <td>{{ ucwords(strtolower(str_replace('_',' ', $item->wastage?->reason_code ?? 'N/A'))) }}</td>
                        <td>{{ $formatQty($item->quantity) }} {{ $item->unit?->symbol }}</td>
                        <td>{{ $formatQty($item->base_quantity) }} {{ $unit }}</td>
                        <td>{{ $item->wastage?->creator?->name ?? 'System' }}</td>
                        <td><span class="progga-badge {{ $item->wastage?->status === \App\Models\InventoryWastage::STATUS_POSTED ? 'progga-badge-success' : 'progga-badge-warning' }}">{{ $item->wastage?->status ?? 'N/A' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No wastage history found for this ingredient.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header"><strong>Unit &amp; Package Conversion</strong></div>
        <div class="p-3">
            <div class="mb-2"><strong>Base Unit:</strong> {{ $ingredient->baseUnit?->name }} ({{ $unit }})</div>
            @forelse($ingredient->unitConversions as $conversion)
                <span class="progga-badge progga-badge-neutral me-1 mb-1">{{ $conversion->label ?: ($conversion->unit?->name . ' = ' . $formatQty($conversion->factor_to_base) . ' ' . $unit) }}</span>
            @empty
                <span class="text-muted">No package conversion configured.</span>
            @endforelse
        </div>
    </div>
</main>
@endsection
