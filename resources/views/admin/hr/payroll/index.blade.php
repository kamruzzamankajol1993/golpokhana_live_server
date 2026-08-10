@extends('admin.master.master')

@section('title', 'Payroll')

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Payroll</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item">Human Resources</span>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Payroll</span>
                </div>
            </div>
            @can('payroll-create')
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('hr.payroll.single.create') }}" class="progga-btn progga-btn-outline">
                        <i class="bi bi-person-plus-fill"></i> Single Employee Payroll
                    </a>
                    <a href="{{ route('hr.payroll.create') }}" class="progga-btn progga-btn-primary">
                        <i class="bi bi-people-fill"></i> Generate All Employees
                    </a>
                </div>
            @endcan
        </div>

        <div class="alert {{ $allowNonCurrentMonth ? 'alert-success' : 'alert-secondary' }} d-flex align-items-start gap-2 mb-3">
            <i class="bi {{ $allowNonCurrentMonth ? 'bi-calendar2-check' : 'bi-calendar2-lock' }} mt-1"></i>
            <div>
                <strong>Payroll Month Policy:</strong>
                {{ $allowNonCurrentMonth ? 'Previous and next month payroll is allowed.' : 'Only current month payroll is allowed.' }}
                @can('hr-setting-update')
                    <a href="{{ route('hr.settings.index', ['tab' => 'payroll']) }}" class="ms-1">Change setting</a>
                @endcan
            </div>
        </div>

        <div class="hr-stat-grid">
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-journal-text"></i></div><div><div class="hr-stat-value">{{ $totalRuns }}</div><div class="hr-stat-label">Payroll Runs</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-pencil-square"></i></div><div><div class="hr-stat-value">{{ $draftRuns }}</div><div class="hr-stat-label">Draft</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-patch-check-fill"></i></div><div><div class="hr-stat-value">{{ $approvedRuns }}</div><div class="hr-stat-label">Approved</div></div></div>
            <div class="hr-stat-card"><div class="hr-stat-icon"><i class="bi bi-cash-coin"></i></div><div><div class="hr-stat-value">{{ $paidRuns }}</div><div class="hr-stat-label">Paid</div></div></div>
        </div>

        @if($currentRun)
            <div class="hr-card mb-3 payroll-current-banner">
                <div class="hr-card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <div>
                        <div class="hr-card-title">{{ $currentRun->month_label }} payroll is {{ $currentRun->status }}</div>
                        <div class="hr-card-subtitle">{{ $currentRun->payroll_code }} · Net payable ৳{{ number_format((float) $currentRun->total_net, 2) }}</div>
                    </div>
                    <a href="{{ route('hr.payroll.show', $currentRun) }}" class="progga-btn progga-btn-outline"><i class="bi bi-eye"></i> Open Current Payroll</a>
                </div>
            </div>
        @endif

        <div class="hr-card mb-3">
            <div class="hr-card-body">
                <div class="hr-filter-grid payroll-run-filter">
                    <div class="hr-search"><i class="bi bi-search"></i><input type="text" id="payrollSearch" class="progga-form-control" placeholder="Search payroll code or employee"></div>
                    <input type="month" id="payrollMonth" class="progga-form-control">
                    <select id="payrollStatus" class="hr-select2" data-search="false">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button type="button" class="progga-btn progga-btn-outline" id="payrollReset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                </div>
            </div>
        </div>

        <div class="hr-card" id="payrollTableContainer">
            <div class="hr-empty"><div class="spinner-border spinner-border-sm"></div><div class="mt-2">Loading payroll runs...</div></div>
        </div>
    </div>
</main>
@endsection

@section('script')
@include('admin.hr.shared.plugins')
<script>
$(function () {
    let currentPage = 1;
    let searchTimer;
    HrUi.initSelect2('.hr-select2');

    function loadRuns(page) {
        currentPage = page || 1;
        const box = $('#payrollTableContainer').addClass('hr-table-loading');
        $.get("{{ route('hr.payroll.index') }}", {
            page: currentPage,
            search: $('#payrollSearch').val(),
            month: $('#payrollMonth').val(),
            status: HrUi.selectValue('payrollStatus')
        }).done(function (html) {
            box.html(html);
        }).fail(function () {
            Swal.fire('Error', 'Failed to load payroll runs.', 'error');
        }).always(function () {
            box.removeClass('hr-table-loading');
        });
    }

    $('#payrollSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadRuns(1); }, 350);
    });
    $('#payrollMonth, #payrollStatus').on('change', function () { loadRuns(1); });
    $('#payrollReset').on('click', function () {
        $('#payrollSearch, #payrollMonth').val('');
        HrUi.resetSelect('payrollStatus');
        loadRuns(1);
    });
    $(document).on('click', '#payrollTableContainer .report-page-link:not(.disabled)', function (e) {
        e.preventDefault();
        const url = new URL(this.href);
        loadRuns(url.searchParams.get('page') || 1);
    });
    $(document).on('click', '.payroll-delete-btn', function () {
        const url = this.dataset.url;
        Swal.fire({
            title: 'Delete draft payroll?',
            text: 'All generated employee payroll details will be removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete Draft',
            confirmButtonColor: '#b33030'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({ url: url, type: 'DELETE', data: { _token: @json(csrf_token()) } })
                .done(function (response) {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: response.message, timer: 1600, showConfirmButton: false });
                    loadRuns(currentPage);
                })
                .fail(function (xhr) { Swal.fire('Cannot delete', xhr.responseJSON?.message || 'Delete failed.', 'error'); });
        });
    });

    @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')), timer: 1800, showConfirmButton: false });
    @endif
    @if(session('error'))
        Swal.fire('Error', @json(session('error')), 'error');
    @endif

    loadRuns(1);
});
</script>
@endsection
