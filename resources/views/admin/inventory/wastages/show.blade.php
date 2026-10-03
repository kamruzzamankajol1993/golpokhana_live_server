@extends('admin.master.master')
@section('title','Wastage '.$wastage->wastage_no)
@section('body')
@php
    $isDraft = $wastage->status === \App\Models\InventoryWastage::STATUS_DRAFT;
    $actor = auth()->user();
    $roleCanMutate = !$actor?->isInventoryManager() || $wastage->location?->type === \App\Models\StockLocation::TYPE_MAIN;
    $canMutateDraft = $isDraft && $roleCanMutate;
@endphp
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">{{ $wastage->wastage_no }}</h1>
            <p class="text-muted mb-0">{{ $wastage->location?->type === \App\Models\StockLocation::TYPE_MAIN ? 'Store Stock' : 'Kitchen Stock' }}</p>
        </div>
        <div class="d-flex gap-2">
            @if($canMutateDraft)
                @can('inventory-wastage-edit')
                    <a href="{{ route('inventory.wastages.edit',$wastage) }}" class="progga-btn progga-btn-secondary"><i class="bi bi-pencil"></i> Edit Draft</a>
                @endcan
                @can('inventory-wastage-delete')
                    <form method="POST" action="{{ route('inventory.wastages.destroy',$wastage) }}" onsubmit="return confirm('Delete this DRAFT wastage? No stock movement has been posted yet.')">
                        @csrf @method('DELETE')
                        <button class="progga-btn progga-btn-danger"><i class="bi bi-trash"></i> Delete Draft</button>
                    </form>
                @endcan
            @endif
            <a href="{{ route('inventory.wastages.index') }}" class="progga-btn progga-btn-outline">Back</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if($isDraft)
        <div class="alert alert-warning"><strong>DRAFT</strong> — stock has not changed yet. This record can be edited or deleted. Open Edit Draft and choose <strong>Post Wastage</strong> when it is final.</div>
    @else
        <div class="alert alert-success"><strong>POSTED</strong> — immutable wastage movement {{ $movement?->movement_no }}. Posted stock transactions are not directly edited/deleted; use controlled correction if needed.</div>
    @endif

    <div class="progga-card mb-4">
        <div class="p-4">
            <strong>Reason:</strong> {{ ucfirst(strtolower($wastage->reason_code)) }}<br>
            <strong>Notes:</strong> {{ $wastage->notes ?: '—' }}<br>
            <strong>Status:</strong> {{ $wastage->status }}<br>
            <strong>{{ $isDraft ? 'Last Updated' : 'Posted' }}:</strong> {{ ($isDraft ? $wastage->updated_at : $wastage->posted_at)?->format('d M Y h:i A') }}
        </div>
    </div>

    <div class="progga-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Ingredient</th><th>Input</th><th>Base Quantity</th><th>{{ $isDraft ? 'Stock Impact' : 'Before → After' }}</th></tr></thead>
                <tbody>
                @foreach($wastage->items as $item)
                    @php $mi=$movement?->items?->firstWhere('ingredient_id',$item->ingredient_id); @endphp
                    <tr>
                        <td>{{ $item->ingredient?->name }}</td>
                        <td>{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->packageConversion?->label ?: $item->unit?->symbol }}</td>
                        <td>{{ rtrim(rtrim((string)$item->base_quantity,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td>
                        <td>
                            @if($isDraft)
                                <span class="text-muted">Not posted yet</span>
                            @else
                                {{ rtrim(rtrim((string)($mi?->source_before ?? '0'),'0'),'.') }} → {{ rtrim(rtrim((string)($mi?->source_after ?? '0'),'0'),'.') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
