<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-sliders me-2"></i>General HR Configuration</div>
            <div class="hr-settings-help">Employee numbering, default access and regional display settings.</div>
        </div>
    </div>
    <div class="progga-card-body">
        <form action="{{ route('hr.settings.general.update') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Employee Code Prefix <span class="progga-required">*</span></label>
                        <input type="text" name="employee_code_prefix" class="progga-form-control" value="{{ old('employee_code_prefix', $hrSetting->employee_code_prefix ?? 'EMP') }}" required>
                        <div class="hr-settings-help">Example: EMP, STAFF or REST-EMP</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Next Employee Number <span class="progga-required">*</span></label>
                        <input type="number" name="employee_code_next_number" class="progga-form-control" min="1" value="{{ old('employee_code_next_number', $hrSetting->employee_code_next_number ?? 1) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Number Padding <span class="progga-required">*</span></label>
                        <select id="hrEmployeeCodePadding" name="employee_code_padding" class="progga-select hr-select2" required>
                            @foreach([2, 3, 4, 5, 6] as $padding)
                                <option value="{{ $padding }}" {{ (int) old('employee_code_padding', $hrSetting->employee_code_padding ?? 4) === $padding ? 'selected' : '' }}>{{ $padding }} digits</option>
                            @endforeach
                        </select>
                        <div class="hr-settings-help">Preview: {{ ($hrSetting->employee_code_prefix ?? 'EMP') }}-{{ str_pad((string) ($hrSetting->employee_code_next_number ?? 1), (int) ($hrSetting->employee_code_padding ?? 4), '0', STR_PAD_LEFT) }}</div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Default Probation Period</label>
                        <div class="input-group">
                            <input type="number" name="default_probation_months" class="progga-form-control" min="0" max="24" value="{{ old('default_probation_months', $hrSetting->default_probation_months ?? 3) }}">
                            <span class="input-group-text">Months</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Default Notice Period</label>
                        <div class="input-group">
                            <input type="number" name="default_notice_period_days" class="progga-form-control" min="0" max="365" value="{{ old('default_notice_period_days', $hrSetting->default_notice_period_days ?? 30) }}">
                            <span class="input-group-text">Days</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Date Format</label>
                        <select id="hrDateFormat" name="date_format" class="progga-select hr-select2">
                            @foreach(['d-m-Y' => 'DD-MM-YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY'] as $value => $label)
                                <option value="{{ $value }}" {{ old('date_format', $hrSetting->date_format ?? 'd-m-Y') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Time Format</label>
                        <select id="hrTimeFormat" name="time_format" class="progga-select hr-select2">
                            <option value="h:i A" {{ old('time_format', $hrSetting->time_format ?? 'h:i A') === 'h:i A' ? 'selected' : '' }}>12 Hour (02:30 PM)</option>
                            <option value="H:i" {{ old('time_format', $hrSetting->time_format ?? 'h:i A') === 'H:i' ? 'selected' : '' }}>24 Hour (14:30)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Timezone</label>
                        <select id="hrTimezone" name="timezone" class="progga-select hr-select2">
                            <option value="Asia/Dhaka" {{ old('timezone', $hrSetting->timezone ?? 'Asia/Dhaka') === 'Asia/Dhaka' ? 'selected' : '' }}>Asia/Dhaka</option>
                            <option value="UTC" {{ old('timezone', $hrSetting->timezone ?? 'Asia/Dhaka') === 'UTC' ? 'selected' : '' }}>UTC</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Employee Login Access</label>
                        <label class="progga-toggle" style="margin-top:8px;">
                            <input type="checkbox" name="allow_employee_login" value="1" {{ old('allow_employee_login', $hrSetting->allow_employee_login ?? true) ? 'checked' : '' }} data-on="Allowed" data-off="Disabled">
                            <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                            <span class="progga-toggle-label">{{ old('allow_employee_login', $hrSetting->allow_employee_login ?? true) ? 'Allowed' : 'Disabled' }}</span>
                        </label>
                        <div class="hr-settings-help">When enabled, a login account can be created for selected employees.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Waiter / POS Access</label>
                        <label class="progga-toggle" style="margin-top:8px;">
                            <input type="checkbox" name="allow_waiter_access" value="1" {{ old('allow_waiter_access', $hrSetting->allow_waiter_access ?? true) ? 'checked' : '' }} data-on="Allowed" data-off="Disabled">
                            <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                            <span class="progga-toggle-label">{{ old('allow_waiter_access', $hrSetting->allow_waiter_access ?? true) ? 'Allowed' : 'Disabled' }}</span>
                        </label>
                        <div class="hr-settings-help">When enabled, an employee can be linked with the existing Waiter/POS module.</div>
                    </div>
                </div>
            </div>

            @can('hr-setting-update')
            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save General Settings</button>
            </div>
            @endcan
        </form>
    </div>
</div>
