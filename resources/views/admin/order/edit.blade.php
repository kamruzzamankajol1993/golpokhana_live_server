@extends('admin.master.master')
@section('title', 'Edit Order — TableTrack RMS')

@section('css')
<style>
    .order-edit-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
        gap: 18px;
        align-items: start;
    }
    .order-edit-card {
        background: #fff;
        border: 1px solid var(--progga-border-light);
        border-radius: 14px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
        overflow: hidden;
    }
    .order-edit-card-header {
        padding: 15px 18px;
        border-bottom: 1px solid var(--progga-border-light);
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        background: rgba(33, 53, 42, .035);
    }
    .order-edit-card-title {
        margin: 0;
        font-size: 15px;
        font-weight: 900;
        color: var(--progga-primary);
    }
    .order-edit-card-body { padding: 18px; }
    .order-edit-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .order-edit-meta span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 9px;
        border-radius: 999px;
        background: #f8f9fa;
        border: 1px solid var(--progga-border-light);
        font-size: 12px;
        font-weight: 700;
        color: var(--progga-text-muted);
    }
    .order-edit-table th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: var(--progga-text-muted);
        background: #f8f9fa;
        white-space: nowrap;
    }
    .order-edit-table td {
        vertical-align: middle;
        font-size: 13px;
    }
    .order-edit-qty-control {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 5px;
        border: 1px solid var(--progga-border-light);
        border-radius: 999px;
        background: #fff;
        min-width: 128px;
    }
    .order-edit-qty-btn {
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 900;
        line-height: 1;
        color: #fff;
        background: var(--progga-primary);
        cursor: pointer;
        transition: transform .12s ease, opacity .12s ease;
    }
    .order-edit-qty-btn:hover { transform: translateY(-1px); opacity: .92; }
    .order-edit-qty-btn.minus { background: #dc3545; }
    .order-edit-qty-value {
        min-width: 32px;
        text-align: center;
        font-size: 15px;
        font-weight: 900;
        color: var(--progga-primary);
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px dashed var(--progga-border-light);
        font-size: 13px;
        color: var(--progga-text-muted);
    }
    .summary-row strong {
        color: var(--progga-text);
    }
    .summary-row.grand {
        margin-top: 5px;
        padding-top: 12px;
        border-top: 2px solid var(--progga-border-light);
        border-bottom: 0;
        font-size: 17px;
        font-weight: 900;
        color: var(--progga-primary);
    }
    .summary-row.grand strong { color: var(--progga-primary); }
    .split-payment-box,
    .transaction-id-box,
    .normal-paid-box {
        display: none;
    }
    .split-payment-box {
        border: 1px solid var(--progga-border-light);
        background: #fbfbfb;
        border-radius: 10px;
        padding: 12px;
        margin-top: 12px;
    }
    .payment-helper-text {
        font-size: 11px;
        font-weight: 700;
        color: var(--progga-text-muted);
        margin-top: 6px;
    }
    @media (max-width: 991.98px) {
        .order-edit-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Edit Order #{{ $order->order_number }}</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <a href="{{ route('order.index') }}" class="progga-breadcrumb-item">Order List</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Edit Order</span>
            </div>

            <div class="order-edit-meta">
                <span><i class="bi bi-person"></i> {{ $order->customer->name ?? 'Walk-in Customer' }}</span>
                <span><i class="bi bi-receipt"></i> {{ $order->order_type ?? 'N/A' }}</span>
                <span><i class="bi bi-credit-card"></i> {{ $order->payment_type ?? 'N/A' }}</span>
                <span><i class="bi bi-clock"></i> {{ optional($order->created_at)->format('d M Y, h:i A') }}</span>
            </div>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('order.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="{{ route('pos.invoice', $order->id) }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-printer"></i> Invoice
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success fw-bold">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger fw-bold">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix these errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('order.update', $order->id) }}" id="orderEditForm">
        @csrf
        @method('PUT')

        <div class="order-edit-grid">
            <div class="order-edit-card">
                <div class="order-edit-card-header">
                    <h2 class="order-edit-card-title">
                        <i class="bi bi-basket me-1"></i> Ordered Items
                    </h2>
                    <span class="badge bg-warning text-dark">No new product add option</span>
                </div>

                <div class="order-edit-card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 order-edit-table">
                            <thead>
                                <tr>
                                    <th style="min-width:220px;">Item</th>
                                    <th class="text-end">Unit Total</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->orderDetails as $detail)
                                    @php
                                        $oldQty = max(1, (int) ($detail->quantity ?? 1));
                                        $lineSubtotal = (float) ($detail->subtotal ?? 0);
                                        $addons = json_decode($detail->addons ?? '[]', true);
                                        if (!is_array($addons)) $addons = [];

                                        $addonTotal = 0;
                                        foreach($addons as $addon) {
                                            $addonTotal += (float) ($addon['price'] ?? 0);
                                        }

                                        $unitTotal = $lineSubtotal > 0
                                            ? ($lineSubtotal / $oldQty)
                                            : ((float) ($detail->price ?? 0) + $addonTotal);
                                    @endphp
                                    <tr class="order-item-row" data-unit="{{ $unitTotal }}">
                                        <td>
                                            <strong>{{ $detail->product_name }}</strong>
                                            @if(!empty($detail->is_complimentary))
                                                <span class="badge bg-success ms-1">Complimentary</span>
                                            @endif

                                            @if(count($addons) > 0)
                                                <div class="text-muted mt-1" style="font-size:11px;">
                                                    @foreach($addons as $addon)
                                                        + {{ $addon['name'] ?? 'Addon' }}@if(!$loop->last), @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if(!empty($detail->food_note))
                                                <div class="text-muted mt-1" style="font-size:11px;">
                                                    Note: {{ $detail->food_note }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            ৳<span class="unit-total">{{ number_format($unitTotal, 0) }}</span>
                                        </td>
                                        <td class="text-center">
                                            @php $currentQty = max(1, (int) old('items.'.$detail->id.'.quantity', $oldQty)); @endphp
                                            <div class="order-edit-qty-control">
                                                <button type="button" class="order-edit-qty-btn minus js-qty-minus" aria-label="Decrease quantity">−</button>
                                                <span class="order-edit-qty-value js-qty-value">{{ $currentQty }}</span>
                                                <button type="button" class="order-edit-qty-btn plus js-qty-plus" aria-label="Increase quantity">+</button>
                                            </div>
                                            <input type="hidden"
                                                   name="items[{{ $detail->id }}][quantity]"
                                                   class="js-order-qty"
                                                   value="{{ $currentQty }}">
                                        </td>
                                        <td class="text-end fw-bold">
                                            ৳<span class="line-total">{{ number_format($unitTotal * $oldQty, 0) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="order-edit-card">
                <div class="order-edit-card-header">
                    <h2 class="order-edit-card-title">
                        <i class="bi bi-wallet2 me-1"></i> Payment Summary
                    </h2>
                </div>

                <div class="order-edit-card-body">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong>৳<span id="summarySubtotal">0</span></strong>
                    </div>
                    <div class="summary-row">
                        <span>Service Charge ({{ number_format($serviceChargeRate, 2) }}%)</span>
                        <strong>৳<span id="summaryService">0</span></strong>
                    </div>
                    <div class="summary-row">
                        <span>{{ $taxSetting->tax_label ?? 'VAT' }} ({{ number_format($vatRate, 2) }}%)</span>
                        <strong>৳<span id="summaryVat">0</span></strong>
                    </div>

                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Discount Type</label>
                            <select name="discount_type" id="discountType" class="form-control">
                                <option value="fixed" {{ old('discount_type', $order->discount_type ?? 'fixed') == 'fixed' ? 'selected' : '' }}>Fixed (৳)</option>
                                <option value="percentage" {{ old('discount_type', $order->discount_type ?? 'fixed') == 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Discount Value</label>
                            <input type="number"
                                   name="discount_value"
                                   id="discountValue"
                                   class="form-control"
                                   value="{{ old('discount_value', $discountValue) }}"
                                   min="0"
                                   step="0.01">
                        </div>
                    </div>

                    <div class="summary-row mt-3">
                        <span>Discount Amount</span>
                        <strong class="text-danger">− ৳<span id="summaryDiscount">0</span></strong>
                    </div>
                    <div class="summary-row grand">
                        <span>Grand Total</span>
                        <strong>৳<span id="summaryGrand">0</span></strong>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" style="font-size:12px;">Payment Type</label>
                        <select name="payment_method" id="paymentMethod" class="form-control" onchange="window.syncOrderEditPaymentFields && window.syncOrderEditPaymentFields(); window.calculateOrderEditTotals && window.calculateOrderEditTotals();">
                            <option value="Cash" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="Card" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Card' ? 'selected' : '' }}>Card</option>
                            <option value="Mobile Banking" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Mobile Banking' ? 'selected' : '' }}>Mobile Banking</option>
                            <option value="Split" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Split' ? 'selected' : '' }}>Split</option>
                        </select>
                    </div>

                    <div class="mt-3 normal-paid-box" id="normalPaidBox">
                        <label class="form-label fw-bold" style="font-size:12px;">Total Paid</label>
                        <input type="number"
                               name="total_paid_amount"
                               id="totalPaidAmount"
                               class="form-control"
                               value="{{ old('total_paid_amount', $order->total_paid_amount ?? 0) }}"
                               min="0"
                               step="0.01">
                        <div class="payment-helper-text">Cash/Card/Mobile Banking will use this Total Paid input.</div>
                    </div>

                    <div class="split-payment-box" id="splitPaymentBox">
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label fw-bold" style="font-size:11px;">Cash</label>
                                <input type="number" name="paid_in_cash" id="paidInCash" class="form-control split-input" value="{{ old('paid_in_cash', $order->paid_in_cash ?? 0) }}" min="0" step="0.01">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold" style="font-size:11px;">Card</label>
                                <input type="number" name="paid_in_card" id="paidInCard" class="form-control split-input" value="{{ old('paid_in_card', $order->paid_in_card ?? 0) }}" min="0" step="0.01">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold" style="font-size:11px;">Mobile Banking</label>
                                <input type="number" name="paid_in_mfc" id="paidInMfc" class="form-control split-input" value="{{ old('paid_in_mfc', $order->paid_in_mfc ?? 0) }}" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="payment-helper-text">Split will use Cash + Card + Mobile Banking inputs. Total Paid will be auto-summed.</div>
                    </div>

                    <div class="mt-3 transaction-id-box" id="transactionIdBox">
                        <label class="form-label fw-bold" style="font-size:12px;">Transaction / Reference No</label>
                        <input type="text"
                               name="transaction_id"
                               id="transactionIdInput"
                               class="form-control"
                               value="{{ old('transaction_id', $order->transaction_id) }}"
                               placeholder="Card auth / TXN / Reference no">
                        <div class="payment-helper-text">This field shows only for Card or Mobile Banking payment.</div>
                    </div>

                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Tips</label>
                            <input type="number"
                                   name="tips_amount"
                                   id="tipsAmount"
                                   class="form-control"
                                   value="{{ old('tips_amount', $order->tips_amount ?? 0) }}"
                                   min="0"
                                   step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Given Money</label>
                            <input type="number"
                                   name="given_money"
                                   id="givenMoney"
                                   class="form-control"
                                   value="{{ old('given_money', $order->given_money ?? (($order->total_paid_amount ?? 0) + ($order->tips_amount ?? 0))) }}"
                                   min="0"
                                   step="0.01">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold" style="font-size:12px; color:#198754;">Change Amount</label>
                            <input type="number"
                                   name="change_amount"
                                   id="changeAmount"
                                   class="form-control fw-bold text-success"
                                   value="{{ old('change_amount', $order->change_amount ?? 0) }}"
                                   readonly>
                        </div>
                    </div>

                    <div class="summary-row mt-3">
                        <span>Total Paid</span>
                        <strong>৳<span id="summaryPaid">0</span></strong>
                    </div>
                    <div class="summary-row">
                        <span>Tips</span>
                        <strong class="text-success">৳<span id="summaryTips">0</span></strong>
                    </div>
                    <div class="summary-row">
                        <span>Given Money</span>
                        <strong>৳<span id="summaryGiven">0</span></strong>
                    </div>
                    <div class="summary-row">
                        <span>Change</span>
                        <strong class="text-success">৳<span id="summaryChange">0</span></strong>
                    </div>
                    <div class="summary-row">
                        <span>Due</span>
                        <strong class="text-danger">৳<span id="summaryDue">0</span></strong>
                    </div>

                    <button type="submit" class="progga-btn progga-btn-primary w-100 mt-3">
                        <i class="bi bi-save"></i> Save Order Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</main>
@endsection

@section('script')
<script>
(function () {
    const vatRate = Number(@json($vatRate));
    const serviceRate = Number(@json($serviceChargeRate));

    const qtyMinusButtons = document.querySelectorAll('.js-qty-minus');
    const qtyPlusButtons = document.querySelectorAll('.js-qty-plus');
    const discountType = document.getElementById('discountType');
    const discountValue = document.getElementById('discountValue');
    const paymentMethod = document.getElementById('paymentMethod');
    const totalPaidAmount = document.getElementById('totalPaidAmount');
    const normalPaidBox = document.getElementById('normalPaidBox');
    const splitPaymentBox = document.getElementById('splitPaymentBox');
    const transactionIdBox = document.getElementById('transactionIdBox');
    const transactionIdInput = document.getElementById('transactionIdInput');
    const splitInputs = document.querySelectorAll('.split-input');
    const tipsAmount = document.getElementById('tipsAmount');
    const givenMoney = document.getElementById('givenMoney');
    const changeAmount = document.getElementById('changeAmount');

    function money(value) {
        value = Number(value || 0);
        return Math.round(value).toLocaleString('en-US');
    }

    function numberValue(el) {
        return Number(el && el.value ? el.value : 0);
    }

    function setBoxVisible(box, shouldShow) {
        if (!box) return;
        box.style.display = shouldShow ? 'block' : 'none';
    }

    /**
     * Payment type change controls input show/hide only.
     * Quantity plus/minus or discount calculation will not control payment UI.
     */
    function syncPaymentFields() {
        if (!paymentMethod) return;

        const selectedPayment = paymentMethod.value;
        const isSplit = selectedPayment === 'Split';
        const showReferenceField = selectedPayment === 'Card' || selectedPayment === 'Mobile Banking';

        // Cash/Card/Mobile Banking => Total Paid input show.
        // Split => Total Paid input hide, ৩টা আলাদা paid input show.
        setBoxVisible(normalPaidBox, !isSplit);
        setBoxVisible(splitPaymentBox, isSplit);

        if (totalPaidAmount) {
            totalPaidAmount.disabled = isSplit;
        }

        splitInputs.forEach(function (input) {
            input.disabled = !isSplit;
        });

        // Transaction / Reference No shows only for Card or Mobile Banking.
        setBoxVisible(transactionIdBox, showReferenceField);
        if (transactionIdInput) {
            transactionIdInput.disabled = !showReferenceField;
            if (!showReferenceField) {
                transactionIdInput.value = '';
            }
        }
    }

    function getCurrentPaidAmount() {
        if (!paymentMethod) return 0;

        if (paymentMethod.value === 'Split') {
            let splitPaid = 0;
            splitInputs.forEach(function (input) {
                splitPaid += numberValue(input);
            });

            // Controller split payment হলে total_paid_amount field-এর উপর depend করে না,
            // তবে summary display এবং non-split এ switch করলে previous total ধরে রাখার জন্য value sync করা হলো।
            if (totalPaidAmount) {
                totalPaidAmount.value = splitPaid.toFixed(2);
            }

            return splitPaid;
        }

        return numberValue(totalPaidAmount);
    }

    function calculateTotals() {
        let subtotal = 0;

        document.querySelectorAll('.order-item-row').forEach(function (row) {
            const unit = Number(row.dataset.unit || 0);
            const qtyInput = row.querySelector('.js-order-qty');
            let qty = parseInt(qtyInput.value || '1', 10);

            if (qty < 1 || isNaN(qty)) {
                qty = 1;
                qtyInput.value = 1;
            }

            const qtyValue = row.querySelector('.js-qty-value');
            if (qtyValue) qtyValue.textContent = qty;

            const lineTotal = unit * qty;
            row.querySelector('.line-total').textContent = money(lineTotal);
            subtotal += lineTotal;
        });

        const service = Math.round((subtotal * serviceRate) / 100);
        const vat = Math.round(((subtotal + service) * vatRate) / 100);

        let discount = 0;
        const discountVal = numberValue(discountValue);
        if (discountType && discountType.value === 'percentage') {
            discount = Math.round((subtotal * discountVal) / 100);
        } else {
            discount = Math.round(discountVal);
        }

        const maxDiscount = Math.round(subtotal + service + vat);
        discount = Math.max(0, Math.min(discount, maxDiscount));

        const grand = Math.max(0, Math.round((subtotal + service + vat) - discount));
        const paid = getCurrentPaidAmount();
        const tips = numberValue(tipsAmount);
        const given = numberValue(givenMoney);
        const change = Math.max(0, given - paid - tips);
        const due = Math.max(0, grand - paid);

        if (changeAmount) {
            changeAmount.value = Math.round(change);
        }

        document.getElementById('summarySubtotal').textContent = money(subtotal);
        document.getElementById('summaryService').textContent = money(service);
        document.getElementById('summaryVat').textContent = money(vat);
        document.getElementById('summaryDiscount').textContent = money(discount);
        document.getElementById('summaryGrand').textContent = money(grand);
        document.getElementById('summaryPaid').textContent = money(paid);
        document.getElementById('summaryTips').textContent = money(tips);
        document.getElementById('summaryGiven').textContent = money(given);
        document.getElementById('summaryChange').textContent = money(change);
        document.getElementById('summaryDue').textContent = money(due);
    }

    function changeRowQty(button, delta) {
        const row = button.closest('.order-item-row');
        if (!row) return;

        const qtyInput = row.querySelector('.js-order-qty');
        let qty = parseInt(qtyInput.value || '1', 10);
        if (isNaN(qty) || qty < 1) qty = 1;

        qty = Math.max(1, qty + delta);
        qtyInput.value = qty;
        calculateTotals();
    }

    qtyMinusButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            changeRowQty(button, -1);
        });
    });

    qtyPlusButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            changeRowQty(button, 1);
        });
    });

    [discountType, discountValue, totalPaidAmount, tipsAmount, givenMoney].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', calculateTotals);
        el.addEventListener('change', calculateTotals);
    });

    if (paymentMethod) {
        paymentMethod.addEventListener('change', function () {
            syncPaymentFields();
            calculateTotals();
        });
        paymentMethod.addEventListener('input', function () {
            syncPaymentFields();
            calculateTotals();
        });
    }

    splitInputs.forEach(function (input) {
        input.addEventListener('input', calculateTotals);
        input.addEventListener('change', calculateTotals);
    });

    // Inline onchange fallback থেকেও call করার জন্য globally expose করা হলো।
    window.syncOrderEditPaymentFields = syncPaymentFields;
    window.calculateOrderEditTotals = calculateTotals;

    // Initial page load state: old payment type অনুযায়ী inputগুলো ঠিকভাবে show/hide হবে।
    syncPaymentFields();
    calculateTotals();
})();
</script>
@endsection
