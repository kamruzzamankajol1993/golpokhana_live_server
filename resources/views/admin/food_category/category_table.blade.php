<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
        <tr>
            <th style="width:50px;">#</th>
            <th style="width:120px;">Image</th>
            <th>Category Name</th>
            <th>Slug</th>
            <th>Parent Category</th>
            <th>Menu Items</th> <th>Status</th>     <th style="width:100px;">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($categories as $key => $category)
            <tr>
                <td>{{ $categories->firstItem() + $key }}</td>
                <td>
                    @if($category->image)
                        <img src="{{ asset('public/uploads/categories/' . $category->image) }}"
                             alt="{{ $category->name }}"
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid var(--progga-border);">
                    @else
                        <div style="width: 60px; height: 60px; border-radius: 8px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #ccc; border: 1px dashed var(--progga-border);">
                            <i class="bi bi-image"></i>
                        </div>
                    @endif
                </td>
                <td><strong>{{ $category->name }}</strong></td>
                <td><span class="progga-badge progga-badge-neutral">{{ $category->slug }}</span></td>
                <td>
                    @if($category->parent)
                        <span class="progga-badge progga-badge-info">{{ $category->parent->name }}</span>
                    @else
                        <span class="progga-badge progga-badge-neutral">Main Category</span>
                    @endif
                </td>

                <td>{{ $category->food_items_count ?? 0 }}</td>

                <td>
                    <label class="progga-toggle">
                        <input type="checkbox" onchange="toggleCategoryStatus({{ $category->id }}, this)"
                               {{ $category->status == 1 ? 'checked' : '' }}
                               data-on="Active" data-off="Inactive">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">{{ $category->status == 1 ? 'Active' : 'Inactive' }}</span>
                    </label>
                </td>

                <td>
                    <div class="progga-table-actions">
                        @can('food-category-edit')
                        <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                            onclick="editCategory({{ $category->id }}, '{{ addslashes($category->name) }}', '{{ $category->slug }}', '{{ $category->parent_category_id }}', '{{ $category->status }}', '{{ $category->image ? asset('public/uploads/categories/' . $category->image) : '' }}', '{{ $category->sort_order }}')" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        @endcan

                        @can('food-category-delete')
                        <button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm"
                            onclick="deleteCategory({{ $category->id }}, '{{ addslashes($category->name) }}')" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center py-4">No categories found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between;">
    <span class="progga-page-info">Showing {{ $categories->firstItem() ?? 0 }} to {{ $categories->lastItem() ?? 0 }} of {{ $categories->total() }} categories</span>
    <div class="category-pagination">
        {{ $categories->links('pagination::bootstrap-4') }}
    </div>
</div>
