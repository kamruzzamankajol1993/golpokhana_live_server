@extends('admin.master.master')
@section('title',($kitchenOnly ?? false) ? 'Kitchen Stock' : 'Current Stock')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ ($kitchenOnly ?? false) ? 'Kitchen Stock' : 'Current Stock' }}</h1><p class="text-muted mb-0">{{ ($kitchenOnly ?? false) ? 'Ingredients currently available for kitchen operations.' : 'Current Store Stock. Stock area/location selection is handled automatically by the system.' }}</p></div>
        <div class="d-flex gap-2 flex-wrap">
            @can('inventory-transaction-history-view')<a href="{{ route('inventory.ledger.index') }}" class="progga-btn progga-btn-secondary"><i class="bi bi-clock-history"></i> Inventory Audit</a>@endcan
            @unless($kitchenOnly ?? false) @can('inventory-adjustment-post')<a href="{{ route('inventory.adjustments.create') }}" class="progga-btn progga-btn-outline"><i class="bi bi-sliders"></i> Adjust Stock</a>@endcan @endunless
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @unless($kitchenOnly ?? false)
    @can('inventory-adjustment-post')
    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>Opening Stock</strong><div class="text-muted small">Use only when entering an ingredient's starting Store Stock. Later physical corrections should use Adjust Stock.</div></div></div>
        <form method="POST" action="{{ route('inventory.stock.opening.store') }}" class="p-3">@csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-4"><label class="progga-form-label">Ingredient</label><select name="ingredient_id" id="openingIngredient" class="progga-form-control" required onchange="filterOpeningUnits()"><option value="">Select ingredient</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="progga-form-label">Quantity</label><input type="number" step="0.00000001" min="0.00000001" name="quantity" class="progga-form-control" required></div>
                <div class="col-md-3"><label class="progga-form-label">Unit</label><select name="unit_choice" id="openingUnit" class="progga-form-control" required><option value="">Select ingredient first</option></select></div>
                <div class="col-md-3"><button class="progga-btn progga-btn-primary w-100">Add Opening Stock</button></div>
                <div class="col-12"><input name="reason" class="progga-form-control" maxlength="1000" placeholder="Optional note / physical count reference"></div>
            </div>
        </form>
    </div>
    @endcan
    @endunless

    <div class="progga-card">
        <div class="progga-card-header inventory-list-toolbar"><div class="inventory-result-copy"><span class="fw-semibold">Ingredient Stock</span><small class="text-muted" id="inventoryResultCount">{{ $balances->total() }} {{ $balances->total() === 1 ? 'record' : 'records' }}</small></div><div class="inventory-search-wrap"><i class="bi bi-search inventory-search-icon"></i><input type="search" id="inventorySearch" class="progga-form-control inventory-search-input" value="{{ request('search') }}" placeholder="Search ingredient..." autocomplete="off"><button type="button" id="inventorySearchClear" class="inventory-search-clear"><i class="bi bi-x-lg"></i></button></div></div>
        <div id="inventoryListContent"><div class="progga-table-wrapper" style="border:none;border-radius:0;"><table class="progga-table"><thead><tr><th style="width:70px;">SL</th><th>Ingredient</th><th>Current Qty</th><th>Low Stock Alert</th><th>Status</th></tr></thead><tbody>
        @forelse($balances as $balance)@php $qty=(string)$balance->quantity_base; $low=(string)($balance->ingredient?->low_stock_level_base ?? '0'); @endphp<tr><td>{{ ($balances->firstItem() ?? 1)+$loop->index }}</td><td><strong>{{ $balance->ingredient?->name }}</strong></td><td><strong>{{ rtrim(rtrim($qty,'0'),'.') }} {{ $balance->ingredient?->baseUnit?->symbol }}</strong></td><td>{{ rtrim(rtrim($low,'0'),'.') }} {{ $balance->ingredient?->baseUnit?->symbol }}</td><td>@if($balance->inventory_state==='NEGATIVE')<span class="progga-badge progga-badge-danger">Negative</span>@elseif($balance->inventory_state==='LOW')<span class="progga-badge progga-badge-warning">Low</span>@else<span class="progga-badge progga-badge-success">OK</span>@endif</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-5">No stock balance rows found.</td></tr>@endforelse
        </tbody></table></div>@include('admin.inventory.partials.pagination',['paginator'=>$balances,'label'=>'stock rows'])</div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.stock.index')])
<script>
const openingIngredients={{ \Illuminate\Support\Js::from($ingredients->map(fn($i)=>['id'=>$i->id,'dimension'=>$i->measurement_dimension,'packages'=>$i->unitConversions->map(fn($c)=>['id'=>(int)$c->id,'label'=>$c->label ?: (($c->unit?->name ?? 'Package').' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')])->values()])->values()) }};
const openingStandardUnits={{ \Illuminate\Support\Js::from($allUnits->where('dimension','!=','PACKAGE')->map(fn($u)=>['id'=>(int)$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension])->values()) }};
function openingEsc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function filterOpeningUnits(){const ingredientId=document.getElementById('openingIngredient')?.value;const ingredient=openingIngredients.find(i=>String(i.id)===String(ingredientId));const unit=document.getElementById('openingUnit');if(!unit)return;if(!ingredient){unit.innerHTML='<option value="">Select ingredient first</option>';return;}let html='<option value="">Select unit / package size</option>';html+=openingStandardUnits.filter(u=>u.dimension===ingredient.dimension).map(u=>`<option value="u:${u.id}">${openingEsc(u.name)} (${openingEsc(u.symbol)})</option>`).join('');if(ingredient.packages.length)html+='<optgroup label="Package sizes">'+ingredient.packages.map(p=>`<option value="c:${p.id}">${openingEsc(p.label)}</option>`).join('')+'</optgroup>';unit.innerHTML=html;}
document.addEventListener('DOMContentLoaded',filterOpeningUnits);
</script>
@endsection
