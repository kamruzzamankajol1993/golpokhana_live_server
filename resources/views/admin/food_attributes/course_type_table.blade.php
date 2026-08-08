<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
            <tr>
                <th style="width:50px;">#</th>
                <th>Course Type</th>
                <th>Status</th>
                <th style="width:110px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($courseTypes as $key => $item)
            <tr>
                <td>{{ $courseTypes->firstItem() + $key }}</td>
                <td><strong>{{ $item->name }}</strong></td>
                <td>
                    <label class="progga-toggle">
                        <input type="checkbox" onchange="toggleStatus({{ $item->id }}, this, 'course-type')" {{ $item->status == 1 ? 'checked' : '' }} data-on="Active" data-off="Inactive">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">{{ $item->status == 1 ? 'Active' : 'Inactive' }}</span>
                    </label>
                </td>
                <td>
                    <div class="progga-table-actions">
                        <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick="editModal('course-type', {{ $item->id }}, '{{ addslashes($item->name) }}', {{ $item->status }})"><i class="bi bi-pencil"></i></button>
                        <button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick="normalDelete({{ $item->id }}, '{{ addslashes($item->name) }}', 'course-type')"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center py-4">No course types found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between;">
    <span class="progga-page-info">Showing {{ $courseTypes->firstItem() ?? 0 }} to {{ $courseTypes->lastItem() ?? 0 }} of {{ $courseTypes->total() }} course types</span>
    <div class="course-pagination">{{ $courseTypes->links('pagination::bootstrap-4') }}</div>
</div>
