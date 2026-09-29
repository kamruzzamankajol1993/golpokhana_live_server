@extends('admin.master.master')
@section('title', 'Offline Sync Logs — ' . ($restaurantSettingName ?? 'Restaurant'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Offline Sync Logs</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <a href="{{ route('offline-pos-devices.index') }}" class="progga-breadcrumb-item">Offline POS Devices</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Sync Logs</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('offline-pos-devices.index') }}" class="progga-btn progga-btn-outline">
                <i class="bi bi-pc-display"></i> Devices
            </a>
            <a href="{{ route('offline-sync-logs.index') }}" class="progga-btn progga-btn-primary">
                <i class="bi bi-arrow-clockwise"></i> Refresh Logs
            </a>
        </div>
    </div>

    @unless($tableReady)
        <div class="alert alert-warning">
            <strong>Sync log table is not available yet.</strong>
            Run <code>php artisan migrate</code> once, then refresh this page.
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="progga-stat-card h-100">
                    <div class="progga-stat-icon primary"><i class="bi bi-list-ul"></i></div>
                    <div class="progga-stat-info"><div class="progga-stat-label">Total Logs</div><div class="progga-stat-value">{{ number_format($stats['total']) }}</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="progga-stat-card h-100">
                    <div class="progga-stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="progga-stat-info"><div class="progga-stat-label">Success</div><div class="progga-stat-value">{{ number_format($stats['success']) }}</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="progga-stat-card h-100">
                    <div class="progga-stat-icon warning"><i class="bi bi-exclamation-circle-fill"></i></div>
                    <div class="progga-stat-info"><div class="progga-stat-label">Partial</div><div class="progga-stat-value">{{ number_format($stats['partial']) }}</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="progga-stat-card h-100">
                    <div class="progga-stat-icon danger"><i class="bi bi-x-circle-fill"></i></div>
                    <div class="progga-stat-info"><div class="progga-stat-label">Failed</div><div class="progga-stat-value">{{ number_format($stats['failed']) }}</div></div>
                </div>
            </div>
        </div>

        <div class="progga-card mb-4">
            <div class="progga-card-header">
                <div class="progga-card-title"><i class="bi bi-funnel me-2"></i>Filter Logs</div>
            </div>
            <div class="progga-card-body">
                <form method="GET" action="{{ route('offline-sync-logs.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="progga-form-label">Search</label>
                        <input type="text" name="q" value="{{ request('q') }}" class="progga-form-control" placeholder="Order no, UUID, error...">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="progga-form-label">Device</label>
                        <select name="device_id" class="progga-form-control">
                            <option value="">All devices</option>
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}" @selected((string)request('device_id') === (string)$device->id)>{{ $device->device_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="progga-form-label">Status</label>
                        <select name="status" class="progga-form-control">
                            <option value="">All status</option>
                            <option value="success" @selected(request('status') === 'success')>Success</option>
                            <option value="partial" @selected(request('status') === 'partial')>Partial</option>
                            <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="progga-form-label">Type</label>
                        <select name="type" class="progga-form-control">
                            <option value="">All types</option>
                            @foreach($types as $logType)
                                <option value="{{ $logType }}" @selected(request('type') === $logType)>{{ $logType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="progga-form-label">Date Range</label>
                        <div class="d-flex gap-2">
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="progga-form-control">
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="progga-form-control">
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button class="progga-btn progga-btn-primary"><i class="bi bi-search"></i> Apply Filter</button>
                        <a href="{{ route('offline-sync-logs.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-x-lg"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <form id="mainSyncLogBulkDeleteForm" method="POST" action="{{ route('offline-sync-logs.bulk-delete') }}" class="m-0">
            @csrf
            @method('DELETE')
            <div class="progga-card">
                <div class="progga-card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div>
                        <div class="progga-card-title">Sync Activity</div>
                        <small class="text-muted">25 per page. Equivalent successful auto-sync requests are stored once; every Partial/Failed request is kept.</small>
                    </div>
                    <button type="submit" class="progga-btn progga-btn-outline" id="mainDeleteSelectedSyncLogs" disabled>
                        <i class="bi bi-trash3"></i> Delete Selected
                    </button>
                </div>
                <div class="progga-card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:46px;" class="text-center"><input type="checkbox" class="form-check-input" id="mainSelectAllSyncLogs" aria-label="Select all logs on this page"></th>
                                    <th style="min-width:160px;">Time</th>
                                    <th style="min-width:170px;">Device</th>
                                    <th style="min-width:170px;">Type</th>
                                    <th>Status</th>
                                    <th style="min-width:420px;">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($logs as $log)
                                @php
                                    $badge = match($log->status) {
                                        'success' => 'bg-success',
                                        'partial' => 'bg-warning text-dark',
                                        default => 'bg-danger',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-center"><input type="checkbox" class="form-check-input js-main-sync-log-check" name="log_ids[]" value="{{ $log->id }}" aria-label="Select sync log {{ $log->id }}"></td>
                                    <td>
                                        <strong>{{ $log->created_at?->format('d M Y') }}</strong><br>
                                        <small class="text-muted">{{ $log->created_at?->format('h:i:s A') }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $log->device?->device_name ?? 'Unknown device' }}</strong>
                                        @if($log->device?->device_uuid)
                                            <br><small class="text-muted font-monospace">{{ \Illuminate\Support\Str::limit($log->device->device_uuid, 24) }}</small>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-secondary">{{ $log->type }}</span></td>
                                    <td><span class="badge {{ $badge }}">{{ strtoupper($log->status) }}</span></td>
                                    <td style="white-space:normal;word-break:break-word;">
                                        <small>{{ $log->message ?: 'No additional details.' }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-5 text-muted">No sync logs found for the selected filter.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($logs->hasPages())
                    <div class="p-3 border-top">{{ $logs->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </form>
    @endunless
</main>
@endsection

@section('script')
<script>
(function(){
    const form = document.getElementById('mainSyncLogBulkDeleteForm');
    const selectAll = document.getElementById('mainSelectAllSyncLogs');
    const deleteBtn = document.getElementById('mainDeleteSelectedSyncLogs');
    if(!form || !deleteBtn) return;

    const checks = () => Array.from(form.querySelectorAll('.js-main-sync-log-check'));
    const refresh = () => {
        const rows = checks();
        const selected = rows.filter(x => x.checked).length;
        deleteBtn.disabled = selected === 0;
        if(selectAll){
            selectAll.checked = rows.length > 0 && selected === rows.length;
            selectAll.indeterminate = selected > 0 && selected < rows.length;
        }
    };

    selectAll?.addEventListener('change', function(){ checks().forEach(x => x.checked = this.checked); refresh(); });
    form.addEventListener('change', e => { if(e.target.classList.contains('js-main-sync-log-check')) refresh(); });
    form.addEventListener('submit', function(e){
        const selected = checks().filter(x => x.checked).length;
        if(!selected){ e.preventDefault(); return; }
        e.preventDefault();
        const submitDelete = () => form.submit();
        if(window.Swal){
            Swal.fire({
                title:'Delete selected sync logs?',
                text:selected + ' log(s) will be permanently deleted.',
                icon:'warning',
                showCancelButton:true,
                confirmButtonText:'Yes, delete',
                confirmButtonColor:'#dc3545'
            }).then(r => { if(r.isConfirmed) submitDelete(); });
        } else if(confirm('Delete ' + selected + ' selected sync log(s)?')) {
            submitDelete();
        }
    });
    refresh();
})();
</script>
@endsection

