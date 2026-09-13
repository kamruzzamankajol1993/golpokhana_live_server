@extends('admin.master.master')

@section('title', 'Single Employee Payroll')

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Single Employee Payroll</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.payroll.index') }}" class="progga-breadcrumb-item">Payroll</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Single Employee</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('hr.payroll.create', ['month' => $month]) }}" class="progga-btn progga-btn-outline"><i class="bi bi-people"></i> Generate All</a>
                <a href="{{ route('hr.payroll.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-left"></i> Payroll List</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-5">
                <div class="hr-card mb-3">
                    <div class="hr-card-header">
                        <div>
                            <div class="hr-card-title"><i class="bi bi-person-check me-2"></i>Select Employee & Month</div>
                            <div class="hr-card-subtitle">Create one employee's payroll without regenerating the full month.</div>
                        </div>
                    </div>
                    <div class="hr-card-body">
                        <div class="mb-3">
                            <label class="progga-form-label">Employee <span class="text-danger">*</span></label>
                            <select id="singlePayrollEmployee" class="hr-select2" data-placeholder="Search employee by name or code">
                                <option value="">Select Employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id', $selectedEmployee?->id) == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->employee_code }} — {{ $employee->name }}{{ $employee->department ? ' · ' . $employee->department->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="progga-form-label">Payroll Month <span class="text-danger">*</span></label>
                            <input type="month" id="singlePayrollMonth" class="progga-form-control" value="{{ old('month', $month) }}"
                                   @unless($allowNonCurrentMonth) min="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" @endunless>
                        </div>

                        <div class="payroll-month-shortcuts">
                            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm single-month-move" data-offset="-1" {{ $allowNonCurrentMonth ? '' : 'disabled' }}><i class="bi bi-chevron-left"></i> Previous</button>
                            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="singleCurrentMonth">Current</button>
                            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm single-month-move" data-offset="1" {{ $allowNonCurrentMonth ? '' : 'disabled' }}>Next <i class="bi bi-chevron-right"></i></button>
                        </div>

                        <div class="alert {{ $allowNonCurrentMonth ? 'alert-success' : 'alert-secondary' }} mt-3 mb-0 py-2 px-3">
                            <small><i class="bi {{ $allowNonCurrentMonth ? 'bi-unlock' : 'bi-lock' }} me-1"></i>{{ $allowNonCurrentMonth ? 'Previous and future payroll months are allowed.' : 'Only current month is allowed by Payroll Settings.' }}</small>
                        </div>
                    </div>
                </div>

                <div class="hr-card">
                    <div class="hr-card-header"><div class="hr-card-title">How It Works</div></div>
                    <div class="hr-card-body">
                        <div class="payroll-step-list">
                            <div><span>1</span><p><strong>Create Draft</strong><small>Only the selected employee is added.</small></p></div>
                            <div><span>2</span><p><strong>Review & Edit</strong><small>Adjust components before approval.</small></p></div>
                            <div><span>3</span><p><strong>Approve Employee</strong><small>Approval does not depend on other employees.</small></p></div>
                            <div><span>4</span><p><strong>Record Payment</strong><small>Payment is also employee-wise.</small></p></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="hr-card mb-3">
                    <div class="hr-card-header">
                        <div>
                            <div class="hr-card-title">Employee Payroll Check</div>
                            <div class="hr-card-subtitle" id="singlePrecheckLabel">Select an employee and month to check readiness.</div>
                        </div>
                        <div id="singlePrecheckLoader" class="spinner-border spinner-border-sm d-none"></div>
                    </div>
                    <div class="hr-card-body">
                        <div class="payroll-check-grid payroll-single-check-grid">
                            <div class="payroll-check-card"><span>Eligible</span><strong id="singleEligible">—</strong></div>
                            <div class="payroll-check-card"><span>Salary Setup</span><strong id="singleSalaryReady">—</strong></div>
                            <div class="payroll-check-card"><span>Missing Rules</span><strong id="singleMissingRules">—</strong></div>
                            <div class="payroll-check-card warning"><span>Missing Attendance</span><strong id="singleMissingAttendance">—</strong></div>
                            <div class="payroll-check-card warning"><span>Pending Leave</span><strong id="singlePendingLeave">—</strong></div>
                        </div>

                        <div id="singleFutureAlert" class="alert alert-warning mt-3 d-none">
                            This is a future month. Attendance-dependent values use only data currently available.
                        </div>
                        <div id="singleExistingRunAlert" class="alert alert-info mt-3 d-none"></div>
                        <div id="singleExistingItemAlert" class="alert alert-danger mt-3 d-none"></div>
                        <div id="singleSalaryAlert" class="alert alert-danger mt-3 d-none"></div>
                        <div id="singleRulesAlert" class="alert alert-danger mt-3 d-none"></div>
                        <div id="singleEligibilityAlert" class="alert alert-danger mt-3 d-none"></div>
                        <div id="singleLockedAlert" class="alert alert-danger mt-3 d-none"></div>
                    </div>
                </div>

                <form action="{{ route('hr.payroll.single.store') }}" method="POST" id="singlePayrollForm">
                    @csrf
                    <input type="hidden" name="employee_id" id="singleEmployeeInput" value="{{ old('employee_id', $selectedEmployee?->id) }}">
                    <input type="hidden" name="month" id="singleMonthInput" value="{{ old('month', $month) }}">

                    <div class="hr-card">
                        <div class="hr-card-header">
                            <div>
                                <div class="hr-card-title">Create Draft Confirmation</div>
                                <div class="hr-card-subtitle">The selected employee will be created as Draft even if other employees are already Approved or Paid.</div>
                            </div>
                        </div>
                        <div class="hr-card-body">
                            @if($errors->any())
                                <div class="alert alert-danger">{{ $errors->first() }}</div>
                            @endif

                            @if($manualComponents->isNotEmpty())
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2"><div class="fw-bold">Monthly Manual Values</div><span class="hr-muted">Blank = 0</span></div>
                                    <div class="alert alert-light border py-2 px-3 mb-2" style="font-size:12px">These values belong only to this payroll month and are not saved in Employee Create/Edit. Example: <strong>Adjustment (Last Month) Addition</strong>, Arrear, Fine and Other.</div>
                                    <div class="row g-2">
                                        @foreach($manualComponents as $component)
                                            <div class="col-md-6">
                                                <label class="progga-form-label">{{ $component->display_label }} <span class="hr-person-meta">({{ ucfirst($component->display_group) }})</span></label>
                                                <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" name="manual_components[{{ $component->id }}]" class="progga-form-control" value="{{ old('manual_components.'.$component->id, '') }}" placeholder="0.00"></div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <label class="payroll-confirm-row">
                                <input type="checkbox" name="confirm_incomplete_attendance" value="1" {{ old('confirm_incomplete_attendance') ? 'checked' : '' }}>
                                <span><strong>Continue with incomplete attendance</strong><small>Not-marked dates are excluded from automatic absence deduction.</small></span>
                            </label>
                            <label class="payroll-confirm-row">
                                <input type="checkbox" name="confirm_pending_leave" value="1" {{ old('confirm_pending_leave') ? 'checked' : '' }}>
                                <span><strong>Continue with pending leave</strong><small>Pending leave is ignored until approved.</small></span>
                            </label>

                            <div class="mt-3">
                                <label class="progga-form-label">Employee Payroll Note</label>
                                <textarea name="notes" rows="3" class="progga-form-control" placeholder="Optional note for this employee payroll">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="hr-card-footer d-flex justify-content-end gap-2">
                            <a href="{{ route('hr.payroll.index') }}" class="progga-btn progga-btn-outline">Cancel</a>
                            @can('payroll-create')
                                <button type="submit" class="progga-btn progga-btn-primary" id="singlePayrollCreateBtn" disabled>
                                    <i class="bi bi-person-plus"></i> Create Employee Draft
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
    HrUi.initSelect2('#singlePayrollEmployee');

    const employeeSelect = $('#singlePayrollEmployee');
    const monthInput = $('#singlePayrollMonth');
    const createBtn = $('#singlePayrollCreateBtn');
    const currentMonth = @json(now()->format('Y-m'));
    let lastCheck = null;

    function moveMonth(value, offset) {
        const parts = value.split('-').map(Number);
        const date = new Date(parts[0], parts[1] - 1 + offset, 1);
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    }

    function resetCheck() {
        lastCheck = null;
        $('#singleEligible, #singleSalaryReady, #singleMissingRules, #singleMissingAttendance, #singlePendingLeave').text('—');
        $('#singlePrecheckLabel').text('Select an employee and month to check readiness.');
        $('#singleFutureAlert, #singleExistingRunAlert, #singleExistingItemAlert, #singleSalaryAlert, #singleRulesAlert, #singleEligibilityAlert, #singleLockedAlert').addClass('d-none').empty();
        createBtn.prop('disabled', true);
    }

    function renderCheck(data) {
        lastCheck = data;
        $('#singlePrecheckLabel').text(data.employee_code + ' — ' + data.employee_name + ' · ' + data.month_label);
        $('#singleEligible').text(data.eligible ? 'Yes' : 'No');
        $('#singleSalaryReady').text(data.salary_ready ? 'Ready' : 'Missing');
        $('#singleMissingRules').text(data.missing_rule_count || 0);
        $('#singleMissingAttendance').text(data.missing_attendance_count);
        $('#singlePendingLeave').text(data.pending_leave_count);
        $('#singleEmployeeInput').val(data.employee_id);
        $('#singleMonthInput').val(data.month);
        $('#singleFutureAlert').toggleClass('d-none', !data.is_future_month);

        if (data.existing_run) {
            $('#singleExistingRunAlert').removeClass('d-none').html('Monthly payroll run exists: <a href="' + data.existing_run.url + '"><strong>' + data.existing_run.code + '</strong></a> (' + data.existing_run.status + '). The employee will be added to this run.');
        } else {
            $('#singleExistingRunAlert').addClass('d-none').empty();
        }

        if (data.existing_item) {
            $('#singleExistingItemAlert').removeClass('d-none').html('This employee payroll already exists. <a href="' + data.existing_item.url + '"><strong>Open Employee Payroll</strong></a>');
        } else {
            $('#singleExistingItemAlert').addClass('d-none').empty();
        }

        $('#singleSalaryAlert').toggleClass('d-none', data.salary_ready).text(data.salary_ready ? '' : (data.salary_message || 'Employee salary setup is missing for the selected month.'));
        const missingRules = data.missing_rules || [];
        $('#singleRulesAlert').toggleClass('d-none', missingRules.length === 0).html(missingRules.length ? '<strong>Missing payroll rule(s):</strong> ' + missingRules.join(', ') + '. <a class="alert-link" href="' + @json(route('hr.settings.index', ['tab' => 'salary-components'])) + '" target="_blank">Open HR Settings</a> or update this employee payroll setup.' : '');
        $('#singleEligibilityAlert').toggleClass('d-none', data.eligible).text(data.eligible ? '' : 'Employee is not eligible for this month based on joining/exit dates.');
        $('#singleLockedAlert').toggleClass('d-none', !data.run_locked).text(data.run_locked ? 'This payroll run is fully paid and locked by Payroll Settings.' : '');

        createBtn.prop('disabled', !data.eligible || !data.salary_ready || (data.missing_rule_count || 0) > 0 || !!data.existing_item || !!data.run_locked);
    }

    function runCheck() {
        const employeeId = employeeSelect.val();
        const month = monthInput.val();
        if (!employeeId || !month) {
            resetCheck();
            return;
        }

        $('#singlePrecheckLoader').removeClass('d-none');
        createBtn.prop('disabled', true);
        $.get(@json(route('hr.payroll.single.precheck')), { employee_id: employeeId, month: month })
            .done(renderCheck)
            .fail(function (xhr) {
                resetCheck();
                Swal.fire('Cannot check payroll', xhr.responseJSON?.message || 'Employee payroll check failed.', 'error');
            })
            .always(function () { $('#singlePrecheckLoader').addClass('d-none'); });
    }

    employeeSelect.on('change', runCheck);
    monthInput.on('change', runCheck);
    $('.single-month-move').on('click', function () {
        monthInput.val(moveMonth(monthInput.val() || currentMonth, Number(this.dataset.offset))).trigger('change');
    });
    $('#singleCurrentMonth').on('click', function () {
        monthInput.val(currentMonth).trigger('change');
    });

    $('#singlePayrollForm').on('submit', function (event) {
        if (!lastCheck || createBtn.prop('disabled')) {
            event.preventDefault();
            return;
        }
        createBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Creating...');
    });

    @if(session('error'))
        Swal.fire('Cannot Create Payroll', @json(session('error')), 'error');
    @endif

    if (employeeSelect.val() && monthInput.val()) {
        runCheck();
    }
});
</script>
@endsection
