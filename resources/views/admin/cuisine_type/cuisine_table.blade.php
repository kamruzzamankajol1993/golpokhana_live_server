<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
            <tr><th>#</th><th>Cuisine Name</th><th>Origin Country</th><th>Description</th><th>Menu Items</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($cuisines as $key => $cuisine)
            <tr>
                <td>{{ $cuisines->firstItem() + $key }}</td>
                <td><strong>{{ $cuisine->name }}</strong></td>
                <td>{{ $cuisine->origin_country ?? '-' }}</td>
                <td><span style="font-size:12px;color:var(--progga-text-muted);">{{ Str::limit($cuisine->description, 40) }}</span></td>
                <td>{{ $cuisine->food_items_count ?? 0 }}</td>
                <td>
                    <label class="progga-toggle">
                        <input type="checkbox" onchange="toggleCuisineStatus({{ $cuisine->id }}, this)" {{ $cuisine->status == 1 ? 'checked' : '' }} data-on="Active" data-off="Inactive">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">{{ $cuisine->status == 1 ? 'Active' : 'Inactive' }}</span>
                    </label>
                </td>
                <td>
                    <div class="progga-table-actions">
                        @can('cuisine-type-edit')
                        <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick="editCuisine({{ $cuisine->id }}, '{{ addslashes($cuisine->name) }}', '{{ addslashes($cuisine->origin_country) }}', '{{ addslashes($cuisine->description) }}', '{{ $cuisine->status }}')"><i class="bi bi-pencil"></i></button>
                        @endcan
                        @can('cuisine-type-delete')
                        <button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick="deleteCuisine({{ $cuisine->id }}, '{{ addslashes($cuisine->name) }}')"><i class="bi bi-trash"></i></button>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center py-4">No cuisine types found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between;">
    <span class="progga-page-info">Showing {{ $cuisines->firstItem() ?? 0 }} to {{ $cuisines->lastItem() ?? 0 }} of {{ $cuisines->total() }}</span>
    <div class="cuisine-pagination">{{ $cuisines->links('pagination::bootstrap-4') }}</div>
</div>
