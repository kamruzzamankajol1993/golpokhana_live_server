<div class="modal-header">
  <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2"></i>Order #{{ $order->order_number }} — Details</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="progga-stat-card" style="padding:14px;">
        <div class="progga-stat-icon primary" style="width:36px;height:36px;font-size:15px;"><i class="bi bi-person-fill"></i></div>
        <div class="progga-stat-info">
          <div class="progga-stat-label">Customer</div>
          <div class="progga-stat-value" style="font-size:15px;line-height:1.2;">{{ $order->customer->name ?? 'Walk-in' }}</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="progga-stat-card" style="padding:14px;">
        <div class="progga-stat-icon secondary" style="width:36px;height:36px;font-size:15px;"><i class="bi bi-layout-wtf"></i></div>
        <div class="progga-stat-info">
          <div class="progga-stat-label">Table / Type</div>
          <div class="progga-stat-value" style="font-size:15px;">{{ $order->table->table_number ?? 'Takeaway' }} — {{ $order->order_type }}</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="progga-stat-card" style="padding:14px;">
        @php
            $icon = 'clock-fill'; $color = 'info';
            if($order->status == 'Completed') { $icon = 'check-circle'; $color = 'primary'; }
            elseif($order->status == 'Pending') { $color = 'warning'; }
        @endphp
        <div class="progga-stat-icon {{ $color }}" style="width:36px;height:36px;font-size:15px;"><i class="bi bi-{{ $icon }}"></i></div>
        <div class="progga-stat-info">
          <div class="progga-stat-label">Status</div>
          <div class="progga-stat-value" style="font-size:15px;">{{ $order->status }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="progga-table-wrapper mb-3">
    <table class="progga-table progga-order-detail-items">
      <thead>
        <tr>
          <th>#</th>
          <th>Item</th>
          <th class="text-center">Qty</th>
          <th class="text-end">Unit Price</th>
          <th class="text-end">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->orderDetails as $index => $item)
        <tr>
          <td>{{ $index + 1 }}</td>
          <td>
              <strong>{{ $item->product_name }}</strong>
              @if((isset($item->is_complimentary) && $item->is_complimentary) || ((float) $item->price <= 0 && (float) $item->subtotal <= 0))
                  <span class="badge bg-success ms-1" style="font-size: 9px;">Complimentary</span>
              @endif
              @if($item->food_note)
                  <div style="font-size: 11px; color: #d33;">* {{ $item->food_note }}</div>
              @endif
          </td>
          <td class="text-center">{{ $item->quantity }}</td>
          <td class="text-end">৳{{ number_format($item->price, 0) }}</td>
          <td class="text-end"><strong>৳{{ number_format($item->subtotal, 0) }}</strong></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="row justify-content-end">
    <div class="col-sm-6">
      @php
          $serviceRateText = rtrim(rtrim(number_format((float)($taxSettingServiceCharge ?? 0), 2), '0'), '.');
          $vatRateText = rtrim(rtrim(number_format((float)($taxSettingVatRate ?? 0), 2), '0'), '.');
          $taxLabelText = $taxSettingTaxLabel ?? 'VAT';
      @endphp
      <div style="background:var(--progga-bg-secondary);border-radius:var(--progga-radius);padding:16px;">
        <div class="progga-order-summary-row">
          <span>Subtotal</span><span>৳{{ number_format($order->subtotal, 0) }}</span>
        </div>
        <div class="progga-order-summary-row">
          <span>Service Charge ({{ $serviceRateText }}%)</span><span>৳{{ number_format($order->service_charge, 0) }}</span>
        </div>
        <div class="progga-order-summary-row">
          <span>{{ $taxLabelText }} ({{ $vatRateText }}%)</span><span>৳{{ number_format($order->vat_tax, 0) }}</span>
        </div>
        @if($order->discount_amount > 0)
        <div class="progga-order-summary-row text-danger">
          <span>Discount</span><span>−৳{{ number_format($order->discount_amount, 0) }}</span>
        </div>
        @endif
        <div class="progga-order-summary-row grand">
          <span>TOTAL</span><span>৳{{ number_format($order->grand_total, 0) }}</span>
        </div>
        <div class="progga-order-summary-row text-success">
          <span>Total Paid</span><span>৳{{ number_format($order->total_paid_amount ?? 0, 0) }}</span>
        </div>
        <div class="progga-order-summary-row text-success">
          <span>Tips</span><span>৳{{ number_format($order->tips_amount ?? 0, 0) }}</span>
        </div>
        <div class="progga-order-summary-row">
          <span>Given Money</span><span>৳{{ number_format($order->given_money ?? 0, 0) }}</span>
        </div>
        <div class="progga-order-summary-row text-success">
          <span>Change</span><span>৳{{ number_format($order->change_amount ?? 0, 0) }}</span>
        </div>
        @if(($order->due ?? 0) > 0)
        <div class="progga-order-summary-row text-danger">
          <span>Due</span><span>৳{{ number_format($order->due, 0) }}</span>
        </div>
        @endif
      </div>
      <div style="margin-top:12px;display:flex;align-items:center;gap:8px;">
        <span class="progga-badge progga-badge-neutral" style="font-size:12px;padding:6px 12px;"><i class="bi bi-cash me-1"></i> Paid by {{ $order->payment_type }}</span>
        <span class="progga-badge progga-badge-neutral" style="font-size:12px;padding:6px 12px;"><i class="bi bi-clock me-1"></i> {{ $order->created_at->format('h:i A, M d') }}</span>
      </div>
    </div>
  </div>

  @if($order->notes)
  <div style="margin-top:16px;padding:12px 16px;background:#fffbf0;border-left:3px solid var(--progga-secondary);border-radius:0 8px 8px 0;">
    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--progga-secondary-dark);margin-bottom:4px;"><i class="bi bi-chat-quote me-1"></i> Order Note</div>
    <div style="font-size:13px;color:var(--progga-text-muted);">{{ $order->notes }}</div>
  </div>
  @endif

</div>
<div class="modal-footer">
  <button class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Close</button>
  <a href="{{ route('pos.invoice', $order->id) }}" target="_blank" class="progga-btn progga-btn-secondary progga-btn-sm"><i class="bi bi-printer me-1"></i> Print Receipt</a>
</div>
