@extends('admin.master.master')

@section('title', $employee->name . ' — Employee Profile')

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Employee Profile</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.employees.index') }}" class="progga-breadcrumb-item">Employees</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">{{ $employee->employee_code }}</span>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                @can('employee-salary-view')
                    <a href="{{ route('hr.employees.edit', $employee) . '#employeePayrollSetupCard' }}" class="progga-btn progga-btn-secondary">
                        <i class="bi bi-wallet2"></i> Salary & Payroll Setup
                    </a>
                @endcan
                @can('employee-edit')
                    <a href="{{ route('hr.employees.edit', $employee) }}" class="progga-btn progga-btn-primary">
                        <i class="bi bi-pencil-square"></i> Edit Employee
                    </a>
                @endcan
            </div>
        </div>

        @php
            $avatar = $employee->image
                ? asset('public/' . ltrim($employee->image, '/'))
                : 'https://ui-avatars.com/api/?name=' . urlencode($employee->name) . '&background=21352a&color=d5aa65&size=180&bold=true';
            $salary = $employee->currentSalaryStructure;
        @endphp

        <div class="hr-profile-hero mb-3">
            <img src="{{ $avatar }}" alt="{{ $employee->name }}" class="hr-profile-image-lg">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h2 class="mb-0" style="font-size:24px;font-weight:900;color:var(--progga-text)">{{ $employee->name }}</h2>
                    <span class="hr-badge {{ $employee->employment_status === 'active' ? 'hr-badge-success' : 'hr-badge-neutral' }}">
                        {{ $employee->employment_status }}
                    </span>
                </div>
                <div class="hr-profile-code">{{ $employee->employee_code }}</div>
                <div class="d-flex gap-2 flex-wrap mt-2">
                    <span class="hr-badge hr-badge-primary"><i class="bi bi-building"></i> {{ $employee->department->name ?? 'No department' }}</span>
                    <span class="hr-badge hr-badge-neutral"><i class="bi bi-person-workspace"></i> {{ $employee->designation->name ?? 'No designation' }}</span>
                    @if($employee->is_waiter)
                        <span class="hr-badge hr-badge-warning"><i class="bi bi-person-badge"></i> Waiter / POS</span>
                    @endif
                    @if($employee->can_login)
                        <span class="hr-badge hr-badge-info"><i class="bi bi-box-arrow-in-right"></i> Login Enabled</span>
                    @endif
                </div>
            </div>
            <div class="hr-profile-contact">
                <div><i class="bi bi-telephone"></i> {{ $employee->phone }}</div>
                <div><i class="bi bi-envelope"></i> {{ $employee->email ?: 'No email' }}</div>
                <div><i class="bi bi-calendar-check"></i> Joined {{ optional($employee->join_date)->format('d-m-Y') }}</div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="hr-stat-card h-100">
                    <div class="hr-stat-icon"><i class="bi bi-person-check"></i></div>
                    <div><div class="hr-stat-value">{{ $attendanceSummary['present'] }}</div><div class="hr-stat-label">Present This Month</div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="hr-stat-card h-100">
                    <div class="hr-stat-icon"><i class="bi bi-clock-history"></i></div>
                    <div><div class="hr-stat-value">{{ $attendanceSummary['late'] }}</div><div class="hr-stat-label">Late This Month</div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="hr-stat-card h-100">
                    <div class="hr-stat-icon"><i class="bi bi-person-x"></i></div>
                    <div><div class="hr-stat-value">{{ $attendanceSummary['absent'] }}</div><div class="hr-stat-label">Absent This Month</div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="hr-stat-card h-100">
                    <div class="hr-stat-icon"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="hr-stat-value" style="font-size:18px">
                            {{ $salary ? '৳' . number_format((float) $salary->basic_salary, 2) : 'Not Set' }}
                        </div>
                        <div class="hr-stat-label">Basic Salary</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="hr-card mb-3">
                    <div class="hr-card-header"><div class="hr-card-title">Employment Details</div></div>
                    <div class="hr-card-body">
                        <div class="hr-info-list">
                            <div><span>Department</span><strong>{{ $employee->department->name ?? 'Not assigned' }}</strong></div>
                            <div><span>Designation</span><strong>{{ $employee->designation->name ?? 'Not assigned' }}</strong></div>
                            <div><span>Employment Type</span><strong>{{ $employee->employmentType->name ?? 'Not assigned' }}</strong></div>
                            <div><span>Default Shift</span><strong>{{ $employee->defaultShift->name ?? 'Not assigned' }}</strong></div>
                            <div><span>Shift Time</span><strong>{{ $employee->defaultShift?->time_range ?? 'N/A' }}</strong></div>
                            <div><span>Probation End</span><strong>{{ optional($employee->probation_end_date)->format('d-m-Y') ?: 'N/A' }}</strong></div>
                            <div><span>Exit Date</span><strong>{{ optional($employee->exit_date)->format('d-m-Y') ?: 'N/A' }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="hr-card mb-3">
                    <div class="hr-card-header"><div class="hr-card-title">Access Details</div></div>
                    <div class="hr-card-body">
                        <div class="hr-info-list">
                            <div><span>Waiter / POS</span><strong>{{ $employee->is_waiter ? 'Enabled' : 'Disabled' }}</strong></div>
                            <div><span>Assigned Zone</span><strong>{{ $employee->zone->name ?? 'N/A' }}</strong></div>
                            <div><span>System Login</span><strong>{{ $employee->can_login ? 'Enabled' : 'Disabled' }}</strong></div>
                            <div><span>User Role</span><strong>{{ $employee->user?->roles?->pluck('name')->join(', ') ?: 'N/A' }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="hr-card">
                    <div class="hr-card-header"><div class="hr-card-title">Personal & Emergency</div></div>
                    <div class="hr-card-body">
                        <div class="hr-info-list">
                            <div><span>Gender</span><strong>{{ ucfirst($employee->gender ?: 'N/A') }}</strong></div>
                            <div><span>Date of Birth</span><strong>{{ optional($employee->date_of_birth)->format('d-m-Y') ?: 'N/A' }}</strong></div>
                            <div><span>NID Number</span><strong>{{ $employee->nid ?: 'N/A' }}</strong></div>
                            <div><span>Emergency Contact</span><strong>{{ $employee->emergency_contact_name ?: 'N/A' }}</strong></div>
                            <div><span>Emergency Phone</span><strong>{{ $employee->emergency_contact_phone ?: 'N/A' }}</strong></div>
                        </div>
                        @if($employee->address)
                            <div class="mt-3"><div class="hr-muted mb-1">Address</div><div>{{ $employee->address }}</div></div>
                        @endif
                        @if($employee->nid_image)
                            <div class="mt-3">
                                <div class="hr-muted mb-1">NID Image</div>
                                <a href="{{ asset('public/' . ltrim($employee->nid_image, '/')) }}" target="_blank" rel="noopener">
                                    <img src="{{ asset('public/' . ltrim($employee->nid_image, '/')) }}" alt="NID of {{ $employee->name }}" style="width:100%;max-width:280px;height:170px;object-fit:contain;border:1px solid var(--progga-border-light);border-radius:10px;background:#fff;">
                                </a>
                            </div>
                        @endif
                        @if($employee->notes)
                            <div class="mt-3"><div class="hr-muted mb-1">Notes</div><div>{{ $employee->notes }}</div></div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="hr-card mb-3">
                    <div class="hr-card-header">
                        <div>
                            <div class="hr-card-title">Salary Structure</div>
                            <div class="hr-card-subtitle">Employee-wise salary setup. Payroll calculation will use this later.</div>
                        </div>
                        @can('employee-salary-view')
                            <a href="{{ route('hr.employees.edit', $employee) . '#employeePayrollSetupCard' }}" class="progga-btn progga-btn-outline progga-btn-sm">
                                <i class="bi bi-gear"></i> Configure Salary
                            </a>
                        @endcan
                    </div>
                    <div class="hr-card-body">
                        @if($salary)
                            <div class="row g-3">
                                <div class="col-md-4"><div class="hr-money-box"><span>Basic Salary</span><strong>৳{{ number_format((float) $salary->basic_salary, 2) }}</strong></div></div>
                                <div class="col-md-4"><div class="hr-money-box"><span>Estimated Gross</span><strong>৳{{ number_format((float) $salary->estimated_gross, 2) }}</strong></div></div>
                                <div class="col-md-4"><div class="hr-money-box"><span>Effective From</span><strong>{{ $salary->effective_from->format('d-m-Y') }}</strong></div></div>
                            </div>
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                @foreach($salary->components as $component)
                                    @if($component->is_active && ($component->rule_mode ?: 'custom') !== 'disabled')
                                        @php
                                            $masterComponent = $component->salaryComponent;
                                            $usingGlobal = ($component->rule_mode ?: 'custom') === 'global';
                                            $globalConfigured = !$usingGlobal || !$masterComponent || $masterComponent->global_configured;
                                        @endphp
                                        <span class="hr-badge {{ $component->component_type === 'earning' ? 'hr-badge-success' : 'hr-badge-danger' }}">
                                            {{ $masterComponent->name ?? 'Component' }}:
                                            @if(!$globalConfigured)
                                                Not Configured
                                            @elseif($component->calculation_type === 'fixed')
                                                ৳{{ number_format((float) $component->effective_amount, 2) }}
                                            @elseif($component->calculation_type === 'percentage')
                                                {{ number_format((float) $component->effective_percentage, 2) }}%
                                            @else
                                                Manual
                                            @endif
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="hr-empty py-4">
                                <i class="bi bi-wallet2"></i>
                                Salary structure has not been configured for this employee.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="hr-card mb-3">
                    <div class="hr-card-header">
                        <div><div class="hr-card-title">Upcoming Duty Roster</div><div class="hr-card-subtitle">Next seven days.</div></div>
                    </div>
                    <div class="hr-card-body">
                        @if($upcomingRoster->isNotEmpty())
                            <div class="hr-roster-strip">
                                @foreach($upcomingRoster as $roster)
                                    <div class="hr-roster-day">
                                        <span>{{ $roster->roster_date->format('D') }}</span>
                                        <strong>{{ $roster->roster_date->format('d M') }}</strong>
                                        <small>{{ $roster->status === 'off' ? 'OFF' : ($roster->status === 'leave' ? 'LEAVE' : ($roster->shift->name ?? 'No Shift')) }}</small>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="hr-muted">No duty roster is assigned for the next seven days.</div>
                        @endif
                    </div>
                </div>

                <div class="hr-card mb-3">
                    <div class="hr-card-header"><div class="hr-card-title">Recent Attendance</div></div>
                    <div class="progga-table-wrapper" style="border:0;border-radius:0">
                        <table class="progga-table">
                            <thead><tr><th>Date</th><th>Shift</th><th>Status</th><th>Check In</th><th>Check Out</th><th>OT</th></tr></thead>
                            <tbody>
                                @forelse($recentAttendances as $attendance)
                                    <tr>
                                        <td>{{ $attendance->attendance_date->format('d-m-Y') }}</td>
                                        <td>{{ $attendance->shift->name ?? 'N/A' }}</td>
                                        <td><span class="hr-badge hr-badge-{{ in_array($attendance->status, ['present', 'late']) ? 'success' : ($attendance->status === 'absent' ? 'danger' : 'neutral') }}">{{ str_replace('_', ' ', $attendance->status) }}</span></td>
                                        <td>{{ optional($attendance->check_in)->format('h:i A') ?: '—' }}</td>
                                        <td>{{ optional($attendance->check_out)->format('h:i A') ?: '—' }}</td>
                                        <td>{{ $attendance->overtime_minutes ? intdiv($attendance->overtime_minutes, 60) . 'h ' . ($attendance->overtime_minutes % 60) . 'm' : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><div class="hr-empty py-4">No attendance records found.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="hr-card h-100">
                            <div class="hr-card-header"><div class="hr-card-title">Leave Balances {{ now()->year }}</div></div>
                            <div class="hr-card-body">
                                @forelse($leaveBalances as $balance)
                                    <div class="hr-balance-row">
                                        <span>{{ $balance->leaveType->name ?? 'Leave' }}</span>
                                        <strong>{{ number_format($balance->available_days, 1) }} days</strong>
                                    </div>
                                @empty
                                    <div class="hr-muted">Leave balances are not initialized.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hr-card h-100">
                            <div class="hr-card-header"><div class="hr-card-title">Recent Leave Requests</div></div>
                            <div class="hr-card-body">
                                @forelse($recentLeaves as $leave)
                                    <div class="hr-balance-row">
                                        <div>
                                            <strong>{{ $leave->leaveType->name ?? 'Leave' }}</strong>
                                            <div class="hr-muted">{{ $leave->from_date->format('d-m-Y') }} to {{ $leave->to_date->format('d-m-Y') }}</div>
                                        </div>
                                        <span class="hr-badge hr-badge-{{ $leave->status === 'approved' ? 'success' : ($leave->status === 'rejected' ? 'danger' : 'warning') }}">{{ $leave->status }}</span>
                                    </div>
                                @empty
                                    <div class="hr-muted">No leave request found.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
$(function () {
    @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')), timer: 1800, showConfirmButton: false });
    @endif
    @if(session('error'))
        Swal.fire('Error', @json(session('error')), 'error');
    @endif
});
</script>
@endsection
