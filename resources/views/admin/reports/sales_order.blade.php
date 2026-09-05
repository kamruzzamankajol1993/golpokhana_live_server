@extends('admin.master.master')
@section('title', 'Sales & Order Report — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">Sales &amp; Order Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" onclick="exportReport('pdf','sales_order')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
            <button type="button" onclick="exportReport('excel','sales_order')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        </div>
    </div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component')</div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-currency-dollar"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Revenue</div><div class="progga-stat-value" id="cardRev">৳{{ number_format($totalRevenue,0) }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Orders</div><div class="progga-stat-value" id="cardOrders">{{ $totalOrders }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon success"><i class="bi bi-graph-up"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Avg. Order Value</div><div class="progga-stat-value" id="cardAvg">৳{{ number_format($avgOrderValue,0) }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon warning"><i class="bi bi-people"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Unique Customers</div><div class="progga-stat-value" id="cardCust">{{ $uniqueCustomers }}</div></div></div></div>
    </div>
    <div class="progga-card" id="salesReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Completed Order Details</div><div class="progga-card-subtitle">Filtered completed orders</div></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr>
            <th>SL</th><th>Order #</th><th>Customer</th><th>Subtotal</th><th>Honored</th><th>Product Discount</th><th>Service</th><th>Tips</th><th>Given</th><th>Change</th><th>Grand Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Time</th><th>KOT to Pay</th>
        </tr></thead><tbody id="salesReportContainer">@include('admin.reports.partials.sales_table_rows')</tbody></table></div>
        <div id="salesReportPagination">@include('admin.reports.partials.custom_pagination',['paginator'=>$orders])</div>
    </div>
</main>
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){
    $('#salesReportContainer').html(data.html||''); $('#salesReportPagination').html(data.pagination||'');
    if(data.summary){ $('#cardRev').text(data.summary.revenue); $('#cardOrders').text(data.summary.orders); $('#cardAvg').text(data.summary.avg); $('#cardCust').text(data.summary.customers); }
};
$(document).on('click','#salesReportPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
</script>
@endsection
