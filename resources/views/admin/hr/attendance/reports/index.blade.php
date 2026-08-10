@extends('admin.master.master')

@section('title', 'Attendance Reports — ' . ($restaurantSettingName ?? 'TableTrack RMS'))

@section('css')
    @include('admin.hr.shared.styles')
    <style>
        .attendance-report-actions{display:flex;gap:8px;flex-wrap:wrap}
        .report-filter-grid{display:grid;grid-template-columns:1.1fr 1fr 1.3fr 1.2fr 1.4fr auto;gap:14px;align-items:end}
        .report-summary-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(105px,1fr));gap:10px;padding:14px 18px;border-bottom:1px solid var(--progga-border-light);background:rgba(213,170,101,.04)}
        .report-mini-stat{padding:10px;border:1px solid var(--progga-border-light);border-radius:10px;background:#fff;text-align:center}
        .report-mini-stat strong{font-size:18px;color:var(--progga-primary);display:block}.report-mini-stat span{font-size:10px;text-transform:uppercase;color:var(--progga-text-muted);font-weight:700}
        .attendance-status-dot{display:inline-flex;align-items:center;justify-content:center;min-width:76px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700}

        .attendance-matrix-legend{display:flex;gap:8px 14px;flex-wrap:wrap;padding:10px 18px;border-bottom:1px solid var(--progga-border-light);font-size:11px;color:var(--progga-text-muted);background:#fff}
        .attendance-matrix-legend span{display:inline-flex;align-items:center;gap:4px}.attendance-matrix-legend strong{color:var(--progga-primary)}
        .attendance-matrix-wrap{overflow:auto;max-width:100%}
        .attendance-month-matrix{min-width:1280px;border-collapse:separate;border-spacing:0}
        .attendance-month-matrix th,.attendance-month-matrix td{padding:8px 5px;text-align:center;white-space:nowrap}
        .attendance-month-matrix .matrix-sticky{position:sticky;left:0;z-index:4;background:#fff;text-align:left;min-width:190px;max-width:190px}
        .attendance-month-matrix .matrix-sticky-2{position:sticky;left:190px;z-index:3;background:#fff;text-align:left;min-width:120px;max-width:120px}
        .attendance-month-matrix thead .matrix-sticky,.attendance-month-matrix thead .matrix-sticky-2{background:var(--progga-primary);color:#fff;z-index:7}
        .attendance-month-matrix tbody tr:hover .matrix-sticky,.attendance-month-matrix tbody tr:hover .matrix-sticky-2{background:#faf5ee}
        .attendance-month-matrix .matrix-day-head,.attendance-month-matrix .matrix-day{min-width:34px;width:34px}
        .attendance-month-matrix .matrix-total{min-width:39px;width:39px}.attendance-month-matrix .matrix-time{min-width:64px}
        .status-cell-present{background:var(--progga-success-bg);color:var(--progga-success);font-weight:800}
        .status-cell-late{background:var(--progga-warning-bg);color:var(--progga-warning);font-weight:800}
        .status-cell-absent{background:var(--progga-danger-bg);color:var(--progga-danger);font-weight:800}
        .status-cell-half_day{background:var(--progga-info-bg);color:var(--progga-info);font-weight:800}
        .status-cell-leave{background:rgba(102,51,153,.09);color:#663399;font-weight:800}
        .status-cell-off_day{background:rgba(0,0,0,.035);color:var(--progga-text-muted)}
        .status-cell-not_marked{background:#fff7e8;color:#a86418;font-weight:800}
        .status-cell-not_applicable,.status-cell-future{color:var(--progga-text-light);background:#fafafa}
        .status-present{background:var(--progga-success-bg);color:var(--progga-success)}.status-late{background:var(--progga-warning-bg);color:var(--progga-warning)}.status-absent{background:var(--progga-danger-bg);color:var(--progga-danger)}.status-half_day{background:var(--progga-info-bg);color:var(--progga-info)}.status-leave{background:rgba(102,51,153,.09);color:#663399}.status-off_day,.status-not_applicable,.status-future,.status-not_marked{background:rgba(0,0,0,.05);color:var(--progga-text-muted)}
        @media(max-width:1199px){.report-filter-grid{grid-template-columns:repeat(3,1fr)}}
        @media(max-width:767px){.report-filter-grid{grid-template-columns:1fr}.report-summary-strip{grid-template-columns:repeat(2,1fr)}}
    </style>
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Attendance Reports</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span><a href="{{ route('hr.attendance.index') }}" class="progga-breadcrumb-item">Attendance</a>
                    <span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span>
                </div>
            </div>
            <div class="attendance-report-actions">
                <a href="{{ route('hr.attendance.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-left"></i> Attendance</a>
                <button type="button" id="attendancePdfBtn" class="progga-btn progga-btn-primary"><i class="bi bi-file-earmark-pdf"></i> Open PDF</button>
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div><div class="hr-card-title">Report Filters</div><div class="hr-card-subtitle">View all employees for a month or one employee's full monthly attendance.</div></div>
            </div>
            <div class="hr-card-body">
                <div class="report-filter-grid">
                    <div><label class="progga-form-label">Report Type</label><select id="attendanceReportType" class="report-select2" data-search="false" data-allow-clear="false"><option value="monthly_summary">All Employees — Monthly</option><option value="employee_detail">Employee-wise Monthly</option></select></div>
                    <div><label class="progga-form-label">Month</label><input type="month" id="attendanceReportMonth" class="progga-form-control" value="{{ $month }}"></div>
                    <div id="employeeFilterWrap" style="display:none"><label class="progga-form-label">Employee</label><select id="attendanceReportEmployee" class="report-select2" data-placeholder="Select employee"><option value=""></option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_code }} — {{ $employee->name }}</option>@endforeach</select></div>
                    <div id="departmentFilterWrap"><label class="progga-form-label">Department</label><select id="attendanceReportDepartment" class="report-select2"><option value="">All Departments</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></div>
                    <div id="reportSearchWrap"><label class="progga-form-label">Search Employee</label><input type="text" id="attendanceReportSearch" class="progga-form-control" placeholder="Name or employee code"></div>
                    <button type="button" id="attendanceReportGenerate" class="progga-btn progga-btn-secondary"><i class="bi bi-search"></i> Generate</button>
                </div>
            </div>
        </div>

        <div class="hr-card" id="attendanceReportResult">
            <div class="hr-empty"><div class="spinner-border spinner-border-sm"></div><div class="mt-2">Loading report...</div></div>
        </div>
    </div>
</main>
@endsection

@section('script')
    @include('admin.hr.shared.plugins')
    <script>
    $(function(){
        let currentPage = 1;
        HrUi.initSelect2('.report-select2');

        function currentParams(page) {
            return {
                page: page || 1,
                report_type: $('#attendanceReportType').val(),
                month: $('#attendanceReportMonth').val(),
                employee_id: $('#attendanceReportEmployee').val(),
                department_id: $('#attendanceReportDepartment').val(),
                search: $('#attendanceReportSearch').val()
            };
        }

        function toggleFilters() {
            const employeeMode = $('#attendanceReportType').val() === 'employee_detail';
            $('#employeeFilterWrap').toggle(employeeMode);
            $('#departmentFilterWrap, #reportSearchWrap').toggle(!employeeMode);
        }

        function validateFilters() {
            if (!$('#attendanceReportMonth').val()) {
                Swal.fire('Select month', 'Please select a report month.', 'info'); return false;
            }
            if ($('#attendanceReportType').val() === 'employee_detail' && !$('#attendanceReportEmployee').val()) {
                Swal.fire('Select employee', 'Please select an employee for the employee-wise report.', 'info'); return false;
            }
            return true;
        }

        function loadReport(page) {
            if (!validateFilters()) return;
            currentPage = page || 1;
            const container = $('#attendanceReportResult').addClass('hr-table-loading');
            $.get("{{ route('hr.attendance.reports.table') }}", currentParams(currentPage))
                .done(function(html){ container.html(html); })
                .fail(function(xhr){ Swal.fire('Report Error', xhr.responseJSON?.message || 'Could not load the attendance report.', 'error'); })
                .always(function(){ container.removeClass('hr-table-loading'); });
        }

        $('#attendanceReportType').on('change', function(){ toggleFilters(); });
        $('#attendanceReportGenerate').on('click', function(){ loadReport(1); });
        $('#attendanceReportMonth, #attendanceReportEmployee, #attendanceReportDepartment').on('change', function(){ if ($('#attendanceReportType').val() !== 'employee_detail' || $('#attendanceReportEmployee').val()) loadReport(1); });
        $('#attendanceReportSearch').on('keydown', function(event){ if (event.key === 'Enter') loadReport(1); });

        $(document).on('click', '#attendanceReportResult .report-page-link:not(.disabled)', function(event){
            event.preventDefault();
            const url = new URL(this.href);
            loadReport(url.searchParams.get('page') || 1);
        });

        $('#attendancePdfBtn').on('click', function(){
            if (!validateFilters()) return;
            const params = new URLSearchParams(currentParams(1));
            params.delete('page');
            window.open("{{ route('hr.attendance.reports.pdf') }}?" + params.toString(), '_blank', 'noopener');
        });

        toggleFilters();
        loadReport(1);
    });
    </script>
@endsection
