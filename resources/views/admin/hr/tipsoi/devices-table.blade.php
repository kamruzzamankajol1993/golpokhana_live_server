<div class="table-responsive">
    <table class="table progga-table tipsoi-device-table mb-0">
        <thead>
            <tr>
                <th>SL</th>
                <th>Identifier</th>
                <th>Remote ID</th>
                <th>Location</th>
                <th>Type</th>
                <th>Connection</th>
                <th>Enrollment</th>
                <th>Allocated</th>
                <th>Last Communication</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($devices as $device)
                <tr>
                    <td>{{ ($devices->firstItem() ?? 1) + $loop->index }}</td>
                    <td class="device-description"><div class="fw-bold">{{ $device->identifier }}</div><div class="small text-muted">{{ $device->description ?: ($device->vendor_id ?: '-') }}</div></td>
                    <td>{{ $device->tipsoi_id ?: '-' }}</td>
                    <td>{{ $device->location ?: '-' }}</td>
                    <td>{{ $device->type ?: '-' }}</td>
                    <td>
                        <span class="tipsoi-device-connection {{ $device->connected ? 'online' : 'offline' }}"><span class="tipsoi-device-dot"></span>{{ $device->connected ? 'Online' : 'Offline' }}</span>
                    </td>
                    <td>@if($device->has_enrollment_feature)<span class="badge text-bg-primary">Supported</span>@else<span class="badge text-bg-light border text-dark">No</span>@endif</td>
                    <td>{{ number_format((int) $device->total_allocated) }}</td>
                    <td>{{ $device->last_communication_at?->format('d M Y h:i A') ?: ($device->last_seen ?: '-') }}</td>
                    <td><span class="badge text-bg-{{ $device->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($device->status ?: 'unknown') }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-hdd-network d-block fs-2 mb-2"></i>No device cached yet. Click “Refresh from Tipsoi”.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('admin.partials.custom_pagination', ['paginator' => $devices])
