@extends('admin.master.master')
@section('title', 'Delivery Report — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Delivery Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div></div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component',['showDeliveryPartnerFilter'=>true])</div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-truck"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Delivery Orders</div><div class="progga-stat-value" id="cardOrders">{{ $totalOrders }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon success"><i class="bi bi-check-circle"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Completed</div><div class="progga-stat-value" id="cardCompleted">{{ $completedOrders }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-cash-stack"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Grand Total</div><div class="progga-stat-value" id="cardValue">৳{{ number_format($totalValue,0) }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon warning"><i class="bi bi-hourglass-split"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Due</div><div class="progga-stat-value" id="cardDue">৳{{ number_format($totalDue,0) }}</div></div></div></div>
    </div>
    <div class="progga-card" id="salesReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Delivery Order List</div><div class="progga-card-subtitle">Partner: <strong id="deliveryPartnerLabel">{{ $selectedDeliveryPartnerLabel }}</strong></div></div><div style="display:flex;gap:8px"><button type="button" id="openDeliveryPdf" data-url="{{ route('reports.delivery.pdf') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" id="downloadDeliveryExcel" data-url="{{ route('reports.delivery.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>Order #</th><th>Date & Time</th><th>Delivery Partner</th><th>Customer</th><th>Phone</th><th>Subtotal</th><th>VAT</th><th>Discount</th><th>Grand Total</th><th>Due</th><th>Payment</th><th>Status</th></tr></thead><tbody id="deliveryReportContainer">@include('admin.reports.partials.delivery_table_rows')</tbody></table></div>
        <div id="deliveryReportPagination">@include('admin.reports.partials.custom_pagination',['paginator'=>$orders])</div>
    </div>
</main>
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){$('#deliveryReportContainer').html(data.html||'');$('#deliveryReportPagination').html(data.pagination||'');if(data.summary){$('#cardOrders').text(data.summary.orders);$('#cardCompleted').text(data.summary.completed);$('#cardValue').text(data.summary.value);$('#cardDue').text(data.summary.due);$('#deliveryPartnerLabel').text(data.summary.partner_label||'ALL');}};
$(document).on('click','#deliveryReportPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
$('#openDeliveryPdf').on('click',function(){window.open($(this).data('url')+'?'+$('#reportFilterForm').serialize(),'_blank','noopener');});
$('#downloadDeliveryExcel').on('click',function(){window.location.href=$(this).data('url')+'?'+$('#reportFilterForm').serialize();});
</script>
@endsection
