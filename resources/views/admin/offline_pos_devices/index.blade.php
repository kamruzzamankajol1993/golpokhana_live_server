@extends('admin.master.master')
@section('title', 'Offline POS Devices — ' . ($restaurantSettingName ?? 'Restaurant'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Offline POS Devices</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Offline POS Devices</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="progga-stat-card">
                <div class="progga-stat-icon primary"><i class="bi bi-pc-display"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Total Devices</div>
                    <div class="progga-stat-value">{{ $devices->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="progga-stat-card">
                <div class="progga-stat-icon success"><i class="bi bi-wifi"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Active Devices</div>
                    <div class="progga-stat-value">{{ $devices->where('status',1)->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="progga-stat-card">
                <div class="progga-stat-icon warning"><i class="bi bi-key"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Secure Bindings</div>
                    <div class="progga-stat-value">{{ $devices->whereNotNull('device_key')->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header">
            <div class="progga-card-title"><i class="bi bi-plus-circle me-2"></i>Create Offline POS Device</div>
        </div>
        <div class="progga-card-body">
            <form method="POST" action="{{ route('offline-pos-devices.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="progga-form-label">Device Name</label>
                    <input type="text" name="device_name" class="progga-form-control" required maxlength="255" placeholder="Example: Front Counter POS 01">
                </div>
                <div class="col-md-4">
                    <button class="progga-btn progga-btn-primary w-100"><i class="bi bi-plus-lg"></i> Create Device</button>
                </div>
            </form>
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header">
            <div class="progga-card-title">Device List</div>
        </div>
        <div class="progga-card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Device Key</th>
                            <th>Device ID</th>
                            <th>Status</th>
                            <th>Last Sync</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td>
                                <strong>{{ $device->device_name }}</strong>
                                <br><small class="text-muted">Offline terminal</small>
                            </td>
                            <td><code>{{ $device->device_key }}</code></td>
                            <td><small class="text-muted">{{ $device->device_uuid }}</small></td>
                            <td>
                                @if($device->status)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $device->last_seen_at?->format('d M Y h:i A') ?? 'Never connected' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('offline-pos-devices.toggle', $device) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="progga-btn progga-btn-outline progga-btn-sm">{{ $device->status ? 'Disable' : 'Enable' }}</button>
                                </form>
                                <form method="POST" action="{{ route('offline-pos-devices.destroy', $device) }}" class="d-inline" data-swal-title="Remove device?" data-swal-confirm="This offline POS device will be removed." data-swal-confirm-text="Yes, remove">
                                    @csrf @method('DELETE')
                                    <button class="progga-btn progga-btn-danger progga-btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No offline POS device configured.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
@endsection
