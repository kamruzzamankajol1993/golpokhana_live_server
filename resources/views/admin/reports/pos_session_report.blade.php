@extends('admin.master.master')
@section('title', 'POS Session Report — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">POS Session Report</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Reports</span></div></div><div style="display:flex;gap:8px"><button type="button" id="sessionReportPdf" data-url="{{ route('reports.pos_sessions.pdf') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" id="sessionReportExcel" data-url="{{ route('reports.pos_sessions.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div></div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component',['showAllFilterType'=>true,'showSearchFilter'=>true,'showReportViewFilter'=>true,'combinedPreviewUrl'=>route('reports.pos_sessions.combined_print'),'searchPlaceholder'=>'Search session/order, employee, status...'])</div>
    <div class="progga-card" id="posSessionReportCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">POS Sessions</div><div class="progga-card-subtitle">Historical session records using selected filter</div></div></div>
        <div id="posSessionReportTableArea">@include('admin.reports.partials.pos_session_report_table')</div>
    </div>
</main>
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){if(data.table_html!==undefined){$('#posSessionReportTableArea').html(data.table_html||'');return;}$('#posSessionReportRows').html(data.html||'');$('#posSessionReportPagination').html(data.pagination||'');};
$(document).on('click','#posSessionReportPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
$('#sessionReportPdf').on('click',function(){window.open($(this).data('url')+'?'+$('#reportFilterForm').serialize(),'_blank','noopener');});
$('#sessionReportExcel').on('click',function(){window.location.href=$(this).data('url')+'?'+$('#reportFilterForm').serialize();});
</script>
@endsection
