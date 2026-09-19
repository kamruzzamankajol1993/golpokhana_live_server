@extends('admin.master.master')
@section('title', 'Offline POS Devices - ' . ($restaurantSettingName ?? 'Restaurant'))

@section('body')
<main class="progga-content">
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Offline POS Devices</h4>
            <p class="text-muted mb-0">Manage offline POS terminals. Each PC has its own unique device key.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('offline-pos-devices.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label">Device Name</label>
                    <input type="text" name="device_name" class="form-control" required maxlength="255" placeholder="e.g. Front Counter POS 1">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100">Create Device</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Device Key</th><th>Device ID</th><th>Status</th><th>Last Seen</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($devices as $device)
                    <tr>
                        <td>{{ $device->device_name }}</td>
                        <td><code>{{ $device->device_key }}</code></td><td><code>{{ $device->device_uuid }}</code></td>
                        <td>{{ $device->status ? 'Active' : 'Inactive' }}</td>
                        <td>{{ $device->last_seen_at?->format('Y-m-d H:i:s') ?? 'Never' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('offline-pos-devices.toggle', $device) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $device->status ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('offline-pos-devices.destroy', $device) }}" class="d-inline" data-swal-title="Remove device?" data-swal-confirm="This offline POS device binding will be removed." data-swal-confirm-text="Yes, remove">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No offline POS device found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</main>
@endsection
