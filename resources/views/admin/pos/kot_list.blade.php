@extends('admin.master.master')
@section('title', 'KOT List — ' . $restaurantSettingName)

@section('css')
<style>
    .pos-list-table th { white-space: nowrap; font-size: 11px; }
    .pos-list-table td { vertical-align: middle; font-size: 12px; }
    .pos-list-loading { opacity: .55; pointer-events: none; }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">KOT List</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">POS System</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">KOT List</span>
            </div>
        </div>
        <a href="{{ route('pos.index') }}" class="progga-btn progga-btn-primary progga-btn-sm">
            <i class="bi bi-display"></i> Open POS
        </a>
    </div>

    <div class="progga-card" id="kotListCard">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Running / Pending KOT</div>
                <div class="progga-card-subtitle">Completed, delivered and cancelled orders are removed automatically. Print uses the same KOT design as POS.</div>
            </div>
            <span class="progga-badge progga-badge-secondary" id="activeKotCount">{{ number_format($activeKots->total()) }} active KOT</span>
        </div>

        <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
            <table class="progga-table pos-list-table">
                <thead>
                    <tr>
                        <th>KOT #</th>
                        <th>Order #</th>
                        <th>Date &amp; Time</th>
                        <th>Table / Type</th>
                        <th>Waiter</th>
                        <th>Qty</th>
                        <th>KOT Status</th>
                        <th>Order Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="kotListRows">
                    @include('admin.pos.partials.kot_list_rows')
                </tbody>
            </table>
        </div>

        <div id="kotListPagination">
            @include('admin.reports.partials.custom_pagination', ['paginator' => $activeKots])
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
(function() {
    let kotListLoading = false;

    function loadKotPage(url, pushState, showLoading) {
        if (kotListLoading) return;
        kotListLoading = true;
        if (showLoading) $('#kotListCard').addClass('pos-list-loading');

        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(data) {
                $('#kotListRows').html(data.html || '');
                $('#kotListPagination').html(data.pagination || '');
                $('#activeKotCount').text(Number(data.total || 0).toLocaleString() + ' active KOT');
                if (pushState) window.history.pushState({}, '', url);
            },
            complete: function() {
                kotListLoading = false;
                $('#kotListCard').removeClass('pos-list-loading');
            }
        });
    }

    $(document).on('click', '#kotListPagination a', function(event) {
        event.preventDefault();
        const $link = $(this);
        if ($link.hasClass('disabled') || $link.attr('aria-disabled') === 'true') return;
        const url = $link.attr('href');
        if (!url || url === '#') return;
        loadKotPage(url, true, true);
    });

    // Keep the operational list current so completed/delivered KOTs disappear without a page reload.
    window.setInterval(function() {
        if (!document.hidden) loadKotPage(window.location.href, false, false);
    }, 10000);
})();
</script>
@endsection
