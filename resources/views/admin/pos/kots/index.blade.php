@extends('admin.master.master')
@section('title', 'KOT List — ' . $restaurantSettingName)

@section('css')
<style>
    .pos-list-table th { white-space:nowrap; font-size:11px; }
    .pos-list-table td { font-size:12px; vertical-align:middle; }
    .pos-list-loading { opacity:.55; pointer-events:none; }
    .pos-list-search { position:relative; width:320px; max-width:100%; margin-left:auto; }
    .pos-list-search .bi-search { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:13px; color:#7a817d; pointer-events:none; }
    .pos-list-search .progga-form-control { width:100%; height:36px; padding-left:34px; font-size:12px; }
    @media (max-width: 767px) { .pos-list-search { width:100%; } }
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
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('pos.kots.pdf') }}" target="_blank" rel="noopener" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <a href="{{ route('pos.kots.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm" style="border-color:#198754;color:#198754;background:#f8fff9;"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>
    </div>

    <div class="progga-card" id="kotListCard">
        <div class="progga-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="progga-card-title">Running / Pending KOT</div>
                <div class="progga-card-subtitle">Completed or cancelled orders are removed automatically</div>
            </div>
            <div class="pos-list-search">
                <i class="bi bi-search"></i>
                <input type="text" id="kotSearchInput" class="progga-form-control" value="{{ request('search') }}" placeholder="Search KOT, order, table, waiter..." autocomplete="off">
            </div>
        </div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
            <table class="progga-table pos-list-table">
                <thead>
                    <tr>
                        <th>ID</th>
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
                <tbody id="kotRows">
                    @include('admin.pos.kots.partials.rows')
                </tbody>
            </table>
        </div>
        <div id="kotPagination">
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

    function withKotSearch(url) {
        const target = new URL(url, window.location.href);
        const search = $.trim($('#kotSearchInput').val() || '');
        if (search) target.searchParams.set('search', search);
        else target.searchParams.delete('search');
        return target.toString();
    }

    function loadKotPage(url, historyMode) {
        const ajaxUrl = withKotSearch(url);
        if (activeRequest) activeRequest.abort();
        $('#kotListCard').addClass('pos-list-loading');

        activeRequest = $.ajax({
            url: ajaxUrl,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(data) {
                $('#kotRows').html(data.html || '');
                $('#kotPagination').html(data.pagination || '');
                if (historyMode === 'push') window.history.pushState({}, '', ajaxUrl);
                if (historyMode === 'replace') window.history.replaceState({}, '', ajaxUrl);
            },
            complete: function() {
                $('#kotListCard').removeClass('pos-list-loading');
                activeRequest = null;
            }
        });
    }

    $('#kotSearchInput').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            loadKotPage("{{ route('pos.kots.index') }}", 'replace');
        }, 250);
    });

    $(document).on('click', '#kotPagination a', function(e) {
        e.preventDefault();
        const $link = $(this);
        const url = $link.attr('href');
        if (!url || url === '#' || $link.hasClass('disabled')) return;
        loadKotPage(url, 'push');
    });
})();
</script>
@endsection
