@php
    $statusLabels = [
        'present' => 'Present',
        'late' => 'Late',
        'absent' => 'Absent',
        'half_day' => 'Half Day',
        'leave' => 'Leave',
        'off_day' => 'Off Day',
        'not_marked' => 'Not Marked',
        'not_applicable' => 'N/A',
        'future' => 'Future',
    ];

    $statusCodes = [
        'present' => 'P',
        'late' => 'L',
        'absent' => 'A',
        'half_day' => 'HD',
        'leave' => 'LV',
        'off_day' => 'O',
        'not_marked' => 'NM',
        'not_applicable' => '—',
        'future' => '—',
    ];

    $formatMinutes = function ($minutes) {
        $minutes = (int) $minutes;
        return floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm';
    };
@endphp

@if ($reportType === 'monthly_summary')
    <div class="hr-card-header">
        <div>
            <div class="hr-card-title">All Employees — {{ $monthLabel }}</div>
            <div class="hr-card-subtitle">Daily attendance matrix with monthly totals. Swipe the table only when viewing on a small screen.</div>
        </div>
        <span class="hr-badge hr-badge-info">{{ $employees->total() }} Employee(s)</span>
    </div>

    <div class="attendance-matrix-legend">
        <span><strong>P</strong> Present</span>
        <span><strong>L</strong> Late</span>
        <span><strong>A</strong> Absent</span>
        <span><strong>HD</strong> Half Day</span>
        <span><strong>LV</strong> Leave</span>
        <span><strong>O</strong> Off Day</span>
        <span><strong>NM</strong> Not Marked</span>
    </div>

    <div class="progga-table-wrapper attendance-matrix-wrap" style="border:0;border-radius:0">
        <table class="progga-table attendance-month-matrix">
            <thead>
                <tr>
                    <th class="matrix-sticky matrix-employee">Employee</th>
                    <th class="matrix-sticky-2 matrix-department">Department</th>
                    @foreach ($days as $day)
                        <th class="matrix-day-head">{{ $day }}</th>
                    @endforeach
                    <th class="matrix-total">P</th>
                    <th class="matrix-total">L</th>
                    <th class="matrix-total">A</th>
                    <th class="matrix-total">HD</th>
                    <th class="matrix-total">LV</th>
                    <th class="matrix-total">O</th>
                    <th class="matrix-total">NM</th>
                    <th class="matrix-time">OT</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    @php $summary = $summaryMap[$employee->id]; @endphp
                    <tr>
                        <td class="matrix-sticky matrix-employee">
                            <div class="hr-person-name">{{ $employee->name }}</div>
                            <div class="hr-person-meta">{{ $employee->employee_code }} · {{ $employee->designation->name ?? 'No designation' }}</div>
                        </td>
                        <td class="matrix-sticky-2 matrix-department">{{ $employee->department->name ?? '—' }}</td>

                        @foreach ($days as $day)
                            @php
                                $date = \Carbon\Carbon::createFromFormat('F Y', $monthLabel)->day($day);
                                $entry = $dayMap[$employee->id . '|' . $date->toDateString()] ?? null;
                                $status = $entry['status'] ?? 'not_marked';
                                $label = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                            @endphp
                            <td
                                class="matrix-day status-cell-{{ $status }}"
                                title="{{ $date->format('d-m-Y') }} — {{ $label }}{{ $entry && $entry['shift'] ? ' — ' . $entry['shift']->name : '' }}"
                            >
                                {{ $statusCodes[$status] ?? '—' }}
                            </td>
                        @endforeach

                        <td class="matrix-total"><strong>{{ $summary['present'] }}</strong></td>
                        <td class="matrix-total"><strong>{{ $summary['late'] }}</strong></td>
                        <td class="matrix-total"><strong>{{ $summary['absent'] }}</strong></td>
                        <td class="matrix-total">{{ $summary['half_day'] }}</td>
                        <td class="matrix-total">{{ $summary['leave'] }}</td>
                        <td class="matrix-total">{{ $summary['off_day'] }}</td>
                        <td class="matrix-total">{{ $summary['not_marked'] }}</td>
                        <td class="matrix-time">{{ $formatMinutes($summary['overtime_minutes']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="40">
                            <div class="hr-empty"><i class="bi bi-calendar-x"></i>No employees found for this month.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.custom_pagination', ['paginator' => $employees])
@else
    <div class="hr-card-header">
        <div>
            <div class="hr-card-title">{{ $employee->name }} — {{ $monthLabel }}</div>
            <div class="hr-card-subtitle">{{ $employee->employee_code }} · {{ $employee->department->name ?? 'No department' }} · {{ $employee->designation->name ?? 'No designation' }}</div>
        </div>
    </div>

    <div class="report-summary-strip">
        <div class="report-mini-stat"><strong>{{ $summary['present'] }}</strong><span>Present</span></div>
        <div class="report-mini-stat"><strong>{{ $summary['late'] }}</strong><span>Late</span></div>
        <div class="report-mini-stat"><strong>{{ $summary['absent'] }}</strong><span>Absent</span></div>
        <div class="report-mini-stat"><strong>{{ $summary['half_day'] }}</strong><span>Half Day</span></div>
        <div class="report-mini-stat"><strong>{{ $summary['leave'] }}</strong><span>Leave</span></div>
        <div class="report-mini-stat"><strong>{{ $summary['off_day'] }}</strong><span>Off Day</span></div>
        <div class="report-mini-stat"><strong>{{ $summary['not_marked'] }}</strong><span>Not Marked</span></div>
        <div class="report-mini-stat"><strong>{{ $formatMinutes($summary['overtime_minutes']) }}</strong><span>Overtime</span></div>
    </div>

    <div class="progga-table-wrapper" style="border:0;border-radius:0">
        <table class="progga-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Status</th>
                    <th>Shift</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Worked</th>
                    <th>Late</th>
                    <th>OT</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        $attendance = $row['attendance'];
                        $shift = $row['shift'];
                    @endphp
                    <tr>
                        <td>{{ $row['date']->format('d-m-Y') }}</td>
                        <td>{{ $row['date']->format('D') }}</td>
                        <td><span class="attendance-status-dot status-{{ $row['status'] }}">{{ $statusLabels[$row['status']] ?? ucfirst($row['status']) }}</span></td>
                        <td>{{ $shift?->name ?? '—' }}</td>
                        <td>{{ $attendance?->check_in?->format('h:i A') ?? '—' }}</td>
                        <td>{{ $attendance?->check_out?->format('h:i A') ?? '—' }}</td>
                        <td>{{ $formatMinutes($attendance?->worked_minutes ?? 0) }}</td>
                        <td>{{ $formatMinutes($attendance?->late_minutes ?? 0) }}</td>
                        <td>{{ $formatMinutes($attendance?->overtime_minutes ?? 0) }}</td>
                        <td>{{ $attendance?->notes ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('admin.partials.custom_pagination', ['paginator' => $rows])
@endif
