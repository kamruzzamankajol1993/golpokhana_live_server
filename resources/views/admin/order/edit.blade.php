@extends('admin.master.master')
@section('title', 'Edit Order — ' . $restaurantSettingName)

@section('css')
<style>
    .order-edit-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
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
    .order-edit-table-wrap {
        width: 100%;
        overflow-x: hidden;
    }
    .order-edit-table {
        width: 100%;
        table-layout: fixed;
    }
    .order-edit-table th {
        padding: 9px 7px;
        font-size: 10px;
        line-height: 1.25;
        text-transform: uppercase;
        letter-spacing: .01em;
        color: var(--progga-text-muted);
        background: #f8f9fa;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .order-edit-table td {
        padding: 9px 7px;
        vertical-align: middle;
        font-size: 12px;
        overflow-wrap: anywhere;
    }
    .order-edit-item-name {
        display: block;
        line-height: 1.3;
        word-break: break-word;
    }
    .order-edit-price-stack {
        display: grid;
        gap: 5px;
    }
    .order-edit-price-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 5px;
        padding-bottom: 4px;
        border-bottom: 1px dashed var(--progga-border-light);
        white-space: nowrap;
    }
    .order-edit-price-line:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }
    .order-edit-price-label {
        font-size: 9px;
        font-weight: 800;
        color: var(--progga-text-muted);
        text-transform: uppercase;
    }
    .order-edit-qty-control {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 4px;
        border: 1px solid var(--progga-border-light);
        border-radius: 999px;
        background: #fff;
        max-width: 100%;
    }
    .order-edit-qty-btn {
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
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
        min-width: 24px;
        text-align: center;
        font-size: 13px;
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
    /* CSS fallback: provider/Split fields still react even if page-level JS is cached or delayed. */
    #orderEditForm:has(#paymentMethod option[value="Split"]:checked) #normalPaidBox { display: none !important; }
    #orderEditForm:has(#paymentMethod option[value="Split"]:checked) #splitPaymentBox { display: block !important; }
    #orderEditForm:has(#paymentMethod option[value="Card"]:checked) #editSingleProviderRow,
    #orderEditForm:has(#paymentMethod option[value="Mobile Banking"]:checked) #editSingleProviderRow { display: flex !important; }
    #orderEditForm:has(#paymentMethod option[value="Card"]:checked) #editCardTypeBox { display: block !important; }
    #orderEditForm:has(#paymentMethod option[value="Mobile Banking"]:checked) #editMfsProviderBox { display: block !important; }
    #orderEditForm:has(#paymentMethod option[value="Card"]:checked) #transactionIdBox,
    #orderEditForm:has(#paymentMethod option[value="Mobile Banking"]:checked) #transactionIdBox { display: block !important; }
    #orderEditForm:has(#paymentMethod option[value="Cash"]:checked) #normalPaidBox,
    #orderEditForm:has(#paymentMethod option[value="Card"]:checked) #normalPaidBox,
    #orderEditForm:has(#paymentMethod option[value="Mobile Banking"]:checked) #normalPaidBox { display: block !important; }
    .order-product-discount-control {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 5px;
        min-width: 0;
        width: 100%;
    }
    .order-product-discount-control .form-control {
        width: 100%;
        min-width: 0;
        min-height: 32px;
        padding: 4px 6px;
        font-size: 11px;
    }
    .order-product-discount-amount {
        margin-top: 5px;
        font-size: 10px;
        font-weight: 800;
        color: #dc3545;
        text-align: right;
    }
    .complimentary-food-label {
        display: block;
        width: max-content;
        margin-top: 4px;
        color: #198754;
        font-size: 10px;
        font-weight: 900;
        line-height: 1.1;
    }
    .order-edit-complimentary-control {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        width: 100%;
        min-width: 0;
        padding: 6px 5px;
        border: 1px solid rgba(25, 135, 84, .28);
        border-radius: 9px;
        background: rgba(25, 135, 84, .06);
        color: #198754;
        font-size: 11px;
        font-weight: 800;
        cursor: pointer;
        user-select: none;
    }
    .order-edit-complimentary-control input {
        width: 15px;
        height: 15px;
        margin: 0;
        accent-color: #198754;
    }
    .order-item-row.is-complimentary-preview td {
        background: rgba(25, 135, 84, .025);
    }
    @media (max-width: 1199.98px) {
        .order-product-discount-control {
            grid-template-columns: 1fr;
        }
        .order-edit-table th,
        .order-edit-table td {
            padding-left: 5px;
            padding-right: 5px;
        }
    }
    @media (max-width: 991.98px) {
        .order-edit-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 767.98px) {
        .order-edit-card-header {
            align-items: flex-start;
            flex-direction: column;
        }
        .order-edit-table-wrap {
            padding: 10px;
            overflow: visible;
        }
        .order-edit-table,
        .order-edit-table tbody,
        .order-edit-table tr,
        .order-edit-table td {
            display: block;
            width: 100%;
        }
        .order-edit-table thead,
        .order-edit-table colgroup {
            display: none;
        }
        .order-edit-table {
            border: 0;
        }
        .order-edit-table tbody {
            border: 0;
        }
        .order-edit-table tr {
            margin-bottom: 12px;
            border: 1px solid var(--progga-border-light);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .04);
        }
        .order-edit-table tr:last-child {
            margin-bottom: 0;
        }
        .order-edit-table td {
            display: grid;
            grid-template-columns: 105px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
            padding: 9px 10px;
            text-align: left !important;
            border-width: 0 0 1px 0;
        }
        .order-edit-table td:last-child {
            border-bottom: 0;
        }
        .order-edit-table td::before {
            content: attr(data-label);
            color: var(--progga-text-muted);
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .order-edit-price-stack,
        .order-product-discount-control {
            width: 100%;
        }
        .order-edit-qty-control,
        .order-edit-complimentary-control,
        .js-delete-order-item {
            justify-self: start;
            width: auto;
        }
        .order-product-discount-control {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }
        .order-product-discount-amount {
            text-align: left;
        }
    }
    @media (max-width: 420px) {
        .order-edit-table td {
            grid-template-columns: 88px minmax(0, 1fr);
        }
        .order-product-discount-control {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('body')
@php
    $deliveryPartnerLabels = [
        'inhouse' => 'In-house Delivery',
        'foodpanda' => 'Foodpanda',
        'foodi' => 'Foodi',
        'pathao_food' => 'Pathao Food',
    ];
    $isDeliveryOrder = strtolower(trim((string) ($order->order_type ?? ''))) === 'delivery';
    $deliveryPartnerValue = $order->delivery_partner ?: 'inhouse';
    $paymentCardTypes = ['Visa', 'Mastercard', 'American Express', 'UnionPay', 'JCB', 'Nexus', 'Diners Club', 'GPay', 'Other'];
    $paymentMfsProviders = ['Rocket', 'bKash', 'MYCash', 'Islami Bank mCash', 'tap', 'FirstCash', 'Upay', 'OK Wallet', 'RUPALICASH', 'TeleCash', 'Islamic Wallet', 'Meghna Pay', 'Nagad', 'LENDEN', 'Other'];
@endphp
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
                @if($isDeliveryOrder)
                    <span><i class="bi bi-truck"></i> {{ $deliveryPartnerLabels[$deliveryPartnerValue] ?? $deliveryPartnerValue }}</span>
                @endif
                <span><i class="bi bi-credit-card"></i> {{ ($order->payment_type ?? '') === 'Card' ? 'Bank / Card' : (($order->payment_type ?? '') === 'Mobile Banking' ? 'MFS' : ($order->payment_type ?? 'N/A')) }}</span>
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
            <div class="order-edit-card col-12">
                <div class="order-edit-card-header">
                    <h2 class="order-edit-card-title">
                        <i class="bi bi-basket me-1"></i> Ordered Items
                    </h2>
                    <span class="badge bg-warning text-dark">No new product add option</span>
                </div>

                <div class="order-edit-card-body p-0">
                    <div class="order-edit-table-wrap">
                        <table class="table table-bordered mb-0 order-edit-table">
                            <colgroup>
                                <col style="width:27%;">
                                <col style="width:15%;">
                                <col style="width:12%;">
                                <col style="width:14%;">
                                <col style="width:24%;">
                                <col style="width:8%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Convert</th>
                                    <th>Product Discount</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->orderDetails as $detail)
                                    @php
                                        $oldQty = max(1, (int) ($detail->quantity ?? 1));
                                        $lineSubtotal = (float) ($detail->subtotal ?? 0);
                                        $addons = json_decode($detail->addons ?? '[]', true);
                                        if (!is_array($addons)) $addons = [];

                                        $isComplimentary = !empty($detail->is_complimentary)
                                            || ((float) ($detail->price ?? 0) <= 0 && $lineSubtotal <= 0);

                                        $storedAddonTotal = 0;
                                        foreach($addons as $addon) {
                                            $storedAddonTotal += (float) ($addon['price'] ?? 0);
                                        }

                                        if ($isComplimentary) {
                                            $food = $detail->foodItem;
                                            $normalFoodPrice = $food
                                                ? (float) ($food->discount_price ?? $food->base_price ?? 0)
                                                : 0;
                                            $currentAddons = $food ? $food->addons->keyBy('id') : collect();
                                            $normalAddonTotal = 0;

                                            foreach($addons as $addon) {
                                                $addonId = (int) ($addon['id'] ?? 0);
                                                if ($addonId > 0 && $currentAddons->has($addonId)) {
                                                    $normalAddonTotal += (float) ($currentAddons->get($addonId)->price ?? 0);
                                                } else {
                                                    $normalAddonTotal += max(0, (float) ($addon['price'] ?? 0));
                                                }
                                            }

                                            $unitTotal = $normalFoodPrice + $normalAddonTotal;
                                        } else {
                                            $unitTotal = $lineSubtotal > 0
                                                ? ($lineSubtotal / $oldQty)
                                                : ((float) ($detail->price ?? 0) + $storedAddonTotal);
                                        }

                                        $oldComplimentaryValue = old('items.'.$detail->id.'.make_complimentary', null);
                                        $previewComplimentary = $oldComplimentaryValue === null
                                            ? $isComplimentary
                                            : filter_var($oldComplimentaryValue, FILTER_VALIDATE_BOOLEAN);

                                        $currentQty = max(1, (int) old('items.'.$detail->id.'.quantity', $oldQty));
                                        $productDiscountType = old('items.'.$detail->id.'.product_discount_type', $detail->product_discount_type ?: 'fixed');
                                        $productDiscountValue = old('items.'.$detail->id.'.product_discount_value', $detail->product_discount_value ?? 0);
                                        if (is_numeric($productDiscountValue)) {
                                            $productDiscountValue = rtrim(rtrim(number_format((float) $productDiscountValue, 2, '.', ''), '0'), '.');
                                        }
                                    @endphp
                                    <tr class="order-item-row {{ $previewComplimentary ? 'is-complimentary-preview' : '' }}"
                                        data-unit="{{ $unitTotal }}"
                                        data-complimentary="{{ $previewComplimentary ? 1 : 0 }}">
                                        <td data-label="Item">
                                            <strong class="order-edit-item-name">{{ $detail->product_name }}</strong>
                                            <span class="complimentary-food-label js-complimentary-label"
                                                  style="{{ $previewComplimentary ? '' : 'display:none;' }}">Complimentary</span>

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
                                        <td data-label="Price">
                                            <div class="order-edit-price-stack">
                                                <div class="order-edit-price-line">
                                                    <span class="order-edit-price-label">Unit</span>
                                                    <strong>৳<span class="unit-total">{{ number_format($previewComplimentary ? 0 : $unitTotal, 0) }}</span></strong>
                                                </div>
                                                <div class="order-edit-price-line">
                                                    <span class="order-edit-price-label">Total</span>
                                                    <strong>৳<span class="line-total">{{ number_format($previewComplimentary ? 0 : ($unitTotal * $currentQty), 0) }}</span></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center" data-label="Qty">
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
                                        <td class="text-center" data-label="Complimentary">
                                            <input type="hidden"
                                                   name="items[{{ $detail->id }}][make_complimentary]"
                                                   value="0">
                                            <label class="order-edit-complimentary-control" title="Checked = complimentary, unchecked = normal food">
                                                <input type="checkbox"
                                                       name="items[{{ $detail->id }}][make_complimentary]"
                                                       value="1"
                                                       class="js-complimentary-toggle"
                                                       {{ $previewComplimentary ? 'checked' : '' }}>
                                                <span><i class="bi bi-gift me-1"></i>Complimentary</span>
                                            </label>
                                        </td>
                                        <td data-label="Product Discount">
                                            <div class="order-product-discount-control">
                                                <select name="items[{{ $detail->id }}][product_discount_type]" class="form-control js-product-discount-type" {{ $previewComplimentary ? 'disabled' : '' }}>
                                                    <option value="fixed" {{ $productDiscountType === 'fixed' ? 'selected' : '' }}>Fixed (৳)</option>
                                                    <option value="percentage" {{ $productDiscountType === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                                </select>
                                                <input type="number"
                                                       name="items[{{ $detail->id }}][product_discount_value]"
                                                       class="form-control js-product-discount-value"
                                                       value="{{ $productDiscountValue }}"
                                                       min="0"
                                                       step="0.01"
                                                       placeholder="Value"
                                                       {{ $previewComplimentary ? 'disabled' : '' }}>
                                            </div>
                                            <div class="order-product-discount-amount">
                                                Discount: − ৳<span class="js-product-discount-amount">{{ number_format($detail->product_discount_amount ?? 0, 0) }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center" data-label="Action">
                                            <button type="button"
                                                    class="progga-btn progga-btn-danger progga-btn-sm progga-btn-icon js-delete-order-item"
                                                    data-item-id="{{ $detail->id }}"
                                                    data-product-name="{{ $detail->product_name }}"
                                                    title="Delete {{ $detail->product_name }}"
                                                    aria-label="Delete {{ $detail->product_name }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="order-edit-card col-12">
                <div class="order-edit-card-header">
                    <h2 class="order-edit-card-title">
                        <i class="bi bi-wallet2 me-1"></i> Payment Summary
                    </h2>
                </div>

                <div class="order-edit-card-body">
                    @if($isDeliveryOrder)
                        <div class="mb-3 p-3" style="background:#fff9e8;border:1px solid #f3d98c;border-radius:10px;">
                            <label class="form-label fw-bold mb-1" for="deliveryPartner" style="font-size:12px;">
                                <i class="bi bi-truck me-1"></i> Delivery Partner
                            </label>
                            <select name="delivery_partner" id="deliveryPartner" class="form-control" required>
                                @foreach($deliveryPartnerLabels as $partnerValue => $partnerLabel)
                                    <option value="{{ $partnerValue }}" {{ old('delivery_partner', $deliveryPartnerValue) === $partnerValue ? 'selected' : '' }}>
                                        {{ $partnerLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="payment-helper-text">Changing this will update the delivery partner saved with the order.</div>
                        </div>
                    @endif

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
                            <label class="form-label fw-bold" style="font-size:12px;">Honored</label>
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
                        <span>Honored</span>
                        <strong class="text-danger">− ৳<span id="summaryDiscount">0</span></strong>
                    </div>
                    <div class="summary-row">
                        <span>Product Discount</span>
                        <strong class="text-danger">− ৳<span id="summaryProductDiscount">0</span></strong>
                    </div>
                    <div class="summary-row grand">
                        <span>Grand Total</span>
                        <strong>৳<span id="summaryGrand">0</span></strong>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" style="font-size:12px;">Payment Type</label>
                        <select name="payment_method" id="paymentMethod" class="form-control" onchange="window.syncOrderEditPaymentFields && window.syncOrderEditPaymentFields(); window.calculateOrderEditTotals && window.calculateOrderEditTotals();">
                            <option value="Cash" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="Card" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Card' ? 'selected' : '' }}>Bank / Card</option>
                            <option value="Mobile Banking" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Mobile Banking' ? 'selected' : '' }}>MFS</option>
                            <option value="Split" {{ old('payment_method', $order->payment_type ?? 'Cash') == 'Split' ? 'selected' : '' }}>Split</option>
                        </select>
                    </div>

                    <div class="row g-2 mt-3" id="editSingleProviderRow" style="display:none;">
                        <div class="col-md-6" id="editCardTypeBox" style="display:none;">
                            <label class="form-label fw-bold" style="font-size:12px;">Card Name <span class="text-danger">*</span></label>
                            <select name="card_type" id="editCardType" class="form-select">
                                <option value="">— Select Card —</option>
                                @foreach($paymentCardTypes as $cardName)
                                    <option value="{{ $cardName }}" {{ old('card_type', $order->card_type ?? '') === $cardName ? 'selected' : '' }}>{{ $cardName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6" id="editMfsProviderBox" style="display:none;">
                            <label class="form-label fw-bold" style="font-size:12px;">MFS Name <span class="text-danger">*</span></label>
                            <select name="mfs_provider" id="editMfsProvider" class="form-select">
                                <option value="">— Select MFS —</option>
                                @foreach($paymentMfsProviders as $mfsName)
                                    <option value="{{ $mfsName }}" {{ old('mfs_provider', $order->mfs_provider ?? '') === $mfsName ? 'selected' : '' }}>{{ $mfsName }}</option>
                                @endforeach
                            </select>
                        </div>
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
                        <div class="payment-helper-text">Cash / Bank / Card / Mobile Banking will use this Total Paid input.</div>
                    </div>

                    <div class="split-payment-box" id="splitPaymentBox">
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label fw-bold" style="font-size:11px;">Cash</label>
                                <input type="number" name="paid_in_cash" id="paidInCash" class="form-control split-input" value="{{ old('paid_in_cash', $order->paid_in_cash ?? 0) }}" min="0" step="0.01">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold" style="font-size:11px;">Bank / Card</label>
                                <input type="number" name="paid_in_card" id="paidInCard" class="form-control split-input" value="{{ old('paid_in_card', $order->paid_in_card ?? 0) }}" min="0" step="0.01">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-bold" style="font-size:11px;">Mobile Banking</label>
                                <input type="number" name="paid_in_mfc" id="paidInMfc" class="form-control split-input" value="{{ old('paid_in_mfc', $order->paid_in_mfc ?? 0) }}" min="0" step="0.01">
                            </div>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold" style="font-size:11px;">Card Type <span id="editSplitCardTypeRequired" class="text-danger" style="display:none;">*</span></label>
                                <select name="split_card_type" id="editSplitCardType" class="form-select form-select-sm">
                                    <option value="">— Select Card —</option>
                                    @foreach($paymentCardTypes as $cardName)
                                        <option value="{{ $cardName }}" {{ old('split_card_type', (($order->payment_type ?? '') === 'Split' ? ($order->card_type ?? '') : '')) === $cardName ? 'selected' : '' }}>{{ $cardName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" style="font-size:11px;">MFS Service <span id="editSplitMfsProviderRequired" class="text-danger" style="display:none;">*</span></label>
                                <select name="split_mfs_provider" id="editSplitMfsProvider" class="form-select form-select-sm">
                                    <option value="">— Select MFS —</option>
                                    @foreach($paymentMfsProviders as $mfsName)
                                        <option value="{{ $mfsName }}" {{ old('split_mfs_provider', (($order->payment_type ?? '') === 'Split' ? ($order->mfs_provider ?? '') : '')) === $mfsName ? 'selected' : '' }}>{{ $mfsName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold" style="font-size:11px;">Bank / Card Reference Number <span id="editSplitCardReferenceRequired" class="text-danger" style="display:none;">*</span></label>
                                <input type="text" name="split_card_reference" id="editSplitCardReference" class="form-control" maxlength="255" value="{{ old('split_card_reference', $order->split_card_reference ?? '') }}" placeholder="Bank / Card Reference Number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" style="font-size:11px;">MFS Reference Number <span id="editSplitMfsReferenceRequired" class="text-danger" style="display:none;">*</span></label>
                                <input type="text" name="split_mfs_reference" id="editSplitMfsReference" class="form-control" maxlength="255" value="{{ old('split_mfs_reference', $order->split_mfs_reference ?? '') }}" placeholder="MFS Reference Number">
                            </div>
                        </div>

                        <div class="payment-helper-text">Split uses Cash + Bank / Card + MFS. Card/MFS provider and reference become required when that amount is greater than 0.</div>
                    </div>

                    <div class="mt-3 transaction-id-box" id="transactionIdBox">
                        <label class="form-label fw-bold" id="editTransactionReferenceLabel" style="font-size:12px;">Reference Number <span class="text-danger">*</span></label>
                        <input type="text"
                               name="transaction_id"
                               id="transactionIdInput"
                               class="form-control"
                               value="{{ old('transaction_id', $order->transaction_id) }}"
                               placeholder="Bank / Card auth / TXN / Reference no">
                        <div class="payment-helper-text">This field shows only for Bank / Card or Mobile Banking payment.</div>
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
    const transactionReferenceLabel = document.getElementById('editTransactionReferenceLabel');
    const singleProviderRow = document.getElementById('editSingleProviderRow');
    const cardTypeBox = document.getElementById('editCardTypeBox');
    const cardTypeSelect = document.getElementById('editCardType');
    const mfsProviderBox = document.getElementById('editMfsProviderBox');
    const mfsProviderSelect = document.getElementById('editMfsProvider');
    const splitCardType = document.getElementById('editSplitCardType');
    const splitMfsProvider = document.getElementById('editSplitMfsProvider');
    const splitCardReference = document.getElementById('editSplitCardReference');
    const splitMfsReference = document.getElementById('editSplitMfsReference');
    const splitInputs = document.querySelectorAll('.split-input');
    const complimentaryToggles = document.querySelectorAll('.js-complimentary-toggle');
    const deleteItemButtons = document.querySelectorAll('.js-delete-order-item');
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
        const isCard = selectedPayment === 'Card';
        const isMfs = selectedPayment === 'Mobile Banking';
        const showReferenceField = isCard || isMfs;

        // Cash/Card/MFS => Total Paid. Split => separate Cash/Card/MFS amounts.
        setBoxVisible(normalPaidBox, !isSplit);
        setBoxVisible(splitPaymentBox, isSplit);

        if (totalPaidAmount) {
            totalPaidAmount.disabled = isSplit;
            totalPaidAmount.required = !isSplit;
        }

        splitInputs.forEach(function (input) {
            input.disabled = !isSplit;
        });

        // Single Bank/Card or MFS uses the same provider + required reference flow as POS.
        if (singleProviderRow) singleProviderRow.style.display = showReferenceField ? 'flex' : 'none';
        if (cardTypeBox) cardTypeBox.style.display = isCard ? 'block' : 'none';
        if (mfsProviderBox) mfsProviderBox.style.display = isMfs ? 'block' : 'none';

        if (cardTypeSelect) {
            cardTypeSelect.disabled = !isCard;
            cardTypeSelect.required = isCard;
        }
        if (mfsProviderSelect) {
            mfsProviderSelect.disabled = !isMfs;
            mfsProviderSelect.required = isMfs;
        }

        setBoxVisible(transactionIdBox, showReferenceField);
        if (transactionIdInput) {
            transactionIdInput.disabled = !showReferenceField;
            transactionIdInput.required = showReferenceField;
            transactionIdInput.placeholder = isCard ? 'Card Reference' : (isMfs ? 'MFS Reference' : 'Reference Number');
        }
        if (transactionReferenceLabel) {
            transactionReferenceLabel.innerHTML = isCard
                ? 'Bank / Card Reference Number <span class="text-danger">*</span>'
                : 'MFS Reference Number <span class="text-danger">*</span>';
        }

        // POS-style Split provider/reference requirements.
        const splitCardAmount = isSplit ? numberValue(document.getElementById('paidInCard')) : 0;
        const splitMfsAmount = isSplit ? numberValue(document.getElementById('paidInMfc')) : 0;
        const needsSplitCard = isSplit && splitCardAmount > 0;
        const needsSplitMfs = isSplit && splitMfsAmount > 0;

        if (splitCardType) {
            splitCardType.disabled = !isSplit;
            splitCardType.required = needsSplitCard;
        }
        if (splitCardReference) {
            splitCardReference.disabled = !isSplit;
            splitCardReference.required = needsSplitCard;
        }
        if (splitMfsProvider) {
            splitMfsProvider.disabled = !isSplit;
            splitMfsProvider.required = needsSplitMfs;
        }
        if (splitMfsReference) {
            splitMfsReference.disabled = !isSplit;
            splitMfsReference.required = needsSplitMfs;
        }

        ['editSplitCardTypeRequired', 'editSplitCardReferenceRequired'].forEach(function (id) {
            const marker = document.getElementById(id);
            if (marker) marker.style.display = needsSplitCard ? 'inline' : 'none';
        });
        ['editSplitMfsProviderRequired', 'editSplitMfsReferenceRequired'].forEach(function (id) {
            const marker = document.getElementById(id);
            if (marker) marker.style.display = needsSplitMfs ? 'inline' : 'none';
        });
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
        let productDiscount = 0;

        document.querySelectorAll('.order-item-row').forEach(function (row) {
            const unit = Number(row.dataset.unit || 0);
            const qtyInput = row.querySelector('.js-order-qty');
            const complimentaryToggle = row.querySelector('.js-complimentary-toggle');
            const isComplimentary = Boolean(complimentaryToggle && complimentaryToggle.checked);
            let qty = parseInt(qtyInput.value || '1', 10);

            if (qty < 1 || isNaN(qty)) {
                qty = 1;
                qtyInput.value = 1;
            }

            const qtyValue = row.querySelector('.js-qty-value');
            if (qtyValue) qtyValue.textContent = qty;

            row.classList.toggle('is-complimentary-preview', isComplimentary);

            const complimentaryLabel = row.querySelector('.js-complimentary-label');
            if (complimentaryLabel) {
                complimentaryLabel.style.display = isComplimentary ? 'block' : 'none';
            }

            const unitDisplay = row.querySelector('.unit-total');
            if (unitDisplay) {
                unitDisplay.textContent = money(isComplimentary ? 0 : unit);
            }

            const lineTotal = isComplimentary ? 0 : (unit * qty);
            row.querySelector('.line-total').textContent = money(lineTotal);
            subtotal += lineTotal;

            const productType = row.querySelector('.js-product-discount-type');
            const productValue = row.querySelector('.js-product-discount-value');
            if (productType) productType.disabled = isComplimentary;
            if (productValue) productValue.disabled = isComplimentary;

            let discountValue = isComplimentary ? 0 : Math.max(0, numberValue(productValue));
            let lineDiscount = 0;

            if (!isComplimentary && productType && productType.value === 'percentage') {
                discountValue = Math.min(discountValue, 100);
                lineDiscount = Math.round((lineTotal * discountValue) / 100);
            } else if (!isComplimentary) {
                lineDiscount = Math.min(discountValue, lineTotal);
            }

            lineDiscount = Math.max(0, Math.round(lineDiscount));
            productDiscount += lineDiscount;

            const amountBox = row.querySelector('.js-product-discount-amount');
            if (amountBox) amountBox.textContent = money(lineDiscount);
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

        productDiscount = Math.max(0, Math.min(Math.round(productDiscount), Math.round(subtotal)));
        const grand = Math.max(0, Math.round((subtotal + service + vat) - discount - productDiscount));
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
        document.getElementById('summaryProductDiscount').textContent = money(productDiscount);
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

    document.querySelectorAll('.js-product-discount-type, .js-product-discount-value').forEach(function (el) {
        el.addEventListener('input', calculateTotals);
        el.addEventListener('change', calculateTotals);
    });

    complimentaryToggles.forEach(function (toggle) {
        toggle.addEventListener('change', calculateTotals);
    });

    deleteItemButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const productName = button.dataset.productName || 'this product';
            const itemId = button.dataset.itemId;
            const form = button.closest('form');

            if (!form || !itemId) return;

            Swal.fire({
                title: 'Delete item?',
                text: 'Are you sure you want to delete "' + productName + '" from this order?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then(function (result) {
                if (!result.isConfirmed) return;

                let deleteInput = form.querySelector('input.js-delete-item-input[name="delete_item_id"]');
                if (!deleteInput) {
                    deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_item_id';
                    deleteInput.className = 'js-delete-item-input';
                    form.appendChild(deleteInput);
                }

                deleteInput.value = itemId;
                button.disabled = true;

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
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
        input.addEventListener('input', function () {
            syncPaymentFields();
            calculateTotals();
        });
        input.addEventListener('change', function () {
            syncPaymentFields();
            calculateTotals();
        });
    });

    const orderEditForm = document.getElementById('orderEditForm');
    if (orderEditForm) {
        orderEditForm.addEventListener('submit', function () {
            syncPaymentFields();
            calculateTotals();
        });
    }

    // Inline onchange fallback থেকেও call করার জন্য globally expose করা হলো।
    window.syncOrderEditPaymentFields = syncPaymentFields;
    window.calculateOrderEditTotals = calculateTotals;

    // Initial page load state: old payment type অনুযায়ী inputগুলো ঠিকভাবে show/hide হবে।
    syncPaymentFields();
    calculateTotals();
})();
</script>
@endsection
