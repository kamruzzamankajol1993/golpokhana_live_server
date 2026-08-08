@forelse($zones as $zone)
<tr>
    <td>{{ $zone->name }}</td>
    <td>
        @if($zone->status)
            <span class="progga-badge progga-badge-success">Active</span>
        @else
            <span class="progga-badge progga-badge-danger">Inactive</span>
        @endif
    </td>
    <td>
    @can('zone-edit')
    <button type="button" class="progga-btn progga-btn-outline progga-btn-sm progga-btn-icon" onclick="editZoneAjax({{ $zone->id }}, '{{ $zone->name }}', {{ $zone->status }})"><i class="bi bi-pencil"></i></button>
    @endcan

    @can('zone-delete')
    <form action="{{ route('zone.destroy', $zone->id) }}" method="POST" style="display:inline;">
        @csrf @method('DELETE')
        <button type="submit" class="progga-btn progga-btn-danger progga-btn-sm progga-btn-icon" onclick="return confirm('Delete this Zone?')"><i class="bi bi-trash"></i></button>
    </form>
    @endcan
</td>
</tr>
@empty
<tr><td colspan="3" class="text-center text-muted">No zones found</td></tr>
@endforelse
<tr><td colspan="3" class="text-center">{{ $zones->links('pagination::bootstrap-4') }}</td></tr>
