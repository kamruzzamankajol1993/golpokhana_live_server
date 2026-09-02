@extends('admin.master.master')
@section('title', 'Due Report — ' . $restaurantSettingName)

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .report-filter-line { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; padding:15px; }
    .report-filter-line .progga-form-group { margin:0; }
    .report-filter-line .progga-form-label { margin-bottom:3px; }
    .report-filter-line input, .report-filter-line select { min-width:145px; }
    .report-table-wrap { overflow-x:auto; }
    .report-orders-table th { white-space:nowrap; font-size:11px; }
    .report-orders-table td { vertical-align:middle; font-size:12px; }
    .report-loading { opacity:.55; pointer-events:none; }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Due Report</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">Reports</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Due Report</span>
            </div>
        </div>
    </div>

    <div class="progga-card" style="margin-bottom:16px;">
        @php($showDeliveryPartnerFilter = true)
        @include('admin.reports.partials.filter_component')
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Due Orders</div>
                    <div class="progga-stat-value" id="dueCardOrders">{{ $totalOrders }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon secondary"><i class="bi bi-cash-stack"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Grand Total</div>
                    <div class="progga-stat-value" id="dueCardGrand">৳{{ number_format($totalGrand, 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon success"><i class="bi bi-wallet2"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Paid Amount</div>
                    <div class="progga-stat-value" id="dueCardPaid">৳{{ number_format($totalPaid, 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon warning"><i class="bi bi-hourglass-split"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Total Due</div>
                    <div class="progga-stat-value" id="dueCardDue">৳{{ number_format($totalDue, 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card" id="salesReportCard">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Outstanding Due List</div>
                <div class="progga-card-subtitle">All channels means every due order, including In-house Delivery, Dine-In, Takeaway, and delivery partner orders.</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="progga-btn progga-btn-primary progga-btn-sm" id="openDuePdf" data-pdf-url="{{ route('reports.due.pdf') }}"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="downloadDueExcel" data-excel-url="{{ route('reports.due.excel') }}" style="border-color:#198754;color:#198754;background:#f8fff9;"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            </div>
        </div>
        <div class="progga-table-wrapper report-table-wrap" style="border:none;border-radius:0;">
            <table class="progga-table report-orders-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date &amp; Time</th>
                        <th>Order Type</th>
                        <th>Delivery Partner</th>
                        <th>Customer</th>
                        <th>Grand Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="dueReportContainer">
                    @include('admin.reports.partials.due_table_rows')
                </tbody>
            </table>
        </div>
        <div id="dueReportPagination">
            @include('admin.reports.partials.custom_pagination', ['paginator' => $orders])
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
document.getElementById('openDuePdf')?.addEventListener('click', function() {
    const baseUrl = this.dataset.pdfUrl;
    const params = $('#reportFilterForm').serialize();
    const pdfUrl = baseUrl + (params ? ('?' + params) : '');
    window.open(pdfUrl, '_blank', 'noopener');
});

document.getElementById('downloadDueExcel')?.addEventListener('click', function() {
    const params = $('#reportFilterForm').serialize();
    window.location.href = this.dataset.excelUrl + (params ? ('?' + params) : '');
});

function updateReportDOM(data) {
    document.getElementById('dueReportContainer').innerHTML = data.html || '';
    document.getElementById('dueReportPagination').innerHTML = data.pagination || '';
    document.getElementById('dueCardOrders').innerText = data.summary.orders;
    document.getElementById('dueCardGrand').innerText = data.summary.grand;
    document.getElementById('dueCardPaid').innerText = data.summary.paid;
    document.getElementById('dueCardDue').innerText = data.summary.due;
}

$(document).on('click', '#dueReportPagination a', function(event) {
    event.preventDefault();
    const $link = $(this);
    if ($link.hasClass('disabled') || $link.attr('aria-disabled') === 'true') return;
    const url = $link.attr('href');
    if (!url || url === '#') return;

    $('#salesReportCard').addClass('report-loading');
    $.ajax({
        url: url,
        type: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        success: function(data) {
            updateReportDOM(data);
            window.history.pushState({}, '', url);
        },
        complete: function() { $('#salesReportCard').removeClass('report-loading'); }
    });
});
</script>
@endsection
