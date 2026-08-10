<div class="attendance-table-result" data-summary="{{ e(json_encode($summary)) }}">
    <div class="progga-table-wrapper" style="border: 0; border-radius: 0;">
        <table class="progga-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Late / OT</th>
                    <th>Note</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($employees as $index => $employee)
                    @php
                        $attendance = $attendanceMap->get($employee->id);
                        $roster = $rosterMap->get($employee->id);

                        $selectedShift = $attendance?->shift_id
                            ?: ($roster?->shift_id ?: $employee->default_shift_id);

                        $status = $attendance?->status
                            ?: ($roster?->status === 'off'
                                ? 'off_day'
                                : ($roster?->status === 'scheduled'
                                    ? 'present'
                                    : ($isGlobalOffDay ? 'off_day' : 'present')));

                        $locked = $attendance?->source === 'system'
                            && $attendance?->status === 'leave';

                        $disableTimeFields = $locked
                            || in_array($status, ['absent', 'leave', 'off_day'], true);
                    @endphp

                    <tr
                        data-employee-id="{{ $employee->id }}"
                        data-locked="{{ $locked ? 1 : 0 }}"
                    >
                        <td>{{ $employees->firstItem() + $index }}</td>

                        <td>
                            <div class="hr-person">
                                <img
                                    class="hr-table-avatar"
                                    src="{{ $employee->image
                                        ? asset($employee->image)
                                        : 'https://ui-avatars.com/api/?name=' . urlencode($employee->name) . '&background=21352a&color=d5aa65&size=80' }}"
                                    alt="{{ $employee->name }}"
                                >

                                <div>
                                    <div class="hr-person-name">{{ $employee->name }}</div>
                                    <div class="hr-person-meta">
                                        {{ $employee->employee_code }} · {{ $employee->department->name ?? 'No department' }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td>
                            <select
                                class="progga-select attendance-input attendance-shift"
                                {{ $locked ? 'disabled' : '' }}
                            >
                                <option value="">No Shift</option>

                                @foreach ($shifts as $shift)
                                    <option
                                        value="{{ $shift->id }}"
                                        {{ (string) $selectedShift === (string) $shift->id ? 'selected' : '' }}
                                    >
                                        {{ $shift->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td>
                            <select
                                class="progga-select attendance-input attendance-status"
                                {{ $locked ? 'disabled' : '' }}
                            >
                                <option value="present" {{ $status === 'present' ? 'selected' : '' }}>Present</option>
                                <option value="late" {{ $status === 'late' ? 'selected' : '' }}>Late</option>
                                <option value="absent" {{ $status === 'absent' ? 'selected' : '' }}>Absent</option>
                                <option value="half_day" {{ $status === 'half_day' ? 'selected' : '' }}>Half Day</option>
                                <option value="leave" {{ $status === 'leave' ? 'selected' : '' }}>Leave</option>
                                <option value="off_day" {{ $status === 'off_day' ? 'selected' : '' }}>Off Day</option>
                            </select>
                        </td>

                        <td>
                            <input
                                type="text"
                                class="progga-form-control attendance-input attendance-time check-in"
                                value="{{ $attendance?->check_in?->format('H:i') }}"
                                {{ $disableTimeFields ? 'disabled' : '' }}
                            >
                        </td>

                        <td>
                            <input
                                type="text"
                                class="progga-form-control attendance-input attendance-time check-out"
                                value="{{ $attendance?->check_out?->format('H:i') }}"
                                {{ $disableTimeFields ? 'disabled' : '' }}
                            >
                        </td>

                        <td>
                            @if ($locked)
                                <span class="hr-badge hr-badge-info mb-1">
                                    <i class="bi bi-lock-fill"></i>
                                    Approved Leave
                                </span>
                            @endif

                            <div class="hr-muted">
                                Late: <strong>{{ $attendance?->late_minutes ?? 0 }}m</strong>
                            </div>

                            <div class="hr-muted">
                                OT: <strong>{{ $attendance?->overtime_minutes ?? 0 }}m</strong>
                            </div>
                        </td>

                        <td>
                            <input
                                type="text"
                                class="progga-form-control attendance-input attendance-note"
                                value="{{ $attendance?->notes }}"
                                placeholder="Optional"
                                {{ $locked ? 'disabled' : '' }}
                            >
                        </td>

                        <td>
                            @if ($attendance && $attendance->source !== 'system')
                                @can('attendance-delete')
                                    <button
                                        type="button"
                                        class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm attendance-delete-btn"
                                        data-id="{{ $attendance->id }}"
                                        title="Delete attendance"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="hr-empty">
                                <i class="bi bi-fingerprint"></i>
                                No active employees found.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.custom_pagination', ['paginator' => $employees])
</div>
