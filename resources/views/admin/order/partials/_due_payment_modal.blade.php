@php
    $dueCardTypes = ['Visa', 'Mastercard', 'American Express', 'UnionPay', 'JCB', 'Nexus', 'Diners Club', 'GPay', 'Bangla QR Card', 'Other'];
    $dueMfsProviders = ['Rocket', 'bKash', 'MYCash', 'Islami Bank mCash', 'tap', 'FirstCash', 'Upay', 'OK Wallet', 'RUPALICASH', 'TeleCash', 'Islamic Wallet', 'Meghna Pay', 'Nagad', 'Bangla QR', 'LENDEN', 'Other'];
    $dueErrorBag = $errors->getBag('duePayment');
    $dueSelectedType = old('payment_type', 'Cash');
@endphp

@if(($order->due ?? 0) > 0)
<div class="modal fade" id="duePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('order.pay_due', $order->id) }}"
                  id="duePaymentForm"
                  data-payment-type="{{ $dueSelectedType }}"
                  novalidate>
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Receive Due Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning py-2" style="font-size:13px;">
                        Remaining due: <strong>৳{{ number_format($order->due, 2) }}</strong>
                    </div>

                    @if($dueErrorBag->any())
                        <div class="alert alert-danger py-2" style="font-size:12px;">
                            <ul class="mb-0 ps-3">
                                @foreach($dueErrorBag->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Date <span class="text-danger">*</span></label>
                        <input type="date"
                               name="paid_at"
                               class="form-control"
                               value="{{ old('paid_at', now()->format('Y-m-d')) }}"
                               max="{{ now()->format('Y-m-d') }}"
                               required>
                        <small class="text-muted">Select the client payment date for this due collection.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Type</label>
                        <select name="payment_type"
                                id="duePaymentType"
                                class="form-select"
                                required
                                onchange="window.syncDuePaymentModalFields && window.syncDuePaymentModalFields();">
                            <option value="Cash" {{ $dueSelectedType === 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="Card" {{ $dueSelectedType === 'Card' ? 'selected' : '' }}>Bank / Card</option>
                            <option value="Mobile Banking" {{ $dueSelectedType === 'Mobile Banking' ? 'selected' : '' }}>MFS</option>
                            <option value="Split" {{ $dueSelectedType === 'Split' ? 'selected' : '' }}>Split</option>
                        </select>
                    </div>

                    <div class="mb-3" id="dueSingleAmountBox">
                        <label class="form-label fw-bold">Payment Amount</label>
                        <input type="number"
                               name="amount"
                               id="duePaymentAmount"
                               class="form-control"
                               min="0.01"
                               max="{{ (float) $order->due }}"
                               step="0.01"
                               value="{{ old('amount', $order->due) }}">
                    </div>

                    <div class="row g-2 mb-3" id="dueSingleProviderRow">
                        <div class="col-md-6" id="dueCardTypeBox">
                            <label class="form-label fw-bold">Card Name <span class="text-danger">*</span></label>
                            <select name="card_type" id="dueCardType" class="form-select">
                                <option value="">— Select Card —</option>
                                @foreach($dueCardTypes as $cardName)
                                    <option value="{{ $cardName }}" {{ old('card_type') === $cardName ? 'selected' : '' }}>{{ $cardName === 'Bangla QR Card' ? 'Bangla QR Card' : $cardName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6" id="dueMfsProviderBox">
                            <label class="form-label fw-bold">MFS Name <span class="text-danger">*</span></label>
                            <select name="mfs_provider" id="dueMfsProvider" class="form-select">
                                <option value="">— Select MFS —</option>
                                @foreach($dueMfsProviders as $mfsName)
                                    <option value="{{ $mfsName }}" {{ old('mfs_provider') === $mfsName ? 'selected' : '' }}>{{ $mfsName === 'Bangla QR' ? 'Bangla QR MFS' : $mfsName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6" id="dueReferenceBox">
                            <label class="form-label fw-bold" id="dueReferenceLabel">Reference Number <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="transaction_reference"
                                   id="dueReferenceInput"
                                   class="form-control"
                                   maxlength="255"
                                   value="{{ old('transaction_reference') }}"
                                   placeholder="Reference Number">
                        </div>
                    </div>

                    <div id="dueSplitPaymentDiv">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <strong style="font-size:13px;">Split Payment</strong>
                            <span style="font-size:12px;">
                                Total: <strong id="dueSplitTotal">৳0.00</strong>
                                &nbsp;|&nbsp;
                                Due After: <strong id="dueSplitRemaining">৳{{ number_format($order->due, 2) }}</strong>
                            </span>
                        </div>

                        <div class="row g-2">
                            <div class="col-4">
                                <label style="font-size:11px;font-weight:700;color:#555;">Cash</label>
                                <input type="number"
                                       name="paid_in_cash"
                                       id="dueSplitCash"
                                       class="form-control due-split-input p-1 text-center"
                                       value="{{ old('paid_in_cash', 0) }}"
                                       min="0"
                                       step="0.01"
                                       oninput="window.syncDuePaymentModalFields && window.syncDuePaymentModalFields();">
                            </div>
                            <div class="col-4">
                                <label style="font-size:11px;font-weight:700;color:#555;">Bank / Card</label>
                                <input type="number"
                                       name="paid_in_card"
                                       id="dueSplitCard"
                                       class="form-control due-split-input p-1 text-center"
                                       value="{{ old('paid_in_card', 0) }}"
                                       min="0"
                                       step="0.01"
                                       oninput="window.syncDuePaymentModalFields && window.syncDuePaymentModalFields();">
                            </div>
                            <div class="col-4">
                                <label style="font-size:11px;font-weight:700;color:#555;">MFS (Mobile)</label>
                                <input type="number"
                                       name="paid_in_mfc"
                                       id="dueSplitMfs"
                                       class="form-control due-split-input p-1 text-center"
                                       value="{{ old('paid_in_mfc', 0) }}"
                                       min="0"
                                       step="0.01"
                                       oninput="window.syncDuePaymentModalFields && window.syncDuePaymentModalFields();">
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label style="font-size:11px;font-weight:700;color:#555;">
                                    Card Type <span id="dueSplitCardTypeRequired" class="text-danger" style="display:none;">*</span>
                                </label>
                                <select name="split_card_type" id="dueSplitCardType" class="form-select form-select-sm">
                                    <option value="">— Select Card —</option>
                                    @foreach($dueCardTypes as $cardName)
                                        <option value="{{ $cardName }}" {{ old('split_card_type') === $cardName ? 'selected' : '' }}>{{ $cardName === 'Bangla QR Card' ? 'Bangla QR Card' : $cardName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label style="font-size:11px;font-weight:700;color:#555;">
                                    MFS Service <span id="dueSplitMfsProviderRequired" class="text-danger" style="display:none;">*</span>
                                </label>
                                <select name="split_mfs_provider" id="dueSplitMfsProvider" class="form-select form-select-sm">
                                    <option value="">— Select MFS —</option>
                                    @foreach($dueMfsProviders as $mfsName)
                                        <option value="{{ $mfsName }}" {{ old('split_mfs_provider') === $mfsName ? 'selected' : '' }}>{{ $mfsName === 'Bangla QR' ? 'Bangla QR MFS' : $mfsName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label style="font-size:11px;font-weight:700;color:#555;">
                                    Bank / Card Reference Number <span id="dueSplitCardReferenceRequired" class="text-danger" style="display:none;">*</span>
                                </label>
                                <input type="text"
                                       name="split_card_reference"
                                       id="dueSplitCardReference"
                                       class="form-control"
                                       maxlength="255"
                                       value="{{ old('split_card_reference') }}"
                                       placeholder="Bank / Card Reference Number">
                            </div>
                            <div class="col-md-6">
                                <label style="font-size:11px;font-weight:700;color:#555;">
                                    MFS Reference Number <span id="dueSplitMfsReferenceRequired" class="text-danger" style="display:none;">*</span>
                                </label>
                                <input type="text"
                                       name="split_mfs_reference"
                                       id="dueSplitMfsReference"
                                       class="form-control"
                                       maxlength="255"
                                       value="{{ old('split_mfs_reference') }}"
                                       placeholder="MFS Reference Number">
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Remark <span class="text-muted fw-normal">(Optional)</span></label>
                        <textarea name="remark" class="form-control" rows="2" maxlength="1000" placeholder="Due payment note">{{ old('remark') }}</textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary">
                        <i class="bi bi-check-circle"></i> Confirm Due Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    #duePaymentForm #dueSingleProviderRow,
    #duePaymentForm #dueCardTypeBox,
    #duePaymentForm #dueMfsProviderBox,
    #duePaymentForm #dueReferenceBox,
    #duePaymentForm #dueSplitPaymentDiv {
        display: none !important;
    }

    #duePaymentForm:has(#duePaymentType option[value="Card"]:checked) #dueSingleProviderRow,
    #duePaymentForm:has(#duePaymentType option[value="Mobile Banking"]:checked) #dueSingleProviderRow {
        display: flex !important;
    }

    #duePaymentForm:has(#duePaymentType option[value="Card"]:checked) #dueCardTypeBox,
    #duePaymentForm:has(#duePaymentType option[value="Card"]:checked) #dueReferenceBox,
    #duePaymentForm:has(#duePaymentType option[value="Mobile Banking"]:checked) #dueMfsProviderBox,
    #duePaymentForm:has(#duePaymentType option[value="Mobile Banking"]:checked) #dueReferenceBox {
        display: block !important;
    }

    #duePaymentForm:has(#duePaymentType option[value="Split"]:checked) #dueSingleAmountBox {
        display: none !important;
    }

    #duePaymentForm:has(#duePaymentType option[value="Split"]:checked) #dueSplitPaymentDiv {
        display: block !important;
    }

    #dueSplitPaymentDiv {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px dashed #ccc;
    }
</style>

<script>
(function () {
    const dueLimit = Number(@json((float) ($order->due ?? 0))) || 0;

    function el(id) {
        return document.getElementById(id);
    }

    function numberValue(input) {
        return Math.max(0, Number(input && input.value) || 0);
    }

    function money(value) {
        return Math.max(0, Number(value) || 0).toFixed(2);
    }

    function setRequired(input, required) {
        if (!input) return;
        input.required = required;
        input.disabled = false;
    }

    function showWarning(title, message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire(title, message, 'warning');
        } else {
            window.alert(message);
        }
    }

    window.syncDuePaymentModalFields = function () {
        const form = el('duePaymentForm');
        const type = el('duePaymentType');
        if (!form || !type) return;

        const method = type.value || 'Cash';
        const isSplit = method === 'Split';
        const isCard = method === 'Card';
        const isMfs = method === 'Mobile Banking';
        form.dataset.paymentType = method;

        const amountBox = el('dueSingleAmountBox');
        const providerRow = el('dueSingleProviderRow');
        const cardBox = el('dueCardTypeBox');
        const mfsBox = el('dueMfsProviderBox');
        const referenceBox = el('dueReferenceBox');
        const splitBox = el('dueSplitPaymentDiv');
        const amountInput = el('duePaymentAmount');
        const cardSelect = el('dueCardType');
        const mfsSelect = el('dueMfsProvider');
        const referenceInput = el('dueReferenceInput');
        const referenceLabel = el('dueReferenceLabel');

        // JS path + CSS :has fallback: either one is enough to make the fields visible.
        if (amountBox) amountBox.style.setProperty('display', isSplit ? 'none' : 'block', 'important');
        if (splitBox) splitBox.style.setProperty('display', isSplit ? 'block' : 'none', 'important');
        if (providerRow) providerRow.style.setProperty('display', (isCard || isMfs) ? 'flex' : 'none', 'important');
        if (cardBox) cardBox.style.setProperty('display', isCard ? 'block' : 'none', 'important');
        if (mfsBox) mfsBox.style.setProperty('display', isMfs ? 'block' : 'none', 'important');
        if (referenceBox) referenceBox.style.setProperty('display', (isCard || isMfs) ? 'block' : 'none', 'important');

        if (amountInput) {
            amountInput.disabled = isSplit;
            amountInput.required = !isSplit;
        }

        if (cardSelect) {
            cardSelect.disabled = !isCard;
            cardSelect.required = isCard;
        }
        if (mfsSelect) {
            mfsSelect.disabled = !isMfs;
            mfsSelect.required = isMfs;
        }
        if (referenceInput) {
            referenceInput.disabled = !(isCard || isMfs);
            referenceInput.required = isCard || isMfs;
            referenceInput.placeholder = isCard ? 'Card Reference' : (isMfs ? 'MFS Reference' : 'Reference Number');
        }
        if (referenceLabel) {
            referenceLabel.innerHTML = isCard
                ? 'Bank / Card Reference Number <span class="text-danger">*</span>'
                : 'MFS Reference Number <span class="text-danger">*</span>';
        }

        const splitCash = el('dueSplitCash');
        const splitCard = el('dueSplitCard');
        const splitMfs = el('dueSplitMfs');
        const splitCardType = el('dueSplitCardType');
        const splitMfsProvider = el('dueSplitMfsProvider');
        const splitCardReference = el('dueSplitCardReference');
        const splitMfsReference = el('dueSplitMfsReference');

        [splitCash, splitCard, splitMfs].forEach(function (input) {
            if (input) input.disabled = !isSplit;
        });

        const cardAmount = isSplit ? numberValue(splitCard) : 0;
        const mfsAmount = isSplit ? numberValue(splitMfs) : 0;
        const needsCard = isSplit && cardAmount > 0;
        const needsMfs = isSplit && mfsAmount > 0;

        if (splitCardType) {
            splitCardType.disabled = !isSplit;
            splitCardType.required = needsCard;
        }
        if (splitCardReference) {
            splitCardReference.disabled = !isSplit;
            splitCardReference.required = needsCard;
        }
        if (splitMfsProvider) {
            splitMfsProvider.disabled = !isSplit;
            splitMfsProvider.required = needsMfs;
        }
        if (splitMfsReference) {
            splitMfsReference.disabled = !isSplit;
            splitMfsReference.required = needsMfs;
        }

        const cardRequiredMarkers = [el('dueSplitCardTypeRequired'), el('dueSplitCardReferenceRequired')];
        const mfsRequiredMarkers = [el('dueSplitMfsProviderRequired'), el('dueSplitMfsReferenceRequired')];
        cardRequiredMarkers.forEach(function (marker) {
            if (marker) marker.style.display = needsCard ? 'inline' : 'none';
        });
        mfsRequiredMarkers.forEach(function (marker) {
            if (marker) marker.style.display = needsMfs ? 'inline' : 'none';
        });

        const splitTotal = numberValue(splitCash) + cardAmount + mfsAmount;
        const splitTotalText = el('dueSplitTotal');
        const splitRemainingText = el('dueSplitRemaining');

        if (splitTotalText) {
            splitTotalText.textContent = '৳' + money(splitTotal);
            splitTotalText.classList.toggle('text-danger', splitTotal > dueLimit + 0.001);
        }
        if (splitRemainingText) {
            splitRemainingText.textContent = '৳' + money(Math.max(0, dueLimit - splitTotal));
            splitRemainingText.classList.toggle('text-danger', splitTotal > dueLimit + 0.001);
        }
        if (isSplit && amountInput) {
            amountInput.value = money(splitTotal);
        }
    };

    function bindDuePaymentForm() {
        const form = el('duePaymentForm');
        const type = el('duePaymentType');
        if (!form || form.dataset.boundDuePayment === '1') return;

        form.dataset.boundDuePayment = '1';

        if (type) {
            type.addEventListener('change', window.syncDuePaymentModalFields);
            type.addEventListener('input', window.syncDuePaymentModalFields);
        }

        ['dueSplitCash', 'dueSplitCard', 'dueSplitMfs'].forEach(function (id) {
            const input = el(id);
            if (!input) return;
            input.addEventListener('input', window.syncDuePaymentModalFields);
            input.addEventListener('change', window.syncDuePaymentModalFields);
        });

        form.addEventListener('submit', function (event) {
            window.syncDuePaymentModalFields();

            if (type && type.value === 'Split') {
                const total = numberValue(el('dueSplitCash')) + numberValue(el('dueSplitCard')) + numberValue(el('dueSplitMfs'));

                if (total <= 0) {
                    event.preventDefault();
                    showWarning('Split Amount Required', 'Enter at least one Split payment amount.');
                    return;
                }

                if (total > dueLimit + 0.001) {
                    event.preventDefault();
                    showWarning('Amount Too High', 'Split payment cannot exceed the remaining due amount of ৳' + money(dueLimit) + '.');
                    return;
                }
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });

        window.syncDuePaymentModalFields();
    }

    bindDuePaymentForm();

    document.addEventListener('DOMContentLoaded', function () {
        bindDuePaymentForm();
        window.syncDuePaymentModalFields();
    });

    window.addEventListener('load', function () {
        window.syncDuePaymentModalFields();

        @if($dueErrorBag->any())
            const modalEl = el('duePaymentModal');
            if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        @endif
    });
})();
</script>
@endif
