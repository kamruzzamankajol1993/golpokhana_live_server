<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title')</title>
    <meta name="title" content="@yield('title')">
  <meta name="description" content="TableTrack Restaurant Management System - Complete solution for your restaurant operations.">
  <meta name="keywords" content="restaurant management, pos, kitchen board, progga rms, dashboard">
  <meta name="author" content="TableTrack RMS">

  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ url()->full() }}">
  <meta property="og:title" content="@yield('title')">
  <meta property="og:description" content="Manage your restaurant efficiently with TableTrack RMS.">
  <meta property="og:image" content="{{ asset('public/'.$restaurantSettingLogo) }}">
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="{{ url()->full() }}">
  <meta property="twitter:title" content="@yield('title')">
  <meta property="twitter:description" content="Manage your restaurant efficiently with TableTrack RMS.">
  <meta property="twitter:image" content="{{ asset('public/'.$restaurantSettingLogo) }}">
  <link rel="icon" type="image/x-icon" href="{{ asset('public/'.$restaurantSettingIconName) }}">
  <link rel="apple-touch-icon" href="{{ asset('public/'.$restaurantSettingIconName) }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="{{ asset('/') }}public/admin/assets/css/progga-style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<style>
  /* New Web Order modal height/dropdown fix */
  #newQrOrderModal .modal-dialog {
    max-width: 980px;
    margin-top: 12px;
    margin-bottom: 12px;
  }
  #newQrOrderModal .modal-content {
    min-height: 620px;
    max-height: calc(100vh - 24px);
    overflow: visible !important;
  }
  #newQrOrderModal .modal-body {
    min-height: 460px;
    overflow: visible !important;
  }
  #newQrOrderModal .choices,
  #newQrOrderModal .choices__inner {
    min-height: 42px;
  }
  #newQrOrderModal .choices__list--dropdown,
  #newQrOrderModal .choices__list[aria-expanded] {
    z-index: 100002 !important;
  }
  @media (max-width: 767.98px) {
    #newQrOrderModal .modal-dialog {
      max-width: calc(100% - 16px);
      margin: 8px auto;
    }
    #newQrOrderModal .modal-content {
      min-height: auto;
      max-height: calc(100vh - 16px);
    }
    #newQrOrderModal .modal-body {
      min-height: auto;
      max-height: calc(100vh - 150px);
      overflow-y: auto !important;
    }
  }
</style>
    @yield('css')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<body class="progga-pos-overflow-lock">

    @yield('body')

<div class="modal fade progga-modal" id="newQrOrderModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="border: 2px solid var(--progga-primary); border-radius: 12px; z-index: 99999;">
      <div class="modal-header" style="background: rgba(33, 53, 42, 0.05); padding: 15px 20px;">
        <h5 class="modal-title text-success" style="font-weight: 800;">
            <i class="bi bi-bell-fill fs-4 me-2"></i>New Web Order Alert!
        </h5>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('notificationSound').pause();">Mute Sound</button>
      </div>

      <div class="modal-body" style="padding: 20px; background: #f8f9fa;">
        <div class="row g-4">
            <div class="col-md-6 border-end">
                <h5 style="font-weight: 800; color: #333; margin-bottom: 5px;">Table <span id="notifyOrderTable" class="text-primary"></span></h5>
                <p class="text-muted" style="font-size: 13px; margin-bottom: 15px;">Order No: <strong id="notifyOrderNumber"></strong></p>

                <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-borderless mb-0" style="font-size: 13px;">
                        <thead style="background: #eee; position: sticky; top: 0;">
                            <tr>
                                <th>Item Name</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="qrOrderItemsBody">
                            </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between mt-3 px-2">
                    <strong style="font-size: 14px; color: #555;">Grand Total:</strong>
                    <strong id="notifyOrderAmount" class="text-danger" style="font-size: 18px;"></strong>
                </div>
                <div id="notifyOrderNotes" class="mt-2 text-warning" style="font-size: 12px; font-weight: bold; display: none;"></div>
            </div>

            <div class="col-md-6">
                <form id="qrAcceptForm">
                    <div class="pos-modal-section mb-3">
                        <label class="pos-modal-label" style="font-weight: 700; font-size: 13px;">Assign Waiter <span class="text-danger">*</span></label>
                        <select id="qrWaiterSelect" class="progga-choices" style="border: 1.5px solid #ccc; border-radius: 6px; font-size: 14px;" required>
                            <option value="">— Select Waiter —</option>
                            @foreach($waiters as $waiter)
                                <option value="{{ $waiter->id }}">{{ $waiter->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pos-modal-section mb-2">
                        <label class="pos-modal-label" style="font-weight: 700; font-size: 13px;">Customer Type</label>
                        <select id="qrCustomerType" class="progga-choices" style="border: 1.5px solid #ccc; border-radius: 6px; font-size: 14px; background: #fff;">
                            <option value="walk_in">Walk-in Customer (No Details Needed)</option>
                            <option value="existing">Search Existing Customer</option>
                            <option value="new">Add New Customer</option>
                        </select>
                    </div>

                    <div id="qrExistingCustomerDiv" style="display: none; margin-bottom: 10px;">
                        <select id="qrExistingCustomerSelect" class="progga-choices" style="width: 100%;">
                            <option value="">— Select Customer —</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="qrNewCustomerDiv" style="display: none;">
                        <div class="row g-2">
                            <div class="col-12">
                                <input type="text" id="qrNewCustomerName" class="form-control" placeholder="Customer Name" style="font-size: 13px;">
                            </div>
                            <div class="col-12">
                                <input type="text" id="qrNewCustomerPhone" class="form-control" placeholder="Phone Number" style="font-size: 13px;">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
      </div>

      <div class="modal-footer justify-content-end border-0" style="background: #fff; padding: 15px 20px; gap: 10px;">
        <button type="button" class="progga-btn progga-btn-secondary px-4" id="btnHoldQrOrder" data-id="">
          <i class="bi bi-pause-circle-fill"></i> Hold for Waiter
        </button>
        <button type="button" class="progga-btn progga-btn-primary px-4" id="btnAcceptQrOrder" data-id="">
          <i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade progga-modal" id="waiterCallModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border: 2px solid #ff9800; border-radius: 12px; z-index: 99999;">
      <div class="modal-header" style="background: rgba(255, 152, 0, 0.1);">
        <h5 class="modal-title text-warning" style="font-weight: 800; color: #e65100 !important;">
            <i class="bi bi-person-raised-hand me-2"></i>Waiter Call
        </h5>
      </div>
      <div class="modal-body text-center py-4">
        <h2 style="font-weight: 800; color: #333;">Table <span id="notifyWaiterTable"></span></h2>
        <p class="text-muted mt-2 mb-0">Customer needs assistance.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pb-4">
        <button type="button" class="btn btn-warning text-dark fw-bold px-4" id="btnResolveWaiter" data-id="" style="border-radius: 8px;">
          <i class="bi bi-check2-all"></i> Mark as Resolved
        </button>
      </div>
    </div>
  </div>
</div>

<audio id="notificationSound" preload="auto" loop>
    <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
</audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="{{ asset('/') }}public/admin/assets/js/progga-app.js"></script>
  <script src="{{ asset('/') }}public/admin/assets/js/progga-pos.js"></script>
<script>

     document.addEventListener("DOMContentLoaded", function () {

    // POS workflow note.
    const choicesElements = document.querySelectorAll('.progga-choices');
    choicesElements.forEach(function(element) {
        new Choices(element, {
            searchEnabled: true,
            itemSelectText: '',
            removeItemButton: true,
            shouldSort: false
        });
        // POS workflow note.
        element.setAttribute('data-no-select2', 'true');
    });
});

$(document).ready(function() {
    let isPolling = true;
let isMasterWaiter = @json(auth()->check() && auth()->user()->hasRole('waiter'));
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    $('#qrCustomerType').on('change', function() {
        let val = $(this).val();
        if(val === 'existing') {
            $('#qrExistingCustomerDiv').show();
            $('#qrNewCustomerDiv').hide();
        } else if(val === 'new') {
            $('#qrExistingCustomerDiv').hide();
            $('#qrNewCustomerDiv').show();
        } else {
            $('#qrExistingCustomerDiv').hide();
            $('#qrNewCustomerDiv').hide();
        }
    });

    function playSound() {
        let audio = document.getElementById('notificationSound');
        audio.currentTime = 0;
        let playPromise = audio.play();
        if (playPromise !== undefined) {
            playPromise.catch(error => { console.log("Auto-play prevented."); });
        }
    }

  

    function checkLiveNotifications() {
        if ($('.modal.show').length > 0 || !isPolling) return;

        $.ajax({
            url: "{{ route('notifications.check') }}",
            type: "GET",
            success: function(res) {
                if (res.status === 'success') {

                    // POS workflow note.
                    if (res.order && !isMasterWaiter) {
                        isPolling = false;
                        playSound();

                        $('#notifyOrderTable').text(res.order.table ? res.order.table.table_number : 'Takeaway');
                        $('#notifyOrderNumber').text(res.order.order_number);
                        $('#notifyOrderAmount').text('৳' + Math.round(res.order.grand_total));
                        $('#btnAcceptQrOrder').data('id', res.order.id);
                        $('#btnHoldQrOrder').data('id', res.order.id);

                        if(res.order.notes) {
                            $('#notifyOrderNotes').html('<i class="bi bi-info-circle-fill"></i> Note: ' + res.order.notes).show();
                        } else {
                            $('#notifyOrderNotes').hide();
                        }

                        let itemsHtml = '';
                        if(res.order.order_details && res.order.order_details.length > 0) {
                            res.order.order_details.forEach(item => {
                                itemsHtml += `
                                <tr>
                                    <td class="text-wrap" style="max-width: 150px; font-weight: 600;">${item.product_name}</td>
                                    <td class="text-center">×${item.quantity}</td>
                                    <td class="text-end text-primary" style="font-weight: 700;">৳${Math.round(item.subtotal)}</td>
                                </tr>`;
                            });
                        }
                        $('#qrOrderItemsBody').html(itemsHtml);

                        $('#qrCustomerType').val('walk_in').trigger('change');
                        $('#qrWaiterSelect').val('');
                        $('#btnHoldQrOrder').prop('disabled', false).html('<i class="bi bi-pause-circle-fill"></i> Hold for Waiter');
                        $('#btnAcceptQrOrder').prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');
                        $('#qrNewCustomerName').val('');
                        $('#qrNewCustomerPhone').val('');

                        $('#newQrOrderModal').modal('show');
                        return;
                    }

                    // POS workflow note.
                    if (res.waiter_call) {
                        isPolling = false;
                        playSound();
                        $('#notifyWaiterTable').text(res.waiter_call.table_number);
                        $('#btnResolveWaiter').data('id', res.waiter_call.id);
                        $('#waiterCallModal').modal('show');
                    }
                }
            }
        });
    }

    setInterval(checkLiveNotifications, 3000);

    $('#btnAcceptQrOrder').on('click', function() {
        let orderId = $(this).data('id');
        let waiterId = $('#qrWaiterSelect').val();
        let customerType = $('#qrCustomerType').val();

        let customerId = $('#qrExistingCustomerSelect').val();
        let customerName = $('#qrNewCustomerName').val();
        let customerPhone = $('#qrNewCustomerPhone').val();

        if(!waiterId) {
            Swal.fire('Wait!', 'Please assign a waiter to this order.', 'warning');
            return;
        }

        if(customerType === 'existing' && !customerId) {
            Swal.fire('Wait!', 'Please select an existing customer from the list.', 'warning');
            return;
        }

        if(customerType === 'new' && !customerName) {
            Swal.fire('Wait!', 'Please enter the new customer name.', 'warning');
            return;
        }

        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

        $.post("{{ route('notifications.accept_order') }}", {
            id: orderId,
            waiter_id: waiterId,
            customer_type: customerType,
            customer_id: customerId,
            customer_name: customerName,
            customer_phone: customerPhone
        }, function(res) {
            if(res.status === 'success') {
                document.getElementById('notificationSound').pause();
                $('#newQrOrderModal').modal('hide');
                btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');

                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success', title: 'Order Sent to Kitchen!', showConfirmButton: false, timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', res.message, 'error');
                btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');
            }
        });
    });

    $('#btnHoldQrOrder').on('click', function() {
        let orderId = $(this).data('id');
        let waiterId = $('#qrWaiterSelect').val();
        let customerType = $('#qrCustomerType').val();

        let customerId = $('#qrExistingCustomerSelect').val();
        let customerName = $('#qrNewCustomerName').val();
        let customerPhone = $('#qrNewCustomerPhone').val();

        if(!orderId) {
            Swal.fire('Error', 'Order ID missing.', 'error');
            return;
        }

        if(customerType === 'existing' && !customerId) {
            Swal.fire('Wait!', 'Please select an existing customer from the list.', 'warning');
            return;
        }

        if(customerType === 'new' && !customerName) {
            Swal.fire('Wait!', 'Please enter the new customer name.', 'warning');
            return;
        }

        let btn = $(this);
        let acceptBtn = $('#btnAcceptQrOrder');
        let originalHtml = btn.html();

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Holding...');
        acceptBtn.prop('disabled', true);

        $.post("{{ route('pos.hold_web_order') }}", {
            id: orderId,
            waiter_id: waiterId,
            customer_type: customerType,
            customer_id: customerId,
            customer_name: customerName,
            customer_phone: customerPhone
        }, function(res) {
            if(res.status === 'success') {
                document.getElementById('notificationSound').pause();
                $('#newQrOrderModal').modal('hide');

                btn.prop('disabled', false).html(originalHtml);
                acceptBtn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Order moved to POS cart!',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    if (typeof window.loadHeldQrOrderToPos === 'function') {
                        window.loadHeldQrOrderToPos(res);
                    } else {
                        window.location.href = "{{ route('pos.index') }}";
                    }
                });
            } else {
                Swal.fire('Error', res.message || 'Unable to hold this order.', 'error');
                btn.prop('disabled', false).html(originalHtml);
                acceptBtn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');
            }
        }).fail(function(xhr) {
            console.error('Hold QR order failed:', xhr.responseText);
            Swal.fire('Error', 'Server failed to hold this order.', 'error');
            btn.prop('disabled', false).html(originalHtml);
            acceptBtn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');
        });
    });

    $('#btnResolveWaiter').on('click', function() {
        let callId = $(this).data('id');
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>...');

        $.post("{{ route('notifications.resolve_waiter') }}", { id: callId }, function(res) {
            if(res.status === 'success') {
                document.getElementById('notificationSound').pause();
                $('#waiterCallModal').modal('hide');
                btn.prop('disabled', false).html('<i class="bi bi-check2-all"></i> Mark as Resolved');
                setTimeout(() => { isPolling = true; }, 1000);
            }
        });
    });

    $('#newQrOrderModal, #waiterCallModal').on('hidden.bs.modal', function () {
        document.getElementById('notificationSound').pause();
        isPolling = true;
    });
});
</script>
    @yield('script')

</body>
</html>
