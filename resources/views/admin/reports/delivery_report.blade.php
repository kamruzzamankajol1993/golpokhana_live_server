@extends('admin.master.master')
@section('title', 'Delivery Report — ' . $restaurantSettingName)

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .report-filter-line { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; padding:15px; }
    .report-filter-line .progga-form-group { margin:0; }
    .report-filter-line .progga-form-label { margin-bottom:3px; }
    .report-filter-line input,
    .report-filter-line select { min-width:145px; }
    .report-table-wrap { overflow-x:auto; }
    .report-orders-table th { white-space:nowrap; font-size:11px; }
    .report-orders-table td { vertical-align:top; font-size:12px; }
    .report-loading { opacity:.55; pointer-events:none; }
</style>
@endsection

@section('body')
<main class="progga-content">
  <div class="progga-page-header">
    <div>
        <h1 class="progga-page-title">Delivery Report</h1>
        <div class="progga-breadcrumb">
            <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
            <span class="progga-breadcrumb-sep">/</span>
            <span class="progga-breadcrumb-item">Reports</span>
            <span class="progga-breadcrumb-sep">/</span>
            <span class="progga-breadcrumb-item active">Delivery Report</span>
        </div>
    </div>
  </div>

  <div class="progga-card" style="margin-bottom:16px;">
      @include('admin.reports.partials.filter_component', ['showDeliveryPartnerFilter' => true])
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon primary"><i class="bi bi-truck"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Delivery Orders</div>
                <div class="progga-stat-value" id="cardOrders">{{ $totalOrders }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon success"><i class="bi bi-check-circle"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Completed</div>
                <div class="progga-stat-value" id="cardCompleted">{{ $completedOrders }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon secondary"><i class="bi bi-cash-stack"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Grand Total</div>
                <div class="progga-stat-value" id="cardValue">৳{{ number_format($totalValue, 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="progga-stat-card">
            <div class="progga-stat-icon warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="progga-stat-info">
                <div class="progga-stat-label">Total Due</div>
                <div class="progga-stat-value" id="cardDue">৳{{ number_format($totalDue, 0) }}</div>
            </div>
        </div>
    </div>
  </div>

  <div class="progga-card" id="salesReportCard">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title">Delivery Order List</div>
            <div class="progga-card-subtitle">Only Delivery orders from {{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }} · Partner: <strong id="deliveryPartnerLabel">{{ $selectedDeliveryPartnerLabel }}</strong></div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="progga-btn progga-btn-primary progga-btn-sm" id="openDeliveryPdf" data-pdf-url="{{ route('reports.delivery.pdf') }}">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="downloadDeliveryExcel" data-excel-url="{{ route('reports.delivery.excel') }}" style="border-color:#198754;color:#198754;background:#f8fff9;">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
        </div>
    </div>

    <div class="progga-table-wrapper report-table-wrap" style="border:none;border-radius:0;">
      <table class="progga-table report-orders-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Date &amp; Time</th>
            <th>Delivery Partner</th>
            <th>Customer</th>
            <th>Phone</th>
            <th>Subtotal</th>
            <th>VAT</th>
            <th>Discount</th>
            <th>Grand Total</th>
            <th>Due</th>
            <th>Payment</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="deliveryReportContainer">
            @include('admin.reports.partials.delivery_table_rows')
        </tbody>
      </table>
    </div>

    <div id="deliveryReportPagination">
        @include('admin.reports.partials.custom_pagination', ['paginator' => $orders])
    </div>
  </div>
</main>
@endsection

@section('script')
<script>
document.getElementById('openDeliveryPdf')?.addEventListener('click', function() {
    const baseUrl = this.dataset.pdfUrl;
    const params = $('#reportFilterForm').serialize();
    const pdfUrl = baseUrl + (params ? ('?' + params) : '');
    window.open(pdfUrl, '_blank', 'noopener');
});

document.getElementById('downloadDeliveryExcel')?.addEventListener('click', function() {
    const baseUrl = this.dataset.excelUrl;
    const params = $('#reportFilterForm').serialize();
    window.location.href = baseUrl + (params ? ('?' + params) : '');
});

function updateReportDOM(data) {
    document.getElementById('deliveryReportContainer').innerHTML = data.html;
    document.getElementById('deliveryReportPagination').innerHTML = data.pagination || '';
    document.getElementById('cardOrders').innerText = data.summary.orders;
    document.getElementById('cardCompleted').innerText = data.summary.completed;
    document.getElementById('cardValue').innerText = data.summary.value;
    document.getElementById('cardDue').innerText = data.summary.due;
    if (data.summary.partner_label !== undefined) document.getElementById('deliveryPartnerLabel').innerText = data.summary.partner_label;
}

$(document).on('click', '#deliveryReportPagination a', function(event) {
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
