@forelse($shifts as $shift)
<tr>
    <td>{{ $shift->name }}</td>
    <td>
        @if($shift->status)
            <span class="progga-badge progga-badge-success">Active</span>
        @else
            <span class="progga-badge progga-badge-danger">Inactive</span>
        @endif
    </td>
    <td>
    @can('shift-edit')
    <button type="button" class="progga-btn progga-btn-outline progga-btn-sm progga-btn-icon" onclick="editShiftAjax({{ $shift->id }}, '{{ $shift->name }}', {{ $shift->status }})"><i class="bi bi-pencil"></i></button>
    @endcan

    @can('shift-delete')
    <form action="{{ route('shift.destroy', $shift->id) }}" method="POST" style="display:inline;">
        @csrf @method('DELETE')
        <button type="submit" class="progga-btn progga-btn-danger progga-btn-sm progga-btn-icon" onclick="return confirm('Delete this Shift?')"><i class="bi bi-trash"></i></button>
    </form>
    @endcan
</td>
</tr>
@empty
<tr><td colspan="3" class="text-center text-muted">No shifts found</td></tr>
@endforelse
<tr><td colspan="3" class="text-center">{{ $shifts->links('pagination::bootstrap-4') }}</td></tr>
