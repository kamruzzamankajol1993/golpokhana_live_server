<div class="progga-table-wrapper due-list-table-scroll" style="border:none;border-radius:0; overflow-x:auto; overflow-y:visible;">
  <table class="progga-table" id="ordersTable">
    <thead>
      <tr>
        <th>Order #</th>
        <th>Branch</th>
        <th>Customer</th>
        <th>Subtotal</th>
        <th>Honored</th>
        <th>Product Discount</th>
        <th>VAT</th>
        <th>Service Charge</th>
        <th>Tips</th>
        <th>Given</th>
        <th>Change</th>
        <th>Grand Total</th>
        <th>Due</th>
        <th>Payment</th>
        <th>Status</th>
        <th>Date</th>
        <th>Time</th>
        <th>Kitchen to Payment</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($orders as $order)
          @php
              $statusClass = strtolower($order->status);
              $badgeClass = 'neutral';
              $normalizedOrderType = strtolower(trim((string) ($order->order_type ?? '')));
              $deliveryPartnerName = $order->delivery_partner_display_name ?: 'Not selected';
              $iconClass = 'clock-fill';
              if($order->status == 'Completed') { $badgeClass = 'primary'; $iconClass = 'check2-all'; }
              elseif($order->status == 'Pending') { $badgeClass = 'warning'; $iconClass = 'clock-fill'; }
              elseif($order->status == 'Cooking') { $badgeClass = 'info'; $iconClass = 'fire'; }
              elseif($order->status == 'Ready') { $badgeClass = 'success'; $iconClass = 'check-circle-fill'; }
              elseif($order->status == 'Cancelled') { $badgeClass = 'danger'; $iconClass = 'x-circle-fill'; }
          @endphp
          <tr class="status-{{ $statusClass }}">
            <td><strong>#{{ $order->order_number }}</strong></td>
            <td><span class="progga-badge progga-badge-neutral">{{ $order->branch->name ?? 'N/A' }}</span></td>
            <td>
              <div class="progga-order-customer">
                <span class="progga-order-customer-name">{{ $order->customer->name ?? 'Walk-in Customer' }}</span>
                <span class="progga-order-customer-table">
                  @if($normalizedOrderType === 'takeaway' || $normalizedOrderType === 'take away')
                    <i class="bi bi-bag"></i> Takeaway
                  @elseif($normalizedOrderType === 'delivery')
                    <i class="bi bi-truck"></i> Delivery
                    <span style="color:#997300;">— {{ $deliveryPartnerName }}</span>
                  @else
                    <i class="bi bi-layout-wtf"></i> Table T-{{ $order->table->table_number ?? 'N/A' }}
                  @endif
                </span>
              </div>
            </td>
            <td><strong>৳{{ number_format($order->subtotal, 0) }}</strong></td>
            <td>
              @php $discountAmount = max(0, (float)($order->discount_amount ?? 0)); @endphp
              <strong class="text-danger">৳{{ number_format($discountAmount, 0) }}</strong>
            </td>
            <td>
              @php $productDiscountAmount = max(0, (float)($order->product_discount_amount ?? 0)); @endphp
              <strong class="text-danger">৳{{ number_format($productDiscountAmount, 0) }}</strong>
            </td>
            <td>
              @php $vatAmount = max(0, (float)($order->vat_tax ?? 0)); @endphp
              <strong>৳{{ number_format($vatAmount, 0) }}</strong>
            </td>
            <td>
              @php $serviceCharge = max(0, (float)($order->service_charge ?? 0)); @endphp
              <strong>৳{{ number_format($serviceCharge, 0) }}</strong>
            </td>
            <td>
              @php $tipsAmount = max(0, (float)($order->tips_amount ?? ((float)($order->total_paid_amount ?? 0) - (float)($order->grand_total ?? 0)))); @endphp
              <strong class="text-success">৳{{ number_format($tipsAmount, 0) }}</strong>
            </td>
            <td>
              @php $givenMoney = max(0, (float)($order->given_money ?? 0)); @endphp
              <strong>৳{{ number_format($givenMoney, 0) }}</strong>
            </td>
            <td>
              @php $changeAmount = max(0, (float)($order->change_amount ?? 0)); @endphp
              <strong class="text-success">৳{{ number_format($changeAmount, 0) }}</strong>
            </td>
            <td><strong style="color: var(--progga-primary);">৳{{ number_format($order->grand_total, 0) }}</strong></td>
            <td>
              @php $dueAmount = max(0, (float)($order->due ?? 0)); @endphp
              <strong class="{{ $dueAmount > 0 ? 'text-danger' : 'text-success' }}">৳{{ number_format($dueAmount, 0) }}</strong>
            </td>
            <td>
              <span class="progga-badge progga-badge-neutral" style="text-align: left; display: inline-block;">
                <i class="bi {{ $order->payment_type == 'Cash' ? 'bi-cash' : ($order->payment_type == 'Card' ? 'bi-credit-card' : ($order->payment_type == 'Split' ? 'bi-diagram-3' : 'bi-phone')) }}"></i>
                {{ ($order->payment_type ?? '') === 'Card' ? 'Bank / Card' : $order->payment_type }}
                @if($order->payment_type == 'Split')
                    <br>
                    <span style="font-size: 10px; font-weight: normal; color: #555;">
                        @if($order->paid_in_cash > 0) Cash: {{ $order->paid_in_cash }} @endif
                        @if($order->paid_in_card > 0) Bank / Card: {{ $order->paid_in_card }} @endif
                        @if($order->paid_in_mfc > 0) MFS: {{ $order->paid_in_mfc }} @endif
                    </span>
                @endif
              </span>
            </td>
            <td>
              <span class="progga-badge progga-badge-{{ $badgeClass }}"><i class="bi {{ $iconClass }}"></i> {{ $order->status }}</span>
            </td>
            <td><span class="progga-order-time">{{ $order->created_at ? $order->created_at->format('d M Y') : '—' }}</span></td>
            <td><span class="progga-order-time">{{ $order->created_at ? $order->created_at->format('h:i A') : '—' }}</span></td>
            <td><span class="progga-order-time">{{ is_null($order->kitchen_to_payment_minutes) ? '—' : $order->kitchen_to_payment_minutes . ' min' }}</span></td>
            <td>
              <div class="dropdown progga-order-action-menu" style="position:static;">
                <button type="button"
                        class="progga-btn progga-btn-outline progga-btn-sm dropdown-toggle"
                        data-bs-toggle="dropdown"
                        data-bs-boundary="viewport" data-bs-popper-config="{"strategy":"fixed"}"
                        aria-expanded="false">
                  <i class="bi bi-three-dots-vertical"></i> Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="z-index:9999;">
                  <li>
                    <button type="button" class="dropdown-item" onclick="viewOrder({{ $order->id }})">
                      <i class="bi bi-eye"></i><span>View Order</span>
                    </button>
                  </li>
                  @can('order-edit')
                  <li>
                    <a href="{{ route('order.edit', $order->id) }}" class="dropdown-item">
                      <i class="bi bi-pencil-square"></i><span>Edit Order</span>
                    </a>
                  </li>
                  @endcan
                  <li>
                    <a href="{{ route('pos.invoice', $order->id) }}" target="_blank" class="dropdown-item">
                      <i class="bi bi-printer"></i><span>Print Receipt</span>
                    </a>
                  </li>
                  <li>
                    <a href="{{ route('order.details', $order->id) }}" class="dropdown-item">
                      <i class="bi bi-card-list"></i><span>Full Details</span>
                    </a>
                  </li>
                  <li>
                    <a href="{{ route('order.due_settlement', $order->id) }}" class="dropdown-item">
                      <i class="bi bi-cash-coin"></i><span>Due Settlement</span>
                    </a>
                  </li>
                  <li>
                    <button type="button" class="dropdown-item" onclick="viewDeleteHistory({{ $order->id }})">
                      <i class="bi bi-clock-history"></i><span>Delete History</span>
                    </button>
                  </li>
                  @can('order-delete')
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <button type="button" class="dropdown-item text-danger" onclick="deleteOrder({{ $order->id }})">
                      <i class="bi bi-trash"></i><span>Delete Order</span>
                    </button>
                  </li>
                  @endcan
                </ul>
              </div>
            </td>
          </tr>
      @empty
          <tr><td colspan="19" class="text-center py-4">No orders found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($showPagination ?? true)
    @include('admin.inventory.partials.pagination', [
        'paginator' => $orders,
        'label' => 'orders'
    ])
@endif

<style>
.due-list-table-scroll {
    width:100%;
    overflow-x:auto !important;
    overflow-y:visible !important;
}
.due-list-table-scroll .progga-table {
    min-width:1900px;
}
.progga-order-action-menu .dropdown-menu {
    position:fixed !important;
}
</style>
