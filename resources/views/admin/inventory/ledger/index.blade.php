@extends('admin.master.master')
@section('title','Inventory Audit')
@section('body')
@php
    $typeLabels = [
        'OPENING_STOCK' => 'Opening Stock',
        'PURCHASE_RECEIVE' => 'Purchase Receive',
        'MAIN_TO_KITCHEN' => 'Assigned to Kitchen',
        'KITCHEN_TO_MAIN' => 'Kitchen Return',
        'ORDER_CONSUMPTION' => 'Sale Recipe Consumption',
        'WASTAGE' => 'Wastage',
        'POSITIVE_ADJUSTMENT' => 'Stock Adjustment (+)',
        'NEGATIVE_ADJUSTMENT' => 'Stock Adjustment (-)',
        'REVERSAL' => 'Reversal',
    ];
@endphp
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Inventory Audit</h1><p class="text-muted mb-0">A complete audit history of stock changes. Technical stock-location setup is handled automatically.</p></div><a href="{{ route('inventory.stock.index') }}" class="progga-btn progga-btn-secondary">{{ ($kitchenOnly ?? false) ? 'Kitchen Stock' : 'Current Stock' }}</a></div>
    <div class="progga-card mb-4 inventory-filter-card"><div class="p-3"><form id="inventoryFilterForm" class="row g-2 align-items-end"><div class="col-md-3"><label class="progga-form-label">Transaction Type</label><select name="movement_type" class="progga-form-control"><option value="">All types</option>@foreach($movementTypes as $type)<option value="{{ $type }}" @selected(request('movement_type')===$type)>{{ $typeLabels[$type] ?? ucwords(strtolower(str_replace('_',' ',$type))) }}</option>@endforeach</select></div><div class="col-md-3"><label class="progga-form-label">Ingredient</label><select name="ingredient_id" class="progga-form-control"><option value="">All ingredients</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" @selected((string)request('ingredient_id')===(string)$ingredient->id)>{{ $ingredient->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="progga-form-label">From</label><input type="text" name="date_from" value="{{ request('date_from') }}" class="progga-form-control progga-datepicker"></div><div class="col-md-2"><label class="progga-form-label">To</label><input type="text" name="date_to" value="{{ request('date_to') }}" class="progga-form-control progga-datepicker"></div><div class="col-md-auto"><button class="progga-btn progga-btn-secondary">Apply</button></div><div class="col-md-auto"><a class="progga-btn progga-btn-light" href="{{ route('inventory.ledger.index') }}">Clear</a></div></form></div></div>
    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar"><div class="inventory-result-copy"><span class="fw-semibold">Transactions</span><small class="text-muted" id="inventoryResultCount">{{ $movements->total() }} {{ $movements->total() === 1 ? 'record' : 'records' }}</small></div><div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search transaction, ingredient or note..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button></div></div>
        <div id="inventoryListContent"><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th><th>Reference</th><th>Type</th><th>Ingredients / Qty</th><th>Stock Effect</th><th>Performed By</th><th>Date & Time</th></tr></thead><tbody>
        @forelse($movements as $movement)
            @php
                $typeLabel = $typeLabels[$movement->movement_type] ?? ucwords(strtolower(str_replace('_',' ',$movement->movement_type)));
                $effect = 'Stock updated';
                if ($movement->movement_type === 'PURCHASE_RECEIVE' || $movement->movement_type === 'OPENING_STOCK') $effect = 'Store +';
                elseif ($movement->movement_type === 'MAIN_TO_KITCHEN') $effect = ($kitchenOnly ?? false) ? 'Kitchen +' : 'Store - / Kitchen +';
                elseif ($movement->movement_type === 'KITCHEN_TO_MAIN') $effect = ($kitchenOnly ?? false) ? 'Kitchen -' : 'Kitchen - / Store +';
                elseif ($movement->movement_type === 'ORDER_CONSUMPTION') $effect = 'Kitchen -';
                elseif ($movement->movement_type === 'WASTAGE') $effect = 'Stock -';
            @endphp
            <tr>
                <td>{{ ($movements->firstItem() ?? 1)+$loop->index }}</td>
                <td><strong>{{ $movement->movement_no }}</strong>@if($movement->reason)<div class="small text-muted">{{ $movement->reason }}</div>@endif</td>
                <td><span class="progga-badge progga-badge-info">{{ $typeLabel }}</span></td>
                <td>@foreach($movement->items as $item)<div><strong>{{ $item->ingredient?->name }}</strong>: {{ rtrim(rtrim((string)$item->quantity_base,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</div>@endforeach</td>
                <td>{{ $effect }}</td>
                <td>{{ $movement->performer?->name ?: 'System' }}</td>
                <td>{{ optional($movement->occurred_at)->format('d M Y, h:i A') ?: '—' }}</td>
            </tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-5">No inventory transactions found.</td></tr>@endforelse
        </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$movements,'label'=>'transactions'])</div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.ledger.index')])
@endsection
