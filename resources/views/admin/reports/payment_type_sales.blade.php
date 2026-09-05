@extends('admin.master.master')
@section('title', 'Payment Wise Sales — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Payment Type Wise Sales</h1></div><div style="display:flex;gap:8px"><button type="button" onclick="exportReport('pdf','payment_type_sales')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" onclick="exportReport('excel','payment_type_sales')" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div></div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component',['showPaymentFilter'=>true])</div>
    <div class="row g-3 mb-4" id="paymentCardsContainer">@include('admin.reports.partials.payment_cards')</div>
    <div class="progga-card" id="salesReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Payment Collection Details</div><div class="progga-card-subtitle">Filtered completed order collections</div></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>Order #</th><th>Date</th><th>Customer</th><th>Table</th><th>Honored</th><th>Product Discount</th><th>Payment Type</th><th>Cash</th><th>Bank / Card</th><th>MFS</th><th>Total Paid</th></tr></thead><tbody id="paymentTableContainer">@include('admin.reports.partials.payment_table_rows')</tbody></table></div>
        <div id="paymentPaginationWrap">@include('admin.reports.partials.custom_pagination',['paginator'=>$paymentOrders])</div>
    </div>
</main>
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){$('#paymentTableContainer').html(data.html||'');$('#paymentCardsContainer').html(data.cards||'');$('#paymentPaginationWrap').html(data.pagination||'');};
$(document).on('click','#paymentPaginationWrap a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
</script>
@endsection
