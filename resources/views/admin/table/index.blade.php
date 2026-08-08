@extends('admin.master.master')
@section('title', 'Table Management — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Table Management</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Tables</span>
            </div>
        </div>
        @can('table-create')
        <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#addTableModal">
            <i class="bi bi-plus-lg"></i> Add Table
        </button>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="font-size:13px;"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="font-size:13px;"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <div class="progga-stat-card" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="progga-stat-icon success" style="width:36px;height:36px;font-size:16px;"><i class="bi bi-check-circle-fill"></i></div>
            <div class="progga-stat-info"><div class="progga-stat-label">Available</div><div class="progga-stat-value" style="font-size:20px;">{{ $availableCount }}</div></div>
        </div>
        <div class="progga-stat-card" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="progga-stat-icon danger" style="width:36px;height:36px;font-size:16px;"><i class="bi bi-people-fill"></i></div>
            <div class="progga-stat-info"><div class="progga-stat-label">Occupied</div><div class="progga-stat-value" style="font-size:20px;">{{ $occupiedCount }}</div></div>
        </div>
        <div class="progga-stat-card" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="progga-stat-icon warning" style="width:36px;height:36px;font-size:16px;"><i class="bi bi-calendar-check"></i></div>
            <div class="progga-stat-info"><div class="progga-stat-label">Reserved</div><div class="progga-stat-value" style="font-size:20px;">{{ $reservedCount }}</div></div>
        </div>
        <div class="progga-stat-card" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="progga-stat-icon primary" style="width:36px;height:36px;font-size:16px;"><i class="bi bi-table"></i></div>
            <div class="progga-stat-info"><div class="progga-stat-label">Total Tables</div><div class="progga-stat-value" style="font-size:20px;">{{ $totalTables }}</div></div>
        </div>
    </div>

    <div class="progga-card" style="margin-bottom:16px;">
        <div class="progga-filters-bar">
            <div class="progga-pos-table-filters" style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="progga-pos-filter-btn active" data-view-filter="all">All Tables</button>
                <button class="progga-pos-filter-btn" data-view-filter="available">Available</button>
                <button class="progga-pos-filter-btn" data-view-filter="occupied">Occupied</button>
                <button class="progga-pos-filter-btn" data-view-filter="reserved">Reserved</button>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;">
                <button class="progga-btn progga-btn-outline progga-btn-sm" id="viewGrid" title="Grid View"><i class="bi bi-grid"></i></button>
                {{-- <button class="progga-btn progga-btn-outline progga-btn-sm" id="viewList" title="List View"><i class="bi bi-list-ul"></i></button> --}}
            </div>
        </div>
    </div>

    <div id="tableGridView">
    <div class="progga-table-grid" id="tableCardGrid">
        @foreach($tables as $table)
            @php
                $orderJson = '{}';
                if($table->dynamic_status == 'occupied' && $table->orders->count() > 0) {
                    $activeOrder = $table->orders->first();
                    $kotsData = [];
                    foreach($activeOrder->kots as $kot) {
                        $itemsData = [];
                        foreach($kot->orderDetails as $item) {
                            $itemsData[] = [
                                'name' => $item->product_name,
                                'qty' => $item->quantity,
                                'price' => $item->subtotal / ($item->quantity > 0 ? $item->quantity : 1)
                            ];
                        }
                        $kotsData[] = [
                            'id' => $kot->kot_number,
                            'status' => $kot->kitchen_status,
                            'time' => $kot->created_at->format('h:i A'),
                            'items' => $itemsData
                        ];
                    }

                    // ডাইনামিক ডাটা অ্যারে
                    $orderData = [
                        'orderId' => '#' . $activeOrder->order_number,
                        'waiter' => $activeOrder->waiter->name ?? 'Unassigned',
                        'elapsed' => \Carbon\Carbon::parse($activeOrder->created_at)->diffInMinutes(now()),
                        'subtotal' => $activeOrder->subtotal,
                        'tax' => $activeOrder->vat_tax,
                        'service_charge' => $activeOrder->service_charge,
                        'discount' => $activeOrder->discount_amount,
                        'grand_total' => $activeOrder->grand_total,
                        'kots' => $kotsData
                    ];
                    $orderJson = json_encode($orderData);
                }
            @endphp

            <div class="progga-table-card {{ $table->dynamic_status }}"
                 data-status="{{ $table->dynamic_status }}"
                 data-table-num="{{ $table->table_number }}"
                 data-zone="{{ $table->zone->name ?? 'N/A' }}"
                 data-capacity="{{ $table->seating_capacity }}"
                 @if($table->dynamic_status == 'occupied')
                 data-order="{{ htmlspecialchars($orderJson, ENT_QUOTES, 'UTF-8') }}"
                 @endif>

                <div class="progga-table-card-actions">
                    @can('table-edit')
                    <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick="editTableData({{ $table->id }}, '{{ $table->table_number }}', {{ $table->seating_capacity }}, '{{ $table->zone_id }}', '{{ $table->initial_status }}', '{{ $table->notes }}')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    @endcan

                    @can('table-delete')
                    <form action="{{ route('table.destroy', $table->id) }}" method="POST" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="button" class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm progga-delete-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endcan
                </div>

                <div class="progga-table-card-icon"><i class="bi bi-layout-wtf"></i></div>
                <div class="progga-table-card-num">{{ $table->table_number }}</div>
                <div class="progga-table-card-info"><i class="bi bi-people"></i> {{ $table->seating_capacity }} seats · {{ $table->zone->name ?? 'N/A' }}</div>
                <span class="progga-badge progga-status-{{ $table->dynamic_status }}">{{ ucfirst($table->dynamic_status) }}</span>
            </div>
        @endforeach
    </div>
</div>
</main>

@include('admin.table.modals.add_table')
@include('admin.table.modals.edit_table')
@include('admin.table.modals.table_offcanvas')

@endsection

@section('script')
<script>
    /* ─── View filter ─── */
    document.querySelectorAll('[data-view-filter]').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('[data-view-filter]').forEach(b=>b.classList.remove('active'));
            btn.classList.add('active');
            var filter = btn.dataset.viewFilter;
            document.querySelectorAll('.progga-table-card').forEach(function(card){
                card.style.display = (filter === 'all' || card.dataset.status === filter) ? '' : 'none';
            });
        });
    });

    // Edit Modal Data Pass
    window.editTableData = function(id, table_number, capacity, zone_id, status, notes) {
        let formAction = "{{ route('table.update', ':id') }}".replace(':id', id);
        $('#editTableForm').attr('action', formAction);

        $('#edit_table_number').val(table_number);
        $('#edit_capacity').val(capacity);
        $('#edit_zone_id').val(zone_id).trigger('change');
        $('#edit_initial_status').val(status).trigger('change');
        $('#edit_notes').val(notes);

        $('#editTableModal').modal('show');
    }

    /* ─── Occupied table card click (Static JS kept intact) ─── */
    /* ─── Occupied table card click ─── */
/* ─── Table card click logic ─── */
$(document).on('click', '.progga-table-card', function(e) {
    // Action button click hole skip korbe
    if (e.target.closest('.progga-table-card-actions')) return;

    let card = this;
    let tableNum = $(card).data('table-num');
    let zone = $(card).data('zone');
    let capacity = $(card).data('capacity');
    let orderDataStr = $(card).attr('data-order');
    let order = null;

    // JSON parse korar chesta
    try {
        if (orderDataStr && orderDataStr !== '{}') {
            order = JSON.parse(orderDataStr);
        }
    } catch (err) {
        console.error("Invalid order JSON:", err);
    }

    // Offcanvas render kora (data thakuk ba na thakuk)
    renderTableOffcanvas(tableNum, zone, capacity, order);

    // Offcanvas show kora
    var ocElement = document.getElementById('tableOrderOffcanvas');
    bootstrap.Offcanvas.getOrCreateInstance(ocElement).show();
});

    function fmt(n) { return '৳' + parseFloat(n).toFixed(2); }

   function renderTableOffcanvas(tableNum, zone, capacity, order) {
    // Basic Info set kora
    document.getElementById('ocTableNum').textContent  = tableNum;
    document.getElementById('ocTableMeta').textContent = (zone || 'N/A') + ' · ' + (capacity || 0) + ' seats';

    let bodyArea = document.getElementById('ocBody');
    let totalsArea = document.getElementById('ocTotals');
    let chipsArea = document.getElementById('ocChips');

    // ১. Jodi data na thake (No Data Case)
    if (!order || Object.keys(order).length === 0) {
        chipsArea.innerHTML = '<span class="progga-oc-chip"><i class="bi bi-info-circle"></i> No Active Order</span>';
        bodyArea.innerHTML = `
            <div class="text-center text-muted py-5" style="margin-top: 50px;">
                <i class="bi bi-cart-x" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3" style="font-weight: 600;">No active order data available</p>
                <small>This table is currently empty or has no pending orders.</small>
            </div>`;
        totalsArea.innerHTML = '';
        return;
    }

    // ২. Jodi data thake (Order Found Case)
    chipsArea.innerHTML =
        '<span class="progga-oc-chip"><i class="bi bi-receipt"></i> ' + order.orderId + '</span>' +
        '<span class="progga-oc-chip"><i class="bi bi-person"></i> ' + order.waiter + '</span>' +
        '<span class="progga-oc-chip"><i class="bi bi-clock"></i> ' + order.elapsed + ' min</span>';

    var bodyHtml = '<div class="progga-oc-section-label" style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #888; margin-bottom: 12px; letter-spacing: 0.5px;">Current Order Items</div>';

    order.kots.forEach(function(kot) {
        let badgeClass = kot.status === 'Pending' ? 'bg-warning text-dark' : (kot.status === 'Cooking' ? 'bg-info text-white' : 'bg-success text-white');

        bodyHtml += `
            <div class="progga-oc-kot" style="background: #fff; border-radius: 12px; border: 1px solid #eee; padding: 12px; margin-bottom: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                <div class="progga-oc-kot-head" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #eee; padding-bottom: 8px; margin-bottom: 10px;">
                    <span class="progga-oc-kot-label" style="font-weight: 800; color: var(--progga-primary); font-size: 13px;">${kot.id}</span>
                    <div>
                        <span class="progga-oc-kot-time" style="font-size: 11px; color: #999; margin-right: 8px;"><i class="bi bi-clock me-1"></i>${kot.time}</span>
                        <span class="badge ${badgeClass}" style="font-size: 10px;">${kot.status}</span>
                    </div>
                </div>`;

        kot.items.forEach(function(item) {
            var lineTotal = item.qty * item.price;
            bodyHtml += `
                <div class="progga-oc-item" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <span class="progga-oc-item-name" style="font-size: 13px; font-weight: 600; color: #444; flex: 1;">${item.name}</span>
                    <span class="progga-oc-item-qty" style="font-size: 12px; color: #777; margin: 0 10px;">×${item.qty}</span>
                    <span class="progga-oc-item-price" style="font-size: 13px; font-weight: 700; color: var(--progga-primary);">${fmt(lineTotal)}</span>
                </div>`;
        });
        bodyHtml += '</div>';
    });
    bodyArea.innerHTML = bodyHtml;

    // Totals logic
    var taxAndService = parseFloat(order.tax) + parseFloat(order.service_charge);
    var totalsHtml = `<div class="progga-oc-total-row" style="display: flex; justify-content: space-between; font-size: 14px; color: #666; margin-bottom: 4px;"><span>Subtotal</span><span>${fmt(order.subtotal)}</span></div>`;

    if(order.discount > 0) {
        totalsHtml += `<div class="progga-oc-total-row" style="display: flex; justify-content: space-between; font-size: 14px; color: #d33; margin-bottom: 4px;"><span>Discount</span><span>−${fmt(order.discount)}</span></div>`;
    }

    totalsHtml += `
        <div class="progga-oc-total-row" style="display: flex; justify-content: space-between; font-size: 14px; color: #666; margin-bottom: 4px;"><span>Tax & Service</span><span>${fmt(taxAndService)}</span></div>
        <div class="progga-oc-total-row grand" style="display: flex; justify-content: space-between; font-size: 20px; font-weight: 900; color: var(--progga-primary); margin-top: 8px; border-top: 2px solid #f1f1f1; padding-top: 8px;"><span>TOTAL</span><span>${fmt(order.grand_total)}</span></div>`;

    totalsArea.innerHTML = totalsHtml;
}
</script>
@endsection
