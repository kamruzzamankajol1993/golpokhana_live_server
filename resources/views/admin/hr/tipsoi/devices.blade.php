@extends('admin.master.master')

@section('title', 'TIPSOI Device List — ' . $restaurantSettingName)

@section('css')
    @include('admin.hr.shared.styles')
    <style>
        .tipsoi-device-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:12px;color:var(--progga-text-muted)}
        .tipsoi-device-endpoint{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;background:rgba(33,53,42,.06);padding:4px 8px;border-radius:7px;color:var(--progga-primary);font-weight:800}
        .tipsoi-device-table td,.tipsoi-device-table th{vertical-align:middle;white-space:nowrap}.tipsoi-device-table .device-description{white-space:normal;min-width:180px}
        .tipsoi-device-connection{display:inline-flex;align-items:center;gap:6px;font-weight:800;font-size:12px}.tipsoi-device-dot{width:8px;height:8px;border-radius:50%;display:inline-block;background:#8a9490}.tipsoi-device-connection.online .tipsoi-device-dot{background:#198754}
        @media(max-width:767px){.tipsoi-device-actions{width:100%;display:grid!important;grid-template-columns:1fr}.tipsoi-device-actions .progga-btn{width:100%;justify-content:center}}
    </style>
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">TIPSOI Device List</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item">Human Resources</span>
                    <span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item">TIPSOI</span><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Device List</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap tipsoi-device-actions">
                <a href="{{ route('hr.attendance.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-calendar-check"></i> Attendance</a>
                @canany(['attendance-edit','attendance-create','hr-setting-update'])
                    <button type="button" class="progga-btn progga-btn-primary" id="tipsoiRefreshDevices"><i class="bi bi-arrow-repeat"></i> Refresh from Tipsoi</button>
                @endcanany
            </div>
        </div>

        @if(!$attendanceSetting?->tipsoi_enabled)
            <div class="alert alert-warning mb-3"><i class="bi bi-exclamation-triangle me-1"></i> Tipsoi is disabled. Enable it from <a href="{{ route('hr.settings.index', ['tab' => 'attendance']) }}">HR Settings → Attendance</a>.</div>
        @endif

        <div class="hr-stat-grid">
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-hdd-network"></i></div><div><div class="hr-stat-value">{{ $deviceSummary['total'] }}</div><div class="hr-stat-label">Cached Devices</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-wifi"></i></div><div><div class="hr-stat-value">{{ $deviceSummary['online'] }}</div><div class="hr-stat-label">Online</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-wifi-off"></i></div><div><div class="hr-stat-value">{{ $deviceSummary['offline'] }}</div><div class="hr-stat-label">Offline</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-fingerprint"></i></div><div><div class="hr-stat-value">{{ $deviceSummary['enrollment'] }}</div><div class="hr-stat-label">Enrollment Supported</div></div></div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Attendance Devices</div>
                    <div class="tipsoi-device-meta mt-1">
                        <span>Mode: <strong>{{ strtoupper((string) ($attendanceSetting?->tipsoi_mode ?: 'live')) }}</strong></span>
                        <span>Refresh the list to get the latest device connection and enrollment status from Tipsoi.</span>
                    </div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="hr-filter-grid">
                    <div class="hr-search"><i class="bi bi-search"></i><input type="text" id="tipsoiDeviceSearch" class="progga-form-control" value="{{ request('search') }}" placeholder="Identifier, location, description, vendor"></div>
                    <div><label class="progga-form-label">Status</label><select id="tipsoiDeviceStatus" class="progga-select"><option value="">All Status</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></div>
                    <div><label class="progga-form-label">Connection</label><select id="tipsoiDeviceConnection" class="progga-select"><option value="">All Connection</option><option value="online" @selected(request('connection')==='online')>Online</option><option value="offline" @selected(request('connection')==='offline')>Offline</option></select></div>
                    <button type="button" id="tipsoiDeviceReset" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                </div>
            </div>
        </div>

        <div class="hr-card" id="tipsoiDeviceTableContainer">
            @include('admin.hr.tipsoi.devices-table', ['devices' => $devices])
        </div>
    </div>
</main>
@endsection

@section('script')
    @include('admin.hr.shared.plugins')
    <script>
    $(function () {
        let timer = null;
        let activeRequest = null;

        function loadDevices(page) {
            const container = $('#tipsoiDeviceTableContainer').addClass('hr-table-loading');
            if (activeRequest && activeRequest.readyState !== 4) activeRequest.abort();
            const request = $.get("{{ route('hr.tipsoi.devices.index') }}", {
                page: page || 1,
                search: $('#tipsoiDeviceSearch').val(),
                status: $('#tipsoiDeviceStatus').val(),
                connection: $('#tipsoiDeviceConnection').val()
            });
            activeRequest = request;
            request.done(function (html) { container.html(html); })
                .fail(function (xhr, statusText) {
                    if (statusText === 'abort') return;
                    Swal.fire('Error', xhr.responseJSON?.message || 'Failed to load attendance devices.', 'error');
                })
                .always(function () {
                    if (activeRequest === request) { activeRequest = null; container.removeClass('hr-table-loading'); }
                });
        }

        $('#tipsoiDeviceSearch').on('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { loadDevices(1); }, 350);
        });
        $('#tipsoiDeviceStatus,#tipsoiDeviceConnection').on('change', function () { loadDevices(1); });
        $('#tipsoiDeviceReset').on('click', function () {
            $('#tipsoiDeviceSearch').val('');
            $('#tipsoiDeviceStatus,#tipsoiDeviceConnection').val('');
            loadDevices(1);
        });
        $(document).on('click', '#tipsoiDeviceTableContainer .report-page-link:not(.disabled)', function (e) {
            e.preventDefault();
            loadDevices(new URL(this.href).searchParams.get('page') || 1);
        });

        $('#tipsoiRefreshDevices').on('click', function () {
            const button = $(this).prop('disabled', true);
            Swal.fire({title:'Refreshing Tipsoi devices...',allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
            $.post("{{ route('hr.tipsoi.devices.refresh') }}", {_token:"{{ csrf_token() }}"})
                .done(function (response) {
                    Swal.fire('Device Refresh Complete', response.message || 'Tipsoi devices refreshed.', 'success').then(function(){ window.location.reload(); });
                })
                .fail(function (xhr) {
                    Swal.fire('Device Refresh Failed', xhr.responseJSON?.message || 'Unable to refresh Tipsoi devices.', 'error');
                })
                .always(function () { button.prop('disabled', false); });
        });
    });
    </script>
@endsection
