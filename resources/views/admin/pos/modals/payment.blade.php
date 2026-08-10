<div class="modal fade progga-modal" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">

      <div class="modal-header" style="background: var(--progga-primary); padding: 16px 20px;">
        <h5 class="modal-title" style="color: #fff; font-size: 15px; font-weight: 800;">
          <i class="bi bi-credit-card me-2"></i>Checkout &amp; Payment — Table <span id="payTableLabel">—</span>
        </h5>
        <button type="button" class="btn-close" style="filter: invert(1) brightness(2);" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" style="padding: 20px; background: var(--progga-bg);">
        <div class="row g-4">

          <div class="col-lg-6">
            <div class="progga-form-label" style="font-weight:700; margin-bottom:12px; font-size: 14px; color: var(--progga-primary);">
              Order Summary
            </div>

            <div style="font-size:11px; color:#777; margin-top:-7px; margin-bottom:9px;">Optional product-wise discount can be applied to selected items only.</div>
            <div id="payModalItemsArea" style="max-height: 300px; overflow-y: auto; padding-right: 3px;"></div>

            <div style="margin-top:14px; padding-top:10px; border-top:2px solid var(--progga-border-light);">
              <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; font-size: 13px; color: #666; margin-bottom: 4px;">
                <span>Subtotal</span><span id="paySubtotal">৳0</span>
              </div>
              <div class="progga-pos-total-row" id="payServiceRow" style="display: {{ ((float) ($taxSettingServiceCharge ?? 0) > 0) ? 'flex' : 'none' }}; justify-content: space-between; font-size: 13px; color: #666; margin-bottom: 4px;">
                <span>Service Charge ({{ $taxSettingServiceCharge }}%)</span><span id="payService">৳0</span>
              </div>
              <div class="progga-pos-total-row" id="payVatRow" style="display: {{ ((float) ($taxSettingVatRate ?? 0) > 0) ? 'flex' : 'none' }}; justify-content: space-between; font-size: 13px; color: #666; margin-bottom: 4px;">
                <span>{{ $taxSettingTaxLabel }} ({{ $taxSettingVatRate }}%)</span><span id="payVat">৳0</span>
              </div>

              <div class="progga-pos-total-row" id="payProductDiscountRow" style="display: flex; justify-content: space-between; font-size: 13px; color: #d33; margin-bottom: 4px;">
                <span>Product Discount</span><span id="payProductDiscount">−৳0</span>
              </div>
              <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; font-size: 13px; color: #d33; margin-bottom: 4px;">
                <span>Honored</span><span id="payDiscount">−৳0</span>
              </div>
              <div class="progga-pos-total-row grand" style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 900; color: var(--progga-primary); margin-top: 8px; border-top: 2px solid #f1f1f1; padding-top: 8px;">
                <span>GRAND TOTAL</span><span id="payTotalAmount">৳0</span>
              </div>


            </div>
          </div>

          <div class="col-lg-6">
            <form class="progga-pay-form" id="payForm">
              <input type="hidden" id="payOrderId" name="order_id">
              <input type="hidden" id="payOrderType" name="order_type">
              <input type="hidden" id="payIsComplimentaryOrder" name="is_complimentary_order" value="0">
              <div class="row mb-3">
                <div class="col-6">
                    <label style="font-size: 11px; font-weight: 700; color: #777; margin-bottom: 4px;">Discount Type</label>
                    <select name="discount_type" id="modal_discount_type" class="form-control" style="border: 1.5px solid var(--progga-border); border-radius: 8px; font-size: 13px;" onchange="calculateModalTotal()">
                        <option value="fixed">Fixed (৳)</option>
                        <option value="percentage">Percentage (%)</option>
                    </select>
                </div>
                <div class="col-6">

                    <label style="font-size: 11px; font-weight: 700; color: #777; margin-bottom: 4px;">Honored</label>
                    <input type="number" name="discount_value" id="modal_discount_value" class="form-control" placeholder="0" min="0" style="border: 1.5px solid var(--progga-border); border-radius: 8px; font-size: 13px;" onkeyup="calculateModalTotal()">
                </div>
              </div>

              <div class="mb-3">
                <label for="paymentRemark" style="font-size: 12px; font-weight: 700; color: #555; margin-bottom: 5px;">Remark <span id="paymentRemarkRequired" class="text-danger" style="display:none;">*</span></label>
                <textarea name="remark" id="paymentRemark" class="form-control" rows="2" maxlength="1000" placeholder="Referred by Whom" style="border: 1.5px solid var(--progga-border); border-radius: 8px; font-size: 13px; resize: vertical;"></textarea>
              </div>

              <div class="progga-form-label" style="font-weight:700; margin-bottom:10px; font-size: 14px; color: var(--progga-primary);">
                Payment Method
              </div>

              <div class="progga-pay-method-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px;">
                <input type="radio" id="payCash" name="payment_method" value="Cash" style="display: none;" checked>
                <label for="payCash" class="progga-pay-method-btn" style="border: 2px solid var(--progga-border); border-radius: 8px; padding: 10px; text-align: center; cursor: pointer;">
                  <i class="bi bi-cash-coin d-block" style="font-size: 18px; color: var(--progga-primary);"></i>
                  <span style="font-size: 11px; font-weight: 700;">Cash</span>
                </label>

                <input type="radio" id="payCard" name="payment_method" value="Card" style="display: none;">
                <label for="payCard" class="progga-pay-method-btn" style="border: 2px solid var(--progga-border); border-radius: 8px; padding: 10px; text-align: center; cursor: pointer;">
                  <i class="bi bi-credit-card d-block" style="font-size: 18px; color: var(--progga-primary);"></i>
                  <span style="font-size: 11px; font-weight: 700;">Card</span>
                </label>

                <input type="radio" id="payBkash" name="payment_method" value="Mobile Banking" style="display: none;">
                <label for="payBkash" class="progga-pay-method-btn" style="border: 2px solid var(--progga-border); border-radius: 8px; padding: 10px; text-align: center; cursor: pointer;">
                  <i class="bi bi-phone d-block" style="font-size: 18px; color: var(--progga-primary);"></i>
                  <span style="font-size: 11px; font-weight: 700;">Mobile</span>
                </label>

                <input type="radio" id="paySplit" name="payment_method" value="Split" style="display: none;">
                <label for="paySplit" class="progga-pay-method-btn" style="border: 2px solid var(--progga-border); border-radius: 8px; padding: 10px; text-align: center; cursor: pointer;">
                  <i class="bi bi-pie-chart-fill d-block" style="font-size: 18px; color: var(--progga-primary);"></i>
                  <span style="font-size: 11px; font-weight: 700;">Split</span>
                </label>
              </div>

              <div id="splitPaymentDiv" style="display: none; background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px dashed #ccc;">
                  <div class="row g-2">
                      <div class="col-4">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">Cash</label>
                          <input type="number" name="paid_in_cash" id="splitCash" class="form-control split-input p-1 text-center" value="0" min="0" step="0.01">
                      </div>
                      <div class="col-4">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">Card</label>
                          <input type="number" name="paid_in_card" id="splitCard" class="form-control split-input p-1 text-center" value="0" min="0" step="0.01">
                      </div>
                      <div class="col-4">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">MFS (Mobile)</label>
                          <input type="number" name="paid_in_mfc" id="splitMfc" class="form-control split-input p-1 text-center" value="0" min="0" step="0.01">
                      </div>
                  </div>
                  <div class="row g-2 mt-1" id="splitReferenceFields">
                      <div class="col-6">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">Card Reference Number <span id="splitCardReferenceRequired" class="text-danger" style="display:none;">*</span></label>
                          <input type="text" name="split_card_reference" id="splitCardReference" class="form-control" maxlength="255" placeholder="Card Reference Number">
                      </div>
                      <div class="col-6">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">MFS Reference Number <span id="splitMfsReferenceRequired" class="text-danger" style="display:none;">*</span></label>
                          <input type="text" name="split_mfs_reference" id="splitMfsReference" class="form-control" maxlength="255" placeholder="MFS Reference Number">
                      </div>
                  </div>
              </div>

              <div class="progga-pm-ref" id="transactionDiv" style="display: none; margin-bottom: 15px;">
                  <label id="transactionReferenceLabel" style="font-size: 12px; font-weight: 700; color: #555;">Card Reference Number <span class="text-danger">*</span></label>
                  <input type="text" name="transaction_id" class="form-control" placeholder="Card Reference Number" style="border: 1.5px solid var(--progga-border); border-radius: 8px;">
              </div>

              <div class="progga-form-label" style="font-weight:700; margin:16px 0 10px; font-size: 14px; color: var(--progga-primary);">
                Payment Amount
              </div>

              <div style="background:#fff; border:1px solid var(--progga-border-light); border-radius:10px; padding:12px; margin-bottom:14px;">
                <div class="progga-pos-total-row" id="normalPaidRow" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 700; color: #333; margin-bottom:10px;">
                  <span>Total Paid</span>
                  <input type="number" id="payTotalPaidAmount" name="total_paid_amount" class="form-control form-control-sm text-end" style="width: 140px; font-weight:bold; border: 1.5px solid var(--progga-border);" value="0" min="0" step="0.01">
                </div>

                <div class="progga-pos-total-row" id="splitPaidDisplayRow" style="display: none; justify-content: space-between; font-size: 14px; font-weight: 800; color: #333; margin-bottom:10px;">
                  <span>Split Total Paid</span><span id="payPaidDisplay">৳0</span>
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 700; color: #333; margin-bottom:10px;">
                  <span>Tips</span>
                  <input type="number" id="payTipsAmount" name="tips_amount" class="form-control form-control-sm text-end" style="width: 140px; font-weight:bold; border: 1.5px solid var(--progga-border);" value="0" min="0" step="0.01">
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 700; color: #333; margin-bottom:10px;">
                  <span>Given Money</span>
                  <input type="number" id="payGivenMoney" name="given_money" class="form-control form-control-sm text-end" style="width: 140px; font-weight:bold; border: 1.5px solid var(--progga-border);" value="0" min="0" step="0.01">
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 900; color: #198754; margin-bottom:10px;">
                  <span>CHANGE</span>
                  <input type="number" id="payChangeAmount" name="change_amount" class="form-control form-control-sm text-end" style="width: 140px; font-weight:900; border: 1.5px solid #198754; color:#198754;" value="0" readonly>
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 900; color: #d33; padding-top:10px; border-top:1px dashed var(--progga-border-light);">
                  <span>DUE AMOUNT</span><span id="payDueAmount">৳0</span>
                </div>
              </div>

              <div class="d-flex gap-2" style="margin-top:20px;">
                <button type="button" id="btnPreInvoice" class="progga-btn progga-btn-outline w-50" style="padding: 12px; font-size: 13px; font-weight: 700; border-radius: 10px;">
                  <i class="bi bi-printer"></i> Print Pre-Invoice
                </button>
                <button type="submit" class="progga-btn progga-btn-secondary w-50" style="padding: 12px; font-size: 13px; font-weight: 700; border-radius: 10px; border: none;">
                  <i class="bi bi-check-circle-fill"></i> Confirm Payment
                </button>
              </div>

            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
input[type="radio"]:checked + .progga-pay-method-btn {
    border-color: var(--progga-primary) !important;
    background: rgba(33, 53, 42, 0.05) !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.progga-product-discount-item {
    background: #fff;
    border: 1px solid var(--progga-border-light);
    border-radius: 9px;
    padding: 9px 10px;
    margin-bottom: 8px;
}
.progga-product-discount-controls {
    display: grid;
    grid-template-columns: minmax(108px, 0.9fr) minmax(90px, 0.8fr) auto;
    gap: 6px;
    align-items: center;
    margin-top: 7px;
}
.progga-product-discount-controls .form-select,
.progga-product-discount-controls .form-control {
    min-height: 31px;
    padding: 4px 7px;
    font-size: 11px;
    border-radius: 7px;
}
.progga-product-discount-amount {
    min-width: 62px;
    text-align: right;
    font-size: 11px;
    font-weight: 800;
    color: #d33;
}
@media (max-width: 767.98px) {
    .progga-product-discount-controls {
        grid-template-columns: 1fr 1fr;
    }
    .progga-product-discount-amount {
        grid-column: 1 / -1;
        text-align: left;
    }
}
</style>

<script>
function posPaymentNumber(value) {
    return parseFloat(String(value || 0).replace(/[^0-9.-]/g, '')) || 0;
}

function posMoney(value) {
    return Math.round(posPaymentNumber(value));
}

window.syncFinalPaymentFields = function() {
    let method = $('input[name="payment_method"]:checked').val() || 'Cash';
    let isSplit = method === 'Split';
    let showReferenceField = method === 'Mobile Banking' || method === 'Card';

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

    // Keep shortage visible as a negative Change; Due is based only on Total Paid.
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
    if (typeof window.syncPaymentRemarkRequirement === 'function') window.syncPaymentRemarkRequirement();
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

    let itemsHtml = '';
    if(data.items && data.items.length > 0) {
        data.items.forEach(item => {
            itemsHtml += `
            <div class="progga-pay-summary-item" style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
                <span class="text-muted">${item.name} ×${item.qty}</span>
                <span style="font-weight: 600;">৳${Math.round(item.total)}</span>
            </div>`;
        });
    } else {
        itemsHtml = '<div class="text-muted text-center" style="font-size:12px;">No items</div>';
    }
    $('#payModalItemsArea').html(itemsHtml);

    calculateModalTotal();
    window.resetFinalPaymentDefaults(posPaymentNumber($('#payTotalAmount').text()));
    bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal')).show();
}

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

$(document).on('click', '#btnPreInvoice', function() {
    let orderId = $('#payOrderId').val();
    if(!orderId) {
        Swal.fire('Info', 'For Takeaway or Delivery without table, please place the order first to generate a pre-invoice.', 'info');
        return;
    }

    let discType = $('#modal_discount_type').val();
    let discVal = $('#modal_discount_value').val() || 0;
    let params = new URLSearchParams();
    params.set('disc_type', discType);
    params.set('disc_val', discVal);

    $('#payModalItemsArea .progga-product-discount-item[data-detail-id]').each(function() {
        let row = $(this);
        let detailId = parseInt(row.data('detail-id') || 0, 10);
        let value = Math.max(0, posPaymentNumber(row.find('.product-discount-value').val()));

        if (detailId > 0 && value > 0) {
            params.set('product_discounts[' + detailId + '][type]', row.find('.product-discount-type').val() || 'fixed');
            params.set('product_discounts[' + detailId + '][value]', value);
        }
    });

    let url = "{{ url('/pos/pre-invoice') }}/" + orderId + "?" + params.toString();
    window.open(url, '_blank');
});

</script>
