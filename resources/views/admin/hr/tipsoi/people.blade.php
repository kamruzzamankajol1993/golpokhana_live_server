@extends('admin.master.master')

@section('title', 'TIPSOI People Sync — ' . $restaurantSettingName)

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">TIPSOI People Sync</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item">Human Resources</span><span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item">TIPSOI</span><span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">People Sync</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @canany(['employee-create','employee-edit'])
                <form action="{{ route('hr.tipsoi.people.refresh') }}" method="POST">@csrf<button class="progga-btn progga-btn-outline"><i class="bi bi-arrow-repeat"></i> Refresh Remote People</button></form>
                <button type="button" class="progga-btn progga-btn-outline" id="tipsoiPullAllPeople"><i class="bi bi-cloud-arrow-down"></i> Pull All to HR</button>
                @endcanany
                @can('employee-create')<a href="{{ route('hr.employees.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-person-plus"></i> Add Employee</a>@endcan
            </div>
        </div>

        @if(!$attendanceSetting?->tipsoi_enabled)
            <div class="alert alert-warning mb-3">TIPSOI is disabled. Enable it from <a href="{{ route('hr.settings.index', ['tab'=>'attendance']) }}">HR Settings → Attendance</a>.</div>
        @endif

        <div class="hr-stat-grid">
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-cloud-check"></i></div><div><div class="hr-stat-value">{{ number_format($remoteTotal) }}</div><div class="hr-stat-label">Cached TIPSOI People</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-person-exclamation"></i></div><div><div class="hr-stat-value">{{ number_format($remoteUnmatchedTotal) }}</div><div class="hr-stat-label">Not Yet Linked to HR</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-toggles"></i></div><div><div class="hr-stat-value" style="font-size:20px">{{ strtoupper((string)($attendanceSetting?->tipsoi_mode ?: 'live')) }}</div><div class="hr-stat-label">Active Mode</div></div></div>
        </div>

        <form action="{{ route('hr.tipsoi.employees.push') }}" method="POST" id="tipsoiBatchPeopleForm">@csrf<input type="hidden" name="scope" value="selected">
            <div class="hr-card mb-3">
                <div class="hr-card-header">
                    <div><div class="hr-card-title">HR Employees</div><div class="hr-card-subtitle">Create or update selected employees through the documented Batch Person Create API; single-person fallback is automatic if the remote server rejects a batch.</div></div>
                    @canany(['employee-create','employee-edit'])
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="tipsoiSelectAllLocal"> <span class="form-check-label">Select visible</span></label>
                        <button class="progga-btn progga-btn-primary progga-btn-sm"><i class="bi bi-cloud-arrow-up"></i> Push Selected (Batch)</button>
                    </div>
                    @endcanany
                </div>
                <div class="hr-card-body border-bottom">
                    <form></form>
                    <div class="hr-filter-grid">
                        <div class="hr-search"><i class="bi bi-search"></i><input type="text" id="tipsoiLocalSearch" class="progga-form-control" value="{{ request('search') }}" placeholder="Name, employee code, identifier, RFID"></div>
                        <div><label class="progga-form-label">Sync Status</label><select id="tipsoiLocalStatus" class="progga-select"><option value="">All</option><option value="synced" @selected(request('sync_status')==='synced')>Synced</option><option value="failed" @selected(request('sync_status')==='failed')>Failed</option></select></div>
                        <button type="button" id="tipsoiLocalFilter" class="progga-btn progga-btn-outline"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="{{ route('hr.tipsoi.people.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                    </div>
                </div>
                <div id="tipsoiLocalTableContainer">@include('admin.hr.tipsoi.people-local-table', ['employees'=>$employees])</div>
            </div>
        </form>

        <div class="hr-card">
            <div class="hr-card-header"><div><div class="hr-card-title">TIPSOI People Not Yet in HR</div><div class="hr-card-subtitle">Remote persons are cached from Tipsoi. Create the HR employee and complete department/designation details before saving.</div></div></div>
            <div class="hr-card-body border-bottom">
                <form method="GET" action="{{ route('hr.tipsoi.people.index') }}" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="table" value="remote"><input type="text" name="remote_search" class="progga-form-control" style="max-width:420px" value="{{ request('remote_search') }}" placeholder="Remote name, identifier, RFID"><button class="progga-btn progga-btn-outline">Search</button>
                </form>
            </div>
            @include('admin.hr.tipsoi.people-remote-table', ['remotePeople'=>$remotePeople])
        </div>
    </div>
</main>
@endsection

@section('script')
@include('admin.hr.shared.plugins')
<script>
$(function(){
    $('#tipsoiSelectAllLocal').on('change', function(){ $('.tipsoi-person-check').prop('checked', this.checked); });
    $('#tipsoiLocalFilter').on('click', function(){
        const u = new URL("{{ route('hr.tipsoi.people.index') }}");
        if ($('#tipsoiLocalSearch').val()) u.searchParams.set('search',$('#tipsoiLocalSearch').val());
        if ($('#tipsoiLocalStatus').val()) u.searchParams.set('sync_status',$('#tipsoiLocalStatus').val());
        window.location = u.toString();
    });
    $('#tipsoiPullAllPeople').on('click', function(){
        Swal.fire({title:'Pull all Tipsoi people into HR?',text:'Matched employees will update and unmatched people will be created as basic HR employees. You can complete their HR details afterward.',icon:'question',showCancelButton:true,confirmButtonText:'Pull All'}).then(function(r){
            if(!r.isConfirmed) return;
            Swal.fire({title:'Pulling employees...',allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
            $.post("{{ route('hr.tipsoi.employees.pull') }}",{_token:"{{ csrf_token() }}"}).done(function(res){Swal.fire('Complete',res.message||'People pulled.','success').then(()=>window.location.reload());}).fail(function(xhr){Swal.fire('Failed',xhr.responseJSON?.message||'Pull failed.','error');});
        });
    });
});
</script>
@endsection
