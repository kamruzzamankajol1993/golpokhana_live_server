<div class="progga-table-wrapper">
    <table class="progga-table" id="foodTable">
        <thead>
            <tr>
                <th class="progga-col-serial">#</th>
                <th class="progga-col-thumb">Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Cuisine</th>
                <th class="progga-col-price">Price</th>
                <th class="progga-col-toggle">Featured</th>
                <th class="progga-col-toggle">Availability</th>
                <th class="progga-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($foodItems as $key => $item)
            <tr class="{{ $item->is_available ? '' : 'progga-row-unavailable' }}">
                <td>{{ $foodItems->firstItem() + $key }}</td>
                <td>
                    <div class="progga-table-thumb {{ $item->is_available ? '' : 'progga-thumb-muted' }}">
                        @if($item->main_image)
                            <img src="{{ asset('public/uploads/foods/'.$item->main_image) }}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
                        @else
                            <span>🍽️</span>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="progga-food-name {{ $item->is_available ? '' : 'progga-food-name-muted' }}">{{ $item->name }}</div>
                    <div class="progga-food-alt" style="font-size:12px;color:var(--progga-text-muted);">{{ Str::limit($item->short_description, 35) }}</div>
                </td>
                <td><span class="progga-badge progga-badge-neutral">{{ $item->category->name ?? 'N/A' }}</span></td>
                <td><span class="progga-badge progga-badge-cuisine">{{ $item->cuisineType->name ?? 'N/A' }}</span></td>
                <td><span class="progga-food-price">৳{{ number_format($item->base_price, 2) }}</span></td>

                <td>
                    <label class="progga-toggle">
                        <input type="checkbox" onchange="toggleFoodStatus({{ $item->id }}, this, 'is_featured')" {{ $item->is_featured ? 'checked' : '' }} data-on="Yes" data-off="No">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">{{ $item->is_featured ? 'Yes' : 'No' }}</span>
                    </label>
                </td>

                <td>
                    <label class="progga-toggle">
                        <input type="checkbox" onchange="toggleFoodStatus({{ $item->id }}, this, 'is_available')" {{ $item->is_available ? 'checked' : '' }} data-on="Available" data-off="Unavailable">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">{{ $item->is_available ? 'Available' : 'Unavailable' }}</span>
                    </label>
                </td>

                <td>
                    <div class="progga-table-actions">

                        @can('food-item-view')
<a href="{{ route('food-item.show', $item->id) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View Details">
    <i class="bi bi-eye"></i>
</a>
@endcan


                        @can('food-item-edit')
                        <a href="{{ route('food-item.edit', $item->id) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @endcan

                        @can('food-item-delete')
                        <button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" title="Delete"
                            onclick="deleteFood({{ $item->id }}, '{{ addslashes($item->name) }}')">
                            <i class="bi bi-trash"></i>
                        </button>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center py-4">No food items found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="progga-card-footer progga-table-footer">
    <span class="progga-page-info">Showing {{ $foodItems->firstItem() ?? 0 }} to {{ $foodItems->lastItem() ?? 0 }} of {{ $foodItems->total() }} items</span>
    <div class="progga-pagination">
        {{ $foodItems->links('pagination::bootstrap-4') }}
    </div>
</div>
