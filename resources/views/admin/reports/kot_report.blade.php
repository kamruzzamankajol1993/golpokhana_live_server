@extends('admin.master.master')
@section('title', 'KOT Report — ' . $restaurantSettingName)

@section('css')
<style>
    .report-list-table th { white-space:nowrap; font-size:11px; }
    .report-list-table td { font-size:12px; vertical-align:middle; }
    .report-list-loading { opacity:.55; pointer-events:none; }
    .report-list-search { position:relative; width:320px; max-width:100%; margin-left:auto; }
    .report-list-search .bi-search { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:13px; color:#7a817d; pointer-events:none; }
    .report-list-search .progga-form-control { width:100%; height:36px; padding-left:34px; font-size:12px; }
    @media (max-width: 767px) { .report-list-search { width:100%; } }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">KOT Report</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">Reports</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">KOT</span>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('reports.kots.pdf') }}" target="_blank" rel="noopener" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <a href="{{ route('reports.kots.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm" style="border-color:#198754;color:#198754;background:#f8fff9;"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </div>

    <div class="progga-card" id="kotReportCard">
        <div class="progga-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="progga-card-title">All KOT History</div>
                <div class="progga-card-subtitle">Shows all KOT records, including completed and delivered orders.</div>
            </div>
            <div class="report-list-search">
                <i class="bi bi-search"></i>
                <input type="text" id="kotReportSearchInput" class="progga-form-control" value="{{ request('search') }}" placeholder="Search KOT, order, table, waiter..." autocomplete="off">
            </div>
        </div>

        <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
            <table class="progga-table report-list-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>KOT</th>
                        <th>Order</th>
                        <th>Type / Table</th>
                        <th>Waiter</th>
                        <th>Items</th>
                        <th>Created</th>
                        <th>KOT Status</th>
                        <th>Order Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="kotReportRows">
                    @include('admin.reports.partials.kot_report_rows')
                </tbody>
            </table>
        </div>
        <div id="kotReportPagination">
            @include('admin.reports.partials.custom_pagination', ['paginator' => $kots])
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
(function() {
    let searchTimer = null;
    let activeRequest = null;

    function withKotReportSearch(url) {
        const target = new URL(url, window.location.href);
        const search = $.trim($('#kotReportSearchInput').val() || '');
        if (search) target.searchParams.set('search', search);
        else target.searchParams.delete('search');
        return target.toString();
    }

    function loadKotReportPage(url, historyMode) {
        const ajaxUrl = withKotReportSearch(url);
        if (activeRequest) activeRequest.abort();
        $('#kotReportCard').addClass('report-list-loading');

        activeRequest = $.ajax({
            url: ajaxUrl,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(data) {
                $('#kotReportRows').html(data.html || '');
                $('#kotReportPagination').html(data.pagination || '');
                if (historyMode === 'push') window.history.pushState({}, '', ajaxUrl);
                if (historyMode === 'replace') window.history.replaceState({}, '', ajaxUrl);
            },
            complete: function() {
                $('#kotReportCard').removeClass('report-list-loading');
                activeRequest = null;
            }
        });
    }

    $('#kotReportSearchInput').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            loadKotReportPage("{{ route('reports.kots') }}", 'replace');
        }, 250);
    });

    $(document).on('click', '#kotReportPagination a', function(e) {
        e.preventDefault();
        const $link = $(this);
        const url = $link.attr('href');
        if (!url || url === '#' || $link.hasClass('disabled')) return;
        loadKotReportPage(url, 'push');
    });
})();
</script>
@endsection
