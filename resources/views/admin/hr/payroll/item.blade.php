@extends('admin.master.master')

@section('title', $item->employee_name . ' Payroll')

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Employee Payroll Details</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.payroll.index') }}" class="progga-breadcrumb-item">Payroll</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.payroll.show', $run) }}" class="progga-breadcrumb-item">{{ $run->payroll_code }}</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">{{ $item->employee_code }}</span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($item->status === 'draft')
                    @can('payroll-approve')
                        <form action="{{ route('hr.payroll.items.approve', [$run, $item]) }}" method="POST" class="payroll-item-approve-form" data-title="Approve employee payroll?" data-text="Only {{ $item->employee_name }} payroll will be approved and locked for editing.">
                            @csrf
                            <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-patch-check"></i> Approve Employee</button>
                        </form>
                    @endcan
                @endif
                @can('payroll-payslip')
                    <a href="{{ route('hr.payroll.items.payslip', [$run, $item]) }}" target="_blank" class="progga-btn progga-btn-outline"><i class="bi bi-file-earmark-pdf"></i> Payslip PDF</a>
                @endcan
                <a href="{{ route('hr.payroll.show', $run) }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-left"></i> Back to Payroll</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="hr-card mb-3">
                    <div class="hr-card-body">
                        <div class="payroll-employee-hero">
                            <div class="payroll-avatar">{{ strtoupper(substr($item->employee_name, 0, 1)) }}</div>
                            <div>
                                <div class="hr-card-title">{{ $item->employee_name }}</div>
                                <div class="hr-muted">{{ $item->employee_code }}</div>
                                <div class="d-flex gap-1 flex-wrap mt-2">
                                    <span class="hr-badge hr-badge-primary">{{ $item->department_name ?: 'No department' }}</span>
                                    <span class="hr-badge hr-badge-neutral">{{ $item->designation_name ?: 'No designation' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hr-card mb-3">
                    <div class="hr-card-header"><div class="hr-card-title">Attendance Summary</div></div>
                    <div class="hr-card-body">
                        <div class="hr-info-list">
                            <div><span>Present</span><strong>{{ (float) $item->present_days }}</strong></div>
                            <div><span>Late</span><strong>{{ (float) $item->late_days }}</strong></div>
                            <div><span>Absent</span><strong>{{ (float) $item->absent_days }}</strong></div>
                            <div><span>Half Day</span><strong>{{ (float) $item->half_days }}</strong></div>
                            <div><span>Paid Leave</span><strong>{{ (float) $item->paid_leave_days }}</strong></div>
                            <div><span>Unpaid Leave</span><strong>{{ (float) $item->unpaid_leave_days }}</strong></div>
                            <div><span>Off Day</span><strong>{{ (float) $item->off_days }}</strong></div>
                            <div><span>Overtime</span><strong>{{ intdiv($item->overtime_minutes, 60) }}h {{ $item->overtime_minutes % 60 }}m</strong></div>
                            <div><span>Not Marked</span><strong>{{ $item->attendance_summary['not_marked'] ?? 0 }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="hr-card">
                    <div class="hr-card-header"><div class="hr-card-title">Payment Information</div></div>
                    <div class="hr-card-body">
                        <div class="hr-info-list">
                            @php
                                $workflowClass = match($item->status) { 'paid' => 'hr-badge-success', 'approved' => 'hr-badge-info', default => 'hr-badge-warning' };
                            @endphp
                            <div><span>Workflow</span><strong><span class="hr-badge {{ $workflowClass }}">{{ ucfirst($item->status) }}</span></strong></div>
                            <div><span>Payment</span><strong><span class="hr-badge {{ $item->payment_status === 'paid' ? 'hr-badge-success' : 'hr-badge-warning' }}">{{ ucfirst($item->payment_status) }}</span></strong></div>
                            <div><span>Method</span><strong>{{ ucwords(str_replace('_', ' ', $item->payment_method)) }}</strong></div>
                            @if($item->approved_at)
                                <div><span>Approved</span><strong>{{ $item->approved_at->format('d-m-Y h:i A') }}</strong></div>
                                <div><span>Approved By</span><strong>{{ $item->approvedBy->name ?? 'System' }}</strong></div>
                            @endif
                            @if($item->account_number)
                                <div><span>Account</span><strong>{{ $item->account_number }}</strong></div>
                            @endif
                            @if($item->payment)
                                <div><span>Paid Date</span><strong>{{ $item->payment->payment_date->format('d-m-Y') }}</strong></div>
                                <div><span>Reference</span><strong>{{ $item->payment->reference_number ?: 'N/A' }}</strong></div>
                            @endif
                        </div>
                        @if($item->status === 'approved' && $item->payment_status === 'unpaid')
                            @can('payroll-pay')
                                <button type="button" class="progga-btn progga-btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#payEmployeeModal"><i class="bi bi-cash-coin"></i> Record Salary Payment</button>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="hr-stat-grid payroll-item-stats">
                    <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-person-check"></i></div><div><div class="hr-stat-value payroll-money">৳{{ number_format((float) $item->prorated_basic_salary, 2) }}</div><div class="hr-stat-label">Payable Basic</div></div></div>
                    <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-plus-circle"></i></div><div><div class="hr-stat-value payroll-money" id="itemGrossDisplay">৳{{ number_format((float) $item->gross_salary, 2) }}</div><div class="hr-stat-label">Gross Salary</div></div></div>
                    <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-dash-circle"></i></div><div><div class="hr-stat-value payroll-money" id="itemDeductionDisplay">৳{{ number_format((float) $item->total_deduction, 2) }}</div><div class="hr-stat-label">Deduction</div></div></div>
                    <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-wallet2"></i></div><div><div class="hr-stat-value payroll-money" id="itemNetDisplay">৳{{ number_format((float) $item->net_salary, 2) }}</div><div class="hr-stat-label">Net Salary</div></div></div>
                </div>

                <form action="{{ route('hr.payroll.items.update', [$run, $item]) }}" method="POST" id="payrollItemForm">
                    @csrf
                    @method('PUT')
                    <div class="hr-card mb-3">
                        <div class="hr-card-header">
                            <div><div class="hr-card-title">Earnings</div><div class="hr-card-subtitle">Fixed, percentage, overtime and manual earnings.</div></div>
                        </div>
                        @include('admin.hr.payroll.partials.component_table', ['components' => $earnings, 'editable' => $item->status === 'draft'])
                    </div>

                    <div class="hr-card mb-3">
                        <div class="hr-card-header">
                            <div><div class="hr-card-title">Deductions</div><div class="hr-card-subtitle">Attendance, leave and manual deductions.</div></div>
                        </div>
                        @include('admin.hr.payroll.partials.component_table', ['components' => $deductions, 'editable' => $item->status === 'draft'])
                    </div>

                    <div class="hr-card mb-3">
                        <div class="hr-card-header"><div class="hr-card-title">Payroll Note</div></div>
                        <div class="hr-card-body">
                            <textarea name="notes" rows="3" class="progga-form-control" {{ $item->status === 'draft' ? '' : 'disabled' }}>{{ old('notes', $item->notes) }}</textarea>
                        </div>
                    </div>

                    @if($item->status === 'draft')
                        @can('payroll-edit')
                            <div class="hr-form-actions">
                                <a href="{{ route('hr.payroll.show', $run) }}" class="progga-btn progga-btn-outline">Cancel</a>
                                <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check2-circle"></i> Save Payroll Changes</button>
                            </div>
                        @endcan
                    @endif
                </form>
            </div>
        </div>
    </div>
</main>

@if($item->status === 'approved' && $item->payment_status === 'unpaid')
<div class="modal fade hr-modal" id="payEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Record Salary Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="{{ route('hr.payroll.items.pay', [$run, $item]) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="payroll-payment-amount mb-3"><span>Net Salary</span><strong>৳{{ number_format((float) $item->net_salary, 2) }}</strong></div>
                    <div class="mb-3"><label class="progga-form-label">Payment Date</label><input type="text" id="employeePaymentDate" name="payment_date" class="progga-form-control" value="{{ now()->toDateString() }}" required></div>
                    <div class="mb-3"><label class="progga-form-label">Payment Method</label><select name="payment_method" class="hr-select2" data-search="false" required><option value="cash" {{ $item->payment_method === 'cash' ? 'selected' : '' }}>Cash</option><option value="bank" {{ $item->payment_method === 'bank' ? 'selected' : '' }}>Bank</option><option value="mobile_banking" {{ $item->payment_method === 'mobile_banking' ? 'selected' : '' }}>Mobile Banking</option></select></div>
                    <div class="mb-3"><label class="progga-form-label">Reference Number</label><input type="text" name="reference_number" class="progga-form-control"></div>
                    <div><label class="progga-form-label">Note</label><textarea name="payment_notes" rows="3" class="progga-form-control"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check2-circle"></i> Confirm Payment</button></div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('script')
@include('admin.hr.shared.plugins')
<script>
$(function () {
    HrUi.initSelect2('.hr-select2');
    HrUi.initFlatpickr('#employeePaymentDate');

    function money(amount) {
        return '৳' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function calculatePreview() {
        let gross = 0;
        let deduction = 0;
        $('.payroll-component-amount').each(function () {
            const amount = parseFloat(this.value) || 0;
            if (this.dataset.type === 'earning') gross += amount;
            if (this.dataset.type === 'deduction') deduction += amount;

            const calculated = parseFloat(this.dataset.calculated) || 0;
            const changed = Math.abs(amount - calculated) >= 0.01;
            const row = $(this).closest('tr');
            row.toggleClass('payroll-overridden-row', changed);
            row.find('.payroll-override-reason-wrap').toggleClass('d-none', !changed || this.dataset.manual === '1');
        });
        $('#itemGrossDisplay').text(money(gross));
        $('#itemDeductionDisplay').text(money(deduction));
        $('#itemNetDisplay').text(money(Math.max(0, gross - deduction)));
    }

    $('.payroll-component-amount').on('input', calculatePreview);
    $('#payrollItemForm').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
    });

    $('.payroll-item-approve-form').on('submit', function (event) {
        event.preventDefault();
        const form = this;
        Swal.fire({
            title: form.dataset.title,
            text: form.dataset.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Approve Employee',
            confirmButtonColor: '#21352a'
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });

    @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')), timer: 1800, showConfirmButton: false });
    @endif
    @if(session('error'))
        Swal.fire('Error', @json(session('error')), 'error');
    @endif

    calculatePreview();
});
</script>
@endsection
