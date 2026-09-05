@extends('admin.master.master')
@section('title', 'KOT Report — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">KOT Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div><div style="display:flex;gap:8px"><button type="button" id="kotReportPdf" data-url="{{ route('reports.kots.pdf') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" id="kotReportExcel" data-url="{{ route('reports.kots.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div></div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component',['showAllFilterType'=>true,'showSearchFilter'=>true,'searchPlaceholder'=>'Search KOT, order, table, waiter...'])</div>
    <div class="progga-card" id="kotReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">KOT History</div><div class="progga-card-subtitle">Historical KOT records using selected filter</div></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>KOT</th><th>Order</th><th>Type / Table</th><th>Waiter</th><th>Items</th><th>Created</th><th>KOT Status</th><th>Order Status</th><th>Action</th></tr></thead><tbody id="kotReportRows">@include('admin.reports.partials.kot_report_rows')</tbody></table></div>
        <div id="kotReportPagination">@include('admin.reports.partials.custom_pagination',['paginator'=>$kots])</div>
    </div>
</main>
@include('admin.pos.partials.print_preview_modal')
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){$('#kotReportRows').html(data.html||'');$('#kotReportPagination').html(data.pagination||'');};
$(document).on('click','#kotReportPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
$('#kotReportPdf').on('click',function(){window.open($(this).data('url')+'?'+$('#reportFilterForm').serialize(),'_blank','noopener');});
$('#kotReportExcel').on('click',function(){window.location.href=$(this).data('url')+'?'+$('#reportFilterForm').serialize();});
</script>
@endsection
