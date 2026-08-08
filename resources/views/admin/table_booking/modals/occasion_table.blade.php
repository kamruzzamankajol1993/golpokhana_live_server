<table class="progga-table table-sm">
    <thead>
        <tr>
            <th>Name</th>
            <th>Status</th>
            <th style="width: 80px; text-align:right;">Actions</th>
        </tr>
    </thead>
    <tbody>
        @forelse($occasions as $occasion)
        <tr>
            <td>{{ $occasion->name }}</td>
            <td>
                @if($occasion->status == 'Active')
                    <span class="progga-badge progga-badge-success" style="font-size: 10px;">Active</span>
                @else
                    <span class="progga-badge progga-badge-danger" style="font-size: 10px;">Inactive</span>
                @endif
            </td>
            <td style="text-align:right;">
                <button type="button" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick="editOccasion({{ $occasion->id }}, '{{ $occasion->name }}', '{{ $occasion->status }}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick="deleteOccasion({{ $occasion->id }})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="3" class="text-center py-3 text-muted">No occasions found.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="mt-2 occasion-pagination" style="display: flex; justify-content: flex-end;">
    {{ $occasions->links('pagination::bootstrap-4') }}
</div>
