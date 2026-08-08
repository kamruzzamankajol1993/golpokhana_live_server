@extends('admin.master.master')
@section('title', 'Sales & Order Report — TableTrack RMS')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .report-filter-line { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; padding:15px; }
    .report-filter-line .progga-form-group { margin:0; }
    .report-filter-line .progga-form-label { margin-bottom:3px; }
    .report-filter-line input,
    .report-filter-line select { min-width:145px; }
    .report-table-wrap { overflow-x:auto; }
    .report-orders-table th { white-space: nowrap; font-size: 11px; }
    .report-orders-table td { vertical-align: top; font-size: 12px; }
    .report-pagination-wrap { padding:14px 16px; border-top:1px solid var(--progga-border-light); }
    .report-loading { opacity:.55; pointer-events:none; }
</style>
@endsection

@section('body')
<main class="progga-content">
  <div class="progga-page-header">
    <div>
        <h1 class="progga-page-title">Sales &amp; Order Report</h1>
        <div class="progga-breadcrumb">
            <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
            <span class="progga-breadcrumb-sep">/</span>
            <span class="progga-breadcrumb-item active">Reports</span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" onclick="exportReport('pdf', 'sales_order')" class="progga-btn progga-btn-outline progga-btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> PDF
        </button>
        <button type="button" onclick="exportReport('excel', 'sales_order')" class="progga-btn progga-btn-outline progga-btn-sm">
            <i class="bi bi-file-earmark-excel"></i> Excel
        </button>
    </div>
  </div>

  <div class="progga-card" style="margin-bottom:16px;">
      @include('admin.reports.partials.filter_component')
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon secondary"><i class="bi bi-currency-dollar"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Total Revenue</div>
                <div class="progga-stat-value" id="cardRev">৳{{ number_format($totalRevenue, 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Total Orders</div>
                <div class="progga-stat-value" id="cardOrders">{{ $totalOrders }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon success"><i class="bi bi-graph-up"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Avg. Order Value</div>
                <div class="progga-stat-value" id="cardAvg">৳{{ number_format($avgOrderValue, 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon warning"><i class="bi bi-people"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Unique Customers</div>
                <div class="progga-stat-value" id="cardCust">{{ $uniqueCustomers }}</div>
            </div>
        </div>
    </div>
  </div>

  <div class="progga-card" id="salesReportCard">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title">Completed Order Details</div>
            <div class="progga-card-subtitle">Showing orders from {{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</div>
        </div>
    </div>

    <div class="progga-table-wrapper report-table-wrap" style="border:none;border-radius:0;">
      <table class="progga-table report-orders-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Subtotal</th>
            <th>Discount</th>
            <th>Service</th>
            <th>Tips</th>
            <th>Given</th>
            <th>Change</th>
            <th>Grand Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Date</th>
            <th>Time</th>
            <th>KOT to Pay</th>
          </tr>
        </thead>
        <tbody id="salesReportContainer">
            @include('admin.reports.partials.sales_table_rows')
        </tbody>
      </table>
    </div>

    <div id="salesReportPagination">
        @include('admin.reports.partials.custom_pagination', ['paginator' => $orders])
    </div>
  </div>
</main>
@endsection

@section('script')
<script>
function updateReportDOM(data) {
    document.getElementById('salesReportContainer').innerHTML = data.html;
    document.getElementById('salesReportPagination').innerHTML = data.pagination || '';
    document.getElementById('cardRev').innerText = data.summary.revenue;
    document.getElementById('cardOrders').innerText = data.summary.orders;
    document.getElementById('cardAvg').innerText = data.summary.avg;
    document.getElementById('cardCust').innerText = data.summary.customers;
}

$(document).on('click', '#salesReportPagination a', function(event) {
    event.preventDefault();
    const $link = $(this);
    if ($link.hasClass('disabled') || $link.attr('aria-disabled') === 'true') return;

    const url = $link.attr('href');
    if (!url || url === '#') return;

    $('#salesReportCard').addClass('report-loading');
    $.ajax({
        url: url,
        type: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        success: function(data) {
            updateReportDOM(data);
            window.history.pushState({}, '', url);
        },
        complete: function() {
            $('#salesReportCard').removeClass('report-loading');
        }
    });
});
</script>
@endsection
