@extends('admin.master.master')
@section('title', 'Loan — '.$restaurantSettingName)
@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Loan</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('hr.dashboard') }}" class="progga-breadcrumb-item">HR Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Loan</span>
                </div>
            </div>
            @can('loan-create')
                <button class="progga-btn progga-btn-primary" id="loanAddBtn">
                    <i class="bi bi-plus-lg"></i> Add Loan
                </button>
            @endcan
        </div>

        <div class="hr-stat-grid">
            <div class="hr-stat-card">
                <div class="hr-stat-icon"><i class="bi bi-bank"></i></div>
                <div>
                    <div class="hr-stat-value">{{ $activeCount }}</div>
                    <div class="hr-stat-label">Active Loans</div>
                </div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-icon"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="hr-stat-value">৳{{ number_format((float) $totalIssued, 2) }}</div>
                    <div class="hr-stat-label">Loan Amount Issued</div>
                </div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-icon"><i class="bi bi-arrow-return-left"></i></div>
                <div>
                    <div class="hr-stat-value">৳{{ number_format((float) $totalRecovered, 2) }}</div>
                    <div class="hr-stat-label">Repaid</div>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-body">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-4">
                        <select id="loanEmployeeFilter" class="hr-select2">
                            <option value="">All Employees</option>
                            @foreach($employees as $e)
                                <option value="{{ $e->id }}">{{ $e->employee_code }} — {{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <select id="loanStatus" class="hr-select2" data-search="false">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <button id="loanReset" class="progga-btn progga-btn-outline w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </div>
                    <div class="col-lg-3">
                        <div class="hr-search">
                            <i class="bi bi-search"></i>
                            <input id="loanSearch" class="progga-form-control" placeholder="Type or paste to search">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card" id="loanTable">
            <div class="hr-empty py-5">
                <div class="spinner-border spinner-border-sm"></div>
                <div class="mt-2">Loading...</div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade progga-modal" id="loanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Loan</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="loanForm">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="progga-form-label">Employee *</label>
                            <select name="employee_id" class="progga-select loan-select" required>
                                <option value="">Select Employee</option>
                                @foreach($employees as $e)
                                    <option value="{{ $e->id }}">{{ $e->employee_code }} — {{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="progga-form-label">Loan Date *</label>
                            <input type="date" name="loan_date" class="progga-form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="progga-form-label">Loan Amount (Principal) *</label>
                            <input type="number" step="0.01" min="0.01" name="principal_amount" class="progga-form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="progga-form-label">Interest Rule *</label>
                            <select name="interest_type" id="loanInterestType" class="progga-select loan-select" required>
                                <option value="none">No Interest</option>
                                <option value="flat_percent">Flat Percentage</option>
                                <option value="fixed_amount">Fixed Interest Amount</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">Interest Value</label>
                            <input type="number" step="0.01" min="0" name="interest_value" class="progga-form-control" value="0">
                            <div class="hr-muted mt-1" id="loanInterestHint">No interest will be added.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">Total Payable</label>
                            <input id="loanTotalPayable" class="progga-form-control" value="0.00" readonly tabindex="-1">
                            <div class="hr-muted mt-1">Auto: Loan Amount + Interest</div>
                        </div>

                        <div class="col-md-4">
                            <label class="progga-form-label">Number of Installments *</label>
                            <input type="number" min="1" max="120" step="1" name="number_of_installments" class="progga-form-control" value="1" required>
                            <div class="hr-muted mt-1">How many monthly installments will be used.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">Monthly Installment</label>
                            <input id="loanMonthlyInstallment" class="progga-form-control" value="0.00" readonly tabindex="-1">
                            <div class="hr-muted mt-1">Auto: Total Payable ÷ Number of Installments</div>
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">Repayment Start Month *</label>
                            <input type="month" id="loanRepaymentMonth" class="progga-form-control" value="{{ now()->format('Y-m') }}" required>
                            <input type="hidden" name="repayment_start_month">
                        </div>

                        <div class="col-12">
                            <div class="alert alert-light border mb-0 py-2 px-3">
                                <strong>Installment rule:</strong> payroll deducts the calculated monthly installment from the repayment start month. The final installment automatically uses the remaining balance, so rounding never over-recovers the loan.
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="progga-form-label">Notes</label>
                            <textarea name="notes" class="progga-form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary">Save Loan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade progga-modal" id="loanRepayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Loan Repayment</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="loanRepayForm">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="progga-form-label">Outstanding</label>
                        <input id="loanOutstanding" class="progga-form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="progga-form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="progga-form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="progga-form-label">Amount *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="progga-form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="progga-form-label">Reference</label>
                        <input name="reference_number" class="progga-form-control">
                    </div>
                    <div>
                        <label class="progga-form-label">Notes</label>
                        <textarea name="notes" class="progga-form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="progga-btn progga-btn-outline" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="progga-btn progga-btn-primary" type="submit">Record Repayment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
@include('admin.hr.shared.plugins')
<script>
$(function () {
    let page = 1;
    let timer;

    HrUi.initSelect2('.hr-select2');
    HrUi.initSelect2('.loan-select');

    function load(p = 1) {
        page = p;
        $('#loanTable').addClass('hr-table-loading');

        $.get(@json(route('hr.loans.index')), {
            page,
            search: $('#loanSearch').val(),
            employee_id: HrUi.selectValue('loanEmployeeFilter'),
            status: HrUi.selectValue('loanStatus')
        }).done(html => {
            $('#loanTable').html(html);
        }).fail(xhr => {
            Swal.fire('Error', xhr.responseJSON?.message || 'Failed to load loans.', 'error');
        }).always(() => {
            $('#loanTable').removeClass('hr-table-loading');
        });
    }

    function syncMonth() {
        const month = $('#loanRepaymentMonth').val();
        $('#loanForm [name="repayment_start_month"]').val(month ? month + '-01' : '');
    }

    function calculateLoan() {
        const principal = parseFloat($('#loanForm [name="principal_amount"]').val()) || 0;
        const interestValue = parseFloat($('#loanForm [name="interest_value"]').val()) || 0;
        const interestType = $('#loanInterestType').val();
        const installments = Math.max(1, parseInt($('#loanForm [name="number_of_installments"]').val(), 10) || 1);

        let interest = 0;
        if (interestType === 'flat_percent') {
            interest = principal * interestValue / 100;
        } else if (interestType === 'fixed_amount') {
            interest = interestValue;
        }

        const totalPayable = Math.round((principal + interest) * 100) / 100;
        const monthlyInstallment = Math.round((totalPayable / installments) * 100) / 100;

        $('#loanTotalPayable').val(totalPayable.toFixed(2));
        $('#loanMonthlyInstallment').val(monthlyInstallment.toFixed(2));

        const noInterest = interestType === 'none';
        $('#loanForm [name="interest_value"]').prop('disabled', noInterest);
        if (noInterest) {
            $('#loanForm [name="interest_value"]').val('0');
        }

        $('#loanInterestHint').text(
            interestType === 'flat_percent'
                ? 'Percentage is calculated once on the Loan Amount (Principal).'
                : (interestType === 'fixed_amount'
                    ? 'This fixed interest amount is added once.'
                    : 'No interest will be added.')
        );
    }

    $('#loanSearch').on('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => load(1), 300);
    });

    $('.hr-select2').on('change', () => load(1));

    $('#loanReset').on('click', () => {
        $('#loanSearch').val('');
        HrUi.resetSelect('loanEmployeeFilter');
        HrUi.resetSelect('loanStatus');
        load(1);
    });

    $(document).on('click', '#loanTable .report-page-link:not(.disabled)', function (e) {
        e.preventDefault();
        load(new URL(this.href).searchParams.get('page') || 1);
    });

    $('#loanAddBtn').on('click', () => {
        $('#loanForm')[0].reset();
        $('#loanForm [name="id"]').val('');
        $('#loanForm [name="employee_id"]').val('').trigger('change');
        $('#loanInterestType').val('none').trigger('change');
        $('#loanForm [name="number_of_installments"]').val(1);
        $('#loanRepaymentMonth').val(@json(now()->format('Y-m')));
        $('#loanForm [name="loan_date"]').val(@json(now()->toDateString()));
        syncMonth();
        calculateLoan();
        $('#loanModal').modal('show');
    });

    $('#loanForm [name="principal_amount"], #loanForm [name="interest_value"], #loanForm [name="number_of_installments"], #loanInterestType')
        .on('input change', calculateLoan);

    $('#loanRepaymentMonth').on('change', syncMonth);

    $(document).on('click', '.loan-edit', function () {
        const record = JSON.parse(this.dataset.record);
        const total = parseFloat(record.total_payable) || 0;
        const oldInstallment = parseFloat(record.installment_amount) || 0;
        const fallbackCount = oldInstallment > 0 ? Math.max(1, Math.ceil(total / oldInstallment)) : 1;

        $('#loanForm [name="id"]').val(record.id);
        $('#loanForm [name="employee_id"]').val(record.employee_id).trigger('change');
        $('#loanForm [name="loan_date"]').val(String(record.loan_date).substring(0, 10));
        $('#loanForm [name="principal_amount"]').val((parseFloat(record.principal_amount) || 0).toFixed(2));
        $('#loanInterestType').val(record.interest_type).trigger('change');
        $('#loanForm [name="interest_value"]').val((parseFloat(record.interest_value) || 0).toFixed(2));
        $('#loanForm [name="number_of_installments"]').val(record.number_of_installments || fallbackCount);
        $('#loanRepaymentMonth').val(String(record.repayment_start_month).substring(0, 7));
        $('#loanForm [name="notes"]').val(record.notes || '');

        syncMonth();
        calculateLoan();
        $('#loanModal').modal('show');
    });

    $('#loanForm').on('submit', function (e) {
        e.preventDefault();
        syncMonth();

        const id = $(this).find('[name="id"]').val();

        $.ajax({
            url: id ? @json(url('/hr/loans')) + '/' + id : @json(route('hr.loans.store')),
            type: id ? 'PUT' : 'POST',
            data: $(this).serialize()
        }).done(response => {
            $('#loanModal').modal('hide');
            Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: response.message,
                timer: 1300,
                showConfirmButton: false
            });
            load(page);
        }).fail(xhr => {
            Swal.fire(
                'Error',
                xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || 'Could not save.',
                'error'
            );
        });
    });

    $(document).on('click', '.loan-repay', function () {
        $('#loanRepayForm')[0].reset();
        $('#loanRepayForm [name="id"]').val(this.dataset.id);
        $('#loanOutstanding').val('৳' + this.dataset.outstanding);
        $('#loanRepayForm [name="payment_date"]').val(@json(now()->toDateString()));
        $('#loanRepayModal').modal('show');
    });

    $('#loanRepayForm').on('submit', function (e) {
        e.preventDefault();
        const id = $(this).find('[name="id"]').val();

        $.post(@json(url('/hr/loans')) + '/' + id + '/repay', $(this).serialize())
            .done(response => {
                $('#loanRepayModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Recorded',
                    text: response.message,
                    timer: 1300,
                    showConfirmButton: false
                });
                load(page);
            }).fail(xhr => {
                Swal.fire('Error', xhr.responseJSON?.message || 'Could not record repayment.', 'error');
            });
    });

    $(document).on('click', '.loan-delete', function () {
        const id = this.dataset.id;

        Swal.fire({
            title: 'Delete loan?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#c63d3d'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: @json(url('/hr/loans')) + '/' + id,
                type: 'DELETE',
                data: {_token: @json(csrf_token())}
            }).done(response => {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted',
                    text: response.message,
                    timer: 1200,
                    showConfirmButton: false
                });
                load(page);
            }).fail(xhr => {
                Swal.fire('Error', xhr.responseJSON?.message || 'Could not delete.', 'error');
            });
        });
    });

    load();
});
</script>
@endsection
