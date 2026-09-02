@extends('admin.master.master')
@section('title','Order Inventory Consumption')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Order Ingredient Consumption</h1><p class="text-muted mb-0">One immutable inventory consumption per order. Kitchen and Payment triggers share the same idempotent service.</p></div></div>
    <div class="progga-card mb-4 inventory-filter-card"><div class="p-3"><form id="inventoryFilterForm" class="row g-2 align-items-end"><div class="col-md-4"><label class="progga-form-label">Trigger</label><select name="trigger_source" class="progga-form-control"><option value="">All triggers</option><option value="KITCHEN_COMPLETE" @selected(request('trigger_source')==='KITCHEN_COMPLETE')>Kitchen Complete</option><option value="PAYMENT_COMPLETE" @selected(request('trigger_source')==='PAYMENT_COMPLETE')>Payment Complete</option></select></div><div class="col-md-auto"><button class="progga-btn progga-btn-secondary">Apply Filter</button></div><div class="col-md-auto"><a href="{{ route('inventory.consumptions.index') }}" class="progga-btn progga-btn-light">Clear</a></div></form></div></div>
    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar"><div class="inventory-result-copy"><span class="fw-semibold">Consumption List</span><small class="text-muted" id="inventoryResultCount">{{ $consumptions->total() }} {{ $consumptions->total() === 1 ? 'record' : 'records' }}</small></div><div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search order number..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button></div></div>
        <div id="inventoryListContent"><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th><th>Order</th><th>Branch</th><th>Trigger</th><th>Consumed At</th><th>Detail Rows</th><th>Ledger</th><th style="width:70px;">Actions</th></tr></thead><tbody>
        @forelse($consumptions as $c)<tr><td>{{ ($consumptions->firstItem() ?? 1)+$loop->index }}</td><td><strong>{{ $c->order?->order_number ?: '#'.$c->order_id }}</strong></td><td>{{ $c->branch?->name }}</td><td><span class="progga-badge progga-badge-neutral">{{ str_replace('_',' ',$c->trigger_source) }}</span></td><td>{{ $c->consumed_at?->format('d M Y h:i A') }}</td><td>{{ $c->items_count }}</td><td>{{ $c->stock_movement_id ? '#'.$c->stock_movement_id : 'No tracked items' }}</td><td><div class="progga-table-actions"><a href="{{ route('inventory.consumptions.show',$c) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View"><i class="bi bi-eye"></i></a></div></td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-5">No order inventory consumption found.</td></tr>@endforelse
        </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$consumptions,'label'=>'consumptions'])</div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.consumptions.index')])
@endsection
