@extends('admin.master.master')
@section('title', 'Complimentary Order Report — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Complimentary Order Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div><div style="display:flex;gap:8px"><button type="button" onclick="exportReport('pdf','complimentary_orders')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" onclick="exportReport('excel','complimentary_orders')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div></div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component')</div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-gift-fill"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Complimentary Orders</div><div class="progga-stat-value" id="compOrders">{{ $totalOrders }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon success"><i class="bi bi-check2-circle"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Completed</div><div class="progga-stat-value" id="compCompleted">{{ $completedOrders }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon warning"><i class="bi bi-basket"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Complimentary Food Qty</div><div class="progga-stat-value" id="compQty">{{ number_format($complimentaryFoodQty) }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-cash-stack"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Order Value</div><div class="progga-stat-value" id="compValue">৳{{ number_format($totalOrderValue,0) }}</div></div></div></div>
    </div>
    <div class="progga-card" id="salesReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Complimentary Food Orders</div><div class="progga-card-subtitle">Product-wise complimentary records inside selected filter</div></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>Order #</th><th>Customer</th><th>Complimentary Food</th><th>Qty</th><th>Grand Total</th><th>Honored</th><th>Product Discount</th><th>Payment</th><th>Status</th><th>Date</th><th>Time</th></tr></thead><tbody id="complimentaryTableContainer">@include('admin.reports.partials.complimentary_table_rows')</tbody></table></div>
        <div id="complimentaryPagination">@include('admin.reports.partials.custom_pagination',['paginator'=>$orders])</div>
    </div>
</main>
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){$('#complimentaryTableContainer').html(data.html||'');$('#complimentaryPagination').html(data.pagination||'');if(data.summary){$('#compOrders').text(data.summary.orders);$('#compCompleted').text(data.summary.completed);$('#compQty').text(data.summary.food_qty);$('#compValue').text(data.summary.value);}};
$(document).on('click','#complimentaryPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
</script>
@endsection
