<div class="progga-table-wrapper" style="border:none;border-radius:0;">
    <table class="progga-table">
        <thead>
            <tr>
                <th style="width:50px;">#</th>
                <th>Waiter</th>
                <th>Phone</th>
                <th>Employee ID</th>
                <th>Zone</th>
                <th>Shift</th>
                <th>Status</th>
                <th style="width:120px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($waiters as $index => $waiter)
            <tr>
                <td>{{ $waiters->firstItem() + $index }}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="{{ $waiter->image ? asset($waiter->image) : 'https://ui-avatars.com/api/?name='.urlencode($waiter->name).'&background=21352a&color=d5aa65&size=68&bold=true' }}" style="width:36px;height:36px;border-radius:50%;flex-shrink:0;object-fit:cover;" alt="Image">
                        <div>
                            <div style="font-weight:600;color:var(--progga-primary);">{{ $waiter->name }}</div>
                            <div style="font-size:12px;color:var(--progga-text-muted);">{{ $waiter->email ?? 'No Email' }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $waiter->phone }}</td>
                <td><span class="progga-badge progga-badge-neutral">{{ $waiter->employee_id }}</span></td>
                <td>{{ $waiter->zone->name ?? 'N/A' }}</td>
                <td>
                    @if(optional($waiter->shift)->name == 'Morning')
                        <span class="progga-badge progga-badge-success">Morning</span>
                    @elseif(optional($waiter->shift)->name == 'Evening')
                        <span class="progga-badge progga-badge-info">Evening</span>
                    @else
                        <span class="progga-badge progga-badge-warning">{{ $waiter->shift->name ?? 'N/A' }}</span>
                    @endif
                </td>
                <td>
    <label class="progga-toggle">
        <input type="checkbox" class="waiter-status-toggle" data-id="{{ $waiter->id }}" {{ $waiter->status ? 'checked' : '' }}>
        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
        <span class="progga-toggle-label">{{ $waiter->status ? 'Active' : 'Inactive' }}</span>
    </label>
</td>
                <td>
    <div class="progga-table-actions">
        @can('waiter-edit')
        <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Edit"
                onclick="editWaiterData({{ $waiter->id }}, '{{ $waiter->name }}', '{{ $waiter->phone }}', '{{ $waiter->email }}', '{{ $waiter->employee_id }}', '{{ $waiter->zone_id }}', '{{ $waiter->shift_id }}', '{{ $waiter->join_date }}', {{ $waiter->status }}, '{{ $waiter->notes }}')">
            <i class="bi bi-pencil"></i>
        </button>
        @endcan

        @can('waiter-delete')
        <form action="{{ route('waiter.destroy', $waiter->id) }}" method="POST" style="display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick="return confirm('Are you sure you want to delete {{ $waiter->name }}?')">
                <i class="bi bi-trash"></i>
            </button>
        </form>
        @endcan
    </div>
</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">No waiters found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between; padding: 15px;">
    <span class="progga-page-info">Showing {{ $waiters->firstItem() ?? 0 }}–{{ $waiters->lastItem() ?? 0 }} of {{ $waiters->total() }} waiters</span>
    <div class="progga-pagination custom-pagination-wrapper">
        {{ $waiters->links('pagination::bootstrap-5') }}
    </div>
</div>
