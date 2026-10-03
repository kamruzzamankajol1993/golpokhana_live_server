@extends('admin.master.master')
@section('title','Food Recipes')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Food Recipes</h1>
            <p class="text-muted mb-0">Define the ingredient quantity used for one sold item / one portion. Recipe version history is handled automatically in the background.</p>
        </div>
    </div>

    <div class="progga-card mb-4"><div class="p-3">
        <form method="GET" id="inventoryFilterForm" class="row g-2 align-items-end">
            <div class="col-md-6"><label class="progga-form-label">Food Item</label><input name="search" value="{{ request('search') }}" class="progga-form-control" placeholder="Search food item..."></div>
            <div class="col-md-3"><label class="progga-form-label">Recipe Status</label><select name="status" class="progga-form-control"><option value="">All</option><option value="configured" @selected(request('status')==='configured')>Configured</option><option value="missing" @selected(request('status')==='missing')>Not Configured</option></select></div>
            <div class="col-md-3 d-flex gap-2"><button class="progga-btn progga-btn-primary">Apply</button><a href="{{ route('inventory.recipes.index') }}" class="progga-btn progga-btn-light">Reset</a></div>
        </form>
    </div></div>

    <div class="progga-card">
        <div class="progga-card-header"><strong>Recipe List</strong><span class="text-muted small" id="inventoryResultCount">{{ $foods->total() }} food items</span></div>
        <div id="inventoryListContent">
        <div class="progga-table-wrapper" style="border:none;border-radius:0;">
            <table class="progga-table">
                <thead><tr><th style="width:70px">SL</th><th>Food Item</th><th>Category</th><th>Recipe</th><th>Ingredients</th><th style="width:150px">Action</th></tr></thead>
                <tbody>
                @forelse($foods as $food)
                    @php($recipe = $food->activeRecipe)
                    <tr>
                        <td>{{ ($foods->firstItem() ?? 1) + $loop->index }}</td>
                        <td><strong>{{ $food->name }}</strong></td>
                        <td>{{ $food->category?->name ?: '—' }}</td>
                        <td>@if($recipe)<span class="progga-badge progga-badge-success">Configured</span>@else<span class="progga-badge progga-badge-warning">Not Configured</span>@endif</td>
                        <td>
                            @if($recipe)
                                @foreach($recipe->items->take(4) as $item)
                                    <div class="small">{{ $item->ingredient?->name }} — {{ rtrim(rtrim((string)$item->input_quantity,'0'),'.') }} {{ $item->inputUnit?->symbol ?: $item->ingredient?->baseUnit?->symbol }}</div>
                                @endforeach
                                @if($recipe->items->count() > 4)<div class="small text-muted">+{{ $recipe->items->count()-4 }} more</div>@endif
                            @else
                                <span class="text-muted">No ingredient recipe yet</span>
                            @endif
                        </td>
                        <td><a href="{{ route('inventory.recipes.edit', $food) }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-pencil"></i> {{ $recipe ? 'Edit Recipe' : 'Add Recipe' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No food items found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.inventory.partials.pagination',['paginator'=>$foods,'label'=>'food items'])
        </div>
    </div>
</main>
@endsection
@section('script')
@include('admin.inventory.partials.list_assets',['indexUrl'=>route('inventory.recipes.index')])
@endsection
