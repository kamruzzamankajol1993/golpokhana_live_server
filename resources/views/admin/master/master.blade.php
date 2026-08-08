<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title')</title>
  <meta name="title" content="@yield('title')">
  <meta name="description" content="TableTrack Restaurant Management System - Complete solution for your restaurant operations.">
  <meta name="keywords" content="restaurant management, pos, kitchen board, TableTrack rms, dashboard">
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

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

  <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">

  <link rel="stylesheet" href="{{ asset('/') }}public/admin/assets/css/progga-style.css">
 <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <style>
    /* New Web Order Alert modal height/dropdown fix */
    #newQrOrderModal {
      z-index: 99990;
    }

    #newQrOrderModal .qr-order-modal-dialog {
      max-width: 980px;
      width: calc(100% - 24px);
    }

    #newQrOrderModal .modal-content {
      max-height: 92vh;
      overflow: visible !important;
    }

    #newQrOrderModal .qr-order-modal-body {
      max-height: calc(92vh - 135px);
      overflow-y: auto;
      overflow-x: visible;
    }

    #newQrOrderModal .choices {
      margin-bottom: 0;
      z-index: 100000;
    }

    #newQrOrderModal .choices.is-open {
      z-index: 100005;
    }

    #newQrOrderModal .choices__inner {
      min-height: 42px;
      border: 1.5px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
      background: #fff;
    }

    #newQrOrderModal .choices__list--dropdown,
    #newQrOrderModal .choices__list[aria-expanded] {
      z-index: 100010 !important;
      max-height: 260px;
      overflow-y: auto;
    }

    #newQrOrderModal .qr-order-items-box {
      max-height: 310px;
      overflow-y: auto;
    }

    @media (max-width: 767px) {
      #newQrOrderModal .qr-order-modal-body {
        max-height: calc(92vh - 150px);
      }

      #newQrOrderModal .border-end {
        border-right: 0 !important;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 16px;
      }

      #newQrOrderModal .modal-footer {
        justify-content: stretch !important;
      }

      #newQrOrderModal .modal-footer .progga-btn {
        flex: 1;
      }
    }
  </style>
  @yield('css')

<style>
    #newQrOrderModal .modal-dialog {
        max-width: 980px;
    }
    #newQrOrderModal .modal-content {
        max-height: 92vh;
        overflow: visible !important;
    }
    #newQrOrderModal .modal-body {
        max-height: calc(92vh - 140px);
        overflow-y: auto;
        overflow-x: visible !important;
    }
    #newQrOrderModal .choices,
    #newQrOrderModal .choices__inner,
    #newQrOrderModal .choices__list--dropdown {
        z-index: 100000 !important;
    }
    #newQrOrderModal .choices__list--dropdown {
        max-height: 260px;
        overflow-y: auto;
    }
</style>
</head>
<body data-page-title="@yield('title')" data-page-subtitle="Overview">

  <div class="progga-layout">

    @include('admin.include.sidebar')
    <div class="progga-sidebar-overlay" id="sidebarOverlay"></div>

    <div class="progga-main">

      @include('admin.include.header')

      @yield('body')

      @include('admin.include.footer')
    </div>
  </div>

  <div class="progga-toast-container"></div>

  <div class="modal fade progga-modal" id="newQrOrderModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered qr-order-modal-dialog">
    <div class="modal-content" style="border: 2px solid var(--progga-primary); border-radius: 12px; z-index: 99999;">
      <div class="modal-header" style="background: rgba(33, 53, 42, 0.05); padding: 15px 20px;">
        <h5 class="modal-title text-success" style="font-weight: 800;">
            <i class="bi bi-bell-fill fs-4 me-2"></i>New Web Order Alert!
        </h5>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('notificationSound').pause();">Mute Sound</button>
      </div>

      <div class="modal-body qr-order-modal-body" style="padding: 20px; background: #f8f9fa;">
        <div class="row g-4">
            <div class="col-md-6 border-end">
    <h5 style="font-weight: 800; color: #333;">Table <span id="notifyOrderTable" class="text-primary"></span></h5>
    <p class="text-muted" style="font-size: 12px;">Order No: <strong id="notifyOrderNumber"></strong></p>

    <div class="qr-order-items-box" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow-y: auto;">
        <table class="table table-sm table-borderless mb-0" style="font-size: 12px;">
            <tbody id="qrOrderItemsBody"></tbody>
        </table>
    </div>

    <div class="mt-3 px-2" style="font-size: 13px; color: #555;">
        <div class="d-flex justify-content-between"><span>Subtotal:</span><span id="notifyOrderSubtotal"></span></div>
        <div class="d-flex justify-content-between"><span>VAT:</span><span id="notifyOrderVat"></span></div>
        <div class="d-flex justify-content-between"><span>Service Charge:</span><span id="notifyOrderService"></span></div>
        <div class="d-flex justify-content-between mt-1 pt-1 border-top"><strong style="color:var(--progga-primary);">Total:</strong><strong id="notifyOrderAmount" class="text-danger" style="font-size: 16px;"></strong></div>
    </div>
    <div id="notifyOrderNotes" class="mt-2 text-warning" style="font-size: 12px; font-weight: bold; display: none;"></div>
</div>

            <div class="col-md-6">
                <form id="qrAcceptForm">
                    <div class="mb-3">
        <label style="font-weight: 700; font-size: 12px;">Preparation Time (Minutes) <span class="text-danger">*</span></label>
        <input type="number" id="qrPrepTime" class="form-control" value="20" min="1" style="border: 1.5px solid #ccc;">
    </div>
    <div class="mb-3">
        <label style="font-weight: 700; font-size: 12px;">Assign Waiter <span class="text-danger">*</span></label>
        <select id="qrWaiterSelect" class="progga-choices">
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

      <div class="modal-footer justify-content-between border-0 flex-wrap gap-2" style="background: #fff; padding: 15px 20px;">
        <button type="button" class="progga-btn progga-btn-outline px-4" id="btnHoldQrOrder" data-id="">
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

  <script src="{{ asset('/') }}public/admin/assets/js/progga-app.js"></script>
  <script src="{{ asset('/') }}public/admin/assets/js/progga-charts.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function () {

    // ১. Choices.js (যেখানে বিশেষভাবে Choices.js লাগবে সেখানে .progga-choices ক্লাস ব্যবহার করবেন)
    window.proggaChoicesInstances = window.proggaChoicesInstances || {};
    const choicesElements = document.querySelectorAll('.progga-choices');
    choicesElements.forEach(function(element) {
        if (window.proggaChoicesInstances[element.id]) return;

        window.proggaChoicesInstances[element.id] = new Choices(element, {
            searchEnabled: true,
            itemSelectText: '',
            removeItemButton: false,
            shouldSort: false,
            position: 'bottom',
            shouldSortItems: false
        });

        // Select2 যাতে এই এলিমেন্টে কাজ না করে, তা নিশ্চিত করতে
        element.setAttribute('data-no-select2', 'true');
    });

    // ২. Flatpickr (No-jQuery Datepicker)
    flatpickr('.progga-datepicker', {
        dateFormat: "Y-m-d",
        allowInput: true,
    });

    // ৩. Summernote Lite (Rich Text Editor)
    if (typeof jQuery !== 'undefined') {
        $('.progga-editor').summernote({
            height: 250,
            placeholder: 'Type your content here...',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    }

    // ৪. SweetAlert2 (Delete Confirmation)
    document.addEventListener('click', function (e) {
        const deleteBtn = e.target.closest('.progga-delete-btn');
        if (deleteBtn) {
            e.preventDefault();
            const form = deleteBtn.closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this data!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#21352a',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed && form) {
                    form.submit();
                }
            });
        }
    });

    // 5. Sidebar Auto Scroll to Active Item
    const activeSidebarItem = document.querySelector('.progga-sidebar-nav .progga-nav-link.active');
    if (activeSidebarItem) {
        setTimeout(() => {
            activeSidebarItem.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 100);
    }

});

// ৬. Global Toast Function
window.showToast = function(title, text, icon = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: title,
        text: text,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
};

$(document).ready(function() {
    // ৩ সেকেন্ড (৩০০০ মিলি-সেকেন্ড) পর অ্যালার্ট মেসেজ অটোমেটিক হাইড হয়ে যাবে
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove(); // হাইড হওয়ার পর DOM থেকেও রিমুভ করে দিবে
        });
    }, 3000);
});
  </script>


<script>
$(document).ready(function() {
    let isPolling = true;

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    function getQrSelectValue(selectId) {
        if (window.proggaChoicesInstances && window.proggaChoicesInstances[selectId]) {
            return window.proggaChoicesInstances[selectId].getValue(true) || '';
        }
        return $('#' + selectId).val() || '';
    }

    function setQrSelectValue(selectId, value) {
        if (window.proggaChoicesInstances && window.proggaChoicesInstances[selectId]) {
            window.proggaChoicesInstances[selectId].setChoiceByValue(String(value || ''));
        } else {
            $('#' + selectId).val(value || '');
        }
        $('#' + selectId).trigger('change');
    }

    // Customer Type Selection Logic
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
        audio.currentTime = 0; // সাউন্ড শুরু থেকে বাজবে
        let playPromise = audio.play();
        if (playPromise !== undefined) {
            playPromise.catch(error => { console.log("Auto-play prevented."); });
        }
    }

    function checkLiveNotifications() {
        // যদি কোনো মোডাল ওপেন থাকে তবে চেক স্কিপ করবে (Queue System)
        if ($('.modal.show').length > 0 || !isPolling) return;

        $.ajax({
            url: "{{ route('notifications.check') }}",
            type: "GET",
            success: function(res) {
                if (res.status === 'success') {
                    // Priority 1: QR Order
                    if (res.order) {
                        isPolling = false;
                        playSound();

                        // ১. বেসিক ডিটেলস সেট করা
                        $('#notifyOrderTable').text(res.order.table ? res.order.table.table_number : 'Takeaway');
                        $('#notifyOrderNumber').text(res.order.order_number);

                        // ২. ভ্যাট, সার্ভিস চার্জ ও টোটাল আলাদাভাবে সেট করা
                        $('#notifyOrderSubtotal').text('৳' + parseFloat(res.order.subtotal));
                        $('#notifyOrderVat').text('৳' + parseFloat(res.order.vat_tax));
                        $('#notifyOrderService').text('৳' + parseFloat(res.order.service_charge));
                        $('#notifyOrderAmount').text('৳' + parseFloat(res.order.grand_total));

                        // ৩. প্রিপারেশন টাইম ডিফল্ট ২০ মিনিট সেট করা
                        $('#qrPrepTime').val(20);

                        // ৪. এক্সেপ্ট বাটনে অর্ডার আইডি যুক্ত করা
                        $('#btnAcceptQrOrder').data('id', res.order.id);
                        $('#btnHoldQrOrder').data('id', res.order.id);

                        // ৫. স্পেশাল নোটস থাকলে দেখানো
                        if(res.order.notes) {
                            $('#notifyOrderNotes').html('<i class="bi bi-info-circle-fill"></i> Note: ' + res.order.notes).show();
                        } else {
                            $('#notifyOrderNotes').hide();
                        }

                        // ৬. আইটেম টেবিল পপুলেট করা
                        let itemsHtml = '';
                        if(res.order.order_details && res.order.order_details.length > 0) {
                            res.order.order_details.forEach(item => {
                                itemsHtml += `
                                <tr>
                                    <td class="text-wrap" style="max-width: 150px; font-weight: 600;">${item.product_name}</td>
                                    <td class="text-center">×${item.quantity}</td>
                                    <td class="text-end text-primary" style="font-weight: 700;">৳${item.subtotal}</td>
                                </tr>`;
                            });
                        }
                        $('#qrOrderItemsBody').html(itemsHtml);

                        // ৭. ডানপাশের কাস্টমার ও ওয়েটার ফর্ম রিসেট করা
                        setQrSelectValue('qrCustomerType', 'walk_in');
                        setQrSelectValue('qrWaiterSelect', '');
                        $('#qrNewCustomerName').val('');
                        $('#qrNewCustomerPhone').val('');

                        // ৮. মোডাল শো করা
                        $('#newQrOrderModal').modal('show');
                        return;
                    }

                    // Priority 2: Waiter Call
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


    // Hold Web Order Logic: keep order for waiter and open it in POS cart
    // Hold Web Order Logic: keep order for waiter and open it in POS cart
$('#btnHoldQrOrder').on('click', function() {
    let orderId = $(this).data('id');
    let waiterId = getQrSelectValue('qrWaiterSelect');
    let prepTime = $('#qrPrepTime').val();
    let customerType = getQrSelectValue('qrCustomerType');

    let customerId = getQrSelectValue('qrExistingCustomerSelect');
    let customerName = $('#qrNewCustomerName').val();
    let customerPhone = $('#qrNewCustomerPhone').val();

    if(!orderId) {
        Swal.fire('Error', 'Order ID not found.', 'error');
        return;
    }

    if(!waiterId) {
        Swal.fire('Wait!', 'Please assign a waiter to this order.', 'warning');
        return;
    }

    if(!prepTime || prepTime < 1) {
        Swal.fire('Wait!', 'Please enter a valid preparation time.', 'warning');
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
    let originalHtml = btn.html();

    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Holding...');

    $.post("{{ url('/pos/hold-web-order') }}", {
        id: orderId,
        waiter_id: waiterId,
        preparation_time: prepTime,
        customer_type: customerType,
        customer_id: customerId,
        customer_name: customerName,
        customer_phone: customerPhone
    }, function(res) {
        if(res.status === 'success') {
            document.getElementById('notificationSound').pause();
            $('#newQrOrderModal').modal('hide');

            btn.prop('disabled', false).html(originalHtml);

            Swal.fire({
                icon: 'success',
                title: 'Order Held',
                text: res.message || 'Order moved to POS cart.',
                confirmButtonText: 'Open POS',
                allowOutsideClick: false
            }).then(function() {
                sessionStorage.setItem('openHeldQrOrderInPos', JSON.stringify(res));
                window.location.href = "{{ route('pos.index') }}?open_held_qr=1";
            });
        } else {
            Swal.fire('Error', res.message || 'Failed to hold order.', 'error');
            btn.prop('disabled', false).html(originalHtml);
        }
    }).fail(function(xhr) {
        console.error("Hold Order Error:", xhr.responseText);
        Swal.fire('Error', 'Server failed to hold this order.', 'error');
        btn.prop('disabled', false).html(originalHtml);
    });
});

    // Accept Web Order Logic
   // Accept Web Order Logic
    $('#btnAcceptQrOrder').on('click', function() {
        let orderId = $(this).data('id');
        let waiterId = getQrSelectValue('qrWaiterSelect');
        let prepTime = $('#qrPrepTime').val(); // প্রিপারেশন টাইম নেওয়া হলো
        let customerType = getQrSelectValue('qrCustomerType');

        let customerId = getQrSelectValue('qrExistingCustomerSelect');
        let customerName = $('#qrNewCustomerName').val();
        let customerPhone = $('#qrNewCustomerPhone').val();

        // Validation - ওয়েটার সিলেক্ট করা হয়েছে কিনা
        if(!waiterId) {
            Swal.fire('Wait!', 'Please assign a waiter to this order.', 'warning');
            return;
        }

        // Validation - প্রিপারেশন টাইম সঠিক আছে কিনা
        if(!prepTime || prepTime < 1) {
            Swal.fire('Wait!', 'Please enter a valid preparation time.', 'warning');
            return;
        }

        // Validation - কাস্টমার টাইপ অনুযায়ী চেক
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

        // AJAX POST Request
        $.post("{{ route('notifications.accept_order') }}", {
            id: orderId,
            waiter_id: waiterId,
            preparation_time: prepTime, // প্রিপারেশন টাইম ব্যাকএন্ডে পাঠানো হচ্ছে
            customer_type: customerType,
            customer_id: customerId,
            customer_name: customerName,
            customer_phone: customerPhone
        }, function(res) {
            if(res.status === 'success') {
                document.getElementById('notificationSound').pause(); // সাউন্ড অফ করা
                $('#newQrOrderModal').modal('hide');
                btn.prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');

                // সাকসেস মেসেজ দেখিয়ে পেজ রিলোড করা যাতে টেবিলের স্ট্যাটাস আপডেট হয়
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

    // Resolve Waiter Call Logic (Same as before)
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
        $('#btnHoldQrOrder').prop('disabled', false).html('<i class="bi bi-pause-circle-fill"></i> Hold for Waiter');
        $('#btnAcceptQrOrder').prop('disabled', false).html('<i class="bi bi-check-circle-fill"></i> Accept & Send to Kitchen');
        isPolling = true;
    });
});
</script>
<script>
// Dynamic Export Script for Ajax Filters
function exportReport(type, reportName) {
    let baseUrl = "{{ route('reports.export.csv') }}";

    if (type === 'pdf') {
        baseUrl = "{{ route('reports.export.pdf') }}";
    } else if (type === 'excel') {
        baseUrl = "{{ route('reports.export.excel') }}";
    }

    let formData = $('#reportFilterForm').serialize();
    let exportUrl = baseUrl + "?report=" + encodeURIComponent(reportName) + "&" + formData;
    window.open(exportUrl, '_blank');
}
</script>
  @yield('script')
</body>
</html>
