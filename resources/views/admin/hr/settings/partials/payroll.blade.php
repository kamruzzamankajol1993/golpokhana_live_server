@php
    $otDayOff = $salaryComponents->first(fn($c) => strtolower((string)($c->rule_code ?: '')) === 'ot_day_off');
    $otGovOff = $salaryComponents->first(fn($c) => strtolower((string)($c->rule_code ?: '')) === 'ot_gov_off');
    $dayOffType = old('ot_day_off_calculation_type', $otDayOff?->calculation_type === 'percentage' ? 'percentage' : 'fixed');
    $govOffType = old('ot_gov_off_calculation_type', $otGovOff?->calculation_type === 'percentage' ? 'percentage' : 'fixed');
@endphp

<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-wallet2 me-2"></i>Payroll Settings</div>
            <div class="hr-settings-help">Only global calculation rules are kept here. Employee-specific values are set from Employee Create/Edit; monthly manual adjustments are entered while creating payroll.</div>
        </div>
    </div>
    <div class="progga-card-body">
        <form action="{{ route('hr.settings.payroll.update') }}" method="POST" id="hrPayrollSettingsForm">
            @csrf

            <div class="hr-section-title">Salary Calculation Basis</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Working Days Method</label>
                        <select id="hrWorkingDaysMethod" name="working_days_method" class="progga-select hr-select2" data-search="false">
                            <option value="calendar_days" {{ old('working_days_method', $payrollSetting->working_days_method ?? 'calendar_days') === 'calendar_days' ? 'selected' : '' }}>Calendar Days</option>
                            <option value="fixed_days" {{ old('working_days_method', $payrollSetting->working_days_method ?? 'calendar_days') === 'fixed_days' ? 'selected' : '' }}>Fixed Days</option>
                            <option value="attendance_days" {{ old('working_days_method', $payrollSetting->working_days_method ?? 'calendar_days') === 'attendance_days' ? 'selected' : '' }}>Scheduled Working Days</option>
                        </select>
                        <div class="hr-settings-help">Controls the divisor used for daily/hourly salary rates.</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Default Working Days</label>
                        <input type="number" step="0.5" name="default_working_days" class="progga-form-control" min="1" max="31" value="{{ number_format((float) old('default_working_days', $payrollSetting->default_working_days ?? 30), 2, '.', '') }}">
                        <div class="hr-settings-help">Used only for Fixed Days.</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Deduction Basis</label>
                        <select name="deduction_basis" class="progga-select hr-select2" data-search="false">
                            <option value="basic_salary" {{ old('deduction_basis', $payrollSetting->deduction_basis ?? 'basic_salary') === 'basic_salary' ? 'selected' : '' }}>Basic Salary</option>
                            <option value="gross_salary" {{ old('deduction_basis', $payrollSetting->deduction_basis ?? 'basic_salary') === 'gross_salary' ? 'selected' : '' }}>Gross Salary</option>
                        </select>
                        <div class="hr-settings-help">Used for Late, Absent, Half Day and unpaid leave daily rate.</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Currency</label>
                        <select id="hrPayrollCurrency" name="currency" class="progga-select hr-select2" data-search="false">
                            <option value="BDT" {{ old('currency', $payrollSetting->currency ?? 'BDT') === 'BDT' ? 'selected' : '' }}>BDT — Bangladeshi Taka</option>
                            <option value="USD" {{ old('currency', $payrollSetting->currency ?? 'BDT') === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="hr-section-title mt-4">Attendance Deduction</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Absent Deduction</label>
                        <select id="hrAbsentDeductionMethod" name="absent_deduction_method" class="progga-select hr-select2" data-search="false">
                            <option value="per_day" {{ old('absent_deduction_method', $payrollSetting->absent_deduction_method ?? 'per_day') === 'per_day' ? 'selected' : '' }}>Deduct Per Absent Day</option>
                            <option value="none" {{ old('absent_deduction_method', $payrollSetting->absent_deduction_method ?? 'per_day') === 'none' ? 'selected' : '' }}>No Automatic Absent Deduction</option>
                        </select>
                        <div class="hr-settings-help">No Automatic means Absent remains in attendance but does not add an absent-day salary deduction. LWP and Half Day can still calculate separately.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Half Day Deduction</label>
                        <div class="input-group"><input type="number" step="0.01" name="half_day_deduction_percentage" class="progga-form-control" min="0" max="100" value="{{ number_format((float) old('half_day_deduction_percentage', $payrollSetting->half_day_deduction_percentage ?? 50), 2, '.', '') }}"><span class="input-group-text">% of 1 day</span></div>
                        <div class="hr-settings-help">Example: 50% = each Half Day deducts half of the daily deduction rate.</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Late Rule</label>
                        <select name="late_deduction_method" class="progga-select hr-select2" data-search="false">
                            <option value="none" {{ old('late_deduction_method', $payrollSetting->late_deduction_method ?? 'none') === 'none' ? 'selected' : '' }}>No Deduction</option>
                            <option value="half_day_after_count" {{ old('late_deduction_method', $payrollSetting->late_deduction_method ?? 'none') === 'half_day_after_count' ? 'selected' : '' }}>Threshold = Half Day</option>
                            <option value="full_day_after_count" {{ old('late_deduction_method', $payrollSetting->late_deduction_method ?? 'none') === 'full_day_after_count' ? 'selected' : '' }}>Threshold = Full Day</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Late Threshold</label>
                        <input type="number" name="late_count_threshold" class="progga-form-control" min="1" max="31" value="{{ old('late_count_threshold', $payrollSetting->late_count_threshold ?? 3) }}">
                        <div class="hr-settings-help">e.g. every 3 late.</div>
                    </div>
                </div>
            </div>

            <div class="hr-section-title mt-4">Overtime Rules</div>
            <div class="alert alert-light border mb-3" style="font-size:12px;">
                <strong>OT is calculated from attendance hours.</strong> On a weekly/roster/company off day, the employee's full worked time is Day Off OT. On a <strong>Public/Government Holiday</strong>, the full worked time is GOV Off OT. Employee Create/Edit can override these two rates separately.
            </div>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="hr-option-card h-100" data-ot-rule="day_off">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div><div class="fw-bold">OT Amount (Day Off)</div><div class="hr-muted">Weekly Off, Duty Roster Off, Company/Special Holiday</div></div>
                            <span class="hr-badge hr-badge-neutral">Allowance</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="progga-form-label">Calculation</label>
                                <select name="ot_day_off_calculation_type" class="progga-select hr-select2 ot-rule-type" data-search="false">
                                    <option value="fixed" {{ $dayOffType === 'fixed' ? 'selected' : '' }}>Fixed Rate Per Hour</option>
                                    <option value="percentage" {{ $dayOffType === 'percentage' ? 'selected' : '' }}>% of Basic Hourly</option>
                                </select>
                            </div>
                            <div class="col-md-6 ot-fixed-field">
                                <label class="progga-form-label">Rate Per Hour</label>
                                <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" name="ot_day_off_amount" class="progga-form-control" value="{{ number_format((float) old('ot_day_off_amount', $otDayOff?->default_amount ?? 0), 2, '.', '') }}"><span class="input-group-text">/ hour</span></div>
                            </div>
                            <div class="col-md-6 ot-percent-field">
                                <label class="progga-form-label">Basic Hourly Percentage</label>
                                <div class="input-group"><input type="number" step="0.01" min="0" max="1000" name="ot_day_off_percentage" class="progga-form-control" value="{{ number_format((float) old('ot_day_off_percentage', $otDayOff?->default_percentage ?? 0), 2, '.', '') }}"><span class="input-group-text">%</span></div>
                            </div>
                        </div>
                        <div class="hr-settings-help mt-2">Fixed: OT Hours × Rate. Percentage: OT Hours × (Basic Daily ÷ Working Hours × %).</div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hr-option-card h-100" data-ot-rule="gov_off">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div><div class="fw-bold">OT Amount (GOV Off)</div><div class="hr-muted">Holiday Type = Public / Government Holiday</div></div>
                            <span class="hr-badge hr-badge-neutral">Allowance</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="progga-form-label">Calculation</label>
                                <select name="ot_gov_off_calculation_type" class="progga-select hr-select2 ot-rule-type" data-search="false">
                                    <option value="fixed" {{ $govOffType === 'fixed' ? 'selected' : '' }}>Fixed Rate Per Hour</option>
                                    <option value="percentage" {{ $govOffType === 'percentage' ? 'selected' : '' }}>% of Basic Hourly</option>
                                </select>
                            </div>
                            <div class="col-md-6 ot-fixed-field">
                                <label class="progga-form-label">Rate Per Hour</label>
                                <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" name="ot_gov_off_amount" class="progga-form-control" value="{{ number_format((float) old('ot_gov_off_amount', $otGovOff?->default_amount ?? 0), 2, '.', '') }}"><span class="input-group-text">/ hour</span></div>
                            </div>
                            <div class="col-md-6 ot-percent-field">
                                <label class="progga-form-label">Basic Hourly Percentage</label>
                                <div class="input-group"><input type="number" step="0.01" min="0" max="1000" name="ot_gov_off_percentage" class="progga-form-control" value="{{ number_format((float) old('ot_gov_off_percentage', $otGovOff?->default_percentage ?? 0), 2, '.', '') }}"><span class="input-group-text">%</span></div>
                            </div>
                        </div>
                        <div class="hr-settings-help mt-2">Public/Government Holiday always takes priority over Day Off when both apply on the same date.</div>
                    </div>
                </div>
            </div>

            <div class="hr-section-title mt-4">Final Payroll Controls</div>
            <div class="row g-3">
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Net Salary Rounding</label><select id="hrRoundingMethod" name="rounding_method" class="progga-select hr-select2" data-search="false"><option value="none" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'none' ? 'selected' : '' }}>No Rounding</option><option value="nearest" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'nearest' ? 'selected' : '' }}>Nearest Whole Amount</option><option value="floor" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'floor' ? 'selected' : '' }}>Round Down</option><option value="ceil" {{ old('rounding_method', $payrollSetting->rounding_method ?? 'nearest') === 'ceil' ? 'selected' : '' }}>Round Up</option></select></div></div>
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Allow Negative Net Salary</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="allow_negative_salary" value="1" {{ old('allow_negative_salary', $payrollSetting->allow_negative_salary ?? false) ? 'checked' : '' }} data-on="Allowed" data-off="Blocked"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('allow_negative_salary', $payrollSetting->allow_negative_salary ?? false) ? 'Allowed' : 'Blocked' }}</span></label></div></div>
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Lock Paid Payroll</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="lock_paid_payroll" value="1" {{ old('lock_paid_payroll', $payrollSetting->lock_paid_payroll ?? true) ? 'checked' : '' }} data-on="Locked" data-off="Editable"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('lock_paid_payroll', $payrollSetting->lock_paid_payroll ?? true) ? 'Locked' : 'Editable' }}</span></label></div></div>
                <div class="col-md-3"><div class="progga-form-group"><label class="progga-form-label">Previous / Next Month Payroll</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="allow_non_current_month_payroll" value="1" {{ old('allow_non_current_month_payroll', $payrollSetting->allow_non_current_month_payroll ?? false) ? 'checked' : '' }} data-on="Allowed" data-off="Current Only"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('allow_non_current_month_payroll', $payrollSetting->allow_non_current_month_payroll ?? false) ? 'Allowed' : 'Current Only' }}</span></label></div></div>
            </div>

            @can('hr-setting-update')
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save Payroll Settings</button>
                </div>
            @endcan
        </form>
    </div>
</div>
