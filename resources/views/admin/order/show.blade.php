@extends('admin.master.master')
@section('title', 'Order Details #'.$order->order_number)

@section('body')
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
                <p class="mb-2" style="font-size: 14px;"><strong>Table:</strong> {{ $order->table->table_number ?? 'Takeaway' }}</p>
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
                <p class="mb-2" style="font-size: 14px;"><strong>Method:</strong> <span class="badge bg-secondary px-2 py-1">{{ $order->payment_type }}</span></p>
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
                        Card: <span style="font-weight:700;">৳{{ number_format($order->paid_in_card, 0) }}</span> <br>
                        Card Ref: <span style="font-weight:700;">{{ $order->split_card_reference ?? 'N/A' }}</span> <br>
                        MFS: <span style="font-weight:700;">৳{{ number_format($order->paid_in_mfc, 0) }}</span> <br>
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
                                <span class="badge bg-success ms-1" style="font-size: 9px;">Complimentary</span>
                            @endif
                            @if($item->food_note)
                                <div style="font-size: 11px; color: #d33; font-style: italic;">Note: {{ $item->food_note }}</div>
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
                <div class="text-muted" style="font-size:12px;">Each later due collection is recorded with date, payment method and remaining due.</div>
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
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Received By</th>
                        <th class="text-end">Due Before</th>
                        <th class="text-end">Due After</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->duePayments as $duePayment)
                        <tr>
                            <td>{{ optional($duePayment->paid_at)->format('d M Y, h:i A') }}</td>
                            <td class="text-end fw-bold text-success">৳{{ number_format($duePayment->amount, 0) }}</td>
                            <td><span class="badge bg-secondary">{{ $duePayment->payment_type }}</span></td>
                            <td>{{ $duePayment->transaction_reference ?: '—' }}</td>
                            <td>{{ optional($duePayment->user)->name ?? 'System/User #' . ($duePayment->received_by ?? '—') }}</td>
                            <td class="text-end">৳{{ number_format($duePayment->due_before, 0) }}</td>
                            <td class="text-end fw-bold {{ (float)$duePayment->due_after > 0 ? 'text-danger' : 'text-success' }}">৳{{ number_format($duePayment->due_after, 0) }}</td>
                            <td>{{ $duePayment->remark ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No later due payment has been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>

@can('order-edit')
@if(($order->due ?? 0) > 0)
<div class="modal fade" id="duePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('order.pay_due', $order->id) }}" id="duePaymentForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Receive Due Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2" style="font-size:13px;">Remaining due: <strong>৳{{ number_format($order->due, 2) }}</strong></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Amount</label>
                        <input type="number" name="amount" class="form-control" min="0.01" max="{{ (float)$order->due }}" step="0.01" value="{{ old('amount', $order->due) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Method</label>
                        <select name="payment_type" id="duePaymentType" class="form-control" required>
                            <option value="Cash" {{ old('payment_type', 'Cash') === 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="Card" {{ old('payment_type') === 'Card' ? 'selected' : '' }}>Card</option>
                            <option value="Mobile Banking" {{ old('payment_type') === 'Mobile Banking' ? 'selected' : '' }}>Mobile Banking</option>
                        </select>
                    </div>
                    <div class="mb-3" id="dueReferenceBox" style="display:none;">
                        <label class="form-label fw-bold" id="dueReferenceLabel">Reference Number</label>
                        <input type="text" name="transaction_reference" id="dueReferenceInput" class="form-control" value="{{ old('transaction_reference') }}" placeholder="Reference Number">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Remark <span class="text-muted fw-normal">(Optional)</span></label>
                        <textarea name="remark" class="form-control" rows="2" placeholder="Due payment note">{{ old('remark') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-circle"></i> Confirm Due Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan
@endsection

@section('script')
<script>
(function () {
    const type = document.getElementById('duePaymentType');
    const box = document.getElementById('dueReferenceBox');
    const input = document.getElementById('dueReferenceInput');
    const label = document.getElementById('dueReferenceLabel');

    function syncDueReference() {
        if (!type || !box || !input) return;
        const needsReference = type.value === 'Card' || type.value === 'Mobile Banking';
        box.style.display = needsReference ? 'block' : 'none';
        input.required = needsReference;
        if (label) label.textContent = type.value === 'Card' ? 'Card Reference Number' : 'MFS Reference Number';
        if (!needsReference) input.value = '';
    }

    if (type) {
        type.addEventListener('change', syncDueReference);
        syncDueReference();
    }

    @if($errors->has('amount') || $errors->has('payment_type') || $errors->has('transaction_reference'))
        const modalEl = document.getElementById('duePaymentModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    @endif
})();
</script>
@endsection
