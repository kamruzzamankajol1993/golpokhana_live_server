<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
            <tr>
                <th style="width:50px;">#</th>
                <th>Allergen Name</th>
                <th>Status</th>
                <th style="width:110px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allergens as $key => $item)
            <tr>
                <td>{{ $allergens->firstItem() + $key }}</td>
                <td><strong>{{ $item->name }}</strong></td>
                <td>
                    <label class="progga-toggle">
                        <input type="checkbox" onchange="toggleStatus({{ $item->id }}, this, 'allergen')" {{ $item->status == 1 ? 'checked' : '' }} data-on="Active" data-off="Inactive">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">{{ $item->status == 1 ? 'Active' : 'Inactive' }}</span>
                    </label>
                </td>
                <td>
                    <div class="progga-table-actions">
                        <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick="editModal('allergen', {{ $item->id }}, '{{ addslashes($item->name) }}', {{ $item->status }})"><i class="bi bi-pencil"></i></button>
                        <button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick="normalDelete({{ $item->id }}, '{{ addslashes($item->name) }}', 'allergen')"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center py-4">No allergens found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between;">
    <span class="progga-page-info">Showing {{ $allergens->firstItem() ?? 0 }} to {{ $allergens->lastItem() ?? 0 }} of {{ $allergens->total() }} allergens</span>
    <div class="allergen-pagination">{{ $allergens->links('pagination::bootstrap-4') }}</div>
</div>
