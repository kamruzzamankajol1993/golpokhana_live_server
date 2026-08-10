@extends('admin.master.master')

@section('title', 'Generate Payroll')

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Generate All Employees Payroll</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.payroll.index') }}" class="progga-breadcrumb-item">Payroll</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Generate All</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('hr.payroll.single.create', ['month' => $month]) }}" class="progga-btn progga-btn-outline"><i class="bi bi-person-plus"></i> Single Employee</a>
                <a href="{{ route('hr.payroll.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-left"></i> Payroll List</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="hr-card mb-3">
                    <div class="hr-card-header">
                        <div><div class="hr-card-title">Payroll Period</div><div class="hr-card-subtitle">One payroll run is maintained for each month.</div></div>
                    </div>
                    <div class="hr-card-body">
                        <label class="progga-form-label">Payroll Month</label>
                        <input type="month" id="generatePayrollMonth" class="progga-form-control" value="{{ $month }}"
                               @unless($allowNonCurrentMonth) min="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" @endunless>

                        <div class="payroll-month-shortcuts mt-3">
                            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm payroll-month-move" data-offset="-1" {{ $allowNonCurrentMonth ? '' : 'disabled' }}><i class="bi bi-chevron-left"></i> Previous</button>
                            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="payrollCurrentMonth">Current</button>
                            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm payroll-month-move" data-offset="1" {{ $allowNonCurrentMonth ? '' : 'disabled' }}>Next <i class="bi bi-chevron-right"></i></button>
                        </div>

                        <div class="alert {{ $allowNonCurrentMonth ? 'alert-success' : 'alert-secondary' }} mt-3 mb-0 py-2 px-3">
                            <small>
                                <i class="bi {{ $allowNonCurrentMonth ? 'bi-unlock' : 'bi-lock' }} me-1"></i>
                                {{ $allowNonCurrentMonth ? 'Previous and future months are enabled.' : 'Only current month is enabled by Payroll Settings.' }}
                            </small>
                        </div>
                    </div>
                </div>

                <div class="hr-card">
                    <div class="hr-card-header"><div class="hr-card-title">Calculation Flow</div></div>
                    <div class="hr-card-body">
                        <div class="payroll-step-list">
                            <div><span>1</span><p><strong>Salary Structure</strong><small>Employee-wise basic and components</small></p></div>
                            <div><span>2</span><p><strong>Attendance & Leave</strong><small>Absence, half day, unpaid leave and OT</small></p></div>
                            <div><span>3</span><p><strong>Draft Review</strong><small>Each employee can also be approved separately</small></p></div>
                            <div><span>4</span><p><strong>Approve & Pay</strong><small>Bulk or employee-wise workflow</small></p></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="hr-card mb-3">
                    <div class="hr-card-header">
                        <div><div class="hr-card-title">Pre-generation Check</div><div class="hr-card-subtitle" id="precheckMonthLabel">{{ $precheck['month_label'] }}</div></div>
                        <div id="precheckLoader" class="spinner-border spinner-border-sm d-none"></div>
                    </div>
                    <div class="hr-card-body">
                        <div class="payroll-check-grid">
                            <div class="payroll-check-card"><span>Eligible Employees</span><strong id="checkEligible">{{ $precheck['eligible_employees'] }}</strong></div>
                            <div class="payroll-check-card"><span>Salary Ready</span><strong id="checkSalaryReady">{{ $precheck['salary_ready'] }}</strong></div>
                            <div class="payroll-check-card warning"><span>Missing Attendance</span><strong id="checkMissingAttendance">{{ $precheck['missing_attendance_count'] }}</strong></div>
                            <div class="payroll-check-card warning"><span>Pending Leave</span><strong id="checkPendingLeave">{{ $precheck['pending_leave_count'] }}</strong></div>
                        </div>

                        <div id="futureMonthAlert" class="alert alert-warning mt-3 d-none">
                            This is a future payroll month. Attendance-based deductions will only use attendance data currently available.
                        </div>

                        <div id="existingPayrollAlert" class="alert alert-info mt-3 {{ $existingRun ? '' : 'd-none' }}">
                            @if($existingRun)
                                Payroll already exists: <a href="{{ route('hr.payroll.show', $existingRun) }}"><strong>{{ $existingRun->payroll_code }}</strong></a> ({{ $existingRun->status }}). Use Single Employee Payroll to add a missing employee.
                            @endif
                        </div>

                        <div id="missingSalaryAlert" class="alert alert-danger mt-3 {{ $precheck['missing_salary_count'] ? '' : 'd-none' }}">
                            <strong id="missingSalaryTitle">Salary setup is missing for {{ $precheck['missing_salary_count'] }} employee(s).</strong>
                            <div id="missingSalaryList" class="mt-2 payroll-missing-list">
                                @foreach($precheck['missing_salary'] as $missing)
                                    <a href="{{ route('hr.employees.salary.show', $missing['id']) }}" target="_blank">{{ $missing['code'] }} — {{ $missing['name'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('hr.payroll.store') }}" method="POST" id="generatePayrollForm">
                    @csrf
                    <input type="hidden" name="month" id="payrollMonthInput" value="{{ $month }}">
                    <div class="hr-card">
                        <div class="hr-card-header"><div><div class="hr-card-title">Generation Confirmation</div><div class="hr-card-subtitle">All employee records will first be created as Draft.</div></div></div>
                        <div class="hr-card-body">
                            @if($errors->any())
                                <div class="alert alert-danger">{{ $errors->first() }}</div>
                            @endif

                            <label class="payroll-confirm-row">
                                <input type="checkbox" name="confirm_incomplete_attendance" value="1" {{ old('confirm_incomplete_attendance') ? 'checked' : '' }}>
                                <span><strong>Continue when attendance is incomplete</strong><small>Not-marked dates will not be treated as absent automatically.</small></span>
                            </label>
                            <label class="payroll-confirm-row">
                                <input type="checkbox" name="confirm_pending_leave" value="1" {{ old('confirm_pending_leave') ? 'checked' : '' }}>
                                <span><strong>Continue when leave requests are pending</strong><small>Pending leave is ignored until it is approved.</small></span>
                            </label>

                            <div class="mt-3">
                                <label class="progga-form-label">Notes</label>
                                <textarea name="notes" rows="3" class="progga-form-control" placeholder="Optional note for this payroll run">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="hr-card-footer d-flex justify-content-end gap-2">
                            <a href="{{ route('hr.payroll.index') }}" class="progga-btn progga-btn-outline">Cancel</a>
                            @can('payroll-create')
                                <button type="submit" class="progga-btn progga-btn-primary" id="generatePayrollBtn" {{ $existingRun || $precheck['missing_salary_count'] ? 'disabled' : '' }}>
                                    <i class="bi bi-people"></i> Generate All as Draft
                                </button>
                            @endcan
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
@include('admin.hr.shared.plugins')
<script>
$(function () {
    const monthInput = $('#generatePayrollMonth');
    const submitBtn = $('#generatePayrollBtn');
    const currentMonth = @json(now()->format('Y-m'));

    function moveMonth(value, offset) {
        const parts = value.split('-').map(Number);
        const date = new Date(parts[0], parts[1] - 1 + offset, 1);
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    }

    function renderPrecheck(data) {
        $('#precheckMonthLabel').text(data.month_label + ' · ' + data.period_start.split('-').reverse().join('-') + ' to ' + data.period_end.split('-').reverse().join('-'));
        $('#checkEligible').text(data.eligible_employees);
        $('#checkSalaryReady').text(data.salary_ready);
        $('#checkMissingAttendance').text(data.missing_attendance_count);
        $('#checkPendingLeave').text(data.pending_leave_count);
        $('#payrollMonthInput').val(data.month);
        $('#futureMonthAlert').toggleClass('d-none', !data.is_future_month);

        const existing = $('#existingPayrollAlert');
        if (data.existing_run) {
            existing.removeClass('d-none').html('Payroll already exists: <a href="' + data.existing_run.url + '"><strong>' + data.existing_run.code + '</strong></a> (' + data.existing_run.status + '). Use Single Employee Payroll to add a missing employee.');
        } else {
            existing.addClass('d-none').empty();
        }

        const salaryAlert = $('#missingSalaryAlert');
        const salaryList = $('#missingSalaryList').empty();
        if (data.missing_salary_count > 0) {
            $('#missingSalaryTitle').text('Salary setup is missing for ' + data.missing_salary_count + ' employee(s).');
            data.missing_salary.forEach(function (employee) {
                const url = @json(url('/hr/employees')) + '/' + employee.id + '/salary';
                salaryList.append($('<a>', { href: url, target: '_blank', text: employee.code + ' — ' + employee.name }));
            });
            salaryAlert.removeClass('d-none');
        } else {
            salaryAlert.addClass('d-none');
        }

        submitBtn.prop('disabled', !!data.existing_run || data.missing_salary_count > 0 || data.eligible_employees < 1);
    }

    function refreshPrecheck() {
        if (!monthInput.val()) return;
        $('#precheckLoader').removeClass('d-none');
        $.get(@json(route('hr.payroll.precheck')), { month: monthInput.val() })
            .done(renderPrecheck)
            .fail(function (xhr) {
                submitBtn.prop('disabled', true);
                Swal.fire('Cannot check payroll', xhr.responseJSON?.message || 'Precheck failed.', 'error');
            })
            .always(function () { $('#precheckLoader').addClass('d-none'); });
    }

    monthInput.on('change', refreshPrecheck);
    $('.payroll-month-move').on('click', function () {
        monthInput.val(moveMonth(monthInput.val() || currentMonth, Number(this.dataset.offset))).trigger('change');
    });
    $('#payrollCurrentMonth').on('click', function () {
        monthInput.val(currentMonth).trigger('change');
    });

    $('#generatePayrollForm').on('submit', function (event) {
        if (submitBtn.prop('disabled')) {
            event.preventDefault();
            return;
        }
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Generating...');
    });

    @if(session('error'))
        Swal.fire('Cannot Generate', @json(session('error')), 'error');
    @endif
});
</script>
@endsection
