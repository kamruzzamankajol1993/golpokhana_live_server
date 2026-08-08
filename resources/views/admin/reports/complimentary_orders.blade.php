@extends('admin.master.master')
@section('title', 'Complimentary Order Report — TableTrack RMS')

@section('css')
<style>
    .report-filter-line { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
    .report-filter-line .progga-form-group { margin:0; min-width:150px; }
    .report-table-wrap { overflow-x:auto; }
    .report-orders-table { min-width:1050px; }
    .report-orders-table th { white-space:nowrap; font-size:11px; }
    .report-orders-table td { vertical-align:top; font-size:12px; }
    .report-loading { opacity:.55; pointer-events:none; }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Complimentary Order Report</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Reports</span>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" onclick="exportReport('pdf', 'complimentary_orders')" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
            <button type="button" onclick="exportReport('excel', 'complimentary_orders')" class="progga-btn progga-btn-outline progga-btn-sm">
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
                <div class="progga-stat-icon primary"><i class="bi bi-gift-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Complimentary Orders</div>
                    <div class="progga-stat-value" id="cardOrders">{{ $totalOrders }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon success"><i class="bi bi-check2-circle"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Completed Orders</div>
                    <div class="progga-stat-value" id="cardCompleted">{{ $completedOrders }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon warning"><i class="bi bi-basket2-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Complimentary Food Qty</div>
                    <div class="progga-stat-value" id="cardFoodQty">{{ number_format($complimentaryFoodQty) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon secondary"><i class="bi bi-currency-dollar"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Total Order Value</div>
                    <div class="progga-stat-value" id="cardValue">৳{{ number_format($totalOrderValue, 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card" id="salesReportCard">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Orders Containing Complimentary Food</div>
                <div class="progga-card-subtitle">Showing orders from {{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</div>
            </div>
        </div>

        <div class="progga-table-wrapper report-table-wrap" style="border:none;border-radius:0;">
            <table class="progga-table report-orders-table">
                <thead>
                    <tr>
                        <th>SL</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Complimentary Food</th>
                        <th>Complimentary Qty</th>
                        <th>Order Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody id="complimentaryReportContainer">
                    @include('admin.reports.partials.complimentary_table_rows')
                </tbody>
            </table>
        </div>

        <div id="complimentaryReportPagination">
            @include('admin.reports.partials.custom_pagination', ['paginator' => $orders])
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
function updateReportDOM(data) {
    document.getElementById('complimentaryReportContainer').innerHTML = data.html;
    document.getElementById('complimentaryReportPagination').innerHTML = data.pagination || '';
    document.getElementById('cardOrders').innerText = data.summary.orders;
    document.getElementById('cardCompleted').innerText = data.summary.completed;
    document.getElementById('cardFoodQty').innerText = data.summary.food_qty;
    document.getElementById('cardValue').innerText = data.summary.value;
}

$(document).on('click', '#complimentaryReportPagination a', function(event) {
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
