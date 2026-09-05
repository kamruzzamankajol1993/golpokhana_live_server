@extends('admin.master.master')
@section('title', 'Waiter Daily Order Report — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">Waiter Order Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div>
        <div style="display:flex;gap:8px"><button type="button" id="waiterPdf" data-url="{{ route('reports.waiter_daily_orders.pdf') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" id="waiterExcel" data-url="{{ route('reports.waiter_daily_orders.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div>
    </div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component',['showWaiterFilter'=>true])</div>
    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Orders</div><div class="progga-stat-value" id="waiterTotalOrders">{{ number_format($totalOrders) }}</div></div></div></div>
        <div class="col-lg-2 col-md-4"><div class="progga-stat-card"><div class="progga-stat-icon success"><i class="bi bi-check-circle"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Completed</div><div class="progga-stat-value" id="waiterCompleted">{{ number_format($completedOrders) }}</div></div></div></div>
        <div class="col-lg-2 col-md-4"><div class="progga-stat-card"><div class="progga-stat-icon warning"><i class="bi bi-hourglass-split"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Active / Pending</div><div class="progga-stat-value" id="waiterActive">{{ number_format($activeOrders) }}</div></div></div></div>
        <div class="col-lg-2 col-md-4"><div class="progga-stat-card"><div class="progga-stat-icon danger"><i class="bi bi-x-circle"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Cancelled</div><div class="progga-stat-value" id="waiterCancelled">{{ number_format($cancelledOrders) }}</div></div></div></div>
        <div class="col-lg-2 col-md-4"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-people"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Waiters with Orders</div><div class="progga-stat-value" id="waiterCount">{{ number_format($waitersWithOrders) }}</div></div></div></div>
        <div class="col-lg-2 col-md-4"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-cash-stack"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Completed Sales</div><div class="progga-stat-value" id="waiterSales">৳{{ number_format($completedSales,0) }}</div></div></div></div>
    </div>
    <div class="progga-card mb-3" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Waiter-wise Summary</div><div class="progga-card-subtitle" id="waiterPeriodLabel">{{ $filterLabel }}</div></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>Waiter User</th><th>User ID</th><th>Total Orders</th><th>Completed</th><th>Active / Pending</th><th>Cancelled</th><th>Honored</th><th>Product Discount</th><th>Completed Sales</th></tr></thead><tbody id="waiterSummaryRows">@include('admin.reports.partials.waiter_summary_rows')</tbody></table></div>
    </div>
    <div class="progga-card" id="salesReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Order Details</div><div class="progga-card-subtitle">Orders inside selected filter</div></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>Order #</th><th>Waiter User</th><th>Order Time</th><th>Table</th><th>Order Type</th><th>Status</th><th>Honored</th><th>Product Discount</th><th>Grand Total</th></tr></thead><tbody id="waiterOrderRows">@include('admin.reports.partials.waiter_order_rows')</tbody></table></div>
        <div id="waiterOrderPagination">@include('admin.reports.partials.custom_pagination',['paginator'=>$orders])</div>
    </div>
</main>
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){
    $('#waiterSummaryRows').html(data.summary_rows||''); $('#waiterOrderRows').html(data.order_rows||''); $('#waiterOrderPagination').html(data.pagination||'');
    if(data.summary){$('#waiterTotalOrders').text(data.summary.orders);$('#waiterCompleted').text(data.summary.completed);$('#waiterActive').text(data.summary.active);$('#waiterCancelled').text(data.summary.cancelled);$('#waiterSales').text(data.summary.sales);$('#waiterCount').text(data.summary.waiters);$('#waiterPeriodLabel').text(data.summary.period||'');$('#activeFilterLabel').text(data.summary.period||'');}
};
$(document).on('click','#waiterOrderPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
$('#waiterPdf').on('click',function(){window.open($(this).data('url')+'?'+$('#reportFilterForm').serialize(),'_blank','noopener');});
$('#waiterExcel').on('click',function(){window.location.href=$(this).data('url')+'?'+$('#reportFilterForm').serialize();});
</script>
@endsection
