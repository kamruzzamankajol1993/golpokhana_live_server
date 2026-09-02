@extends('admin.master.master')
@section('title', 'POS Session Report — ' . $restaurantSettingName)

@section('css')
<style>
    .report-list-table th { white-space:nowrap; font-size:11px; }
    .report-list-table td { font-size:12px; vertical-align:middle; }
    .report-list-loading { opacity:.55; pointer-events:none; }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">POS Session Report</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">Reports</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">POS Session</span>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('reports.pos_sessions.pdf') }}" target="_blank" rel="noopener" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <a href="{{ route('reports.pos_sessions.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm" style="border-color:#198754;color:#198754;background:#f8fff9;"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </div>

    <div class="progga-card" id="posSessionReportCard">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">All POS Sessions</div>
                <div class="progga-card-subtitle">Complete historical session list across all calendar dates.</div>
            </div>
        </div>

        <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
            <table class="progga-table report-list-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Grand Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="posSessionReportRows">
                    @include('admin.reports.partials.pos_session_report_rows')
                </tbody>
            </table>
        </div>
        <div id="posSessionReportPagination">
            @include('admin.reports.partials.custom_pagination', ['paginator' => $sessions])
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
(function() {
    $(document).on('click', '#posSessionReportPagination a', function(e) {
        e.preventDefault();
        const $link = $(this);
        const url = $link.attr('href');
        if (!url || url === '#' || $link.hasClass('disabled')) return;

        $('#posSessionReportCard').addClass('report-list-loading');
        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(data) {
                $('#posSessionReportRows').html(data.html || '');
                $('#posSessionReportPagination').html(data.pagination || '');
                window.history.pushState({}, '', url);
            },
            complete: function() { $('#posSessionReportCard').removeClass('report-list-loading'); }
        });
    });
})();
</script>
@endsection
