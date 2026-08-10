<div class="progga-table-wrapper" style="border:0;border-radius:0">
    <table class="progga-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Attendance</th>
                <th>Basic</th>
                <th>Gross</th>
                <th>Deduction</th>
                <th>Net Salary</th>
                <th>Workflow</th>
                <th>Payment</th>
                <th style="width:160px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                @php
                    $workflowClass = match($item->status) {
                        'paid' => 'hr-badge-success',
                        'approved' => 'hr-badge-info',
                        default => 'hr-badge-warning',
                    };
                @endphp
                <tr>
                    <td>{{ $items->firstItem() + $index }}</td>
                    <td>
                        <a href="{{ route('hr.payroll.items.show', [$run, $item]) }}" class="hr-person-name text-decoration-none">{{ $item->employee_name }}</a>
                        <div class="hr-person-meta">{{ $item->employee_code }} · {{ $item->department_name ?: 'No department' }} · {{ $item->designation_name ?: 'No designation' }}</div>
                    </td>
                    <td>
                        <div class="payroll-attendance-chips">
                            <span class="good">P {{ (float) $item->present_days }}</span>
                            <span class="warn">L {{ (float) $item->late_days }}</span>
                            <span class="bad">A {{ (float) $item->absent_days }}</span>
                            <span>HD {{ (float) $item->half_days }}</span>
                        </div>
                    </td>
                    <td>৳{{ number_format((float) $item->prorated_basic_salary, 2) }}</td>
                    <td>৳{{ number_format((float) $item->gross_salary, 2) }}</td>
                    <td class="text-danger">৳{{ number_format((float) $item->total_deduction, 2) }}</td>
                    <td><strong>৳{{ number_format((float) $item->net_salary, 2) }}</strong></td>
                    <td><span class="hr-badge {{ $workflowClass }}">{{ ucfirst($item->status) }}</span></td>
                    <td><span class="hr-badge {{ $item->payment_status === 'paid' ? 'hr-badge-success' : 'hr-badge-warning' }}">{{ ucfirst($item->payment_status) }}</span></td>
                    <td>
                        <div class="progga-table-actions">
                            <a href="{{ route('hr.payroll.items.show', [$run, $item]) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View details"><i class="bi bi-eye"></i></a>

                            @if($item->status === 'draft')
                                @can('payroll-approve')
                                    <form action="{{ route('hr.payroll.items.approve', [$run, $item]) }}" method="POST" class="d-inline payroll-item-approve-form" data-title="Approve {{ $item->employee_name }} payroll?" data-text="Only this employee payroll will be approved.">
                                        @csrf
                                        <button type="submit" class="progga-btn progga-btn-primary progga-btn-icon progga-btn-sm" title="Approve employee"><i class="bi bi-patch-check"></i></button>
                                    </form>
                                @endcan
                            @endif

                            @can('payroll-payslip')
                                <a href="{{ route('hr.payroll.items.payslip', [$run, $item]) }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Open payslip"><i class="bi bi-file-earmark-pdf"></i></a>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="hr-empty"><i class="bi bi-people"></i>No employee payroll record found.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('admin.partials.custom_pagination', ['paginator' => $items])
