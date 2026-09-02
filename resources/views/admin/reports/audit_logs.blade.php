@extends('admin.master.master')
@section('title', 'Audit Log — ' . $restaurantSettingName)

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Audit Log</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">Reports</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Audit Log</span>
            </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span class="progga-badge progga-badge-primary">Scope: {{ $auditScopeLabel }}</span>
            <a href="{{ route('reports.audit_logs.pdf', request()->query()) }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <a href="{{ route('reports.audit_logs.excel', request()->query()) }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </div>

    <div class="progga-card mb-3">
        <form method="GET" class="progga-card-body d-flex gap-2 flex-wrap align-items-end">
            @include('admin.reports.partials.branch_filter', [
                'branchSelectId' => 'auditBranchFilter',
                'wrapperClass' => 'progga-form-group mb-0'
            ])

            <div class="progga-form-group mb-0">
                <label class="progga-form-label">Action</label>
                <input class="progga-form-control" name="action" value="{{ request('action') }}" placeholder="order.updated, branch...">
            </div>
            <div class="progga-form-group mb-0">
                <label class="progga-form-label">User</label>
                <select class="progga-select" name="user_id">
                    <option value="">All Users</option>
                    @foreach($auditUsers as $auditUser)
                        <option value="{{ $auditUser->id }}" @selected((string)request('user_id') === (string)$auditUser->id)>
                            {{ $auditUser->name }}{{ $auditUser->user_id ? ' (' . $auditUser->user_id . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="progga-form-group mb-0">
                <label class="progga-form-label">From</label>
                <input type="date" class="progga-form-control" name="start_date" value="{{ request('start_date') }}">
            </div>
            <div class="progga-form-group mb-0">
                <label class="progga-form-label">To</label>
                <input type="date" class="progga-form-control" name="end_date" value="{{ request('end_date') }}">
            </div>
            <button class="progga-btn progga-btn-primary progga-btn-sm"><i class="bi bi-funnel"></i> Filter</button>
            <a class="progga-btn progga-btn-outline progga-btn-sm" href="{{ route('reports.audit_logs') }}">Reset</a>
        </form>
    </div>

    <div class="progga-card">
        <div class="progga-table-wrapper" style="overflow-x:auto;">
            <table class="progga-table" style="min-width:1200px;">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Branch</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Changes</th>
                        <th>Route / IP</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="white-space:nowrap;">{{ optional($log->created_at)->format('d M Y, h:i:s A') }}</td>
                        <td>{{ $log->branch->name ?? ($log->branch_id ? 'Branch #'.$log->branch_id : 'Global') }}</td>
                        <td>{{ $log->user->name ?? ($log->user_id ? 'User #'.$log->user_id : 'System / Public') }}</td>
                        <td><span class="progga-badge progga-badge-primary">{{ $log->action }}</span></td>
                        <td style="max-width:260px;white-space:normal;">{{ $log->description ?: '—' }}</td>
                        <td style="max-width:420px;white-space:normal;">
                            @php
                                $before = $log->before_values ?: [];
                                $after = $log->after_values ?: [];
                                $keys = collect(array_keys($before))->merge(array_keys($after))->unique()->values();
                            @endphp
                            @if($keys->isEmpty())
                                <span class="text-muted">—</span>
                            @else
                                @foreach($keys->take(12) as $key)
                                    <div style="font-size:11px;margin-bottom:2px;">
                                        <strong>{{ $key }}:</strong>
                                        <span class="text-muted">{{ is_scalar($before[$key] ?? null) || is_null($before[$key] ?? null) ? ($before[$key] ?? '∅') : json_encode($before[$key]) }}</span>
                                        <span>→</span>
                                        <span>{{ is_scalar($after[$key] ?? null) || is_null($after[$key] ?? null) ? ($after[$key] ?? '∅') : json_encode($after[$key]) }}</span>
                                    </div>
                                @endforeach
                                @if($keys->count() > 12)<div class="text-muted" style="font-size:11px;">+ {{ $keys->count() - 12 }} more fields</div>@endif
                            @endif
                        </td>
                        <td style="font-size:11px;">
                            <div>{{ $log->route_name ?: $log->http_method }}</div>
                            <div class="text-muted">{{ $log->ip_address ?: '—' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted">No audit events found for this scope/filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $logs->links() }}</div>
    </div>
</main>
@endsection
