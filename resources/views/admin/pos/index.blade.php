@extends('admin.pos.master')
@section('title', 'POS System — TableTrack RMS')

@section('css')
<style>
    .modal-backdrop { display: none !important; }
    body.modal-open { overflow: auto !important; padding-right: 0 !important; }

    .swal2-container {
        z-index: 99999 !important;
    }

    .pos-type-wrap:has(#posTypeDineIn:checked) label[for="posTypeDineIn"],
    .pos-type-wrap:has(#posTypeTakeaway:checked) label[for="posTypeTakeaway"],
    .pos-type-wrap:has(#posTypeDelivery:checked) label[for="posTypeDelivery"] {
      background: var(--progga-primary) !important;
      color: var(--progga-secondary) !important;
    }


    .progga-session-status {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
      border: 1px solid rgba(25, 135, 84, 0.25);
      background: rgba(25, 135, 84, 0.10);
      color: #198754;
      white-space: nowrap;
    }

    .progga-session-status.no-session {
      border-color: rgba(220, 53, 69, 0.25);
      background: rgba(220, 53, 69, 0.10);
      color: #dc3545;
    }

    .progga-session-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: #198754;
      box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.14);
    }

    .progga-session-status.no-session .progga-session-dot {
      background: #dc3545;
      box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.14);
    }



    .progga-pos-cat-name {
      font-size: 11px !important;
      line-height: 1.2 !important;
      font-weight: 900 !important;
    }

    .pos-closed-screen {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: #f4f6f9;
    }

    .pos-closed-card {
      width: min(560px, 100%);
      background: #ffffff;
      border: 1px solid rgba(220, 53, 69, 0.18);
      border-radius: 20px;
      box-shadow: 0 18px 45px rgba(31, 41, 55, 0.12);
      padding: 42px 30px;
      text-align: center;
    }

    .pos-closed-icon {
      width: 76px;
      height: 76px;
      margin: 0 auto 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(220, 53, 69, 0.10);
      color: #dc3545;
      font-size: 34px;
    }

    .pos-closed-title {
      margin: 0 0 10px;
      font-size: 28px;
      font-weight: 900;
      color: #1f2937;
    }

    .pos-closed-message {
      margin: 0;
      font-size: 20px;
      font-weight: 800;
      color: #dc3545;
    }

    .pos-closed-window {
      margin-top: 14px;
      font-size: 13px;
      color: #6b7280;
    }

    .pos-closed-actions {
      margin-top: 24px;
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    @media (max-width: 767.98px) {
      .progga-session-status {
        padding: 6px 9px;
        font-size: 11px;
      }
      .progga-session-start-label {
        display: none;
      }
    }
</style>
@endsection

@section('body')
@if(!($isPosOrderTimeOpen ?? true))
<div class="pos-closed-screen" aria-hidden="true">
    <div class="pos-closed-icon"><i class="bi bi-clock-history"></i></div>
</div>
@else
<div class="progga-pos-wrapper">
   <div class="progga-pos-header">
      <div class="progga-pos-logo">
        @if(!empty($restaurantSettingIconName))
            <img src="{{ asset('public/'.$restaurantSettingIconName) }}" alt="Icon" style="width: 60px; height: 60px; object-fit: contain;">
        @else
            {{ strtoupper(substr($restaurantSettingName ?? 'P', 0, 1)) }}
        @endif
        <span>{{ $restaurantSettingName ?? 'Progga RMS' }}</span>
      </div>

      <div class="progga-pos-step-indicator">
        <div class="progga-pos-step active" id="indStep1"><span class="progga-pos-step-num">1</span> Table</div>
        <span class="progga-pos-step-divider">›</span>
        <div class="progga-pos-step" id="indStep2"><span class="progga-pos-step-num">2</span> Order</div>
      </div>

      <div class="d-flex align-items-center" style="gap: 15px;">
        @if($randomHalfOrderButtonVisible ?? false)
            <form action="{{ route('pos.random_half_order.activate') }}" method="POST" class="m-0 p-0">
                @csrf
                <button type="submit" class="progga-btn progga-btn-primary progga-btn-sm text-decoration-none" title="Apply saved order hide percentage">
                    <i class="bi bi-shuffle"></i> Go
                </button>
            </form>
        @endif
        @if(!auth()->user()->hasRole('waiter'))
            @if($activeSession)
                <div class="progga-session-status" title="Current POS session is running">
                    <span class="progga-session-dot"></span>
                    <span>Session Running</span>
                    <span class="progga-session-start-label">• Started {{ $activeSession->start_time->format('h:i A') }}</span>
                    <span>• <span id="posSessionTimer" data-start="{{ $activeSession->start_time->format('Y-m-d H:i:s') }}">00h 00m</span></span>
                </div>
            @else
                <div class="progga-session-status no-session" title="No active POS session found">
                    <span class="progga-session-dot"></span>
                    <span>No Active Session</span>
                </div>
            @endif
            <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm text-decoration-none" data-bs-toggle="modal" data-bs-target="#sessionHistoryModal">
                <i class="bi bi-history"></i> Session History
            </button>
            <a href="{{ route('home') }}" class="progga-pos-close m-0"><i class="bi bi-house"></i></a>
        @else
            <form action="{{ route('logout') }}" method="POST" class="m-0 p-0">
                @csrf
                <button type="submit" class="progga-btn progga-btn-danger progga-btn-sm" title="Logout from POS">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </form>
        @endif
      </div>
    </div>

    <div class="progga-pos-body">
        @include('admin.pos.step1_tables')
        @include('admin.pos.step2_order')
    </div>
</div>

@include('admin.pos.modals.new_order')
@include('admin.pos.modals.addon')
@include('admin.pos.modals.payment')
@include('admin.pos.partials.offcanvas_wrapper')

<div class="modal fade progga-modal" id="sessionHistoryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="sessionModalTitle">
                    <i class="bi bi-table me-2"></i>Work Period Sessions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" style="max-height: 500px; overflow-y: auto;">

                <div id="sessionListView">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle text-center" style="font-size: 13px;">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Employee</th>
                                    <th>Day</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                    <th>Grand Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sessions ?? [] as $key => $sess)
                                    <tr>
                                        <td><strong>#{{ $key+1 }}</strong></td>
                                        <td>{{ $sess->user->name ?? 'N/A' }}</td>
                                        <td><span class="badge bg-secondary">{{ $sess->weekday }}</span></td>
                                        <td>{{ $sess->start_time->format('d M y - h:i A') }}</td>
                                        <td>{{ $sess->end_time ? $sess->end_time->format('d M y - h:i A') : '—' }}</td>
                                        <td>{{ $sess->duration ?? 'Running' }}</td>
                                        <td><strong>৳{{ round($sess->grand_total) }}</strong></td>
                                        <td>
                                            <span class="badge {{ $sess->status == 'Open' ? 'bg-success' : 'bg-danger' }}">
                                                {{ $sess->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-center">
                                                <button type="button"
                                                    class="btn btn-sm btn-primary btnEditSession"
                                                    data-id="{{ $sess->id }}"
                                                    data-start="{{ $sess->start_time ? $sess->start_time->format('Y-m-d\TH:i') : '' }}"
                                                    data-end="{{ $sess->end_time ? $sess->end_time->format('Y-m-d\TH:i') : '' }}"
                                                    data-status="{{ $sess->status }}">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                @if($sess->status == 'Closed')
                                                    <a href="{{ route('pos.session.report', $sess->id) }}" target="_blank" class="btn btn-sm btn-warning fw-bold">
                                                        <i class="bi bi-printer"></i> Print
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-muted py-4">No sessions found!</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="sessionEditView" style="display: none;">
                    <form id="inlineEditSessionForm">
                        <input type="hidden" id="inlineEditSessionId" name="session_id">

                        <div class="row mt-2">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" style="font-size: 13px;">Start Time</label>
                                <input type="datetime-local" id="inlineEditStartTime" name="start_time" class="form-control" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" style="font-size: 13px;">End Time</label>
                                <input type="datetime-local" id="inlineEditEndTime" name="end_time" class="form-control">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold" style="font-size: 13px;">Status</label>
                                <select id="inlineEditStatus" name="status" class="form-control">
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-3 border-top pt-3">
                            <button type="button" class="btn btn-secondary fw-bold" id="btnCancelEdit">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </button>
                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

@endif
@endsection

@section('script')
@if($isPosOrderTimeOpen ?? true)

<script>
    @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')), timer: 1800, showConfirmButton: false });
    @endif
    @if(session('error'))
        Swal.fire({ icon: 'error', title: 'Error', text: @json(session('error')) });
    @endif

(function bootPosScriptWhenJqueryReady() {
    if (!window.jQuery) {
        setTimeout(bootPosScriptWhenJqueryReady, 50);
        return;
    }


    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    let currentOrder = {
        order_type: 'dine_in', table_id: null, table_name: '',
        waiter_id: null, waiter_name: '', customer_id: null, customer_name: '',
        customer_phone: '', is_walk_in: 1, order_notes: '', is_complimentary_order: 0
    };
    window.currentOrder = currentOrder;
    let currentCat = '';
    let isComplimentaryMode = false;
    let isWaiter = @json(auth()->user()->hasRole('waiter'));

    function updatePosSessionTimer() {
        var timer = document.getElementById('posSessionTimer');
        if (!timer) return;

        var startText = timer.getAttribute('data-start');
        if (!startText) return;

        var startTime = new Date(String(startText).replace(' ', 'T'));
        var now = new Date();
        var diffMs = now - startTime;
        if (diffMs < 0) diffMs = 0;

        var totalMinutes = Math.floor(diffMs / 60000);
        var hours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;

        timer.textContent = String(hours).padStart(2, '0') + 'h ' + String(minutes).padStart(2, '0') + 'm';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updatePosSessionTimer);
    } else {
        updatePosSessionTimer();
    }
    setInterval(updatePosSessionTimer, 60000);

    $(document).ready(function() {
        // POS workflow note.
        if(isWaiter) {
            $('#btnSendToKitchen').html('<i class="bi bi-send"></i> Send to Front Desk');
        }

        loadFoods('');
    });

    function getCartParams() {
        return {
            order_id: currentOrder.order_id || null,
            order_type: currentOrder.order_type,
            table_id: currentOrder.table_id
        };
    }

    function setPosTableStatus(tableId, status) {
        if (!tableId) return;

        const normalized = String(status || 'available').toLowerCase();
        const label = normalized.charAt(0).toUpperCase() + normalized.slice(1);
        const $card = $('.progga-pos-table-card[data-table-id="' + tableId + '"]');

        if ($card.length) {
            $card.removeClass('available occupied reserved')
                .addClass(normalized)
                .attr('data-status', normalized)
                .data('status', normalized);

            $card.find('.progga-badge')
                .removeClass('progga-status-available progga-status-occupied progga-status-reserved')
                .addClass('progga-status-' + normalized)
                .text(label);
        }

        const $option = $('#modalTableSelect option[value="' + tableId + '"]');
        if ($option.length) {
            let baseText = $option.data('table-name') || $option.text();
            baseText = String(baseText).replace(/\s+—\s+(Occupied|Reserved|Available)$/i, '');
            $option.attr('data-status', normalized).data('status', normalized);

            if (normalized === 'available') {
                $option.prop('disabled', false).text(baseText);
            } else {
                $option.prop('disabled', true).text(baseText + ' — ' + label);
            }
        }

        const activeFilter = $('.progga-pos-filter-btn.active').data('table-filter');
        if (activeFilter && activeFilter !== 'all') {
            $('.progga-pos-table-card').hide();
            $('.progga-pos-table-card[data-status="' + activeFilter + '"]').show();
        }
    }


    let newOrderModalMode = 'all';
    // POS workflow note.
    // POS workflow note.
    let newOrderStartedFromModal = false;

    function resetNewOrderModalCommon() {
        $('#posWalkIn').prop('checked', true).trigger('change');
        $('#order_notes').val('');
        $('#posComplimentaryOrder').prop('checked', false);
        currentOrder.is_complimentary_order = 0;
        isComplimentaryMode = false;
        $('#newCustomerForm').hide();
        $('#customerSearchContainer').show();
        $('#new_cus_name, #new_cus_phone').val('');
        $('#showNewCustomerFormBtn').text('+ Add New Customer').removeClass('progga-btn-danger').addClass('progga-btn-primary');
    }

    function openAllTypeOrderModal() {
        newOrderModalMode = 'all';
        currentOrder.table_id = null;
        currentOrder.table_name = '';
        currentOrder.order_type = 'dine_in';
        currentOrder.order_id = null;

        $('#labelDineIn, #labelTakeaway, #labelDelivery').show();
        $('#posTypeDineIn, #posTypeTakeaway, #posTypeDelivery').prop('disabled', false);
        $('#posTypeDineIn').prop('checked', true);
        $('#posTypeTakeaway, #posTypeDelivery').prop('checked', false);
        $('#modalTableSelect').val('').trigger('change');
        $('#modalSelectedTableNum').text('T-00');
        $('#modalTableSelectSection').show();
        $('#modalTableDisplaySection').hide();
        resetNewOrderModalCommon();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newOrderModal')).show();
    }

    function resetNewOrderModalAfterClose() {
        newOrderModalMode = 'all';
        currentOrder.table_id = null;
        currentOrder.table_name = '';
        currentOrder.order_type = 'dine_in';
        currentOrder.order_id = null;

        $('#labelDineIn, #labelTakeaway, #labelDelivery').show();
        $('#posTypeDineIn, #posTypeTakeaway, #posTypeDelivery').prop('disabled', false);
        $('#posTypeDineIn').prop('checked', true);
        $('#posTypeTakeaway, #posTypeDelivery').prop('checked', false);

        // POS workflow note.
        $('#modalTableSelect').val('').trigger('change');
        $('#modalSelectedTableNum').text('T-00');
        $('#modalTableSelectSection').show();
        $('#modalTableDisplaySection').hide();

        resetNewOrderModalCommon();
    }

    $('#newOrderModal').on('hidden.bs.modal', function () {
        // POS workflow note.
        if (newOrderStartedFromModal) {
            newOrderStartedFromModal = false;
            return;
        }

        resetNewOrderModalAfterClose();
    });

    function openDineInTableModal(tableId, tableName) {
        newOrderModalMode = 'table';
        currentOrder.table_id = tableId;
        currentOrder.table_name = tableName;
        currentOrder.order_type = 'dine_in';
        currentOrder.order_id = null;

        $('#labelDineIn').show();
        $('#labelTakeaway, #labelDelivery').hide();
        $('#posTypeDineIn').prop('disabled', false).prop('checked', true);
        $('#posTypeTakeaway, #posTypeDelivery').prop('disabled', true);
        $('#modalTableSelect').val(tableId);
        $('#modalTableSelectSection').hide();
        $('#modalSelectedTableNum').text(tableName);
        $('#modalTableDisplaySection').slideDown();
        resetNewOrderModalCommon();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newOrderModal')).show();
    }

    function showStep(step) {
        $('.progga-pos-screen').removeClass('active');
        $('#posStep' + step).addClass('active');
        $('.progga-pos-step').removeClass('active');
        $('#indStep' + step).addClass('active');

        if(step === 2) {
            let tableMetaHtml = currentOrder.table_name;
            if(currentOrder.order_type === 'takeaway') tableMetaHtml = '<span class="text-danger">Takeaway</span>';
            if(currentOrder.order_type === 'delivery') tableMetaHtml = '<span class="text-warning">Delivery</span>';
            $('#posSelectedTableMeta').html(tableMetaHtml);

            let typeText = 'Dine-In';
            if(currentOrder.order_type === 'takeaway') typeText = 'Takeaway';
            if(currentOrder.order_type === 'delivery') typeText = 'Delivery';
            $('#metaType').text(typeText);

            let customerText = currentOrder.is_walk_in === 1 ? 'Walk-in Customer' : (currentOrder.customer_name ? currentOrder.customer_name : 'Registered Customer');
            $('#metaCustomer').text(customerText);

            let waiterText = currentOrder.waiter_id ? currentOrder.waiter_name : 'Unassigned';
            $('#metaWaiter').html('<i class="bi bi-person-badge"></i> ' + waiterText);

            if(currentOrder.is_complimentary_order === 1) {
                $('#posSelectedTableMeta').append(' <span class="badge bg-success ms-1" style="font-size:10px;">Complimentary</span>');
            }

            loadFoods('');
            loadCart();
        }
    }

    $('#posBackToTables').click(function() { showStep(1); });

    function reloadActiveOrderOffcanvas(orderId, tableId, orderType) {
        const normalizedType = String(orderType || '')
            .toLowerCase()
            .replace(/[\s-]+/g, '_');
        const isDineIn = normalizedType === 'dine_in' || normalizedType === 'dinein';
        const url = isDineIn && tableId
            ? "{{ route('pos.get_table_order', ':id') }}".replace(':id', tableId)
            : "{{ route('pos.get_pos_order', ':id') }}".replace(':id', orderId);

        return $.get(url, function(res) {
            if (typeof res === 'object' && res !== null) {
                if (res.status === 'load_cart') {
                    bootstrap.Offcanvas.getInstance(document.getElementById('tableOrderOffcanvas'))?.hide();
                    window.loadHeldQrOrderToPos(res.order_data);
                    return;
                }

                if (res.status === 'error') {
                    Swal.fire('Notice', res.message || 'Could not reload the active order.', 'info');
                    return;
                }
            }

            $('#ocBody').html(res);
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tableOrderOffcanvas')).show();
        });
    }

    $(document).on('click', '.progga-pos-table-card', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const $card = $(this);
        const tId = $card.attr('data-table-id');
        const tNum = $card.attr('data-table-num');

        if(!tId || $card.data('opening-order') === true) {
            return;
        }

        $card.data('opening-order', true);

        // Always verify from server first.
        // After table swap, frontend card status can be stale for a moment;
        // server check prevents New Order modal and active order offcanvas from opening together.
        $.get("{{ route('pos.get_table_order', ':id') }}".replace(':id', tId), function(res) {
            $card.data('opening-order', false);

            if(typeof res === 'object' && res !== null) {
                if(res.status === 'load_cart') {
                    bootstrap.Modal.getInstance(document.getElementById('newOrderModal'))?.hide();
                    window.loadHeldQrOrderToPos(res.order_data);
                    return;
                }

                if(res.status === 'error') {
                    setPosTableStatus(tId, 'available');
                    openDineInTableModal(tId, tNum);
                    return;
                }
            }

            // HTML response means active order exists, so only offcanvas should open.
            bootstrap.Modal.getInstance(document.getElementById('newOrderModal'))?.hide();
            setPosTableStatus(tId, 'occupied');
            $('#ocBody').html(res);
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tableOrderOffcanvas')).show();
        }).fail(function() {
            $card.data('opening-order', false);

            const uiStatus = String($card.attr('data-status') || '').toLowerCase();
            if(uiStatus === 'occupied' || $card.hasClass('occupied')) {
                Swal.fire('Error', 'Could not load active table order. Please try again.', 'error');
                return;
            }

            openDineInTableModal(tId, tNum);
        });
    });

   $('#modeTakeaway').click(function() {
        openAllTypeOrderModal();
    });

    $('#modeTakeawayDeliveryList').click(function() {
        $('#posTableGrid').hide();
        $('#posTableSection').hide();
        $('#posTakeawayDeliveryPanel').fadeIn('fast');
    });

    $('#btnCompletePendingTakeawayDelivery').click(function() {
        const $btn = $(this);
        const originalHtml = $btn.html();
        const pendingCards = $('.progga-pos-running-order-card').filter(function() {
            return String($(this).attr('data-order-status') || '').toLowerCase() === 'pending';
        });

        if(pendingCards.length < 1) {
            Swal.fire('No Pending Order', 'No pending Takeaway / Delivery order found.', 'info');
            return;
        }

        Swal.fire({
            title: 'Complete pending payments?',
            text: pendingCards.length + ' pending Takeaway / Delivery order(s) will be marked as paid and completed.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, complete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if(!result.isConfirmed) return;

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

            $.post("{{ route('pos.takeaway_delivery.complete_pending') }}", {}, function(res) {
                if(res.status === 'success') {
                    const completedIds = (res.completed_ids || []).map(function(id) { return String(id); });

                    completedIds.forEach(function(id) {
                        const $card = $('.progga-pos-running-order-card[data-order-id="' + id + '"]');
                        $card.attr('data-order-status', 'completed').addClass('completed');
                        $card.find('.js-td-order-status-badge')
                             .removeClass('progga-status-occupied')
                             .addClass('progga-status-available')
                             .text('Completed');
                    });

                    const remainingPending = $('.progga-pos-running-order-card').filter(function() {
                        return String($(this).attr('data-order-status') || '').toLowerCase() === 'pending';
                    }).length;

                    $btn.html('<i class="bi bi-check2-circle"></i> Complete Pending Payment (' + remainingPending + ')');
                    Swal.fire('Done', res.message || 'Pending payments completed successfully.', 'success');
                } else {
                    $btn.html(originalHtml);
                    Swal.fire('Error', res.message || 'Payment completion failed.', 'error');
                }
            }).fail(function(xhr) {
                $btn.html(originalHtml);
                Swal.fire('Error', xhr.responseJSON?.message || 'Payment completion failed.', 'error');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        });
    });

    $('#btnBackToTablesFromTdOrders').click(function() {
        $('#posTakeawayDeliveryPanel').hide();
        $('#posTableSection').show();
        $('#posTableGrid').fadeIn('fast');
    });

    $(document).on('click', '.progga-pos-running-order-card', function() {
        if(String($(this).attr('data-order-status') || '').toLowerCase() === 'completed') {
            Swal.fire('Completed', 'This Takeaway / Delivery order is already completed.', 'success');
            return;
        }

        const orderId = $(this).data('order-id');

        $.get("{{ route('pos.get_pos_order', ':id') }}".replace(':id', orderId), function(res) {
            if(res.status === 'load_cart') {
                window.loadHeldQrOrderToPos(res.order_data);
            } else if(res.status === 'error') {
                Swal.fire('Notice', res.message, 'info');
            } else {
                $('#ocBody').html(res);
                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('tableOrderOffcanvas')).show();
            }
        });
    });

   $('input[name="orderType"]').on('change', function() {
        let type = $(this).val();
        currentOrder.order_type = type;
        currentOrder.order_id = null;

        if(type === 'takeaway' || type === 'delivery') {
            $('#modalTableSelectSection').slideUp();
            $('#modalTableDisplaySection').slideUp();
            $('#modalTableSelect').val('').trigger('change');
            currentOrder.table_id = null;
            currentOrder.table_name = type === 'takeaway' ? 'Takeaway' : 'Delivery';
        } else {
            if(newOrderModalMode === 'table') {
                $('#modalTableSelectSection').hide();
                $('#modalSelectedTableNum').text(currentOrder.table_name);
                $('#modalTableDisplaySection').slideDown();
            } else {
                $('#modalTableDisplaySection').hide();
                $('#modalTableSelectSection').slideDown();
                let selectedOption = $('#modalTableSelect option:selected');
                currentOrder.table_id = $('#modalTableSelect').val() || null;
                currentOrder.table_name = currentOrder.table_id ? (selectedOption.data('table-name') || selectedOption.text()) : '';
            }
        }
    });

    $('#modalTableSelect').on('change', function() {
        let selectedOption = $(this).find('option:selected');
        currentOrder.table_id = $(this).val() || null;
        currentOrder.table_name = currentOrder.table_id ? (selectedOption.data('table-name') || selectedOption.text()) : '';
        if(currentOrder.table_id) {
            $('#modalSelectedTableNum').text(currentOrder.table_name);
        }
    });

    $('#posWalkIn').on('change', function() {
        if($(this).is(':checked')) { $('#posCustomerFields').slideUp(); }
        else { $('#posCustomerFields').slideDown(); }
    });

    $('#showNewCustomerFormBtn').on('click', function() {
        let isNewFormVisible = $('#newCustomerForm').is(':hidden');
        if(isNewFormVisible) {
            $('#newCustomerForm').slideDown();
            $('#customerSearchContainer').hide();
            $(this).text('- Cancel New Customer').removeClass('progga-btn progga-btn-primary').addClass('progga-btn progga-btn-danger');
        } else {
            $('#newCustomerForm').slideUp();
            $('#customerSearchContainer').show();
            $(this).text('+ Add New Customer').removeClass('progga-btn progga-btn-danger').addClass('progga-btn progga-btn-primary');
            $('#new_cus_name').val('');
            $('#new_cus_phone').val('');
        }
    });

    $('#posStartOrderBtn').click(function() {
        let selectedOrderType = $('input[name="orderType"]:checked').val() || 'dine_in';
        currentOrder.order_type = selectedOrderType;

        if(selectedOrderType === 'dine_in') {
            if(newOrderModalMode === 'all') {
                let selectedOption = $('#modalTableSelect option:selected');
                currentOrder.table_id = $('#modalTableSelect').val() || null;
                currentOrder.table_name = currentOrder.table_id ? (selectedOption.data('table-name') || selectedOption.text()) : '';
            }

            if(!currentOrder.table_id) {
                Swal.fire('Notice', 'Please select an available table for Dine-In order.', 'info');
                return;
            }
        } else {
            currentOrder.table_id = null;
            currentOrder.table_name = selectedOrderType === 'takeaway' ? 'Takeaway' : 'Delivery';
        }

        currentOrder.waiter_id = $('#posWaiterSelect').val();
        currentOrder.waiter_name = currentOrder.waiter_id ? $('#posWaiterSelect option:selected').text() : 'Unassigned';
        currentOrder.is_walk_in = $('#posWalkIn').is(':checked') ? 1 : 0;
        currentOrder.order_notes = $('#order_notes').val() || '';
        currentOrder.is_complimentary_order = $('#posComplimentaryOrder').is(':checked') ? 1 : 0;
        isComplimentaryMode = currentOrder.is_complimentary_order === 1;

        if(currentOrder.is_walk_in === 0) {
            currentOrder.customer_id = $('#posCustomerSelect').val();
            let newName = $('#new_cus_name').val();
            if (newName) {
                currentOrder.customer_name = newName;
            } else if (currentOrder.customer_id) {
                currentOrder.customer_name = $('#posCustomerSelect option:selected').text().split(' - ')[0];
            } else {
                currentOrder.customer_name = 'Registered Customer';
            }
            currentOrder.customer_phone = $('#new_cus_phone').val();
        } else {
            currentOrder.customer_name = 'Walk-in Customer';
            currentOrder.customer_id = null;
        }

        newOrderStartedFromModal = true;
        bootstrap.Modal.getInstance(document.getElementById('newOrderModal')).hide();
        showStep(2);
    });

    function loadFoods(catId = '', search = '') {
        currentCat = catId;
        $.get("{{ route('pos.get_foods') }}", { category_id: catId, search: search }, function(res) {
            $('#posFoodGrid').html(res);
        });
    }

    $(document).on('click', '.progga-pos-cat-item', function() {
        $('.progga-pos-cat-item').removeClass('active');
        $(this).addClass('active');
        loadFoods($(this).data('cat-id'), $('#posFoodSearch').val());
    });
    $('#posFoodSearch').on('keyup', function() { loadFoods(currentCat, $(this).val()); });

    window.checkAddonAndCart = function(foodId, hasAddon) {
        if(hasAddon == 1) {
            $.get("{{ route('pos.get_addons', ':id') }}".replace(':id', foodId), function(res) {
                $('#addonModalFoodName').text(res.food.name);
                $('#addonModalFoodId').val(foodId);
                let addonHtml = '';
                res.food.addons.forEach(addon => {
                    addonHtml += `<label class="d-block border p-2 mb-2"><input type="checkbox" name="addons[]" value="${addon.id}"> ${addon.name} (+৳${addon.price})</label>`;
                });
                $('#addonListDiv').html(addonHtml);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('addonModal')).show();
            });
        } else {
            addToCart(foodId, []);
        }
    }

    $('#addonForm').on('submit', function(e) {
        e.preventDefault();
        let foodId = $('#addonModalFoodId').val();
        let addons = [];
        $('input[name="addons[]"]:checked').each(function() { addons.push($(this).val()); });
        addToCart(foodId, addons);
        bootstrap.Modal.getInstance(document.getElementById('addonModal')).hide();
    });

    function addToCart(foodId, addons) {
        let payload = getCartParams();
        payload.food_id = foodId;
        payload.addons = addons;
        payload.qty = 1;
        payload.is_complimentary = isComplimentaryMode ? 1 : 0;
        $.post("{{ route('pos.cart.add') }}", payload, function(res) {
            if(res.status === 'success') loadCart();
        }).fail(function(xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'The restaurant is currently closed. Orders will be accepted from 12:01 PM.';
            window.Swal.fire('POS Closed', message, 'warning');
        });
    }

    function loadCart(preserveScroll = false) {
        let $cartBody = $('#posCartBody');
        let $cartItems = $('#posCartItems');

        let cartBodyScrollTop = preserveScroll ? ($cartBody.scrollTop() || 0) : 0;
        let cartItemsScrollTop = preserveScroll ? ($cartItems.scrollTop() || 0) : 0;
        let windowScrollTop = preserveScroll ? ($(window).scrollTop() || 0) : 0;

        function restoreCartScrollPosition() {
            if(!preserveScroll) return;

            $('#posCartBody').scrollTop(cartBodyScrollTop);
            $('#posCartItems').scrollTop(cartItemsScrollTop);
            $(window).scrollTop(windowScrollTop);
        }

        $.get("{{ route('pos.cart.get') }}", getCartParams(), function(res) {
            $('#posCartBody').html(res);

            // POS workflow note.
            // POS workflow note.
            restoreCartScrollPosition();
            requestAnimationFrame(restoreCartScrollPosition);
            setTimeout(restoreCartScrollPosition, 0);
            setTimeout(restoreCartScrollPosition, 80);

            // POS workflow note.
            if(isWaiter) {
                $('#btnSendToKitchen').html('<i class="bi bi-send"></i> Send to Front Desk');
            } else {
                $('#btnSendToKitchen').html('<i class="bi bi-send"></i> Send to Kitchen');
            }
        });
    }

    window.loadHeldQrOrderToPos = function(data) {
        currentOrder.order_id = data.order_id || null;
        currentOrder.order_type = data.order_type || 'dine_in';
        currentOrder.table_id = data.table_id || null;
        currentOrder.table_name = data.table_number || (currentOrder.order_type === 'delivery' ? 'Delivery' : (currentOrder.order_type === 'takeaway' ? 'Takeaway' : ('Table ' + (data.table_id || ''))));
        currentOrder.waiter_id = data.waiter_id || null;
        currentOrder.waiter_name = data.waiter_name || 'Unassigned';
        currentOrder.customer_id = data.customer_id || null;
        currentOrder.customer_name = data.customer_name || 'Walk-in Customer';
        currentOrder.customer_phone = data.customer_phone || '';
        currentOrder.is_walk_in = parseInt(typeof data.is_walk_in !== 'undefined' ? data.is_walk_in : (data.customer_id ? 0 : 1));
        currentOrder.order_notes = data.notes || '';
        currentOrder.is_complimentary_order = 0;
        isComplimentaryMode = false;

        if(currentOrder.order_type === 'dine_in' && currentOrder.table_id) {
            let tableCard = $('.progga-pos-table-card[data-table-id="' + currentOrder.table_id + '"]');
            if(tableCard.length) {
                tableCard.removeClass('available reserved').addClass('occupied');
                tableCard.attr('data-status', 'occupied');
                tableCard.find('.progga-badge')
                    .removeClass('progga-status-available progga-status-reserved')
                    .addClass('progga-status-occupied')
                    .text('Occupied');
            }
        }

        showStep(2);
        loadCart();
    };

    $(document).ready(function() {
        const params = new URLSearchParams(window.location.search);
        const shouldOpenHeldQr = params.get('open_held_qr') === '1';
        const storedHeldQr = sessionStorage.getItem('openHeldQrOrderInPos');

        if (shouldOpenHeldQr && storedHeldQr) {
            try {
                const heldOrderData = JSON.parse(storedHeldQr);
                sessionStorage.removeItem('openHeldQrOrderInPos');

                if (typeof window.loadHeldQrOrderToPos === 'function') {
                    setTimeout(function() {
                        window.loadHeldQrOrderToPos(heldOrderData);
                    }, 300);
                }
            } catch (e) {
                console.error('Failed to open held QR order in POS cart:', e);
                sessionStorage.removeItem('openHeldQrOrderInPos');
            }
        }
    });


    window.removeCartItem = function(cartId) {
        let payload = getCartParams();
        payload.cart_id = cartId;
        $.post("{{ route('pos.cart.remove') }}", payload, function() { loadCart(true); });
    }

    window.updateQty = function(cartId, action) {
        let payload = getCartParams();
        payload.cart_id = cartId;
        payload.action = action;
        $.post("{{ route('pos.cart.update') }}", payload, function(res) {
            if(res.status === 'success') loadCart(true);
        });
    }

    let cartQtyUpdateTimers = {};
    let cartQtyUpdateXhr = {};

    window.scheduleCartQtyUpdate = function(cartId, qty, el) {
        clearTimeout(cartQtyUpdateTimers[cartId]);

        cartQtyUpdateTimers[cartId] = setTimeout(function() {
            window.setCartQty(cartId, qty, el);
        }, 250);
    }

    window.setCartQty = function(cartId, qty, el) {
        qty = parseInt(qty) || 0;

        if(el) {
            $(el).prop('disabled', true);
        }

        let payload = getCartParams();
        payload.cart_id = cartId;
        payload.action = 'set';
        payload.qty = qty;

        if(cartQtyUpdateXhr[cartId] && cartQtyUpdateXhr[cartId].readyState !== 4) {
            cartQtyUpdateXhr[cartId].abort();
        }

        cartQtyUpdateXhr[cartId] = $.post("{{ route('pos.cart.update') }}", payload, function(res) {
            if(res.status === 'success') loadCart(true);
        }).always(function() {
            if(el) {
                $(el).prop('disabled', false);
            }
        });
    }

    window.updateItemNote = function(cartId, note) {
        let payload = getCartParams();
        payload.cart_id = cartId;
        payload.note = note;
        $.post("{{ route('pos.cart.update_note') }}", payload, function(res) {
            showToast('Info', 'Note updated', 'info');
        });
    }

    $(document).on('change', 'input[name="payment_method"]', function() {
        // Keep reference field visible for Card and Mobile Banking payment.
        if (typeof window.syncFinalPaymentFields === 'function') {
            window.syncFinalPaymentFields();
        } else if ($(this).val() === 'Card' || $(this).val() === 'Mobile Banking') {
            $('#transactionDiv').slideDown('fast').find('input[name="transaction_id"]').prop('disabled', false);
        } else {
            $('#transactionDiv').slideUp('fast').find('input[name="transaction_id"]').prop('disabled', true).val('');
        }
    });

    $(document).on('click', '#btnSendToKitchen', function(e) {
        e.preventDefault();

        if (currentOrder.order_type === 'dine_in' && !currentOrder.table_id) {
            window.proggaToast('Please select a table first!', 'danger');
            return;
        }

        // Send immediately without a confirmation popup.
        // For front-desk users the successful response redirects straight to the
        // KOT print view, which automatically opens the browser print dialog.
        var discType = $('#cart_discount_type').val() || 'fixed';
        var discVal = parseFloat($('#cart_discount_value').val()) || 0;

        var payload = {
            order_id: currentOrder.order_id || null,
            order_type: currentOrder.order_type,
            table_id: currentOrder.table_id,
            waiter_id: currentOrder.waiter_id,
            is_walk_in: currentOrder.is_walk_in,
            customer_id: currentOrder.customer_id,
            customer_name: currentOrder.customer_name,
            customer_phone: currentOrder.customer_phone,
            order_notes: currentOrder.order_notes,
            is_complimentary_order: currentOrder.is_complimentary_order || 0,
            discount_type: discType,
            discount_value: discVal,
            preparation_time: $('#cart_prep_time').val() || 20,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        var btn = $('#btnSendToKitchen');
        var originalHtml = btn.html();

        $.ajax({
            url: "{{ route('pos.place_order') }}",
            type: "POST",
            data: payload,
            beforeSend: function() {
                btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);
            },
            success: function(res) {
                if(res.status === 'success') {
                    window.location.href = res.redirect_url || "{{ route('pos.index') }}";
                } else {
                    window.Swal.fire('Error', res.message, 'error');
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr) {
                console.error("Error Response:", xhr.responseText);
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Server failed to process order.';
                window.Swal.fire('Error', message, 'error');
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });

    $(document).on('click', '#btnHoldOrder', function(e) {
        e.preventDefault();
        $('#btnSendToKitchen').trigger('click');
    });

    function posPaymentNumber(value) {
        return parseFloat(String(value || 0).replace(/[^0-9.-]/g, '')) || 0;
    }

    function posMoney(value) {
        return Math.round(posPaymentNumber(value));
    }

    window.getProductDiscountTotal = function() {
        let total = 0;

        $('#payModalItemsArea .progga-product-discount-item[data-detail-id]').each(function() {
            let row = $(this);
            let lineTotal = Math.max(0, posPaymentNumber(row.data('line-total')));
            let type = row.find('.product-discount-type').val() || 'fixed';
            let value = Math.max(0, posPaymentNumber(row.find('.product-discount-value').val()));
            let amount = 0;

            if (type === 'percentage') {
                amount = lineTotal * Math.min(value, 100) / 100;
            } else {
                amount = Math.min(value, lineTotal);
            }

            amount = Math.max(0, Math.round(amount));
            row.find('.progga-product-discount-amount').text('−৳' + posMoney(amount));
            total += amount;
        });

        return Math.max(0, Math.round(total));
    };

    window.syncPaymentRemarkRequirement = function() {
        let orderDiscountValue = Math.max(0, posPaymentNumber($('#modal_discount_value').val()));
        let hasProductDiscount = false;

        $('#payModalItemsArea .product-discount-value').each(function() {
            if (Math.max(0, posPaymentNumber($(this).val())) > 0) {
                hasProductDiscount = true;
                return false;
            }
        });

        let hasDiscount = orderDiscountValue > 0 || hasProductDiscount;
        let remarkInput = $('#paymentRemark');

        remarkInput.prop('required', hasDiscount);
        $('#paymentRemarkRequired').toggle(hasDiscount);

        if (!hasDiscount) {
            remarkInput.removeClass('is-invalid');
        }

        return hasDiscount;
    };

    window.syncFinalPaymentFields = function() {
        let method = $('input[name="payment_method"]:checked').val() || 'Cash';
        let isSplit = method === 'Split';
        let showReferenceField = method === 'Card' || method === 'Mobile Banking';

        $('#normalPaidRow').css('display', isSplit ? 'none' : 'flex');
        $('#splitPaidDisplayRow').css('display', isSplit ? 'flex' : 'none');
        $('#splitPaymentDiv').toggle(isSplit);
        $('#payTotalPaidAmount').prop('disabled', isSplit);
        $('#splitCash, #splitCard, #splitMfc').prop('disabled', !isSplit);
        let splitCardAmount = isSplit ? posPaymentNumber($('#splitCard').val()) : 0;
        let splitMfsAmount = isSplit ? posPaymentNumber($('#splitMfc').val()) : 0;
        let requireSplitCardReference = isSplit && splitCardAmount > 0;
        let requireSplitMfsReference = isSplit && splitMfsAmount > 0;

        $('#splitCardReference')
            .prop('disabled', !isSplit)
            .prop('required', requireSplitCardReference);
        $('#splitMfsReference')
            .prop('disabled', !isSplit)
            .prop('required', requireSplitMfsReference);
        $('#splitCardReferenceRequired').toggle(requireSplitCardReference);
        $('#splitMfsReferenceRequired').toggle(requireSplitMfsReference);

        if (!isSplit) {
            $('#splitCardReference, #splitMfsReference').val('').removeClass('is-invalid');
        } else {
            if (!requireSplitCardReference) $('#splitCardReference').removeClass('is-invalid');
            if (!requireSplitMfsReference) $('#splitMfsReference').removeClass('is-invalid');
        }

        let transactionInput = $('#transactionDiv').find('input[name="transaction_id"]');
        $('#transactionDiv').toggle(showReferenceField);
        transactionInput
            .prop('disabled', !showReferenceField)
            .prop('required', showReferenceField);

        if (method === 'Card') {
            $('#transactionReferenceLabel').html('Card Reference Number <span class="text-danger">*</span>');
            transactionInput.attr('placeholder', 'Card Reference Number');
        } else if (method === 'Mobile Banking') {
            $('#transactionReferenceLabel').html('MFS Reference Number <span class="text-danger">*</span>');
            transactionInput.attr('placeholder', 'MFS Reference Number');
        }

        if (!showReferenceField) {
            transactionInput.val('').removeClass('is-invalid');
        }
    };

    window.getFinalPaymentBillPaid = function() {
        let method = $('input[name="payment_method"]:checked').val() || 'Cash';

        if (method === 'Split') {
            let cash = posPaymentNumber($('#splitCash').val());
            let card = posPaymentNumber($('#splitCard').val());
            let mfc = posPaymentNumber($('#splitMfc').val());
            let splitTotal = cash + card + mfc;
            $('#payTotalPaidAmount').val(splitTotal.toFixed(2));
            $('#payPaidDisplay').text('৳' + posMoney(splitTotal));
            return splitTotal;
        }

        return posPaymentNumber($('#payTotalPaidAmount').val());
    };

    window.updateDueAmount = function() {
        let grand = posPaymentNumber($('#payTotalAmount').text());
        let paid = window.getFinalPaymentBillPaid();
        let tips = posPaymentNumber($('#payTipsAmount').val());
        let givenMoney = posPaymentNumber($('#payGivenMoney').val());

        // Due is controlled by Total Paid. Given Money is only used to show cash change/shortage.
        // Therefore a shortage remains visible as a negative Change until the user corrects it.
        let due = Math.max(0, grand - paid);
        let changeAmount = givenMoney - paid - tips;
        let isNegativeChange = changeAmount < 0;

        $('#payDueAmount').text('৳' + posMoney(due));
        $('#payChangeAmount')
            .val(posMoney(changeAmount))
            .css({
                'border-color': isNegativeChange ? '#dc3545' : '#198754',
                'color': isNegativeChange ? '#dc3545' : '#198754'
            });
    };

    window.resetFinalPaymentDefaults = function(grand) {
        grand = posMoney(grand);
        $('#payCash').prop('checked', true);
        $('#splitCash, #splitCard, #splitMfc').val(0);
        $('#payTotalPaidAmount').prop('disabled', false).val(grand);
        $('#payTipsAmount').val(0);
        $('#payGivenMoney').val(grand);
        $('#payChangeAmount').val(0);
        $('#transactionDiv').find('input[name="transaction_id"]').val('');
        $('#splitCardReference, #splitMfsReference').val('').removeClass('is-invalid');
        $('#paymentRemark').val('').removeClass('is-invalid');
        window.syncPaymentRemarkRequirement();
        window.syncFinalPaymentFields();
        window.updateDueAmount();
    };

    window.openPaymentModal = function(data) {
        let oc = document.getElementById('tableOrderOffcanvas');
        if(oc) bootstrap.Offcanvas.getInstance(oc)?.hide();

        $('#payOrderId').val(data.order_id || '');
        $('#payOrderType').val(data.order_type || 'takeaway');
        $('#payIsComplimentaryOrder').val(data.is_complimentary_order ? 1 : 0);

        let defaultLabel = data.order_type === 'delivery' ? 'Delivery' : 'Takeaway';
        $('#payTableLabel').text(data.table_no || defaultLabel);

        $('#paymentModal').data('subtotal', parseFloat(data.subtotal || 0));
        $('#paySubtotal').text('৳' + Math.round(data.subtotal || 0));

        $('#modal_discount_type').val('fixed');
        $('#modal_discount_value').val('');

        let escapeHtml = function(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        };

        let itemsHtml = '';
        if(data.items && data.items.length > 0) {
            data.items.forEach((item, index) => {
                let itemId = parseInt(item.id || 0, 10);
                let lineTotal = Math.max(0, posPaymentNumber(item.total));
                let savedType = item.product_discount_type === 'percentage' ? 'percentage' : 'fixed';
                let savedValue = Math.max(0, posPaymentNumber(item.product_discount_value));
                let safeName = escapeHtml(item.name);
                let controlHtml = '';

                if (itemId > 0) {
                    controlHtml = `
                    <div class="progga-product-discount-controls">
                        <select class="form-select product-discount-type"
                                name="product_discounts[${itemId}][type]"
                                form="payForm"
                                aria-label="Discount type for ${safeName}">
                            <option value="fixed" ${savedType === 'fixed' ? 'selected' : ''}>Fixed (৳)</option>
                            <option value="percentage" ${savedType === 'percentage' ? 'selected' : ''}>Percentage (%)</option>
                        </select>
                        <input type="number"
                               class="form-control product-discount-value"
                               name="product_discounts[${itemId}][value]"
                               form="payForm"
                               min="0"
                               step="0.01"
                               value="${savedValue > 0 ? savedValue : ''}"
                               placeholder="Discount">
                        <span class="progga-product-discount-amount">−৳0</span>
                    </div>`;
                }

                itemsHtml += `
                <div class="progga-product-discount-item" data-detail-id="${itemId || ''}" data-line-total="${lineTotal}">
                    <div class="progga-pay-summary-item" style="display:flex; justify-content:space-between; gap:10px; font-size:13px;">
                        <span class="text-muted">${safeName} ×${parseInt(item.qty || 0, 10)}</span>
                        <span style="font-weight:700; white-space:nowrap;">৳${Math.round(lineTotal)}</span>
                    </div>
                    ${controlHtml}
                </div>`;
            });
        } else {
            itemsHtml = '<div class="text-muted text-center" style="font-size:12px;">No items</div>';
        }
        $('#payModalItemsArea').html(itemsHtml);

        calculateModalTotal();
        window.resetFinalPaymentDefaults(posPaymentNumber($('#payTotalAmount').text()));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();

        // Re-sync after Bootstrap finishes showing the modal, so Card/Mobile reference field cannot be hidden by older handlers.
        setTimeout(function () {
            if (typeof window.syncFinalPaymentFields === 'function') {
                window.syncFinalPaymentFields();
            }
        }, 80);
    }

    window.calculateModalTotal = function() {
        // Keep track of the previous auto-filled payable amount. If Given Money
        // is still following that default value, discounts should reduce it too.
        let previousGrand = posPaymentNumber($('#payTotalAmount').text());
        let previousGivenMoney = posPaymentNumber($('#payGivenMoney').val());

        let subtotal = parseFloat($('#paymentModal').data('subtotal')) || 0;
        let vat_rate = parseFloat("{{ $taxSettingVatRate ?? 0 }}");

        let orderType = $('#payOrderType').val();
        let service_rate = (orderType === 'dine_in' || orderType === 'Dine-In') ? parseFloat("{{ $taxSettingServiceCharge ?? 0 }}") : 0;

        let disc_type = $('#modal_discount_type').val();
        let disc_val = parseFloat($('#modal_discount_value').val()) || 0;

        let service = Math.round((subtotal * service_rate) / 100);
        let vat = Math.round(((subtotal + service) * vat_rate) / 100);
        // Existing whole-order discount formula stays exactly on the original subtotal.
        let discount_amount = Math.round((disc_type === 'percentage') ? (subtotal * disc_val / 100) : disc_val);
        let product_discount_amount = typeof window.getProductDiscountTotal === 'function'
            ? window.getProductDiscountTotal()
            : 0;

        let grand = Math.max(0, Math.round((subtotal + vat + service) - discount_amount - product_discount_amount));

        $('#payProductDiscount').text('−৳' + product_discount_amount);
        $('#payDiscount').text('−৳' + discount_amount);
        $('#payVat').text('৳' + vat);
        $('#payService').text('৳' + service);
        $('#payTotalAmount').text('৳' + grand);
        window.syncPaymentRemarkRequirement();

        // Only show tax rows when the corresponding setting has a positive rate.
        // A database value of 0 or NULL is shared to the view as 0.
        $('#payServiceRow').css('display', service_rate > 0 ? 'flex' : 'none');
        $('#payVatRow').css('display', vat_rate > 0 ? 'flex' : 'none');

        if ($('input[name="payment_method"]:checked').val() !== 'Split') {
            $('#payTotalPaidAmount').val(grand);

            // When Given Money is still auto-filled from the previous grand total,
            // reduce it together with product-wise/order discounts so Change stays 0.
            // A manually entered cash amount remains untouched.
            let givenWasAutoFilled = previousGivenMoney === 0 || Math.abs(previousGivenMoney - previousGrand) < 0.01;
            if (givenWasAutoFilled) {
                $('#payGivenMoney').val(grand);
            }
        }

        if(typeof window.syncFinalPaymentFields === 'function') {
            window.syncFinalPaymentFields();
        }
        if(typeof window.updateDueAmount === 'function') {
            window.updateDueAmount();
        }
    }

    $(document).on('input change', '.product-discount-type, .product-discount-value', function() {
        window.calculateModalTotal();
    });

    $(document).on('keyup change', '#payTotalPaidAmount, #payTipsAmount, #payGivenMoney, .split-input', function() {
        if ($(this).hasClass('split-input')) window.syncFinalPaymentFields();
        window.updateDueAmount();
    });

    $(document).on('change', 'input[name="payment_method"]', function() {
        let grand = posMoney($('#payTotalAmount').text());
        if ($(this).val() !== 'Split') {
            $('#payTotalPaidAmount').val(grand);
        }
        window.syncFinalPaymentFields();
        window.updateDueAmount();
    });

    $(document).on('submit', '#payForm', function(e) {
        e.preventDefault();

        window.syncFinalPaymentFields();
        window.updateDueAmount();

        let hasDiscount = window.syncPaymentRemarkRequirement();
        let remarkInput = $('#paymentRemark');
        if (hasDiscount && !$.trim(remarkInput.val())) {
            remarkInput.addClass('is-invalid').trigger('focus');
            Swal.fire(
                'Remark Required',
                'Remark is required when a discount is applied.',
                'warning'
            );
            return;
        }
        remarkInput.removeClass('is-invalid');

        let paymentMethod = $('input[name="payment_method"]:checked').val() || 'Cash';
        let referenceInput = $('#transactionDiv').find('input[name="transaction_id"]');
        let requiresReference = paymentMethod === 'Card' || paymentMethod === 'Mobile Banking';

        if (requiresReference && !$.trim(referenceInput.val())) {
            referenceInput.addClass('is-invalid').trigger('focus');
            let referenceName = paymentMethod === 'Card' ? 'Card Reference Number' : 'MFS Reference Number';
            Swal.fire(
                'Reference Required',
                'Please enter the ' + referenceName + ' before completing payment.',
                'warning'
            );
            return;
        }

        referenceInput.removeClass('is-invalid');

        if (paymentMethod === 'Split') {
            let splitCardAmount = posPaymentNumber($('#splitCard').val());
            let splitMfsAmount = posPaymentNumber($('#splitMfc').val());
            let splitCardReference = $('#splitCardReference');
            let splitMfsReference = $('#splitMfsReference');

            if (splitCardAmount > 0 && !$.trim(splitCardReference.val())) {
                splitCardReference.addClass('is-invalid').trigger('focus');
                Swal.fire('Card Reference Required', 'Please enter the Card Reference Number for the Card amount.', 'warning');
                return;
            }

            splitCardReference.removeClass('is-invalid');

            if (splitMfsAmount > 0 && !$.trim(splitMfsReference.val())) {
                splitMfsReference.addClass('is-invalid').trigger('focus');
                Swal.fire('MFS Reference Required', 'Please enter the MFS Reference Number for the MFS amount.', 'warning');
                return;
            }

            splitMfsReference.removeClass('is-invalid');
        }

        let totalPaid = window.getFinalPaymentBillPaid();
        let tipsAmount = posPaymentNumber($('#payTipsAmount').val());
        let givenMoney = posPaymentNumber($('#payGivenMoney').val());
        let requiredGivenMoney = totalPaid + tipsAmount;
        let saveAsDueOrder = $(this).data('saveAsDueOrder') === true;
        $(this).removeData('saveAsDueOrder');

        // Keep the negative Change visible while the operator is entering a short amount.
        // On submit, offer either correcting the amount or intentionally saving the shortage as Due.
        if (givenMoney + 0.001 < requiredGivenMoney && !saveAsDueOrder) {
            let paymentForm = $(this);
            $('#payGivenMoney').addClass('is-invalid');

            Swal.fire({
                icon: 'warning',
                title: 'Insufficient Given Money',
                text: 'You entered less money than the payment amount. Correct the amount or save the shortage as a Due Order.',
                showDenyButton: true,
                showCancelButton: false,
                confirmButtonText: 'Correct Amount',
                denyButtonText: 'Save as Due Order',
                reverseButtons: true
            }).then((result) => {
                if (result.isDenied) {
                    $('#payGivenMoney').removeClass('is-invalid');
                    paymentForm.data('saveAsDueOrder', true);
                    paymentForm.trigger('submit');
                    return;
                }

                if (result.isConfirmed) {
                    $('#payGivenMoney').trigger('focus').select();
                }
            });

            return;
        }

        $('#payGivenMoney').removeClass('is-invalid');
        $('#payTipsAmount').removeClass('is-invalid');

        let btn = $(this).find('button[type="submit"]');
        let originalHtml = btn.html();
        btn.html('<i class="spinner-border spinner-border-sm"></i> Processing...').prop('disabled', true);

        let formData = $(this).serialize();
        if (saveAsDueOrder) {
            formData += '&save_as_due_order=1';
        }

        $.ajax({
            url: "{{ route('pos.complete_payment') }}",
            type: "POST",
            data: formData + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Paid!', timer: 1500, showConfirmButton: false }).then(() => {
                        window.location.href = res.redirect_url;
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr) {
                let message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Server error. Please try again.';
                Swal.fire('Error', message, 'error');
                btn.html(originalHtml).prop('disabled', false);
            }
        });
    });

    window.openOrderItemDeleteModal = function(orderId, orderDetailId, itemName, maxQty) {
        $('#deleteOrderId').val(orderId);
        $('#deleteOrderDetailId').val(orderDetailId);
        $('#deleteOrderItemName').text(itemName);
        $('#deleteOrderItemMaxQty').text(maxQty);
        $('#deleteOrderItemQty').attr('max', maxQty).val(1);
        $('#deleteOrderItemReason').val('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('orderItemDeleteModal')).show();
    }

    $(document).on('click', '#btnDeleteFullQty', function() {
        $('#deleteOrderItemQty').val($('#deleteOrderItemQty').attr('max') || 1);
    });

    $(document).on('submit', '#orderItemDeleteForm', function(e) {
        e.preventDefault();

        let qty = parseInt($('#deleteOrderItemQty').val()) || 0;
        let maxQty = parseInt($('#deleteOrderItemQty').attr('max')) || 0;

        if(qty < 1 || qty > maxQty) {
            Swal.fire('Invalid Quantity', 'Please enter a quantity between 1 and ' + maxQty + '.', 'warning');
            return;
        }

        let btn = $('#btnConfirmOrderItemDelete');
        let originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting...');

        $.ajax({
            url: "{{ route('pos.order_item.remove') }}",
            type: "POST",
            data: $(this).serialize() + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function(res) {
                btn.prop('disabled', false).html(originalHtml);

                if(res.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('orderItemDeleteModal'))?.hide();
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: res.message, timer: 1200, showConfirmButton: false });

                    let tableId = currentOrder.table_id || $('#btnContinueOrdering').data('table-id');
                    let orderId = currentOrder.order_id || $('#btnContinueOrdering').data('order-id');
                    if(tableId) {
                        $.get("{{ route('pos.get_table_order', ':id') }}".replace(':id', tableId), function(html) {
                            if(typeof html === 'object' && html.status === 'error') {
                                location.reload();
                            } else {
                                $('#ocBody').html(html);
                            }
                        });
                    } else if(orderId) {
                        $.get("{{ route('pos.get_pos_order', ':id') }}".replace(':id', orderId), function(html) {
                            if(typeof html === 'object' && html.status === 'error') {
                                location.reload();
                            } else {
                                $('#ocBody').html(html);
                            }
                        });
                    } else {
                        location.reload();
                    }
                } else {
                    Swal.fire('Error', res.message || 'Could not delete item.', 'error');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                let msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error. Please try again.';
                Swal.fire('Error', msg, 'error');
            }
        });
    });

    $(document).on('click', '#btnToggleTableSwap', function() {
        $('#tableSwapForm').slideToggle(140);
    });

    $(document).on('submit', '#tableSwapForm', function(e) {
        e.preventDefault();

        const $form = $(this);
        const orderId = $form.find('input[name="order_id"]').val();
        const currentTableId = $form.find('input[name="current_table_id"]').val();
        const newTableId = $('#tableSwapNewTable').val();
        const newTableNumber = $('#tableSwapNewTable option:selected').data('table-number') || $('#tableSwapNewTable option:selected').text();

        if (!newTableId) {
            Swal.fire('Select Table', 'Please select an available table first.', 'warning');
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'Swap Table?',
            text: 'Move this order to ' + newTableNumber + '?',
            showCancelButton: true,
            confirmButtonText: 'Yes, Swap',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const $btn = $('#btnConfirmTableSwap');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Swapping...');

            $.ajax({
                url: "{{ route('pos.table_swap') }}",
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    order_id: orderId,
                    new_table_id: newTableId
                },
                success: function(res) {
                    $btn.prop('disabled', false).html(originalHtml);

                    if (res.status !== 'success') {
                        Swal.fire('Error', res.message || 'Table swap failed.', 'error');
                        return;
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Table Swapped',
                        text: res.message || 'Table swapped successfully.',
                        timer: 900,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(function() {
                        window.location.reload();
                    });

                    setTimeout(function() {
                        window.location.reload();
                    }, 950);
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(originalHtml);
                    let msg = 'Server error. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', msg, 'error');
                }
            });
        });
    });

    $(document).on('click', '#btnAddComplimentary', function() {
        const tId = $(this).data('table-id');
        const orderId = $(this).data('order-id');
        const waiterId = $(this).data('waiter-id');
        const waiterName = $(this).data('waiter-name');
        const customerId = $(this).data('customer-id');
        const customerName = $(this).data('customer-name');
        const orderType = $(this).data('order-type') || 'dine_in';
        const orderLabel = $(this).data('order-label') || $('#ocTableNum').text();

        currentOrder.table_id = tId || null;
        currentOrder.table_name = orderLabel;
        currentOrder.order_id = orderId;
        currentOrder.order_type = orderType;
        currentOrder.waiter_id = waiterId ? waiterId : null;
        currentOrder.waiter_name = waiterName ? waiterName : '';

        if(customerId) {
            currentOrder.is_walk_in = 0;
            currentOrder.customer_id = customerId;
            currentOrder.customer_name = customerName;
        } else {
            currentOrder.is_walk_in = 1;
            currentOrder.customer_id = null;
            currentOrder.customer_name = '';
        }

        currentOrder.is_complimentary_order = 0;
        isComplimentaryMode = true;

        var ocElement = document.getElementById('tableOrderOffcanvas');
        if (ocElement) {
            var ocInstance = bootstrap.Offcanvas.getInstance(ocElement);
            if (ocInstance) ocInstance.hide();
        }

        showStep(2);
        loadCart();
        if(window.Swal) {
            Swal.fire({
                icon: 'info',
                title: 'Complimentary Mode On',
                text: 'Now select food items. They will be added with 0 value.',
                timer: 1600,
                showConfirmButton: false
            });
        }
    });

    $(document).on('click', '.js-make-order-item-complimentary', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const orderId = $btn.data('order-id');
        const orderDetailId = $btn.data('order-detail-id');
        const tableId = $btn.data('table-id');
        const orderType = $btn.data('order-type') || 'dine_in';
        const productName = $btn.data('product-name') || 'this food';

        Swal.fire({
            title: 'Make Complimentary?',
            text: productName + ' will become complimentary and its food/addon value will be changed to 0.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, make complimentary',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#198754'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.post("{{ route('pos.order_item.complimentary') }}", {
                order_id: orderId,
                order_detail_id: orderDetailId
            }).done(function(res) {
                if (res.status !== 'success') {
                    $btn.prop('disabled', false).html(originalHtml);
                    Swal.fire('Error', res.message || 'Complimentary conversion failed.', 'error');
                    return;
                }

                reloadActiveOrderOffcanvas(orderId, tableId, orderType)
                    .done(function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Complimentary',
                            text: res.message || 'Food converted to complimentary successfully.',
                            timer: 1100,
                            showConfirmButton: false
                        });
                    })
                    .fail(function() {
                        window.location.reload();
                    });
            }).fail(function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                Swal.fire('Error', xhr.responseJSON?.message || 'Complimentary conversion failed.', 'error');
            });
        });
    });

    $(document).on('click', '#btnContinueOrdering', function() {
        const tId = $(this).data('table-id');
        const orderId = $(this).data('order-id');
        const waiterId = $(this).data('waiter-id');
        const waiterName = $(this).data('waiter-name');
        const customerId = $(this).data('customer-id');
        const customerName = $(this).data('customer-name');

        const orderType = $(this).data('order-type') || 'dine_in';
        const orderLabel = $(this).data('order-label') || $('#ocTableNum').text();

        currentOrder.table_id = tId || null;
        currentOrder.table_name = orderLabel;
        currentOrder.order_id = orderId;
        currentOrder.order_type = orderType;
        currentOrder.is_complimentary_order = 0;
        isComplimentaryMode = false;

        currentOrder.waiter_id = waiterId ? waiterId : null;
        currentOrder.waiter_name = waiterName ? waiterName : '';

        if(customerId) {
            currentOrder.is_walk_in = 0;
            currentOrder.customer_id = customerId;
            currentOrder.customer_name = customerName;
        } else {
            currentOrder.is_walk_in = 1;
            currentOrder.customer_id = null;
            currentOrder.customer_name = '';
        }

        var ocElement = document.getElementById('tableOrderOffcanvas');
        if (ocElement) {
            var ocInstance = bootstrap.Offcanvas.getInstance(ocElement);
            if (ocInstance) ocInstance.hide();
        }

        showStep(2);
        loadCart();
    });

    $(document).on('click', '.progga-pos-filter-btn', function() {
        $('.progga-pos-filter-btn').removeClass('active');
        $(this).addClass('active');

        let filterValue = $(this).data('table-filter');

        if(filterValue === 'all') {
            $('.progga-pos-table-card').fadeIn('fast');
        } else {
            $('.progga-pos-table-card').hide();
            $('.progga-pos-table-card[data-status="' + filterValue + '"]').fadeIn('fast');
        }
    });

    $(document).on('click', '#posCartFab', function() {
        $('.progga-pos-cart').addClass('show');
        $('#posMobileBackdrop').fadeIn('fast');
        $('body').addClass('progga-pos-overflow-lock');
    });

    $(document).on('click', '#posMobileCartClose, #posMobileBackdrop', function() {
        $('.progga-pos-cart').removeClass('show');
        $('#posMobileBackdrop').fadeOut('fast');
        $('body').removeClass('progga-pos-overflow-lock');
    });

    // POS workflow note.
    $(document).on('click', '.btnEditSession', function(e) {
        e.preventDefault();

        let sessionId = $(this).attr('data-id');
        let sessionStatus = $(this).attr('data-status').trim(); // POS workflow note.

        $('#sessionModalTitle').html('<i class="bi bi-pencil-square me-2"></i>Edit Session #' + sessionId);
        $('#inlineEditSessionId').val(sessionId);
        $('#inlineEditStartTime').val($(this).attr('data-start'));
        $('#inlineEditEndTime').val($(this).attr('data-end'));

        // POS workflow note.
        $('#inlineEditStatus').val(sessionStatus).trigger('change');

        $('#sessionListView').hide();
        $('#sessionEditView').fadeIn('fast');
    });

    // POS workflow note.
    $(document).on('click', '#btnCancelEdit', function(e) {
        e.preventDefault();
        $('#sessionModalTitle').html('<i class="bi bi-table me-2"></i>Work Period Sessions');
        $('#sessionEditView').hide();
        $('#sessionListView').fadeIn('fast');
    });

    // POS workflow note.
    $('#inlineEditSessionForm').on('submit', function(e) {
        e.preventDefault();
        let formData = $(this).serialize();

        let btn = $(this).find('button[type="submit"]');
        let originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Saving...');

        $.post("{{ route('pos.session.update') }}", formData, function(res) {
            if(res.status === 'success') {
                Swal.fire('Updated!', res.message, 'success').then(() => {
                    location.reload();
                });
            }
        }).fail(function() {
            Swal.fire('Error', 'Something went wrong!', 'error');
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // POS workflow note.
    $('#sessionHistoryModal').on('hidden.bs.modal', function () {
        $('#sessionModalTitle').html('<i class="bi bi-table me-2"></i>Work Period Sessions');
        $('#sessionEditView').hide();
        $('#sessionListView').show();
    });


})();
</script>
@else
<script>
(function showPosClosedPopup() {
    const popupMessage = @json($posClosedMessage ?? 'The restaurant is currently closed. Orders will be accepted from 12:01 PM.');
    const orderWindow = @json(
        'Order hours: ' .
        \Carbon\Carbon::createFromFormat('H:i', $posOpeningTime ?? '12:01')->format('h:i A') .
        ' – ' .
        \Carbon\Carbon::createFromFormat('H:i', $posClosingTime ?? '06:00')->format('h:i A')
    );
    const dashboardUrl = @json(route('home'));

    function openClosedNotification() {
        if (!window.Swal) {
            setTimeout(openClosedNotification, 50);
            return;
        }

        window.Swal.fire({
            icon: 'warning',
            title: 'Restaurant Is Currently Closed',
            text: popupMessage,
            footer: orderWindow,
            confirmButtonText: 'Go to Dashboard',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCloseButton: false
        }).then(function () {
            window.location.href = dashboardUrl;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openClosedNotification);
    } else {
        openClosedNotification();
    }
})();
</script>
@endif
@endsection
