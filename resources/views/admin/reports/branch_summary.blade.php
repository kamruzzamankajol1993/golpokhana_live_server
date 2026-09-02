@extends('admin.master.master')
@section('title', 'Branch Performance - ' . ($restaurantSettingName ?? 'Restaurant'))

@section('body')
<main class="progga-content">
<div class="progga-page-header">
    <div>
        <h1 class="progga-page-title">Branch Performance</h1>
        <div class="progga-breadcrumb">
            <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
            <span class="progga-breadcrumb-sep">/</span>
            <span class="progga-breadcrumb-item active">Branch Performance</span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('reports.branch_summary.pdf', request()->query()) }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <a href="{{ route('reports.branch_summary.excel', request()->query()) }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="{{ route('reports.branch_summary.csv', request()->query()) }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a>
    </div>
</div>

<div class="progga-card mb-3">
    <div class="progga-card-body">
        <form method="GET" action="{{ route('reports.branch_summary') }}" class="report-filter-line" id="branchSummaryFilter">
            @include('admin.reports.partials.branch_filter', ['branchSelectId' => 'branchPerformanceBranchFilter'])
            <div class="progga-form-group">
                <label class="progga-form-label">Filter Type</label>
                <select name="filter_type" id="summaryFilterType" class="progga-select">
                    <option value="year" {{ $filterType === 'year' ? 'selected' : '' }}>Year Wise</option>
                    <option value="month" {{ $filterType === 'month' ? 'selected' : '' }}>Month Wise</option>
                    <option value="date" {{ $filterType === 'date' ? 'selected' : '' }}>Date Range</option>
                </select>
            </div>
            <div class="progga-form-group" id="summaryYearField">
                <label class="progga-form-label">Year</label>
                <select name="year" class="progga-select">
                    @foreach($yearOptions as $yr)
                        <option value="{{ $yr }}" {{ (int)$year === (int)$yr ? 'selected' : '' }}>{{ $yr }}</option>
                    @endforeach
                </select>
            </div>
            <div class="progga-form-group" id="summaryMonthField" style="display:{{ $filterType === 'month' ? 'block' : 'none' }};">
                <label class="progga-form-label">Month</label>
                <select name="month" class="progga-select">
                    @for($m=1; $m<=12; $m++)
                        <option value="{{ $m }}" {{ (int)$month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            <div id="summaryDateFields" style="display:{{ $filterType === 'date' ? 'flex' : 'none' }};gap:10px;align-items:flex-end;">
                <div class="progga-form-group">
                    <label class="progga-form-label">From</label>
                    <input class="progga-form-control" type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                </div>
                <div class="progga-form-group">
                    <label class="progga-form-label">To</label>
                    <input class="progga-form-control" type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}">
                </div>
            </div>
            <div style="margin-top:auto;display:flex;gap:8px;">
                <button class="progga-btn progga-btn-primary progga-btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
                <a href="{{ route('reports.branch_summary') }}" class="progga-btn progga-btn-outline progga-btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Completed Orders</div><div class="fs-4 fw-bold">{{ number_format($totals['orders']) }}</div></div></div></div>
    <div class="col-md-3"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Sales</div><div class="fs-4 fw-bold">৳{{ number_format($totals['sales'], 0) }}</div></div></div></div>
    <div class="col-md-3"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Active Employees</div><div class="fs-4 fw-bold">{{ number_format($totals['active_employees']) }}</div></div></div></div>
    <div class="col-md-3"><div class="progga-card"><div class="progga-card-body"><div class="text-muted small">Payroll Paid</div><div class="fs-4 fw-bold">৳{{ number_format($totals['payroll_paid'], 0) }}</div></div></div></div>
</div>

<div class="progga-card">
    <div class="progga-card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Branch-wise Summary</h5>
            <div class="text-muted small">{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</div>
        </div>
        <span class="progga-badge progga-badge-primary">{{ $reportScope['label'] }}</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Branch</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">Sales</th>
                    <th class="text-end">Due</th>
                    <th class="text-end">QR Orders</th>
                    <th class="text-end">POS Sessions</th>
                    <th class="text-end">Active Employees</th>
                    <th class="text-end">Payroll Runs</th>
                    <th class="text-end">Payroll Paid</th>
                    <th class="text-end">Audit Events</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><strong>{{ $row['branch_name'] }}</strong>@if($row['branch_code'])<div class="text-muted small">{{ $row['branch_code'] }}</div>@endif</td>
                        <td class="text-end">{{ number_format($row['completed_orders']) }}</td>
                        <td class="text-end">৳{{ number_format($row['sales_total'], 0) }}</td>
                        <td class="text-end">৳{{ number_format($row['due_total'], 0) }}</td>
                        <td class="text-end">{{ number_format($row['qr_orders']) }}</td>
                        <td class="text-end">{{ number_format($row['pos_sessions']) }}</td>
                        <td class="text-end">{{ number_format($row['active_employees']) }}</td>
                        <td class="text-end">{{ number_format($row['payroll_runs']) }}</td>
                        <td class="text-end">৳{{ number_format($row['payroll_paid'], 0) }}</td>
                        <td class="text-end">{{ number_format($row['audit_events']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No branch data found for this scope.</td></tr>
                @endforelse
            </tbody>
            @if($rows->count() > 1)
            <tfoot class="fw-bold">
                <tr>
                    <td>All Branches</td>
                    <td class="text-end">{{ number_format($totals['orders']) }}</td>
                    <td class="text-end">৳{{ number_format($totals['sales'], 0) }}</td>
                    <td class="text-end">৳{{ number_format($totals['due'], 0) }}</td>
                    <td class="text-end">{{ number_format($totals['qr_orders']) }}</td>
                    <td class="text-end">{{ number_format($totals['pos_sessions']) }}</td>
                    <td class="text-end">{{ number_format($totals['active_employees']) }}</td>
                    <td class="text-end">{{ number_format($totals['payroll_runs']) }}</td>
                    <td class="text-end">৳{{ number_format($totals['payroll_paid'], 0) }}</td>
                    <td class="text-end">{{ number_format($totals['audit_events']) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

</main>
<script>
(function () {
    const type = document.getElementById('summaryFilterType');
    const year = document.getElementById('summaryYearField');
    const month = document.getElementById('summaryMonthField');
    const dates = document.getElementById('summaryDateFields');
    function sync() {
        const value = type.value;
        year.style.display = value === 'date' ? 'none' : 'block';
        month.style.display = value === 'month' ? 'block' : 'none';
        dates.style.display = value === 'date' ? 'flex' : 'none';
    }
    type.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
