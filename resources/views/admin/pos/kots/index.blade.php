@extends('admin.master.master')
@section('title', 'KOT List — ' . $restaurantSettingName)

@section('css')
<style>
    .pos-list-table th { white-space:nowrap; font-size:11px; }
    .pos-list-table td { font-size:12px; vertical-align:middle; }
    .pos-list-loading { opacity:.55; pointer-events:none; }
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
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Running / Pending KOT</div>
                <div class="progga-card-subtitle">Completed or cancelled orders are removed automatically</div>
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
    $(document).on('click', '#kotPagination a', function(e) {
        e.preventDefault();
        const $link = $(this);
        const url = $link.attr('href');
        if (!url || url === '#' || $link.hasClass('disabled')) return;

        $('#kotListCard').addClass('pos-list-loading');
        $.ajax({
            url: url,
            type: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function(data) {
                $('#kotRows').html(data.html || '');
                $('#kotPagination').html(data.pagination || '');
                window.history.pushState({}, '', url);
            },
            complete: function() { $('#kotListCard').removeClass('pos-list-loading'); }
        });
    });
})();
</script>
@endsection
