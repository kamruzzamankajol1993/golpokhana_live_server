<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-wallet2 me-2"></i>Payroll Settings</div>
            <div class="hr-settings-help">Set salary divisor, absence, half day, late, overtime and payroll locking rules.</div>
        </div>
    </div>
    <div class="progga-card-body">
        <form action="{{ route('hr.settings.payroll.update') }}" method="POST">
            @csrf
            <div class="hr-section-title">Salary Cycle & Divisor</div>
            <div class="row g-3">
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Salary Cycle Start Day</label><input type="number" name="salary_cycle_start_day" class="progga-form-control" min="1" max="31" value="{{ old('salary_cycle_start_day', $payrollSetting->salary_cycle_start_day ?? 1) }}"></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Salary Cycle End Day</label><input type="number" name="salary_cycle_end_day" class="progga-form-control" min="1" max="31" value="{{ old('salary_cycle_end_day', $payrollSetting->salary_cycle_end_day) }}" placeholder="Month end"></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Currency</label><select id="hrPayrollCurrency" name="currency" class="progga-select hr-select2" data-search="false"><option value="BDT" {{ old('currency', $payrollSetting->currency ?? 'BDT') === 'BDT' ? 'selected' : '' }}>BDT — Bangladeshi Taka</option><option value="USD" {{ old('currency', $payrollSetting->currency ?? 'BDT') === 'USD' ? 'selected' : '' }}>USD — US Dollar</option></select></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Working Days Method</label><select id="hrWorkingDaysMethod" name="working_days_method" class="progga-select hr-select2" data-search="false"><option value="calendar_days" {{ old('working_days_method', $payrollSetting->working_days_method ?? 'calendar_days') === 'calendar_days' ? 'selected' : '' }}>Calendar Days</option><option value="fixed_days" {{ old('working_days_method', $payrollSetting->working_days_method ?? 'calendar_days') === 'fixed_days' ? 'selected' : '' }}>Fixed Days</option><option value="attendance_days" {{ old('working_days_method', $payrollSetting->working_days_method ?? 'calendar_days') === 'attendance_days' ? 'selected' : '' }}>Scheduled Working Days</option></select></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Default Working Days</label><input type="number" step="0.5" name="default_working_days" class="progga-form-control" min="1" max="31" value="{{ old('default_working_days', $payrollSetting->default_working_days ?? 30) }}"><div class="hr-settings-help">Used only when Fixed Days is selected.</div></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Deduction Basis</label><select name="deduction_basis" class="progga-select hr-select2" data-search="false"><option value="basic_salary" {{ old('deduction_basis', $payrollSetting->deduction_basis ?? 'basic_salary') === 'basic_salary' ? 'selected' : '' }}>Basic Salary</option><option value="gross_salary" {{ old('deduction_basis', $payrollSetting->deduction_basis ?? 'basic_salary') === 'gross_salary' ? 'selected' : '' }}>Gross Salary</option></select><div class="hr-settings-help">Daily absent/leave rate will use this amount.</div></div></div>
            </div>

            <div class="hr-section-title mt-4">Attendance Deduction</div>
            <div class="row g-3">
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Absent Deduction</label><select id="hrAbsentDeductionMethod" name="absent_deduction_method" class="progga-select hr-select2" data-search="false"><option value="per_day" {{ old('absent_deduction_method', $payrollSetting->absent_deduction_method ?? 'per_day') === 'per_day' ? 'selected' : '' }}>Deduct Per Absent Day</option><option value="none" {{ old('absent_deduction_method', $payrollSetting->absent_deduction_method ?? 'per_day') === 'none' ? 'selected' : '' }}>No Automatic Deduction</option></select></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Half Day Deduction</label><div class="input-group"><input type="number" step="0.01" name="half_day_deduction_percentage" class="progga-form-control" min="0" max="100" value="{{ old('half_day_deduction_percentage', $payrollSetting->half_day_deduction_percentage ?? 50) }}"><span class="input-group-text">%</span></div></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Late Deduction Rule</label><select name="late_deduction_method" class="progga-select hr-select2" data-search="false"><option value="none" {{ old('late_deduction_method', $payrollSetting->late_deduction_method ?? 'none') === 'none' ? 'selected' : '' }}>No Deduction</option><option value="half_day_after_count" {{ old('late_deduction_method', $payrollSetting->late_deduction_method ?? 'none') === 'half_day_after_count' ? 'selected' : '' }}>Threshold = Half Day</option><option value="full_day_after_count" {{ old('late_deduction_method', $payrollSetting->late_deduction_method ?? 'none') === 'full_day_after_count' ? 'selected' : '' }}>Threshold = Full Day</option></select></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Late Count Threshold</label><input type="number" name="late_count_threshold" class="progga-form-control" min="1" max="31" value="{{ old('late_count_threshold', $payrollSetting->late_count_threshold ?? 3) }}"><div class="hr-settings-help">Example: every 3 late records.</div></div></div>
            </div>

            <div class="hr-section-title mt-4">Overtime & Final Amount</div>
            <div class="row g-3">
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Overtime Calculation</label><select id="hrOvertimeCalculationMethod" name="overtime_calculation_method" class="progga-select hr-select2" data-search="false"><option value="hourly_rate" {{ old('overtime_calculation_method', $payrollSetting->overtime_calculation_method ?? 'hourly_rate') === 'hourly_rate' ? 'selected' : '' }}>Hourly Rate</option><option value="fixed_rate" {{ old('overtime_calculation_method', $payrollSetting->overtime_calculation_method ?? 'hourly_rate') === 'fixed_rate' ? 'selected' : '' }}>Employee Fixed Hourly Rate</option><option value="none" {{ old('overtime_calculation_method', $payrollSetting->overtime_calculation_method ?? 'hourly_rate') === 'none' ? 'selected' : '' }}>No Automatic Overtime</option></select></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Overtime Rate Source</label><select name="overtime_basis" class="progga-select hr-select2" data-search="false"><option value="employee_rate" {{ old('overtime_basis', $payrollSetting->overtime_basis ?? 'employee_rate') === 'employee_rate' ? 'selected' : '' }}>Employee OT Rate, then Basic Hourly</option><option value="basic_hourly" {{ old('overtime_basis', $payrollSetting->overtime_basis ?? 'employee_rate') === 'basic_hourly' ? 'selected' : '' }}>Always Basic Hourly</option></select></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Basic Hourly Multiplier</label><input type="number" step="0.01" name="overtime_rate_multiplier" class="progga-form-control" min="0" max="10" value="{{ old('overtime_rate_multiplier', $payrollSetting->overtime_rate_multiplier ?? 1.5) }}"><div class="hr-settings-help">Example: 1.5 means 150% of basic hourly rate.</div></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Net Salary Rounding</label><select id="hrRoundingMethod" name="rounding_method" class="progga-select hr-select2" data-search="false"><option value="none" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'none' ? 'selected' : '' }}>No Rounding</option><option value="nearest" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'nearest' ? 'selected' : '' }}>Nearest Whole Amount</option><option value="floor" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'floor' ? 'selected' : '' }}>Round Down</option><option value="ceil" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'ceil' ? 'selected' : '' }}>Round Up</option></select></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Allow Negative Net Salary</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="allow_negative_salary" value="1" {{ old('allow_negative_salary', $payrollSetting->allow_negative_salary ?? false) ? 'checked' : '' }} data-on="Allowed" data-off="Blocked"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('allow_negative_salary', $payrollSetting->allow_negative_salary ?? false) ? 'Allowed' : 'Blocked' }}</span></label></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Lock Paid Payroll</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="lock_paid_payroll" value="1" {{ old('lock_paid_payroll', $payrollSetting->lock_paid_payroll ?? true) ? 'checked' : '' }} data-on="Locked" data-off="Editable"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('lock_paid_payroll', $payrollSetting->lock_paid_payroll ?? true) ? 'Locked' : 'Editable' }}</span></label></div></div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Allow Previous / Next Month Payroll</label>
                        <label class="progga-toggle" style="margin-top:8px;">
                            <input type="checkbox" name="allow_non_current_month_payroll" value="1"
                                {{ old('allow_non_current_month_payroll', $payrollSetting->allow_non_current_month_payroll ?? false) ? 'checked' : '' }}
                                data-on="Yes" data-off="No">
                            <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                            <span class="progga-toggle-label">{{ old('allow_non_current_month_payroll', $payrollSetting->allow_non_current_month_payroll ?? false) ? 'Yes' : 'No' }}</span>
                        </label>
                        <div class="hr-settings-help">Yes allows payroll for previous and future months. No allows only the current month.</div>
                    </div>
                </div>
            </div>
            @can('hr-setting-update')
    <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="progga-btn progga-btn-primary">
            <i class="bi bi-check-lg"></i> Save Payroll Settings
        </button>
    </div>
@endcan
        </form>
    </div>
</div>
