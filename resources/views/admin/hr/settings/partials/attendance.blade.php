<div class="progga-card mb-3">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-clock-history me-2"></i>Global Attendance Schedule</div>
            <div class="hr-settings-help">Used only when an employee has no duty roster and no default shift.</div>
        </div>
    </div>
    <div class="progga-card-body">
        <form action="{{ route('hr.settings.attendance.update') }}" method="POST" id="hrAttendanceSettingForm">
            @csrf

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Global Start Time</label>
                        <input type="time" name="global_start_time" class="progga-form-control" value="{{ old('global_start_time', substr((string) ($attendanceSetting->global_start_time ?? '09:00:00'), 0, 5)) }}" required>
                        <div class="hr-settings-help">Fallback office start time.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Global End Time</label>
                        <input type="time" name="global_end_time" class="progga-form-control" value="{{ old('global_end_time', substr((string) ($attendanceSetting->global_end_time ?? '17:00:00'), 0, 5)) }}" required>
                        <div class="hr-settings-help">Fallback office end time.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Global Grace Time</label>
                        <div class="input-group">
                            <input type="number" name="grace_minutes" class="progga-form-control" min="0" max="240" value="{{ old('grace_minutes', $attendanceSetting->grace_minutes ?? 10) }}">
                            <span class="input-group-text">Minutes</span>
                        </div>
                        <div class="hr-settings-help">Applied after the resolved start time.</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Half Day After</label><div class="input-group"><input type="number" name="half_day_after_minutes" class="progga-form-control" min="1" max="1440" value="{{ old('half_day_after_minutes', $attendanceSetting->half_day_after_minutes ?? 240) }}"><span class="input-group-text">Minutes</span></div></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Absent After</label><div class="input-group"><input type="number" name="absent_after_minutes" class="progga-form-control" min="1" max="1440" value="{{ old('absent_after_minutes', $attendanceSetting->absent_after_minutes ?? 480) }}"><span class="input-group-text">Minutes</span></div></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Minimum Overtime</label><div class="input-group"><input type="number" name="minimum_overtime_minutes" class="progga-form-control" min="0" max="480" value="{{ old('minimum_overtime_minutes', $attendanceSetting->minimum_overtime_minutes ?? 30) }}"><span class="input-group-text">Minutes</span></div></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Default Working Hours</label><div class="input-group"><input type="number" step="0.25" name="default_working_hours" class="progga-form-control" min="1" max="24" value="{{ old('default_working_hours', $attendanceSetting->default_working_hours ?? 8) }}"><span class="input-group-text">Hours</span></div></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Weekly Off Days</label><select id="hrWeeklyOffDays" name="weekly_off_days[]" class="progga-select hr-select2" multiple>@php($selectedOffDays = old('weekly_off_days', $attendanceSetting->weekly_off_days ?? ['Friday']))@foreach(['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)<option value="{{ $day }}" {{ in_array($day, $selectedOffDays ?? []) ? 'selected' : '' }}>{{ $day }}</option>@endforeach</select><div class="hr-settings-help">Select one or more weekly off days.</div></div></div>

                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Manual Attendance</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="allow_manual_attendance" value="1" {{ old('allow_manual_attendance', $attendanceSetting->allow_manual_attendance ?? true) ? 'checked' : '' }} data-on="Allowed" data-off="Disabled"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('allow_manual_attendance', $attendanceSetting->allow_manual_attendance ?? true) ? 'Allowed' : 'Disabled' }}</span></label></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Automatic Late Calculation</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="auto_calculate_late" value="1" {{ old('auto_calculate_late', $attendanceSetting->auto_calculate_late ?? true) ? 'checked' : '' }} data-on="Enabled" data-off="Disabled"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('auto_calculate_late', $attendanceSetting->auto_calculate_late ?? true) ? 'Enabled' : 'Disabled' }}</span></label></div></div>
                <div class="col-md-4"><div class="progga-form-group"><label class="progga-form-label">Automatic Overtime Calculation</label><label class="progga-toggle" style="margin-top:8px;"><input type="checkbox" name="auto_calculate_overtime" value="1" {{ old('auto_calculate_overtime', $attendanceSetting->auto_calculate_overtime ?? true) ? 'checked' : '' }} data-on="Enabled" data-off="Disabled"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ old('auto_calculate_overtime', $attendanceSetting->auto_calculate_overtime ?? true) ? 'Enabled' : 'Disabled' }}</span></label></div></div>
            </div>

            <hr class="my-4">

            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                <div>
                    <div class="progga-card-title"><i class="bi bi-fingerprint me-2"></i>Tipsoi API</div>
                    <div class="hr-settings-help">Controls the complete Tipsoi integration for employee, attendance, device, enrollment and device assignment features. Demo and Live credentials are stored separately.</div>
                </div>
                <div class="text-end">
                    <div class="small fw-semibold mb-1">Enable TIPSOI Integration</div>
                    <label class="progga-toggle">
                        <input type="checkbox" name="tipsoi_enabled" value="1" {{ old('tipsoi_enabled', $attendanceSetting->tipsoi_enabled ?? false) ? 'checked' : '' }} data-on="Enabled" data-off="Disabled">
                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        <span class="progga-toggle-label">{{ old('tipsoi_enabled', $attendanceSetting->tipsoi_enabled ?? false) ? 'Enabled' : 'Disabled' }}</span>
                    </label>
                    <div class="hr-settings-help mt-1">OFF = no TIPSOI employee, attendance, device, enrollment or allocation API call will run.</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Active Mode</label>
                        <select name="tipsoi_mode" class="progga-select hr-select2" data-search="false">
                            <option value="demo" {{ old('tipsoi_mode', $attendanceSetting->tipsoi_mode ?? 'live') === 'demo' ? 'selected' : '' }}>Demo</option>
                            <option value="live" {{ old('tipsoi_mode', $attendanceSetting->tipsoi_mode ?? 'live') === 'live' ? 'selected' : '' }}>Live</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="progga-form-group">
                        <label class="progga-form-label">SSL Verification</label>
                        <select name="tipsoi_ssl_mode" class="progga-select hr-select2" data-search="false">
                            <option value="auto" {{ old('tipsoi_ssl_mode', $attendanceSetting->tipsoi_ssl_mode ?? 'auto') === 'auto' ? 'selected' : '' }}>Auto</option>
                            <option value="verify" {{ old('tipsoi_ssl_mode', $attendanceSetting->tipsoi_ssl_mode ?? 'auto') === 'verify' ? 'selected' : '' }}>Verify</option>
                            <option value="disable" {{ old('tipsoi_ssl_mode', $attendanceSetting->tipsoi_ssl_mode ?? 'auto') === 'disable' ? 'selected' : '' }}>Disable Verification</option>
                        </select>
                    </div>
                </div>
                <div class="col-12"><div class="hr-inline-note mb-0"><strong>Demo API</strong></div></div>
                <div class="col-md-7">
                    <div class="progga-form-group"><label class="progga-form-label">Demo Base URL</label><input type="url" name="tipsoi_demo_url" class="progga-form-control" value="{{ old('tipsoi_demo_url', $attendanceSetting->tipsoi_demo_url ?? 'https://test.api-inovace360.com/api/v1') }}"></div>
                </div>
                <div class="col-md-5">
                    <div class="progga-form-group"><label class="progga-form-label">Demo API Key</label><input type="password" name="tipsoi_demo_api_key" class="progga-form-control" value="" autocomplete="new-password" placeholder="{{ $attendanceSetting->tipsoi_demo_api_key ? 'Saved — leave blank to keep current key' : 'Enter demo API key' }}"></div>
                </div>

                <div class="col-12"><div class="hr-inline-note mb-0"><strong>Live API</strong></div></div>
                <div class="col-md-7">
                    <div class="progga-form-group"><label class="progga-form-label">Live Base URL</label><input type="url" name="tipsoi_live_url" class="progga-form-control" value="{{ old('tipsoi_live_url', $attendanceSetting->tipsoi_live_url ?? 'https://api-inovace360.com/api/v1') }}"></div>
                </div>
                <div class="col-md-5">
                    <div class="progga-form-group"><label class="progga-form-label">Live API Key</label><input type="password" name="tipsoi_live_api_key" class="progga-form-control" value="" autocomplete="new-password" placeholder="{{ $attendanceSetting->tipsoi_live_api_key ? 'Saved — leave blank to keep current key' : 'Enter live API key' }}"></div>
                </div>
            </div>

            @if($attendanceSetting->tipsoi_last_sync_at)
                <div class="hr-inline-note mt-3 mb-0">
                    <strong>Last Tipsoi Sync:</strong> {{ $attendanceSetting->tipsoi_last_sync_at->format('d M Y, h:i A') }}
                    · {{ strtoupper((string) ($attendanceSetting->tipsoi_last_sync_status ?? 'unknown')) }}
                    @if($attendanceSetting->tipsoi_last_sync_message)<br><span class="text-muted">{{ $attendanceSetting->tipsoi_last_sync_message }}</span>@endif
                </div>
            @endif

            @can('hr-setting-update')
                <div class="d-flex justify-content-end gap-2 mt-4 flex-wrap">
                    <button type="button" class="progga-btn progga-btn-outline" id="tipsoiTestConnectionBtn"><i class="bi bi-wifi"></i> Test Tipsoi Connection</button>
                    <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save Attendance & Tipsoi Settings</button>
                </div>
            @endcan
        </form>
    </div>
</div>
