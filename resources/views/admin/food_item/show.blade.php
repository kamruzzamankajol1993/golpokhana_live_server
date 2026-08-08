@extends('admin.master.master')
@section('title', 'Food Details — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Food Item Details</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('food-item.index') }}" class="progga-breadcrumb-item">Food Menu</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">{{ $foodItem->name }}</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('food-item.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Menu
            </a>
            @can('food-item-edit')
            <a href="{{ route('food-item.edit', $foodItem->id) }}" class="progga-btn progga-btn-primary progga-btn-sm">
                <i class="bi bi-pencil"></i> Edit Item
            </a>
            @endcan
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="progga-card mb-4" style="padding: 24px;">
                <div class="d-flex gap-4 align-items-start">
                    <div style="width: 160px; height: 160px; flex-shrink: 0; border-radius: 12px; overflow: hidden; border: 1px solid var(--progga-border); background:#f8f9fa;">
                        @if($foodItem->main_image)
                            <img src="{{ asset('public/uploads/foods/'.$foodItem->main_image) }}" alt="{{ $foodItem->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <div class="d-flex align-items-center justify-content-center h-100" style="font-size: 3rem; color: #ccc;">🍽️</div>
                        @endif
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <h2 style="font-size: 22px; font-weight: 700; color: var(--progga-primary); margin:0;">{{ $foodItem->name }}</h2>
                            @if($foodItem->is_chefs_special)
                                <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Chef's Special</span>
                            @endif
                        </div>
                        <p style="font-size: 14px; color: var(--progga-text-muted); margin-bottom: 12px;">{{ $foodItem->short_description }}</p>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="progga-badge progga-badge-neutral"><i class="bi bi-tags me-1"></i> {{ $foodItem->category->name ?? 'N/A' }}</span>
                            @if($foodItem->subCategory)
                                <span class="progga-badge progga-badge-neutral"><i class="bi bi-diagram-3 me-1"></i> {{ $foodItem->subCategory->name }}</span>
                            @endif
                            @if($foodItem->cuisineType)
                                <span class="progga-badge progga-badge-info"><i class="bi bi-globe me-1"></i> {{ $foodItem->cuisineType->name }}</span>
                            @endif
                        </div>

                        <div style="font-size: 13px; color: var(--progga-primary);">
                            <strong>Slug:</strong> <code>{{ $foodItem->slug }}</code>
                        </div>
                    </div>
                </div>
            </div>

            <div class="progga-card mb-4">
                <div class="progga-card-header bg-light">
                    <h5 class="m-0 fw-bold" style="font-size: 15px;">Description</h5>
                </div>
                <div class="progga-card-body p-3">
                    <p style="font-size: 14px; color: var(--progga-text-muted); line-height: 1.6; margin:0;">
                        {{ $foodItem->description ?? 'No detailed description provided.' }}
                    </p>
                </div>
            </div>

            @if($foodItem->galleryImages->count() > 0)
            <div class="progga-card mb-4">
                <div class="progga-card-header bg-light">
                    <h5 class="m-0 fw-bold" style="font-size: 15px;">Gallery Images</h5>
                </div>
                <div class="progga-card-body p-3">
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($foodItem->galleryImages as $gallery)
                            <div style="width: 120px; height: 120px; border-radius: 8px; overflow: hidden; border: 1px solid var(--progga-border);">
                                <img src="{{ asset('public/uploads/foods/'.$gallery->image) }}" alt="Gallery" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="progga-card mb-4">
                <div class="progga-card-header bg-light">
                    <h5 class="m-0 fw-bold" style="font-size: 15px;"><i class="bi bi-tags me-2 text-success"></i>Pricing & Tax</h5>
                </div>
                <div class="progga-card-body p-3">
                    <table class="table table-sm table-borderless mb-0" style="font-size: 14px;">
                        <tr><td class="text-muted">Base Price:</td><td class="text-end fw-bold">৳{{ number_format($foodItem->base_price, 2) }}</td></tr>
                        @if($foodItem->discount_price)
                        <tr><td class="text-muted">Discount Price:</td><td class="text-end fw-bold text-danger">৳{{ number_format($foodItem->discount_price, 2) }}</td></tr>
                        @endif
                        <tr><td class="text-muted">Tax Rate:</td><td class="text-end">{{ $foodItem->tax_rate ?? 'None' }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="progga-card mb-4">
                <div class="progga-card-header bg-light">
                    <h5 class="m-0 fw-bold" style="font-size: 15px;"><i class="bi bi-info-circle me-2 text-info"></i>Status & Visibility</h5>
                </div>
                <div class="progga-card-body p-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span style="font-size: 13px; color: var(--progga-text-muted);">Available for Sale:</span>
                        @if($foodItem->is_available) <span class="badge bg-success">Yes</span> @else <span class="badge bg-danger">No</span> @endif
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span style="font-size: 13px; color: var(--progga-text-muted);">Featured on Menu:</span>
                        @if($foodItem->is_featured) <span class="badge bg-primary">Yes</span> @else <span class="badge bg-secondary">No</span> @endif
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span style="font-size: 13px; color: var(--progga-text-muted);">Dine-In:</span>
                        @if($foodItem->is_dine_in) <span class="text-success fw-bold"><i class="bi bi-check-lg"></i></span> @else <span class="text-danger fw-bold"><i class="bi bi-x-lg"></i></span> @endif
                    </div>
                    <div class="d-flex justify-content-between">
                        <span style="font-size: 13px; color: var(--progga-text-muted);">Takeaway:</span>
                        @if($foodItem->is_takeaway) <span class="text-success fw-bold"><i class="bi bi-check-lg"></i></span> @else <span class="text-danger fw-bold"><i class="bi bi-x-lg"></i></span> @endif
                    </div>

                    <hr style="border-color: var(--progga-border); margin: 12px 0;">

                    <div style="font-size: 13px;">
                        <div class="mb-1 text-muted">Active Days:</div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @php $activeDays = is_array($foodItem->active_days) ? $foodItem->active_days : []; @endphp
                            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                                @if(in_array($day, $activeDays))
                                    <span class="badge bg-secondary" style="font-size: 10px;">{{ $day }}</span>
                                @endif
                            @endforeach
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Time:</span>
                            <span>{{ $foodItem->start_time ? \Carbon\Carbon::parse($foodItem->start_time)->format('h:i A') : 'N/A' }} - {{ $foodItem->end_time ? \Carbon\Carbon::parse($foodItem->end_time)->format('h:i A') : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="progga-card mb-4">
                <div class="progga-card-header bg-light">
                    <h5 class="m-0 fw-bold" style="font-size: 15px;"><i class="bi bi-list-ul me-2 text-primary"></i>Other Details</h5>
                </div>
                <div class="progga-card-body p-3">
                    <table class="table table-sm table-borderless mb-2" style="font-size: 13px;">
                        <tr><td class="text-muted">Prep Time:</td><td class="text-end">{{ $foodItem->preparation_time ?? '-' }} min</td></tr>
                        <tr><td class="text-muted">Calories:</td><td class="text-end">{{ $foodItem->calories ?? '-' }} kcal</td></tr>
                        <tr><td class="text-muted">Spice Level:</td><td class="text-end">{{ $foodItem->spice_level ?? 'N/A' }}</td></tr>
                        <tr><td class="text-muted">Serving Size:</td><td class="text-end">{{ $foodItem->serving_size ?? 'N/A' }}</td></tr>
                    </table>

                    <div class="mt-3">
                        <div class="text-muted" style="font-size: 13px; margin-bottom: 4px;">Allergens:</div>
                        @php $itemAllergens = is_array($foodItem->allergens) ? $foodItem->allergens : []; @endphp
                        @if(count($itemAllergens) > 0)
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($itemAllergens as $allergen)
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><i class="bi bi-exclamation-circle me-1"></i>{{ $allergen }}</span>
                                @endforeach
                            </div>
                        @else
                            <span style="font-size: 13px;">None</span>
                        @endif

                        @if($foodItem->allergen_notes)
                            <div style="font-size: 12px; color: var(--progga-text-muted); margin-top: 6px; background: #f8f9fa; padding: 6px; border-radius: 4px;">
                                <strong>Note:</strong> {{ $foodItem->allergen_notes }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($foodItem->addons->count() > 0)
            <div class="progga-card mb-4">
                <div class="progga-card-header bg-light">
                    <h5 class="m-0 fw-bold" style="font-size: 15px;"><i class="bi bi-plus-square-dotted me-2 text-warning"></i>Available Add-ons</h5>
                </div>
                <div class="progga-card-body p-3">
                    <ul class="list-group list-group-flush">
                        @foreach($foodItem->addons as $addon)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="font-size: 13px; border-color: var(--progga-border);">
                            {{ $addon->name }}
                            <span class="fw-bold">৳{{ number_format($addon->price, 2) }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

        </div>
    </div>
</main>
@endsection
