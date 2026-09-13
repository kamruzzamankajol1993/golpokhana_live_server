@extends('admin.master.master')

@section('title', 'Attendance — ' . $restaurantSettingName)

@section('css')
    @include('admin.hr.shared.styles')
    <style>
        .attendance-row-dirty{background:rgba(213,170,101,.07)!important}
        .attendance-time{min-width:105px}.attendance-note{min-width:140px}.attendance-status{min-width:120px}.attendance-shift{min-width:150px}
        .attendance-summary-value{transition:all .2s}
        .attendance-action-bar{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
        .attendance-upload-drop{border:1.5px dashed var(--progga-border);border-radius:12px;padding:22px;text-align:center;background:rgba(213,170,101,.05)}
        .attendance-upload-drop i{font-size:28px;color:var(--progga-secondary-dark);display:block;margin-bottom:8px}
        .attendance-import-help{font-size:12px;color:var(--progga-text-muted);line-height:1.7}
        @media(max-width:767px){.attendance-action-bar{width:100%;justify-content:stretch}.attendance-action-bar .progga-btn{flex:1}}
    </style>
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Attendance</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item">Human Resources</span>
                    <span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Attendance</span>
                </div>
            </div>
            <div class="attendance-action-bar">
                @can('attendance-view')
                    <a href="{{ route('hr.attendance.reports.index') }}" class="progga-btn progga-btn-outline">
                        <i class="bi bi-file-earmark-bar-graph"></i> Reports
                    </a>
                @endcan
                @canany(['attendance-create','attendance-edit'])
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-toggle="modal" data-bs-target="#attendanceTemplateModal">
                        <i class="bi bi-file-earmark-arrow-down"></i> Excel Template
                    </button>
                    <button type="button" class="progga-btn progga-btn-secondary" data-bs-toggle="modal" data-bs-target="#attendanceImportModal">
                        <i class="bi bi-file-earmark-arrow-up"></i> Import Excel
                    </button>
                @endcanany
                @canany(['attendance-create','attendance-edit'])
                    <button id="saveAttendanceBtn" class="progga-btn progga-btn-primary"><i class="bi bi-check2-circle"></i> Save Visible</button>
                @endcanany
            </div>
        </div>

        <div class="hr-stat-grid">
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-people"></i></div><div><div class="hr-stat-value attendance-summary-value" data-summary="total">{{ $summary['total'] }}</div><div class="hr-stat-label">Active Employees</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-person-check"></i></div><div><div class="hr-stat-value attendance-summary-value" data-summary="present">{{ $summary['present'] }}</div><div class="hr-stat-label">Present</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-clock-history"></i></div><div><div class="hr-stat-value attendance-summary-value" data-summary="late">{{ $summary['late'] }}</div><div class="hr-stat-label">Late</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-person-x"></i></div><div><div class="hr-stat-value attendance-summary-value" data-summary="absent">{{ $summary['absent'] }}</div><div class="hr-stat-label">Absent</div></div></div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div><div class="hr-card-title">Daily Attendance Sheet</div><div class="hr-card-subtitle">Select a date, mark exceptions and save the visible page.</div></div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="markAllPresent"><i class="bi bi-check-all"></i> Mark Visible Present</button>
                    <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="resetVisible"><i class="bi bi-arrow-counterclockwise"></i> Reload Visible</button>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="hr-filter-grid five">
                    <div class="hr-search"><i class="bi bi-search"></i><input type="text" id="attendanceSearch" class="progga-form-control" placeholder="Search employee"></div>
                    <div><label class="progga-form-label">Attendance Date</label><input id="attendanceDate" class="progga-form-control" value="{{ $date }}"></div>
                    <div><label class="progga-form-label">Department</label><select id="attendanceDepartment" class="attendance-select2"><option value="">All Departments</option>@foreach($departments as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
                    <div><label class="progga-form-label">Shift</label><select id="attendanceShiftFilter" class="attendance-select2"><option value="">All Shifts</option>@foreach($shifts as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
                    <div><label class="progga-form-label">Status</label><select id="attendanceStatusFilter" class="attendance-select2" data-search="false"><option value="">All Status</option><option value="not_marked">Not Marked</option><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="half_day">Half Day</option><option value="leave">Leave</option><option value="off_day">Off Day</option></select></div>
                    <button type="button" id="attendanceReset" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Reset Filters</button>
                </div>
            </div>
        </div>

        <div class="hr-card" id="attendanceTableContainer">
            @include('admin.hr.attendance.table', $initialTableData)
        </div>
    </div>
</main>

@include('admin.hr.attendance.modals.template')
@include('admin.hr.attendance.modals.import')
@endsection

@section('script')
    @include('admin.hr.shared.plugins')
    <script>
    $(function () {
        let currentPage = Number($('#attendanceTableContainer .attendance-table-result').data('current-page') || 1);
        let searchTimer = null;
        let activeAttendanceRequest = null;
        let attendancePageInitializing = true;
        const attendanceUses12Hour = @json(($timeFormat ?? 'h:i A') === 'h:i A');

        HrUi.initSelect2('.attendance-select2');
        HrUi.initSelect2($('#attendanceTableContainer'));
        const datePicker = HrUi.initFlatpickr('#attendanceDate', {
            onChange: function () {
                if (!attendancePageInitializing) loadAttendance(1);
            }
        });

        function initTimePickers() {
            document.querySelectorAll('#attendanceTableContainer .attendance-time').forEach(function (element) {
                const wasDisabled = element.disabled;
                const picker = HrUi.initFlatpickr(element, {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: 'H:i',
                    altInput: attendanceUses12Hour,
                    altFormat: attendanceUses12Hour ? 'h:i K' : 'H:i',
                    time_24hr: !attendanceUses12Hour,
                    onChange: function () {
                        $(element).closest('tr').addClass('attendance-row-dirty');
                        updateRowMetrics($(element).closest('tr'));
                    }
                });

                if (picker?.altInput) {
                    picker.altInput.disabled = wasDisabled;
                    picker.altInput.setAttribute('autocomplete', 'off');
                }
            });

            refreshLiveMetrics();
        }

        function parseTimeMinutes(value) {
            if (!value || !/^\d{1,2}:\d{2}$/.test(value)) return null;
            const parts = value.split(':').map(Number);
            if (parts[0] > 23 || parts[1] > 59) return null;
            return (parts[0] * 60) + parts[1];
        }

        function liveAttendanceSettings() {
            const result = $('#attendanceTableContainer .attendance-table-result');
            return {
                minimumOvertime: Number(result.attr('data-minimum-overtime') || 0),
                autoOvertime: Number(result.attr('data-auto-overtime')) !== 0
            };
        }

        function updateRowMetrics(row) {
            if (!row || !row.length) return;

            const status = row.find('.attendance-status').val();
            if (['absent', 'leave', 'off_day'].includes(status)) {
                row.find('.attendance-late-preview').text('0m');
                row.find('.attendance-ot-preview').text('0m');
                return;
            }

            const shiftOption = row.find('.attendance-shift option:selected').get(0);
            const shiftStart = parseTimeMinutes(shiftOption?.dataset.start || '');
            let shiftEnd = parseTimeMinutes(shiftOption?.dataset.end || '');
            const checkIn = parseTimeMinutes(row.find('input.check-in').first().val());
            let checkOut = parseTimeMinutes(row.find('input.check-out').first().val());

            let lateMinutes = 0;
            let overtimeMinutes = 0;

            if (shiftStart !== null && shiftEnd !== null) {
                const overnight = Number(shiftOption?.dataset.overnight || 0) === 1 || shiftEnd <= shiftStart;
                if (overnight) shiftEnd += 1440;

                let normalizedCheckOut = checkOut;
                if (normalizedCheckOut !== null && checkIn !== null && normalizedCheckOut <= checkIn) {
                    normalizedCheckOut += 1440;
                }

                const grace = Number(shiftOption?.dataset.grace || 0);
                if (checkIn !== null && checkIn > shiftStart + grace) {
                    lateMinutes = Math.max(0, checkIn - shiftStart);
                }

                const settings = liveAttendanceSettings();
                if (normalizedCheckOut !== null && normalizedCheckOut > shiftEnd && settings.autoOvertime) {
                    const rawOvertime = normalizedCheckOut - shiftEnd;
                    overtimeMinutes = rawOvertime >= settings.minimumOvertime ? rawOvertime : 0;
                }
            }

            row.find('.attendance-late-preview').text(lateMinutes + 'm');
            row.find('.attendance-ot-preview').text(overtimeMinutes + 'm');
        }

        function refreshLiveMetrics() {
            $('#attendanceTableContainer tbody tr[data-employee-id]').each(function () {
                updateRowMetrics($(this));
            });
        }

        function setRowTimeDisabled(row, disabled) {
            row.find('input.attendance-time').each(function () {
                const picker = this._flatpickr;
                this.disabled = disabled;
                if (picker?.altInput) picker.altInput.disabled = disabled;
                if (disabled) {
                    if (picker) picker.clear();
                    else this.value = '';
                }
            });
        }

        function updateSummary(summary) {
            if (!summary) return;
            Object.keys(summary).forEach(function (key) {
                $('[data-summary="' + key + '"]').text(summary[key]);
            });
        }

        function loadAttendance(page) {
            currentPage = page || 1;
            const container = $('#attendanceTableContainer').addClass('hr-table-loading');

            if (activeAttendanceRequest && activeAttendanceRequest.readyState !== 4) {
                activeAttendanceRequest.abort();
            }

            const request = $.get("{{ route('hr.attendance.index') }}", {
                page: currentPage,
                date: $('#attendanceDate').val(),
                search: $('#attendanceSearch').val(),
                department_id: HrUi.selectValue('attendanceDepartment'),
                shift_id: HrUi.selectValue('attendanceShiftFilter'),
                status: HrUi.selectValue('attendanceStatusFilter')
            });
            activeAttendanceRequest = request;

            request.done(function (html) {
                container.html(html);
                HrUi.initSelect2(container);
                const rawSummary = container.find('.attendance-table-result').attr('data-summary');
                if (rawSummary) {
                    try { updateSummary(JSON.parse(rawSummary)); } catch (error) {}
                }
                initTimePickers();
            }).fail(function (xhr, statusText) {
                if (statusText === 'abort') return;
                Swal.fire('Error', 'Failed to load attendance.', 'error');
            }).always(function () {
                if (activeAttendanceRequest === request) {
                    container.removeClass('hr-table-loading');
                    activeAttendanceRequest = null;
                }
            });
        }

        $('#attendanceSearch').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { loadAttendance(1); }, 350);
        });
        $('.attendance-select2').on('change', function () { loadAttendance(1); });

        $('#attendanceReset').on('click', function () {
            $('#attendanceSearch').val('');
            ['attendanceDepartment', 'attendanceShiftFilter', 'attendanceStatusFilter'].forEach(function (id) { HrUi.resetSelect(id); });
            datePicker.setDate(new Date(), true);
        });

        $('#resetVisible').on('click', function () { loadAttendance(currentPage); });

        $(document).on('click', '#attendanceTableContainer .report-page-link:not(.disabled)', function (event) {
            event.preventDefault();
            const url = new URL(this.href);
            loadAttendance(url.searchParams.get('page') || 1);
        });

        $(document).on('change input', '.attendance-input', function () {
            $(this).closest('tr').addClass('attendance-row-dirty');
        });

        $(document).on('change', '.attendance-shift', function () {
            updateRowMetrics($(this).closest('tr'));
        });

        $(document).on('input change', '.attendance-time', function () {
            updateRowMetrics($(this).closest('tr'));
        });

        $(document).on('change', '.attendance-status', function () {
            const row = $(this).closest('tr');
            const disabled = ['absent', 'leave', 'off_day'].includes(this.value);
            setRowTimeDisabled(row, disabled);
            updateRowMetrics(row);
        });

        $('#markAllPresent').on('click', function () {
            $('#attendanceTableContainer tbody tr[data-employee-id]').each(function () {
                if (Number($(this).data('locked')) === 1) return;
                $(this).find('.attendance-status').val('present').trigger('change');
                $(this).addClass('attendance-row-dirty');
            });
        });

        $('#saveAttendanceBtn').on('click', function () {
            const rows = [];
            $('#attendanceTableContainer tbody tr[data-employee-id]').each(function () {
                const row = $(this);
                if (Number(row.data('locked')) === 1) return;
                rows.push({
                    employee_id: row.data('employee-id'),
                    shift_id: row.find('.attendance-shift').val() || null,
                    status: row.find('.attendance-status').val(),
                    check_in: row.find('.check-in').val() || null,
                    check_out: row.find('.check-out').val() || null,
                    notes: row.find('.attendance-note').val() || null
                });
            });

            if (!rows.length) return Swal.fire('No records', 'No editable employee records are visible.', 'info');

            const button = $(this).prop('disabled', true);
            $.ajax({
                url: "{{ route('hr.attendance.bulk.store') }}",
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: "{{ csrf_token() }}",
                    attendance_date: $('#attendanceDate').val(),
                    rows: rows
                })
            }).done(function (response) {
                updateSummary(response.summary);
                Swal.fire({ icon: 'success', title: 'Saved', text: response.message, timer: 1800, showConfirmButton: false });
                loadAttendance(currentPage);
            }).fail(function (xhr) {
                Swal.fire('Could not save', xhr.responseJSON?.message || 'Please check attendance data.', 'error');
            }).always(function () {
                button.prop('disabled', false);
            });
        });

        $('#attendanceImportForm').on('submit', function (event) {
            event.preventDefault();
            const form = this;
            const button = $('#attendanceImportSubmit').prop('disabled', true);
            const formData = new FormData(form);

            Swal.fire({
                title: 'Importing attendance',
                text: 'Please wait while the Excel file is processed.',
                allowOutsideClick: false,
                didOpen: function () { Swal.showLoading(); }
            });

            $.ajax({
                url: form.action,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                const errors = normalizeErrors(response.errors).map(escapeHtml);
                const warning = errors.length
                    ? '<div class="text-start mt-3"><strong>' + response.error_count + ' row warning(s):</strong><div style="max-height:220px;overflow:auto;font-size:12px;margin-top:8px">' + errors.join('<br>') + '</div></div>'
                    : '';
                Swal.fire({ icon: errors.length ? 'warning' : 'success', title: 'Import Complete', html: '<p>' + escapeHtml(response.message) + '</p>' + warning });
                bootstrap.Modal.getInstance(document.getElementById('attendanceImportModal'))?.hide();
                form.reset();
                loadAttendance(1);
            }).fail(function (xhr) {
                const response = xhr.responseJSON || {};
                const errors = normalizeErrors(response.errors).map(escapeHtml);
                Swal.fire({
                    icon: 'error',
                    title: 'Import Failed',
                    html: '<p>' + escapeHtml(response.message || 'The attendance file could not be imported.') + '</p>' + (errors.length ? '<div class="text-start" style="max-height:220px;overflow:auto;font-size:12px">' + errors.join('<br>') + '</div>' : '')
                });
            }).always(function () {
                button.prop('disabled', false);
            });
        });

        function normalizeErrors(errors) {
            if (!errors) return [];
            if (Array.isArray(errors)) return errors;
            if (typeof errors === 'object') {
                return Object.values(errors).flatMap(function (item) {
                    return Array.isArray(item) ? item : [item];
                });
            }
            return [String(errors)];
        }

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        $(document).on('click', '.attendance-delete-btn', function () {
            const attendanceId = this.dataset.id;
            Swal.fire({ title: 'Remove attendance?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove' }).then(function (result) {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: "{{ url('/hr/attendance') }}/" + attendanceId,
                    type: 'DELETE',
                    data: { _token: "{{ csrf_token() }}" }
                }).done(function (response) {
                    updateSummary(response.summary);
                    Swal.fire({ icon: 'success', title: 'Removed', text: response.message, timer: 1400, showConfirmButton: false });
                    loadAttendance(currentPage);
                }).fail(function (xhr) {
                    Swal.fire('Cannot remove', xhr.responseJSON?.message || 'Failed.', 'error');
                });
            });
        });

        initTimePickers();
        attendancePageInitializing = false;
    });
    </script>
@endsection
