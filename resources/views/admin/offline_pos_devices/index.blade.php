@extends('admin.master.master')
@section('title', 'Offline POS Devices - ' . ($restaurantSettingName ?? 'Restaurant'))

@section('body')
<main class="progga-content">
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Offline POS Devices</h4>
            <p class="text-muted mb-0">Each device is permanently bound to one branch. In All Branches, choose the target branch in the form below.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('offline-pos-devices.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-12">
                    @include('admin.branch.partials.form_branch_selector', ['label' => 'Device Branch'])
                </div>
                <div class="col-md-8">
                    <label class="form-label">Device Name</label>
                    <input type="text" name="name" class="form-control" required maxlength="120" placeholder="e.g. Front Counter POS 1">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100">Create & Bind Device</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Branch</th><th>Device ID</th><th>Status</th><th>Last Seen</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($devices as $device)
                    <tr>
                        <td>{{ $device->name }}</td>
                        <td>{{ $device->branch->name ?? 'N/A' }}</td>
                        <td><code>{{ $device->device_uuid }}</code></td>
                        <td>{{ $device->is_active ? 'Active' : 'Inactive' }}</td>
                        <td>{{ $device->last_seen_at?->format('Y-m-d H:i:s') ?? 'Never' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('offline-pos-devices.toggle', $device) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="branch_id" value="{{ $device->branch_id }}"><button class="btn btn-sm btn-outline-secondary">{{ $device->is_active ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('offline-pos-devices.destroy', $device) }}" class="d-inline" data-swal-title="Remove device?" data-swal-confirm="This offline POS device binding will be removed." data-swal-confirm-text="Yes, remove">@csrf @method('DELETE')<input type="hidden" name="branch_id" value="{{ $device->branch_id }}"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No device has been bound to this branch yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</main>
@endsection
