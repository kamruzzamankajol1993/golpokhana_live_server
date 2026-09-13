@php
    $isEdit = isset($employee);
    $employeeValue = fn ($field, $default = null) => old($field, $isEdit ? data_get($employee, $field) : $default);
    $isWaiterChecked = old('is_waiter', $isEdit ? $employee->is_waiter : false);
    $canLoginChecked = old('can_login', $isEdit ? $employee->can_login : false);
    $salaryStructure = $isEdit ? $employee->currentSalaryStructure : null;
    $salaryRuleMap = $salaryStructure ? $salaryStructure->components->keyBy('salary_component_id') : collect();
    $salaryEnabled = old('salary_enabled', $salaryStructure ? 1 : 1);
    $leaveBalanceYear = now()->year;
    $leaveBalanceMap = $isEdit
        ? $employee->leaveBalances->where('year', $leaveBalanceYear)->keyBy('leave_type_id')
        : collect();
    $avatar = $isEdit && $employee->image
        ? asset('public/' . ltrim($employee->image, '/'))
        : 'https://ui-avatars.com/api/?name=' . urlencode($isEdit ? $employee->name : 'Employee') . '&background=21352a&color=d5aa65&size=160&bold=true';
    $nidImage = $isEdit && $employee->nid_image ? asset('public/' . ltrim($employee->nid_image, '/')) : null;
@endphp

@if(session('error') || $errors->any())
    <div class="alert alert-danger mb-3" style="border-radius:12px;">
        <div class="fw-bold mb-2">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Employee was not updated
        </div>

        @if(session('error'))
            <div class="mb-2" style="font-size:13px;word-break:break-word;">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="fw-semibold mb-1" style="font-size:12px;">Please correct the following field(s):</div>
            <ul class="mb-0 ps-3" style="font-size:12px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if(session('employee_edit_log_ref'))
            <div class="mt-2 pt-2 border-top" style="font-size:11px;">
                <strong>Log Reference:</strong> {{ session('employee_edit_log_ref') }}<br>
                <span class="text-muted">Diagnostic file: storage/logs/employee-edit.log</span>
            </div>
        @endif
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-3">
        <div class="hr-card h-100">
            <div class="hr-card-body text-center">
                <img id="employeeImagePreview" src="{{ $avatar }}" class="hr-profile-image mb-3" alt="Employee image">
                <div class="hr-card-title">{{ $isEdit ? $employee->employee_code : 'Employee Photo' }}</div>
                <div class="hr-muted mb-3">JPG, PNG or WEBP · Maximum 2 MB</div>
                <label class="progga-btn progga-btn-outline w-100 justify-content-center" for="employeeImage">
                    <i class="bi bi-camera"></i> Choose Photo
                </label>
                <input type="file" class="d-none" name="image" id="employeeImage" accept="image/*">
                @error('image')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="col-xl-9">
        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Personal Information</div>
                    <div class="hr-card-subtitle">Basic identity and communication details.</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="progga-form-label">Full Name <span class="progga-required">*</span></label>
                        <input type="text" class="progga-form-control" name="name" value="{{ $employeeValue('name') }}" required>
                        @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Phone <span class="progga-required">*</span></label>
                        <input type="text" class="progga-form-control" name="phone" value="{{ $employeeValue('phone') }}" required>
                        @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Gender</label>
                        <select id="employeeGender" name="gender" class="employee-form-select2" data-search="false">
                            <option value="">Select Gender</option>
                            <option value="male" {{ $employeeValue('gender') === 'male' ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ $employeeValue('gender') === 'female' ? 'selected' : '' }}>Female</option>
                            <option value="other" {{ $employeeValue('gender') === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Date of Birth</label>
                        <input type="text" id="employeeDob" class="progga-form-control employee-date" name="date_of_birth" value="{{ old('date_of_birth', $isEdit ? optional($employee->date_of_birth)->format('Y-m-d') : '') }}">
                        @error('date_of_birth')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="progga-form-label">Address</label>
                        <input type="text" class="progga-form-control" name="address" value="{{ $employeeValue('address') }}">
                        @error('address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">NID Number</label>
                        <input type="text" class="progga-form-control" name="nid" value="{{ $employeeValue('nid') }}" maxlength="50" placeholder="Enter NID number">
                        @error('nid')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="progga-form-label">NID Image</label>
                        <div class="p-3" style="border:1px dashed var(--progga-border);border-radius:12px;background:#fafbfa;">
                            <div class="d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                                <div style="width:180px;max-width:100%;height:110px;border:1px solid var(--progga-border-light);border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                                    <img id="employeeNidImagePreview" src="{{ $nidImage ?: '' }}" alt="NID image preview" style="width:100%;height:100%;object-fit:contain;{{ $nidImage ? '' : 'display:none;' }}">
                                    <div id="employeeNidImageEmpty" class="hr-muted text-center px-2" style="font-size:11px;{{ $nidImage ? 'display:none;' : '' }}">
                                        <i class="bi bi-card-image d-block mb-1" style="font-size:22px"></i>
                                        No NID image selected
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="progga-form-control" name="nid_image" id="employeeNidImage" accept="image/jpeg,image/png,image/webp">
                                    <div class="hr-muted mt-1">JPG, PNG or WEBP · Maximum 4 MB. Preview appears before saving.</div>
                                    @if($nidImage)
                                        <a href="{{ $nidImage }}" target="_blank" rel="noopener" class="small text-decoration-none d-inline-block mt-1">View current NID image</a>
                                    @endif
                                    @error('nid_image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Employment Information</div>
                    <div class="hr-card-subtitle">Department, designation, shift and service dates.</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="progga-form-label">Department <span class="progga-required">*</span></label>
                        <select id="employeeDepartmentId" name="department_id" class="employee-form-select2" required>
                            <option value="">Select Department</option>
                            @foreach($departments as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('department_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Designation <span class="progga-required">*</span></label>
                        <select id="employeeDesignationId" name="designation_id" class="employee-form-select2" required>
                            <option value="">Select Designation</option>
                            @foreach($designations as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('designation_id') === (string) $item->id ? 'selected' : '' }}>
                                    {{ $item->name }}{{ $item->department ? ' — ' . $item->department->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('designation_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Employment Type <span class="progga-required">*</span></label>
                        <select id="employeeEmploymentTypeId" name="employment_type_id" class="employee-form-select2" required>
                            <option value="">Select Employment Type</option>
                            @foreach($employmentTypes as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('employment_type_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('employment_type_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Joining Date <span class="progga-required">*</span></label>
                        <input type="text" id="employeeJoinDate" class="progga-form-control employee-date" name="join_date" value="{{ old('join_date', $isEdit ? optional($employee->join_date)->format('Y-m-d') : now()->toDateString()) }}" required>
                        @error('join_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Probation End</label>
                        <input type="text" id="employeeProbationEnd" class="progga-form-control employee-date" name="probation_end_date" value="{{ old('probation_end_date', $isEdit ? optional($employee->probation_end_date)->format('Y-m-d') : '') }}">
                        @error('probation_end_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Default Shift</label>
                        <select id="employeeDefaultShiftId" name="default_shift_id" class="employee-form-select2">
                            <option value="">No Default Shift</option>
                            @foreach($shifts as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('default_shift_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }} — {{ $item->time_range }}</option>
                            @endforeach
                        </select>
                        @error('default_shift_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Employment Status</label>
                        <select id="employeeEmploymentStatus" name="employment_status" class="employee-form-select2" data-search="false">
                            @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'resigned' => 'Resigned', 'terminated' => 'Terminated'] as $value => $label)
                                <option value="{{ $value }}" {{ $employeeValue('employment_status', 'active') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3" id="employeeExitDateWrap">
                        <label class="progga-form-label">Exit Date</label>
                        <input type="text" id="employeeExitDate" class="progga-form-control employee-date" name="exit_date" value="{{ old('exit_date', $isEdit ? optional($employee->exit_date)->format('Y-m-d') : '') }}">
                        @error('exit_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3" id="employeeLeaveBalanceCard">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Leave Balance Setup — {{ $leaveBalanceYear }}</div>
                    <div class="hr-card-subtitle">Global leave balance is selected by default. Choose Custom only when this employee needs a different annual entitlement.</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="progga-table-wrapper">
                    <table class="progga-table">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th style="width:210px">Balance Rule</th>
                                <th style="width:180px">Global Balance</th>
                                <th style="width:230px">Employee Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leaveTypes as $leaveType)
                                @php
                                    $savedBalance = $leaveBalanceMap->get($leaveType->id);
                                    $balanceMode = old(
                                        'leave_balances.'.$leaveType->id.'.mode',
                                        $savedBalance?->entitlement_mode ?: 'global'
                                    );
                                    $customBalance = old(
                                        'leave_balances.'.$leaveType->id.'.entitled_days',
                                        $savedBalance?->entitled_days ?? $leaveType->days_per_year
                                    );
                                @endphp
                                <tr class="employee-leave-balance-row">
                                    <td>
                                        <strong>{{ $leaveType->name }}</strong>
                                        <div class="hr-person-meta">{{ $leaveType->code ?: 'No code' }} · {{ $leaveType->is_paid ? 'Paid' : 'Unpaid' }}</div>
                                    </td>
                                    <td>
                                        <select
                                            name="leave_balances[{{ $leaveType->id }}][mode]"
                                            class="employee-form-select2 employee-leave-balance-mode"
                                            data-search="false"
                                        >
                                            <option value="global" {{ $balanceMode === 'global' ? 'selected' : '' }}>Use Global Balance</option>
                                            <option value="custom" {{ $balanceMode === 'custom' ? 'selected' : '' }}>Custom for Employee</option>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="hr-badge hr-badge-neutral">{{ number_format((float) $leaveType->days_per_year, 2) }} days</span>
                                    </td>
                                    <td class="employee-leave-custom-wrap">
                                        <div class="input-group">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="366"
                                                class="progga-form-control employee-leave-custom-input"
                                                name="leave_balances[{{ $leaveType->id }}][entitled_days]"
                                                value="{{ number_format((float) $customBalance, 2, '.', '') }}"
                                            >
                                            <span class="input-group-text">days</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"><div class="hr-empty py-3">No active leave type configured.</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3" id="employeePayrollSetupCard">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Salary & Payroll Setup</div>
                    <div class="hr-card-subtitle">Salary, allowance and deduction rows are dynamic. Employee custom rule overrides the global HR Settings rule.</div>
                </div>
                <label class="progga-toggle">
                    <input type="checkbox" name="salary_enabled" id="employeeSalaryEnabled" value="1" {{ $salaryEnabled ? 'checked' : '' }}>
                    <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                    <span class="progga-toggle-label">Configure Payroll</span>
                </label>
            </div>
            <div class="hr-card-body" id="employeeSalaryFields">
                <div class="alert alert-light border mb-3" style="font-size:12px;">
                    <strong>Rule priority:</strong> Employee Custom → Global Rule → Configuration Warning. A configured <strong>0</strong> is valid; “Not configured” is different from zero.
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="progga-form-label">Effective From <span class="progga-required">*</span></label>
                        <input type="text" id="employeeSalaryEffectiveFrom" class="progga-form-control employee-date" name="salary_effective_from" value="{{ old('salary_effective_from', optional($salaryStructure?->effective_from)->format('Y-m-d') ?: optional($employee->join_date)->format('Y-m-d') ?: old('join_date', now()->toDateString())) }}">
                        <div class="hr-person-meta mt-1">For the first salary setup, keep this equal to Joining Date if payroll should start from joining.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Basic Salary <span class="progga-required">*</span></label>
                        <input type="number" step="0.01" min="0" class="progga-form-control" name="basic_salary" value="{{ old('basic_salary', $salaryStructure?->basic_salary ?? 0) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Payment Method</label>
                        <select name="salary_payment_method" id="salaryPaymentMethod" class="employee-form-select2" data-search="false">
                            @foreach(['bank'=>'Bank','cash'=>'Cash','mobile_banking'=>'Mobile Banking'] as $v=>$label)
                                <option value="{{ $v }}" {{ old('salary_payment_method', $salaryStructure?->payment_method ?? 'bank') === $v ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="progga-form-label">Account Name</label><input type="text" class="progga-form-control" name="salary_account_name" value="{{ old('salary_account_name', $salaryStructure?->account_name) }}"></div>
                    <div class="col-md-4"><label class="progga-form-label">Bank / Account Number</label><input type="text" class="progga-form-control" name="salary_account_number" value="{{ old('salary_account_number', $salaryStructure?->account_number) }}"></div>
                    <div class="col-md-4"><label class="progga-form-label">Mobile Banking Provider</label><input type="text" class="progga-form-control" name="salary_mobile_banking_provider" value="{{ old('salary_mobile_banking_provider', $salaryStructure?->mobile_banking_provider) }}"></div>
                </div>

                <div class="alert alert-info border mb-4" style="font-size:12px;">
                    <strong>Monthly-only values are not saved on the employee.</strong> Components configured as <strong>Manual at Payroll</strong> (for example Arrear, Fine, Other and Adjustment (Last Month)) are entered when payroll is created and remain only in that month's payroll snapshot.
                </div>

                @foreach(['salary'=>'Salary','allowance'=>'Allowance','deduction'=>'Deduction'] as $groupKey=>$groupLabel)
                    @php $groupRows = $salaryComponents->filter(fn($c) => ($c->component_group ?: ($c->type === 'deduction' ? 'deduction' : 'salary')) === $groupKey && strtoupper((string)$c->code) !== 'BASIC' && $c->allow_employee_override && $c->calculation_type !== 'manual'); @endphp
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2"><div class="fw-bold">{{ $groupLabel }} Rules</div><span class="hr-muted">{{ $groupRows->count() }} configurable component(s)</span></div>
                        <div class="progga-table-wrapper">
                            <table class="progga-table">
                                <thead><tr><th>Component</th><th style="width:190px">Rule</th><th>Global Value</th><th style="width:220px">Custom Value</th></tr></thead>
                                <tbody>
                                @forelse($groupRows as $component)
                                    @php
                                        $savedRule = $salaryRuleMap->get($component->id);
                                        $mode = old('payroll_components.'.$component->id.'.mode', $savedRule?->rule_mode ?: 'global');
                                        $ruleCode = strtolower((string)($component->rule_code ?: 'standard'));
                                        $isOtRule = in_array($ruleCode, ['ot_day_off', 'ot_gov_off'], true);
                                        $isAttendanceDeduction = in_array($ruleCode, ['late', 'lwp_absent'], true);
                                        $percentValue = number_format((float)$component->default_percentage, 2, '.', '');
                                        if (!$component->global_configured) {
                                            $globalText = 'Not configured';
                                        } elseif ($component->calculation_type === 'percentage') {
                                            $globalText = $isOtRule
                                                ? $percentValue.'% of Basic Hourly'
                                                : ($isAttendanceDeduction ? $percentValue.'% of Daily Deduction Rate' : $percentValue.'% of '.str_replace('_',' ',$component->percentage_of));
                                        } else {
                                            $globalText = '৳'.number_format((float)$component->default_amount,2).($isOtRule ? ' / hour' : '');
                                        }
                                    @endphp
                                    <tr class="employee-payroll-rule-row" data-component="{{ $component->id }}">
                                        <td><strong>{{ $component->display_label }}</strong><div class="hr-person-meta">{{ $component->code }} · {{ ucfirst($component->calculation_type) }}</div></td>
                                        <td><select name="payroll_components[{{ $component->id }}][mode]" class="employee-form-select2 employee-rule-mode" data-search="false"><option value="global" {{ $mode==='global'?'selected':'' }}>Use Global Rule</option><option value="custom" {{ $mode==='custom'?'selected':'' }}>Custom for Employee</option><option value="disabled" {{ $mode==='disabled'?'selected':'' }}>Not Applicable</option></select></td>
                                        <td><span class="hr-badge {{ $component->global_configured ? 'hr-badge-neutral' : 'hr-badge-danger' }}">{{ $globalText }}</span></td>
                                        <td class="employee-custom-rule-wrap">
                                            @if($component->calculation_type === 'percentage')
                                                <div class="input-group"><input type="number" step="0.01" min="0" class="progga-form-control" name="payroll_components[{{ $component->id }}][percentage]" value="{{ number_format((float) old('payroll_components.'.$component->id.'.percentage', $savedRule?->percentage ?? $component->default_percentage), 2, '.', '') }}"><span class="input-group-text">{{ $isOtRule ? '% Basic Hourly' : ($isAttendanceDeduction ? '% Daily Rate' : '%') }}</span></div>
                                            @else
                                                <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" class="progga-form-control" name="payroll_components[{{ $component->id }}][amount]" value="{{ number_format((float) old('payroll_components.'.$component->id.'.amount', $savedRule?->amount ?? $component->default_amount), 2, '.', '') }}">@if($isOtRule)<span class="input-group-text">/ hour</span>@endif</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><div class="hr-empty py-3">No active {{ strtolower($groupLabel) }} component configured.</div></td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Access & Operational Role</div>
                    <div class="hr-card-subtitle">Waiter/POS access and system login are separate permissions.</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="hr-option-card">
                            <label class="progga-toggle">
                                <input type="checkbox" name="is_waiter" id="employeeIsWaiter" value="1" {{ $isWaiterChecked ? 'checked' : '' }}>
                                <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                                <span class="progga-toggle-label fw-bold">Waiter / POS Access</span>
                            </label>
                            <div class="hr-muted mt-2">The employee will be linked with the existing waiter/POS module.</div>
                            <div id="waiterAccessFields" class="mt-3">
                                <label class="progga-form-label">Assigned Zone <span class="progga-required">*</span></label>
                                <select id="employeeZoneId" name="zone_id" class="employee-form-select2">
                                    <option value="">Select Zone</option>
                                    @foreach($zones as $item)
                                        <option value="{{ $item->id }}" {{ (string) $employeeValue('zone_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @error('zone_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="hr-option-card">
                            <label class="progga-toggle">
                                <input type="checkbox" name="can_login" id="employeeCanLogin" value="1" {{ $canLoginChecked ? 'checked' : '' }}>
                                <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                                <span class="progga-toggle-label fw-bold">Can Login to System</span>
                            </label>
                            <div class="hr-muted mt-2">
                                @if($isEdit && $employee->user_id)
                                    Login account is already linked. Email/password are not required to save this employee; leave the password blank to keep the existing one.
                                @else
                                    Login access can be enabled now without email/password. Leave both blank and add credentials later from Employee Edit, or provide both to create the login account now.
                                @endif
                            </div>
                            <div id="loginAccessFields" class="row g-2 mt-2">
                                <div class="col-12">
                                    <label class="progga-form-label">Login Email <span class="hr-muted fw-normal">(Optional)</span></label>
                                    <input type="email" class="progga-form-control" name="email" value="{{ $employeeValue('email') }}">
                                    @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="progga-form-label">{{ $isEdit ? 'New Password' : 'Password' }} <span class="hr-muted fw-normal">(Optional)</span></label>
                                    <input type="password" class="progga-form-control" name="password" autocomplete="new-password">
                                    @if($isEdit)<div class="hr-muted mt-1">Leave blank to keep the existing password.</div>@endif
                                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="progga-form-label">Confirm Password</label>
                                    <input type="password" class="progga-form-control" name="password_confirmation" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Emergency Contact & Notes</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="progga-form-label">Emergency Contact Name</label>
                        <input type="text" class="progga-form-control" name="emergency_contact_name" value="{{ $employeeValue('emergency_contact_name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Emergency Contact Phone</label>
                        <input type="text" class="progga-form-control" name="emergency_contact_phone" value="{{ $employeeValue('emergency_contact_phone') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Internal Notes</label>
                        <textarea class="progga-form-control" name="notes" rows="3">{{ $employeeValue('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="hr-form-actions mt-3">
    <a href="{{ $isEdit ? route('hr.employees.show', $employee) : route('hr.employees.index') }}" class="progga-btn progga-btn-outline">
        <i class="bi bi-arrow-left"></i> Cancel
    </a>
    <button type="submit" class="progga-btn progga-btn-primary">
        <i class="bi bi-check2-circle"></i> {{ $isEdit ? 'Update Employee' : 'Save Employee' }}
    </button>
</div>
