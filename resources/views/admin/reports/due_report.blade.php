@extends('admin.master.master')
@section('title', 'Due Report — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Due Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div></div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component',['showDeliveryPartnerFilter'=>true])</div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Due Orders</div><div class="progga-stat-value" id="dueCardOrders">{{ $totalOrders }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon secondary"><i class="bi bi-cash-stack"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Grand Total</div><div class="progga-stat-value" id="dueCardGrand">৳{{ number_format($totalGrand,0) }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon success"><i class="bi bi-wallet2"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Paid</div><div class="progga-stat-value" id="dueCardPaid">৳{{ number_format($totalPaid,0) }}</div></div></div></div>
        <div class="col-md-3"><div class="progga-stat-card"><div class="progga-stat-icon warning"><i class="bi bi-hourglass-split"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Due</div><div class="progga-stat-value" id="dueCardDue">৳{{ number_format($totalDue,0) }}</div></div></div></div>
    </div>
    <div class="progga-card" id="salesReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Outstanding Due List</div><div class="progga-card-subtitle">Filtered outstanding dues</div></div><div style="display:flex;gap:8px"><button type="button" id="openDuePdf" data-url="{{ route('reports.due.pdf') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" id="downloadDueExcel" data-url="{{ route('reports.due.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>Order #</th><th>Date & Time</th><th>Order Type</th><th>Delivery Partner</th><th>Customer</th><th>Grand Total</th><th>Paid</th><th>Due</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead><tbody id="dueReportContainer">@include('admin.reports.partials.due_table_rows')</tbody></table></div>
        <div id="dueReportPagination">@include('admin.reports.partials.custom_pagination',['paginator'=>$orders])</div>
    </div>
</main>
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){$('#dueReportContainer').html(data.html||'');$('#dueReportPagination').html(data.pagination||'');if(data.summary){$('#dueCardOrders').text(data.summary.orders);$('#dueCardGrand').text(data.summary.grand);$('#dueCardPaid').text(data.summary.paid);$('#dueCardDue').text(data.summary.due);}};
$(document).on('click','#dueReportPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
$('#openDuePdf').on('click',function(){window.open($(this).data('url')+'?'+$('#reportFilterForm').serialize(),'_blank','noopener');});
$('#downloadDueExcel').on('click',function(){window.location.href=$(this).data('url')+'?'+$('#reportFilterForm').serialize();});
</script>
@endsection
