@extends('admin.master.master')
@section('title',($kitchenOnly ?? false) ? 'Kitchen Stock' : 'Current Inventory Stock')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">{{ ($kitchenOnly ?? false) ? 'Kitchen Stock' : 'Current Stock' }}</h1><p class="text-muted mb-0">{{ ($kitchenOnly ?? false) ? 'Only stock currently available in your Kitchen location is shown.' : 'Fast balance view. The immutable Stock Ledger remains the audit source of truth.' }}</p></div>@unless($kitchenOnly ?? false)<a href="{{ route('inventory.ledger.index') }}" class="progga-btn progga-btn-secondary"><i class="bi bi-journal-text"></i> Stock Ledger</a>@endunless</div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @can('inventory-adjustment-post')
    @if($specificBranch)
    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>Opening Stock</strong><div class="text-muted small">Allowed only before an ingredient/location has any stock history. Later corrections must use adjustment/reversal workflow.</div></div></div>
        <form method="POST" action="{{ route('inventory.stock.opening.store') }}" class="p-3">@csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="progga-form-label">Location</label><select name="location_id" class="progga-form-control" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="progga-form-label">Ingredient</label><select name="ingredient_id" id="openingIngredient" class="progga-form-control" required onchange="filterOpeningUnits()"><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}" data-dimension="{{ $ingredient->measurement_dimension }}" data-package-units="{{ $ingredient->unitConversions->where('is_active',true)->pluck('unit_id')->implode(',') }}">{{ $ingredient->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="progga-form-label">Quantity</label><input type="number" step="0.00000001" min="0.00000001" name="quantity" class="progga-form-control" required></div>
                <div class="col-md-2"><label class="progga-form-label">Unit</label><select name="unit_choice" id="openingUnit" class="progga-form-control" required><option value="">Select ingredient first</option></select></div>
                <div class="col-md-2"><button class="progga-btn progga-btn-primary w-100">Post Opening</button></div>
                <div class="col-12"><input name="reason" class="progga-form-control" maxlength="1000" placeholder="Opening stock note / physical count reference"></div>
            </div>
        </form>
    </div>
    @else<div class="alert alert-info">Opening stock posting is branch-specific. Select a specific branch from the header first.</div>@endif
    @endcan

    @unless($kitchenOnly ?? false)
    <div class="progga-card mb-4 inventory-filter-card"><div class="p-3"><form id="inventoryFilterForm" class="row g-2 align-items-end"><div class="col-md-4"><label class="progga-form-label">Location</label><select name="location_id" class="progga-form-control"><option value="">All locations</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)request('location_id')===(string)$location->id)>{{ ($allBranchesSelected ?? false) ? ($location->branch?->name.' - ') : '' }}{{ $location->name }}</option>@endforeach</select></div><div class="col-md-auto"><button class="progga-btn progga-btn-secondary">Apply Filter</button></div><div class="col-md-auto"><a href="{{ route('inventory.stock.index') }}" class="progga-btn progga-btn-light">Clear</a></div></form></div></div>
    @endunless
    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar"><div class="inventory-result-copy"><span class="fw-semibold">Stock List</span><small class="text-muted" id="inventoryResultCount">{{ $balances->total() }} {{ $balances->total() === 1 ? 'record' : 'records' }}</small></div><div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search ingredient or code..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button></div></div>
        <div id="inventoryListContent"><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th>@if(($allBranchesSelected ?? false))<th>Branch</th>@endif<th>Location</th><th>Ingredient</th><th>Current Qty</th><th>Low Stock Level</th><th>Status</th></tr></thead><tbody>
        @forelse($balances as $balance)@php $qty=(string)$balance->quantity_base; $low=(string)($balance->ingredient?->low_stock_level_base ?? '0'); @endphp<tr><td>{{ ($balances->firstItem() ?? 1)+$loop->index }}</td>@if(($allBranchesSelected ?? false))<td>{{ $balance->branch?->name }}</td>@endif<td>{{ $balance->location?->name }}</td><td><strong>{{ $balance->ingredient?->name }}</strong><br><small class="text-muted">{{ $balance->ingredient?->code }}</small></td><td><strong>{{ rtrim(rtrim($qty,'0'),'.') }} {{ $balance->ingredient?->baseUnit?->symbol }}</strong></td><td>{{ rtrim(rtrim($low,'0'),'.') }} {{ $balance->ingredient?->baseUnit?->symbol }}</td><td>@if($balance->inventory_state==='NEGATIVE')<span class="progga-badge progga-badge-danger">Negative</span>@elseif($balance->inventory_state==='LOW')<span class="progga-badge progga-badge-warning">Low</span>@else<span class="progga-badge progga-badge-success">OK</span>@endif</td></tr>@empty<tr><td colspan="{{ ($allBranchesSelected ?? false) ? 7 : 6 }}" class="text-center text-muted py-5">No stock balance rows found.</td></tr>@endforelse
        </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$balances,'label'=>'stock rows'])</div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.stock.index')])
<script>
const openingIngredients={{ \Illuminate\Support\Js::from($ingredients->map(fn($i)=>[
    'id'=>$i->id,'dimension'=>$i->measurement_dimension,
    'packages'=>$i->unitConversions->map(fn($c)=>['id'=>(int)$c->id,'label'=>$c->label ?: (($c->unit?->name ?? 'Package').' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')])->values()
])->values()) }};
const openingStandardUnits={{ \Illuminate\Support\Js::from($allUnits->where('dimension','!=','PACKAGE')->map(fn($u)=>['id'=>(int)$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension])->values()) }};
function openingEsc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function filterOpeningUnits(){
    const ingredientId=document.getElementById('openingIngredient')?.value;
    const ingredient=openingIngredients.find(i=>String(i.id)===String(ingredientId));
    const unit=document.getElementById('openingUnit');if(!unit)return;
    if(!ingredient){unit.innerHTML='<option value="">Select ingredient first</option>';return;}
    let html='<option value="">Select unit / package size</option>';
    html+=openingStandardUnits.filter(u=>u.dimension===ingredient.dimension).map(u=>`<option value="u:${u.id}">${openingEsc(u.name)} (${openingEsc(u.symbol)})</option>`).join('');
    if(ingredient.packages.length)html+='<optgroup label="Package variants">'+ingredient.packages.map(p=>`<option value="c:${p.id}">${openingEsc(p.label)}</option>`).join('')+'</optgroup>';
    unit.innerHTML=html;
}
document.addEventListener('DOMContentLoaded',filterOpeningUnits);
</script>
@endsection
