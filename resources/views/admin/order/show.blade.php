@extends('admin.master.master')
@section('title', 'Order Details #'.$order->order_number)

@section('body')
@php
    $isDeliveryOrder = strtolower(trim((string) ($order->order_type ?? ''))) === 'delivery';
    $deliveryPartnerName = $order->delivery_partner_display_name ?: 'Not selected';
    $paymentType = trim((string) ($order->payment_type ?? ''));
    $cardTypeName = trim((string) ($order->card_type ?? ''));
    $mfsProviderName = trim((string) ($order->mfs_provider ?? ''));
    $paymentMethodLabel = $paymentType === 'Card' ? 'Bank / Card' : ($paymentType === 'Mobile Banking' ? 'MFS / Mobile Banking' : $paymentType);
@endphp
<main class="progga-content">
    <div class="progga-page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="progga-page-title">Order #{{ $order->order_number }} Details</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <a href="{{ route('order.index') }}" class="progga-breadcrumb-item">Order List</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Details</span>
            </div>
        </div>
        <a href="{{ route('order.index') }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-left"></i> Back to Orders</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success fw-bold"><i class="bi bi-check-circle me-1"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix these errors:</strong>
            <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="progga-card h-100 p-4" style="border-top: 4px solid var(--progga-primary);">
                <h5 class="mb-3" style="font-weight: 800; color: var(--progga-primary);"><i class="bi bi-info-circle me-2"></i> General Info</h5>
                <p class="mb-2" style="font-size: 14px;"><strong>Status:</strong> <span class="badge bg-primary px-2 py-1">{{ $order->status }}</span></p>
                <p class="mb-2" style="font-size: 14px;"><strong>Order Type:</strong> {{ $order->order_type }}</p>
                @if($isDeliveryOrder)
                    <p class="mb-2" style="font-size: 14px;"><strong>Delivery Partner:</strong> <span class="badge bg-warning text-dark px-2 py-1">{{ $deliveryPartnerName }}</span></p>
                    <p class="mb-2" style="font-size: 14px;"><strong>Table:</strong> N/A</p>
                @else
                    <p class="mb-2" style="font-size: 14px;"><strong>Table:</strong> {{ $order->table->table_number ?? 'Takeaway' }}</p>
                @endif
                <p class="mb-2" style="font-size: 14px;"><strong>Waiter:</strong> {{ $order->waiter->name ?? 'N/A' }}</p>
                <p class="mb-2" style="font-size: 14px;"><strong>Date:</strong> {{ $order->created_at->format('d M, Y h:i A') }}</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="progga-card h-100 p-4" style="border-top: 4px solid var(--progga-info);">
                <h5 class="mb-3" style="font-weight: 800; color: var(--progga-primary);"><i class="bi bi-person me-2"></i> Customer Info</h5>
                <p class="mb-2" style="font-size: 14px;"><strong>Name:</strong> {{ $order->customer->name ?? 'Walk-in Customer' }}</p>
                <p class="mb-2" style="font-size: 14px;"><strong>Phone:</strong> {{ $order->customer->phone ?? 'N/A' }}</p>
                <p class="mb-2" style="font-size: 14px;"><strong>Address:</strong> {{ $order->delivery_address ?? 'N/A' }}</p>
                <p class="mb-2" style="font-size: 14px; color: #d33;"><strong>Notes:</strong> <i>{{ $order->notes ?? 'No special instructions' }}</i></p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="progga-card h-100 p-4" style="border-top: 4px solid var(--progga-success);">
                <h5 class="mb-3" style="font-weight: 800; color: var(--progga-primary);"><i class="bi bi-credit-card me-2"></i> Payment Info</h5>
                <p class="mb-2" style="font-size: 14px;"><strong>Method:</strong> <span class="badge bg-secondary px-2 py-1">{{ $paymentMethodLabel }}</span></p>
                @if($paymentType === 'Card' && $cardTypeName !== '')
                    <p class="mb-2" style="font-size: 14px;"><strong>Card:</strong> <span class="badge bg-light text-dark border px-2 py-1">{{ $cardTypeName }}</span></p>
                @endif
                @if($paymentType === 'Mobile Banking' && $mfsProviderName !== '')
                    <p class="mb-2" style="font-size: 14px;"><strong>MFS:</strong> <span class="badge bg-light text-dark border px-2 py-1">{{ $mfsProviderName }}</span></p>
                @endif
                <p class="mb-2" style="font-size: 14px;"><strong>Trx ID:</strong> {{ $order->transaction_id ?? 'N/A' }}</p>
                @if(!empty($order->payment_remark))
                    <p class="mb-2" style="font-size: 14px;"><strong>Remark:</strong> {{ $order->payment_remark }}</p>
                @endif
                <p class="mb-2" style="font-size: 14px;"><strong>Total Paid:</strong> ৳{{ number_format($order->total_paid_amount ?? 0, 0) }}</p>
                <p class="mb-2" style="font-size: 14px;"><strong>Tips:</strong> ৳{{ number_format($order->tips_amount ?? 0, 0) }}</p>
                <p class="mb-2" style="font-size: 14px;"><strong>Given Money:</strong> ৳{{ number_format($order->given_money ?? 0, 0) }}</p>
                <p class="mb-2" style="font-size: 14px;"><strong>Change:</strong> ৳{{ number_format($order->change_amount ?? 0, 0) }}</p>
                <p class="mb-2" style="font-size: 14px;"><strong>Payment Status:</strong>
                    @if($order->due > 0)
                        <span class="badge bg-danger px-2 py-1">Due/Partial</span>
                    @else
                        <span class="badge bg-success px-2 py-1">Fully Paid</span>
                    @endif
                </p>
                <p class="mb-2" style="font-size: 14px;"><strong>Current Due:</strong> <span class="{{ ($order->due ?? 0) > 0 ? 'text-danger' : 'text-success' }} fw-bold">৳{{ number_format($order->due ?? 0, 0) }}</span></p>
                @if($order->payment_type == 'Split')
                    <div class="mt-3 p-2" style="background: rgba(33, 53, 42, 0.05); border-radius: 6px; font-size: 13px; border: 1px dashed var(--progga-border);">
                        <strong style="color: var(--progga-primary);">Split Breakdown:</strong><br>
                        Cash: <span style="font-weight:700;">৳{{ number_format($order->paid_in_cash, 0) }}</span> <br>
                        Bank / Card{{ $cardTypeName !== '' ? ' (' . $cardTypeName . ')' : '' }}: <span style="font-weight:700;">৳{{ number_format($order->paid_in_card, 0) }}</span> <br>
                        Bank / Card Ref: <span style="font-weight:700;">{{ $order->split_card_reference ?? 'N/A' }}</span> <br>
                        MFS{{ $mfsProviderName !== '' ? ' (' . $mfsProviderName . ')' : '' }}: <span style="font-weight:700;">৳{{ number_format($order->paid_in_mfc, 0) }}</span> <br>
                        MFS Ref: <span style="font-weight:700;">{{ $order->split_mfs_reference ?? 'N/A' }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@if($order->review)
    <div class="progga-card p-4 mb-4" style="border-left: 5px solid #ffc107; background: #fffdf5;">
        <h5 class="mb-2" style="font-weight: 800; color: #b28900;">
            <i class="bi bi-chat-quote-fill me-2"></i> Customer Feedback
        </h5>
        <div class="mb-2">
            @for($i=1; $i<=5; $i++)
                <i class="bi {{ $i <= $order->review->rating ? 'bi-star-fill text-warning' : 'bi-star text-muted' }}"></i>
            @endfor
        </div>
        <p class="mb-1" style="font-size: 16px; color: #333; font-style: italic;">
            "{{ $order->review->review }}"
        </p>
        <span style="font-size: 12px; color: #888;">
            <i class="bi bi-clock me-1"></i> Submitted on: {{ $order->review->created_at->format('d M, Y h:i A') }}
        </span>
    </div>
    @endif
    <div class="progga-card p-4">
        <h5 class="mb-3" style="font-weight: 800; color: var(--progga-primary);"><i class="bi bi-cart-check me-2"></i> Ordered Items</h5>
        <div class="table-responsive mb-4" style="border-radius: 8px; overflow: hidden; border: 1px solid var(--progga-border-light);">
            <table class="table table-borderless align-middle mb-0">
                <thead style="background: #f8f9fa; border-bottom: 2px solid var(--progga-border-light);">
                    <tr>
                        <th class="text-center">#</th>
                        <th>Product Details</th>
                        <th class="text-center">Addons</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Product Discount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->orderDetails as $index => $item)
                    @php $addons = json_decode($item->addons, true) ?? []; @endphp
                    <tr style="border-bottom: 1px dotted #eee;">
                        <td class="text-center text-muted">{{ $index + 1 }}</td>
                        <td>
                            <strong style="color: var(--progga-text);">{{ $item->product_name }}</strong>
                            @if((isset($item->is_complimentary) && $item->is_complimentary) || ((float) $item->price <= 0 && (float) $item->subtotal <= 0))
                                <div style="font-size:10px; color:#198754; font-weight:800; margin-top:2px;">Complimentary</div>
                            @endif
                            @if($item->complimentary_note)
                                <div style="font-size: 11px; color: #198754; font-weight: 700; margin-top: 2px;">Complimentary Note: {{ $item->complimentary_note }}</div>
                            @endif
                            @if($item->food_note)
                                <div style="font-size: 11px; color: #d33; font-style: italic;">Food Note: {{ $item->food_note }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if(count($addons) > 0)
                                @foreach($addons as $addon)
                                    <span class="badge bg-light text-dark border">+{{ $addon['name'] }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-center fw-bold">x{{ $item->quantity }}</td>
                        <td class="text-end">৳{{ number_format($item->price, 0) }}</td>
                        <td class="text-end fw-bold" style="color: var(--progga-primary);">৳{{ number_format($item->subtotal, 0) }}</td>
                        <td class="text-end">
                            @if(($item->product_discount_amount ?? 0) > 0)
                                <span class="fw-bold text-danger">−৳{{ number_format($item->product_discount_amount, 0) }}</span>
                                <div class="text-muted" style="font-size:10px;">
                                    @if(($item->product_discount_type ?? 'fixed') === 'percentage')
                                        {{ number_format($item->product_discount_value ?? 0, 2) }}%
                                    @else
                                        Fixed
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php
            $serviceRateText = rtrim(rtrim(number_format((float)($taxSettingServiceCharge ?? 0), 2), '0'), '.');
            $vatRateText = rtrim(rtrim(number_format((float)($taxSettingVatRate ?? 0), 2), '0'), '.');
            $taxLabelText = $taxSettingTaxLabel ?? 'VAT';
        @endphp

        <div class="row">
            <div class="col-md-6 offset-md-6">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <table class="table table-sm table-borderless mb-0" style="font-size: 14px;">
                        <tr>
                            <td class="text-end text-muted fw-bold">Subtotal:</td>
                            <td class="text-end fw-bold" style="width: 150px;">৳{{ number_format($order->subtotal, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end text-muted fw-bold">Service Charge ({{ $serviceRateText }}%):</td>
                            <td class="text-end fw-bold">৳{{ number_format($order->service_charge, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end text-muted fw-bold">{{ $taxLabelText }} ({{ $vatRateText }}%):</td>
                            <td class="text-end fw-bold">৳{{ number_format($order->vat_tax, 0) }}</td>
                        </tr>
                        @if(($order->product_discount_amount ?? 0) > 0)
                        <tr>
                            <td class="text-end fw-bold" style="color: #d33;">Product Discount:</td>
                            <td class="text-end fw-bold" style="color: #d33;">- ৳{{ number_format($order->product_discount_amount, 0) }}</td>
                        </tr>
                        @endif
                        @if($order->discount_amount > 0)
                        <tr>
                            <td class="text-end fw-bold" style="color: #d33;">Honored ({{ ucfirst($order->discount_type) }}):</td>
                            <td class="text-end fw-bold" style="color: #d33;">- ৳{{ number_format($order->discount_amount, 0) }}</td>
                        </tr>
                        @endif
                        <tr style="border-top: 2px solid #ccc;">
                            <td class="text-end fw-bold fs-5 pt-2" style="color: var(--progga-primary);">TOTAL:</td>
                            <td class="text-end fw-bold fs-5 pt-2" style="color: var(--progga-primary);">৳{{ number_format($order->grand_total, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end fw-bold text-success pt-3">Total Paid Amount:</td>
                            <td class="text-end text-success fw-bold pt-3">৳{{ number_format($order->total_paid_amount, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end fw-bold text-success">Tips:</td>
                            <td class="text-end text-success fw-bold">৳{{ number_format($order->tips_amount ?? 0, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end fw-bold">Given Money:</td>
                            <td class="text-end fw-bold">৳{{ number_format($order->given_money ?? 0, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end fw-bold text-success">Change:</td>
                            <td class="text-end text-success fw-bold">৳{{ number_format($order->change_amount ?? 0, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-end fw-bold text-danger">Due Amount:</td>
                            <td class="text-end text-danger fw-bold fs-6">৳{{ number_format($order->due, 0) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card p-4 mt-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-1" style="font-weight:800;color:var(--progga-primary);"><i class="bi bi-clock-history me-2"></i>Due Payment History</h5>
                <div class="text-muted" style="font-size:12px;">Each later due collection is recorded with payment type, provider/reference and remaining due.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge {{ ($order->due ?? 0) > 0 ? 'bg-danger' : 'bg-success' }} px-3 py-2">Current Due: ৳{{ number_format($order->due ?? 0, 0) }}</span>
                @can('order-edit')
                    @if(($order->due ?? 0) > 0)
                        <button type="button" class="progga-btn progga-btn-primary progga-btn-sm" data-bs-toggle="modal" data-bs-target="#duePaymentModal">
                            <i class="bi bi-cash-coin"></i> Pay Due
                        </button>
                    @endif
                @endcan
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" style="font-size:12px;">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th class="text-end">Paid Amount</th>
                        <th>Payment Type</th>
                        <th>Provider / Split Details</th>
                        <th>Reference</th>
                        <th>Received By</th>
                        <th class="text-end">Due Before</th>
                        <th class="text-end">Due After</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->duePayments as $duePayment)
                        @php
                            $duePaymentType = trim((string) ($duePayment->payment_type ?? ''));
                            $duePaymentMethodLabel = $duePaymentType === 'Card'
                                ? 'Bank / Card'
                                : ($duePaymentType === 'Mobile Banking' ? 'MFS' : ($duePaymentType ?: '—'));
                            $duePaymentDetails = [];
                            $duePaymentReferences = [];

                            if ($duePaymentType === 'Card') {
                                $duePaymentDetails[] = 'Card: ' . ($duePayment->card_type ?: '—');
                                if ($duePayment->transaction_reference) {
                                    $duePaymentReferences[] = 'Card: ' . $duePayment->transaction_reference;
                                }
                            } elseif ($duePaymentType === 'Mobile Banking') {
                                $duePaymentDetails[] = 'MFS: ' . ($duePayment->mfs_provider ?: '—');
                                if ($duePayment->transaction_reference) {
                                    $duePaymentReferences[] = 'MFS: ' . $duePayment->transaction_reference;
                                }
                            } elseif ($duePaymentType === 'Split') {
                                if ((float) ($duePayment->paid_in_cash ?? 0) > 0) {
                                    $duePaymentDetails[] = 'Cash: ৳' . number_format((float) $duePayment->paid_in_cash, 0);
                                }
                                if ((float) ($duePayment->paid_in_card ?? 0) > 0) {
                                    $duePaymentDetails[] = 'Bank / Card (' . ($duePayment->card_type ?: '—') . '): ৳' . number_format((float) $duePayment->paid_in_card, 0);
                                }
                                if ((float) ($duePayment->paid_in_mfc ?? 0) > 0) {
                                    $duePaymentDetails[] = 'MFS (' . ($duePayment->mfs_provider ?: '—') . '): ৳' . number_format((float) $duePayment->paid_in_mfc, 0);
                                }
                                if ($duePayment->split_card_reference) {
                                    $duePaymentReferences[] = 'Card: ' . $duePayment->split_card_reference;
                                }
                                if ($duePayment->split_mfs_reference) {
                                    $duePaymentReferences[] = 'MFS: ' . $duePayment->split_mfs_reference;
                                }
                            } elseif ($duePaymentType === 'Cash') {
                                $duePaymentDetails[] = 'Cash';
                            }
                        @endphp
                        <tr>
                            <td>{{ optional($duePayment->paid_at)->format('d M Y, h:i A') }}</td>
                            <td class="text-end fw-bold text-success">৳{{ number_format($duePayment->amount, 0) }}</td>
                            <td><span class="badge bg-secondary">{{ $duePaymentMethodLabel }}</span></td>
                            <td>
                                @forelse($duePaymentDetails as $detail)
                                    <div>{{ $detail }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td>
                                @forelse($duePaymentReferences as $reference)
                                    <div>{{ $reference }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td>{{ optional($duePayment->user)->name ?? 'System/User #' . ($duePayment->received_by ?? '—') }}</td>
                            <td class="text-end">৳{{ number_format($duePayment->due_before, 0) }}</td>
                            <td class="text-end fw-bold {{ (float)$duePayment->due_after > 0 ? 'text-danger' : 'text-success' }}">৳{{ number_format($duePayment->due_after, 0) }}</td>
                            <td>{{ $duePayment->remark ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No later due payment has been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>

@can('order-edit')
    @include('admin.order.partials._due_payment_modal', ['order' => $order])
@endcan
@endsection
