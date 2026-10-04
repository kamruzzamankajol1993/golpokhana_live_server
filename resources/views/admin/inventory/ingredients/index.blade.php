@extends('admin.master.master')
@section('title','Ingredients')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Ingredients</h1>
            <p class="text-muted mb-0">Ingredient master, current stock visibility, base unit and package conversion.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            @can('inventory-units-manage')
                <a href="{{ route('inventory.units.index') }}" class="progga-btn progga-btn-outline">
                    <i class="bi bi-rulers"></i> Units &amp; Conversion
                </a>
            @endcan
            @canany(['inventory-view', 'inventory-kitchen-stock-view'])
                <a href="{{ route('inventory.stock.index') }}" class="progga-btn progga-btn-secondary">
                    <i class="bi bi-boxes"></i> {{ auth()->user()?->isKitchenManager() ? 'Kitchen Stock' : 'Current Stock' }}
                </a>
            @endcanany
            <a href="{{ route('inventory.ingredients.create') }}" class="progga-btn progga-btn-primary">
                <i class="bi bi-plus-lg"></i> Add Ingredient
            </a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="progga-card mb-4 inventory-filter-card">
        <div class="p-3">
            <form id="inventoryFilterForm" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="progga-form-label">Unit Type</label>
                    <select class="progga-form-control" name="dimension">
                        <option value="">All types</option>
                        @foreach(['WEIGHT'=>'Weight','VOLUME'=>'Volume','COUNT'=>'Count'] as $value=>$label)
                            <option value="{{ $value }}" @selected(request('dimension')===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto"><button class="progga-btn progga-btn-secondary">Apply Filter</button></div>
                <div class="col-md-auto"><a class="progga-btn progga-btn-light" href="{{ route('inventory.ingredients.index') }}">Clear</a></div>
            </form>
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar">
            <div class="inventory-result-copy">
                <span class="fw-semibold">Ingredient List</span>
                <small class="text-muted" id="inventoryResultCount">{{ $ingredients->total() }} {{ $ingredients->total() === 1 ? 'record' : 'records' }}</small>
            </div>
            <div class="inventory-search-wrap">
                <i class="bi bi-search inventory-search-icon"></i>
                <input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search ingredient..." autocomplete="off">
                <button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>

        <div id="inventoryListContent">
            <div class="progga-table-wrapper" style="border:none;border-radius:0;">
                <table class="progga-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">SL</th>
                            <th>Ingredient</th>
                            <th>Base Unit</th>
                            <th>Stock</th>
                            <th>Low Stock Alert</th>
                            <th>Purchase / Package Conversion</th>
                            <th>Status</th>
                            <th style="width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($ingredients as $ingredient)
                        @php
                            $storeStock = (float) $ingredient->balances->filter(fn($balance) => $balance->location?->type === \App\Models\StockLocation::TYPE_MAIN)->sum(fn($balance) => (float) $balance->quantity_base);
                            $kitchenStock = (float) $ingredient->balances->filter(fn($balance) => $balance->location?->type === \App\Models\StockLocation::TYPE_KITCHEN)->sum(fn($balance) => (float) $balance->quantity_base);
                            $totalStock = $storeStock + $kitchenStock;
                            $formatQty = fn($qty) => rtrim(rtrim(number_format((float)$qty, 8, '.', ''), '0'), '.');
                        @endphp
                        <tr>
                            <td>{{ ($ingredients->firstItem() ?? 1)+$loop->index }}</td>
                            <td>
                                <strong>{{ $ingredient->name }}</strong>
                                <div class="small text-muted">{{ ucfirst(strtolower($ingredient->measurement_dimension)) }}</div>
                            </td>
                            <td>{{ $ingredient->baseUnit?->name }} ({{ $ingredient->baseUnit?->symbol }})</td>
                            <td>
                                <strong class="{{ $totalStock < 0 ? 'text-danger' : '' }}">{{ $formatQty($totalStock) }} {{ $ingredient->baseUnit?->symbol }}</strong>
                                <div class="small text-muted">Store: {{ $formatQty($storeStock) }} · Kitchen: <span class="{{ $kitchenStock < 0 ? 'text-danger fw-semibold' : '' }}">{{ $formatQty($kitchenStock) }}</span></div>
                            </td>
                            <td>{{ $formatQty($ingredient->low_stock_level_base) }} {{ $ingredient->baseUnit?->symbol }}</td>
                            <td>
                                @forelse($ingredient->unitConversions->where('is_active',true) as $conversion)
                                    <span class="progga-badge progga-badge-neutral me-1">{{ $conversion->label ?: ($conversion->unit?->name.' ('.rtrim(rtrim((string)$conversion->factor_to_base,'0'),'.').' '.$ingredient->baseUnit?->symbol.')') }}</span>
                                @empty
                                    <span class="text-muted">Standard units only</span>
                                @endforelse
                            </td>
                            <td><span class="progga-badge progga-badge-{{ $ingredient->is_active?'success':'neutral' }}">{{ $ingredient->is_active?'Active':'Inactive' }}</span></td>
                            <td>
                                <div class="progga-table-actions">
                                    <a href="{{ route('inventory.ingredients.show',$ingredient) }}" class="progga-btn progga-btn-secondary progga-btn-icon progga-btn-sm" title="View stock & history" aria-label="View {{ $ingredient->name }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('inventory.ingredients.edit',$ingredient) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('inventory.ingredients.destroy',$ingredient) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" title="Delete" data-delete-name="{{ $ingredient->name }}" onclick="inventoryConfirmDelete(this)"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">No ingredients found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.inventory.partials.pagination',['paginator'=>$ingredients,'label'=>'ingredients'])
        </div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.ingredients.index')])
@endsection
