<div class="progga-table-wrapper" style="border:0;border-radius:0">
    <table class="progga-table">
        <thead>
            <tr>
                @can('employee-delete')
                    <th style="width:42px;text-align:center">
                        <input type="checkbox" class="form-check-input" id="employeeSelectAllVisible" title="Select all visible employees">
                    </th>
                @endcan
                <th>#</th>
                <th>Employee</th>
                <th>NID</th>
                <th>NID Image</th>
                <th>Department / Role</th>
                <th>Employment</th>
                <th>Shift</th>
                <th>Salary</th>
                <th>Access</th>
                <th>Status</th>
                <th style="width:156px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $index => $employee)
                @php
                    $salary = $employee->currentSalaryStructure;
                    $avatar = $employee->image
                        ? asset('public/' . ltrim($employee->image, '/'))
                        : 'https://ui-avatars.com/api/?name=' . urlencode($employee->name) . '&background=21352a&color=d5aa65&size=80&bold=true';
                @endphp
                <tr>
                    @can('employee-delete')
                        <td style="text-align:center">
                            <input
                                type="checkbox"
                                class="form-check-input employee-row-checkbox"
                                value="{{ $employee->id }}"
                                aria-label="Select {{ $employee->name }}"
                            >
                        </td>
                    @endcan
                    <td>{{ $employees->firstItem() + $index }}</td>
                    <td>
                        <div class="hr-person">
                            <img class="hr-table-avatar" src="{{ $avatar }}" alt="{{ $employee->name }}">
                            <div>
                                <a href="{{ route('hr.employees.show', $employee) }}" class="hr-person-name text-decoration-none">
                                    {{ $employee->name }}
                                </a>
                                <div class="hr-person-meta">{{ $employee->employee_code }} · {{ $employee->phone }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($employee->nid)
                            <span style="font-weight:700;white-space:nowrap">{{ $employee->nid }}</span>
                        @else
                            <span class="hr-muted">N/A</span>
                        @endif
                    </td>
                    <td>
                        @if($employee->nid_image)
                            <a href="{{ asset('public/' . ltrim($employee->nid_image, '/')) }}" target="_blank" rel="noopener" title="View NID image">
                                <img src="{{ asset('public/' . ltrim($employee->nid_image, '/')) }}" alt="NID of {{ $employee->name }}" style="width:64px;height:42px;object-fit:cover;border-radius:7px;border:1px solid var(--progga-border-light);background:#fff;">
                            </a>
                        @else
                            <span class="hr-muted">N/A</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:700">{{ $employee->department->name ?? 'Not assigned' }}</div>
                        <div class="hr-person-meta">{{ $employee->designation->name ?? 'No designation' }}</div>
                    </td>
                    <td>
                        <span class="hr-badge hr-badge-neutral">{{ $employee->employmentType->name ?? 'N/A' }}</span>
                        <div class="hr-person-meta mt-1">Joined {{ optional($employee->join_date)->format('d-m-Y') }}</div>
                    </td>
                    <td>
                        <div style="font-weight:700">{{ $employee->defaultShift->name ?? 'No shift' }}</div>
                        <div class="hr-person-meta">{{ $employee->defaultShift?->time_range ?? '' }}</div>
                    </td>
                    <td>
                        @if($salary)
                            <div style="font-weight:800">৳{{ number_format((float) $salary->basic_salary, 2) }}</div>
                            <div class="hr-person-meta">Effective {{ $salary->effective_from?->format('d-m-Y') ?? 'N/A' }}</div>
                        @else
                            <span class="hr-badge hr-badge-warning">Not configured</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            @if($employee->is_waiter)
                                <span class="hr-badge hr-badge-warning"><i class="bi bi-person-badge"></i> Waiter</span>
                            @endif
                            @if($employee->can_login)
                                <span class="hr-badge hr-badge-info"><i class="bi bi-box-arrow-in-right"></i> Login</span>
                            @endif
                            @if(!$employee->is_waiter && !$employee->can_login)
                                <span class="hr-muted">No access</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @can('employee-edit')
                            <select
                                class="progga-select employee-status-select"
                                data-id="{{ $employee->id }}"
                                data-current="{{ $employee->employment_status }}"
                                style="min-width:112px;padding:7px 28px 7px 9px"
                            >
                                <option value="active" {{ $employee->employment_status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $employee->employment_status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="resigned" {{ $employee->employment_status === 'resigned' ? 'selected' : '' }}>Resigned</option>
                                <option value="terminated" {{ $employee->employment_status === 'terminated' ? 'selected' : '' }}>Terminated</option>
                            </select>
                        @else
                            <span class="hr-badge {{ $employee->employment_status === 'active' ? 'hr-badge-success' : 'hr-badge-neutral' }}">
                                {{ $employee->employment_status }}
                            </span>
                        @endcan
                    </td>
                    <td>
                        <div class="progga-table-actions">
                            @can('employee-view')
                                <a
                                    href="{{ route('hr.employees.show', $employee) }}"
                                    class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                                    title="View employee"
                                >
                                    <i class="bi bi-eye"></i>
                                </a>
                            @endcan

                            @can('employee-edit')
                                <a
                                    href="{{ route('hr.employees.edit', $employee) }}"
                                    class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                                    title="Edit employee"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>
                            @endcan

                            @can('employee-salary-view')
                                <a
                                    href="{{ route('hr.employees.edit', $employee) . '#employeePayrollSetupCard' }}"
                                    class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                                    title="Salary setup"
                                >
                                    <i class="bi bi-wallet2"></i>
                                </a>
                            @endcan

                            @can('employee-delete')
                                <button
                                    type="button"
                                    class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm employee-delete-btn"
                                    data-id="{{ $employee->id }}"
                                    title="Delete employee"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="@can('employee-delete')12 @else 11 @endcan">
                        <div class="hr-empty">
                            <i class="bi bi-people"></i>
                            No employees found.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.partials.custom_pagination', ['paginator' => $employees])
