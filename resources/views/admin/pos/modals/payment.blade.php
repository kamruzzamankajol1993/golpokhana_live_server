<div class="modal fade progga-modal" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">

      <div class="modal-header" style="background: var(--progga-primary); padding: 16px 20px;">
        <h5 class="modal-title" id="paymentModalTitle" style="color: #fff; font-size: 15px; font-weight: 800;">
          <i id="paymentModalTitleIcon" class="bi bi-credit-card me-2"></i><span id="paymentModalTitleText">Checkout &amp; Payment</span> — Table <span id="payTableLabel">—</span>
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
                <span>Item Discount</span><span id="payProductDiscount">−৳0</span>
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

                    <label style="font-size: 11px; font-weight: 700; color: #777; margin-bottom: 4px;">Discount Amount</label>
                    <input type="number" name="discount_value" id="modal_discount_value" class="form-control" placeholder="0" min="0" style="border: 1.5px solid var(--progga-border); border-radius: 8px; font-size: 13px;" onkeyup="calculateModalTotal()">
                </div>
              </div>

              <div class="mb-3">
                <label for="paymentRemark" style="font-size: 12px; font-weight: 700; color: #555; margin-bottom: 5px;">Remark <span id="paymentRemarkRequired" class="text-danger" style="display:none;">*</span></label>
                <textarea name="remark" id="paymentRemark" class="form-control" rows="2" maxlength="1000" placeholder="Referred by Whom" style="border: 1.5px solid var(--progga-border); border-radius: 8px; font-size: 13px; resize: vertical;"></textarea>
              </div>

              <div id="paymentMethodSection">
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
                  <span style="font-size: 11px; font-weight: 700;">Bank / Card</span>
                </label>

                <input type="radio" id="payBkash" name="payment_method" value="Mobile Banking" style="display: none;">
                <label for="payBkash" class="progga-pay-method-btn" style="border: 2px solid var(--progga-border); border-radius: 8px; padding: 10px; text-align: center; cursor: pointer;">
                  <i class="bi bi-phone d-block" style="font-size: 18px; color: var(--progga-primary);"></i>
                  <span style="font-size: 11px; font-weight: 700;">MFS</span>
                </label>

                <input type="radio" id="paySplit" name="payment_method" value="Split" style="display: none;">
                <label for="paySplit" class="progga-pay-method-btn" style="border: 2px solid var(--progga-border); border-radius: 8px; padding: 10px; text-align: center; cursor: pointer;">
                  <i class="bi bi-pie-chart-fill d-block" style="font-size: 18px; color: var(--progga-primary);"></i>
                  <span style="font-size: 11px; font-weight: 700;">Split</span>
                </label>
              </div>

              <div class="row g-2 align-items-end mb-3" id="singlePaymentProviderRow">
                  <div id="cardTypeDiv" class="col-6" style="display:none;">
                      <label style="font-size:12px;font-weight:700;color:#555;margin-bottom:5px;">Card Name <span class="text-danger">*</span></label>
                      <select name="card_type" id="cardTypeSelect" class="form-select" style="border:1.5px solid var(--progga-border);border-radius:8px;font-size:13px;">
                          <option value="">— Select Card —</option>
                          <option value="Visa">Visa</option>
                          <option value="Mastercard">Mastercard</option>
                          <option value="American Express">American Express</option>
                          <option value="UnionPay">UnionPay</option>
                          <option value="JCB">JCB</option>
                          <option value="Nexus">Nexus</option>
                          <option value="Diners Club">Diners Club</option>
                          <option value="GPay">GPay</option>
                          <option value="Other">Other</option>
                      </select>
                  </div>

                  <div id="mfsProviderDiv" class="col-6" style="display:none;">
                      <label style="font-size:12px;font-weight:700;color:#555;margin-bottom:5px;">MFS Name <span class="text-danger">*</span></label>
                      <select name="mfs_provider" id="mfsProviderSelect" class="form-select" style="border:1.5px solid var(--progga-border);border-radius:8px;font-size:13px;">
                          <option value="">— Select MFS —</option>
                          <option value="Rocket">Rocket</option>
                          <option value="bKash">bKash</option>
                          <option value="MYCash">MYCash</option>
                          <option value="Islami Bank mCash">Islami Bank mCash</option>
                          <option value="tap">tap</option>
                          <option value="FirstCash">FirstCash</option>
                          <option value="Upay">Upay</option>
                          <option value="OK Wallet">OK Wallet</option>
                          <option value="RUPALICASH">RUPALICASH</option>
                          <option value="TeleCash">TeleCash</option>
                          <option value="Islamic Wallet">Islamic Wallet</option>
                          <option value="Meghna Pay">Meghna Pay</option>
                          <option value="Nagad">Nagad</option>
                          <option value="LENDEN">LENDEN</option>
                          <option value="Other">Other</option>
                      </select>
                  </div>

                  <div class="progga-pm-ref col-6" id="transactionDiv" style="display:none;">
                      <label id="transactionReferenceLabel" style="font-size:12px;font-weight:700;color:#555;margin-bottom:5px;">Reference <span class="text-danger">*</span></label>
                      <input type="text" name="transaction_id" class="form-control" placeholder="Reference Number" style="border:1.5px solid var(--progga-border);border-radius:8px;font-size:13px;">
                  </div>
              </div>

              <div id="splitPaymentDiv" style="display: none; background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px dashed #ccc;">
                  <div class="row g-2">
                      <div class="col-4">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">Cash</label>
                          <input type="number" name="paid_in_cash" id="splitCash" class="form-control split-input p-1 text-center" value="0" min="0" step="0.01">
                      </div>
                      <div class="col-4">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">Bank / Card</label>
                          <input type="number" name="paid_in_card" id="splitCard" class="form-control split-input p-1 text-center" value="0" min="0" step="0.01">
                      </div>
                      <div class="col-4">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">MFS (Mobile)</label>
                          <input type="number" name="paid_in_mfc" id="splitMfc" class="form-control split-input p-1 text-center" value="0" min="0" step="0.01">
                      </div>
                  </div>
                  <div class="row g-2 mt-1" id="splitProviderFields">
                      <div class="col-6">
                          <label style="font-size:11px;font-weight:700;color:#555;">Card Type <span id="splitCardTypeRequired" class="text-danger" style="display:none;">*</span></label>
                          <select name="split_card_type" id="splitCardType" class="form-select form-select-sm">
                              <option value="">— Select Card —</option>
                              <option value="Visa">Visa</option>
                              <option value="Mastercard">Mastercard</option>
                              <option value="American Express">American Express</option>
                              <option value="UnionPay">UnionPay</option>
                              <option value="JCB">JCB</option>
                              <option value="Nexus">Nexus</option>
                              <option value="Diners Club">Diners Club</option>
                              <option value="GPay">GPay</option>
                              <option value="Other">Other</option>
                          </select>
                      </div>
                      <div class="col-6">
                          <label style="font-size:11px;font-weight:700;color:#555;">MFS Service <span id="splitMfsProviderRequired" class="text-danger" style="display:none;">*</span></label>
                          <select name="split_mfs_provider" id="splitMfsProvider" class="form-select form-select-sm">
                              <option value="">— Select MFS —</option>
                              <option value="Rocket">Rocket</option>
                              <option value="bKash">bKash</option>
                              <option value="MYCash">MYCash</option>
                              <option value="Islami Bank mCash">Islami Bank mCash</option>
                              <option value="tap">tap</option>
                              <option value="FirstCash">FirstCash</option>
                              <option value="Upay">Upay</option>
                              <option value="OK Wallet">OK Wallet</option>
                              <option value="RUPALICASH">RUPALICASH</option>
                              <option value="TeleCash">TeleCash</option>
                              <option value="Islamic Wallet">Islamic Wallet</option>
                              <option value="Meghna Pay">Meghna Pay</option>
                              <option value="Nagad">Nagad</option>
                              <option value="LENDEN">LENDEN</option>
                              <option value="Other">Other</option>
                          </select>
                      </div>
                  </div>
                  <div class="row g-2 mt-1" id="splitReferenceFields">
                      <div class="col-6">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">Bank / Card Reference Number <span id="splitCardReferenceRequired" class="text-danger" style="display:none;">*</span></label>
                          <input type="text" name="split_card_reference" id="splitCardReference" class="form-control" maxlength="255" placeholder="Bank / Card Reference Number">
                      </div>
                      <div class="col-6">
                          <label style="font-size: 11px; font-weight: 700; color: #555;">MFS Reference Number <span id="splitMfsReferenceRequired" class="text-danger" style="display:none;">*</span></label>
                          <input type="text" name="split_mfs_reference" id="splitMfsReference" class="form-control" maxlength="255" placeholder="MFS Reference Number">
                      </div>
                  </div>
              </div>
              </div>

              <div id="paymentAmountSection">
              <div class="progga-form-label" style="font-weight:700; margin:16px 0 10px; font-size: 14px; color: var(--progga-primary);">
                Payment Amount
              </div>

              <div style="background:#fff; border:1px solid var(--progga-border-light); border-radius:10px; padding:12px; margin-bottom:14px;">
                <div class="progga-pos-total-row" id="normalPaidRow" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 700; color: #333; margin-bottom:10px;">
                  <span>Total Paid</span>
                  <input type="number" id="payTotalPaidAmount" name="total_paid_amount" class="form-control form-control-sm text-end" style="width: 140px; font-weight:bold; border: 1.5px solid var(--progga-border);" value="0" min="0" step="0.01">
                </div>

                <div class="progga-pos-total-row" id="bookingAdvanceRow" style="display:none;justify-content:space-between;align-items:center;margin-bottom:10px;">
                  <span>Advance</span><input type="number" name="booking_advance" id="payAdvanceAmount" class="form-control form-control-sm text-end" value="0" min="0" step="0.01" readonly style="width:140px;">
                </div>

                <div class="progga-pos-total-row" id="splitPaidDisplayRow" style="display: none; justify-content: space-between; font-size: 14px; font-weight: 800; color: #333; margin-bottom:10px;">
                  <span>Split Payment</span><span id="payPaidDisplay">৳0</span>
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 700; color: #333; margin-bottom:10px;">
                  <span>Tips</span>
                  <input type="number" id="payTipsAmount" name="tips_amount" class="form-control form-control-sm text-end" style="width: 140px; font-weight:bold; border: 1.5px solid var(--progga-border);" value="0" min="0" step="0.01">
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 700; color: #333; margin-bottom:10px;">
                  <span>Given Money</span>
                  <div style="display:flex; align-items:center; gap:6px;">
                    <input type="number" id="payGivenMoney" name="given_money" class="form-control form-control-sm text-end" style="width: 140px; font-weight:bold; border: 1.5px solid var(--progga-border);" value="0" placeholder="0" autocomplete="off" min="0" step="0.01">
                    @if(($posSetting->given_money_manual_toggle_enabled ?? true))
                    <button type="button" id="btnToggleGivenMoney" class="btn btn-outline-secondary btn-sm" style="width:32px; height:31px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:6px;" title="Auto / Reset Given Money" aria-label="Auto or reset Given Money">
                      <i class="bi bi-arrow-repeat"></i>
                    </button>
                    @endif
                  </div>
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 14px; font-weight: 900; color: #198754; margin-bottom:10px;">
                  <span>CHANGE</span>
                  <input type="number" id="payChangeAmount" name="change_amount" class="form-control form-control-sm text-end" style="width: 140px; font-weight:900; border: 1.5px solid #198754; color:#198754;" value="0" readonly>
                </div>

                <div class="progga-pos-total-row" style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 900; color: #d33; padding-top:10px; border-top:1px dashed var(--progga-border-light);">
                  <span>DUE AMOUNT</span><span id="payDueAmount">৳0</span>
                </div>
              </div>
              </div>

              <div class="d-flex gap-2" style="margin-top:20px;">
                <button type="submit" id="payFormSubmitBtn" class="progga-btn progga-btn-secondary w-100" style="padding: 12px; font-size: 13px; font-weight: 700; border-radius: 10px; border: none;">
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

// Payment state/calculation is owned by the POS page script.
</script>
