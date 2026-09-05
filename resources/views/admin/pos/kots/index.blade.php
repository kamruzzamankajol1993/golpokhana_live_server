@extends('admin.master.master')
@section('title', 'POS KOT List — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">POS KOT List</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item">POS System</span><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">KOT List</span></div></div><div style="display:flex;gap:8px"><button type="button" id="posKotPdf" data-url="{{ route('pos.kots.pdf') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button><button type="button" id="posKotExcel" data-url="{{ route('pos.kots.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</button></div></div>
    <div class="progga-card mb-3">@include('admin.reports.partials.filter_component',['showSearchFilter'=>true,'searchPlaceholder'=>'Search KOT, order, table, waiter...'])</div>
    <div class="progga-card" id="kotListCard" data-report-card>
        <div class="progga-card-header"><div><div class="progga-card-title">Running / Pending KOT</div><div class="progga-card-subtitle">Completed, cancelled and delivered orders remain excluded from this operational list.</div></div></div>
        <div class="report-table-shell"><table class="progga-table enhanced-report-table"><thead><tr><th>SL</th><th>KOT</th><th>Order</th><th>Type / Table</th><th>Waiter</th><th>Items</th><th>Created</th><th>KOT Status</th><th>Order Status</th><th>Action</th></tr></thead><tbody id="kotRows">@include('admin.pos.kots.partials.rows')</tbody></table></div>
        <div id="kotPagination">@include('admin.reports.partials.custom_pagination',['paginator'=>$kots])</div>
    </div>
</main>
@include('admin.pos.partials.print_preview_modal')
@endsection
@section('script')
<script>
window.updateReportDOM=function(data){$('#kotRows').html(data.html||'');$('#kotPagination').html(data.pagination||'');};
$(document).on('click','#kotPagination a',function(e){e.preventDefault();if($(this).hasClass('disabled'))return;const u=$(this).attr('href');if(u&&u!=='#')window.reportAjaxRequest(u,'push');});
$('#posKotPdf').on('click',function(){window.open($(this).data('url')+'?'+$('#reportFilterForm').serialize(),'_blank','noopener');});
$('#posKotExcel').on('click',function(){window.location.href=$(this).data('url')+'?'+$('#reportFilterForm').serialize();});
</script>
@endsection
