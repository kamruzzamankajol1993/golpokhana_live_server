<div class="progga-table-wrapper" style="border:none;border-radius:0;">
  <table class="progga-table" id="ordersTable">
    <thead>
      <tr>
        <th>Order #</th>
        <th>Customer</th>
        <th>Subtotal</th>
        <th>Discount Amount</th>
        <th>Tips</th>
        <th>Given</th>
        <th>Change</th>
        <th>Grand Total</th>
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
              $iconClass = 'clock-fill';
              if($order->status == 'Completed') { $badgeClass = 'primary'; $iconClass = 'check2-all'; }
              elseif($order->status == 'Pending') { $badgeClass = 'warning'; $iconClass = 'clock-fill'; }
              elseif($order->status == 'Cooking') { $badgeClass = 'info'; $iconClass = 'fire'; }
              elseif($order->status == 'Ready') { $badgeClass = 'success'; $iconClass = 'check-circle-fill'; }
              elseif($order->status == 'Cancelled') { $badgeClass = 'danger'; $iconClass = 'x-circle-fill'; }
          @endphp
          <tr class="status-{{ $statusClass }}">
            <td><strong>#{{ $order->order_number }}</strong></td>
            <td>
              <div class="progga-order-customer">
                <span class="progga-order-customer-name">{{ $order->customer->name ?? 'Walk-in Customer' }}</span>
                <span class="progga-order-customer-table">
                  @if($order->order_type == 'Takeaway')
                    <i class="bi bi-bag"></i> Takeaway
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
              <span class="progga-badge progga-badge-neutral" style="text-align: left; display: inline-block;">
                <i class="bi {{ $order->payment_type == 'Cash' ? 'bi-cash' : ($order->payment_type == 'Card' ? 'bi-credit-card' : ($order->payment_type == 'Split' ? 'bi-diagram-3' : 'bi-phone')) }}"></i>
                {{ $order->payment_type }}
                @if($order->payment_type == 'Split')
                    <br>
                    <span style="font-size: 10px; font-weight: normal; color: #555;">
                        @if($order->paid_in_cash > 0) Cash: {{ $order->paid_in_cash }} @endif
                        @if($order->paid_in_card > 0) Card: {{ $order->paid_in_card }} @endif
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
              <div class="progga-table-actions d-flex align-items-center gap-1">
                <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                        title="View Order"
                        onclick="viewOrder({{ $order->id }})">
                    <i class="bi bi-eye"></i>
                </button>
@can('order-edit')
                <a href="{{ route('order.edit', $order->id) }}"
                   class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                   title="Edit Order"
                   style="color: #fd7e14; border-color: #fd7e14;">
                    <i class="bi bi-pencil-square"></i>
                </a>
 @endcan
                <a href="{{ route('pos.invoice', $order->id) }}"
                   target="_blank"
                   class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                   title="Print Receipt">
                    <i class="bi bi-printer"></i>
                </a>

                <a href="{{ route('order.details', $order->id) }}"
                   class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                   title="Show Full Details"
                   style="color: var(--progga-primary); border-color: var(--progga-primary);">
                    <i class="bi bi-card-list"></i>
                </a>

                <button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                        title="Delete History"
                        onclick="viewDeleteHistory({{ $order->id }})"
                        style="color: #6f42c1; border-color: #6f42c1;">
                    <i class="bi bi-clock-history"></i>
                </button>

                @can('order-delete')
                    <button type="button"
                            class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm"
                            title="Delete Order"
                            onclick="deleteOrder({{ $order->id }})"
                            style="color: var(--progga-danger); border-color: var(--progga-danger);">
                        <i class="bi bi-trash"></i>
                    </button>
                @endcan
              </div>
            </td>
          </tr>
      @empty
          <tr><td colspan="14" class="text-center py-4">No orders found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@php
    $currentPage = $orders->currentPage();
    $lastPage = $orders->lastPage();
    $startPage = max(1, $currentPage - 2);
    $endPage = min($lastPage, $currentPage + 2);
@endphp

<div class="progga-card-footer progga-order-pagination-footer">
  <span class="progga-page-info">
    Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
  </span>

  @if($lastPage > 1)
    <nav class="progga-pagination-wrap" aria-label="Order pagination">
      <div class="progga-pagination">
        <a href="{{ $orders->url(1) }}"
           class="progga-page-btn {{ $orders->onFirstPage() ? 'disabled' : '' }}"
           aria-label="First page">
          <i class="bi bi-chevron-double-left"></i> First
        </a>

        <a href="{{ $orders->previousPageUrl() ?: '#' }}"
           class="progga-page-btn {{ $orders->onFirstPage() ? 'disabled' : '' }}"
           aria-label="Previous page">
          <i class="bi bi-chevron-left"></i> Prev
        </a>

        @if($startPage > 1)
          <a href="{{ $orders->url(1) }}" class="progga-page-num">1</a>
          @if($startPage > 2)
            <span class="progga-page-ellipsis">...</span>
          @endif
        @endif

        @for($page = $startPage; $page <= $endPage; $page++)
          @if($page == $currentPage)
            <span class="progga-page-num active">{{ $page }}</span>
          @else
            <a href="{{ $orders->url($page) }}" class="progga-page-num">{{ $page }}</a>
          @endif
        @endfor

        @if($endPage < $lastPage)
          @if($endPage < $lastPage - 1)
            <span class="progga-page-ellipsis">...</span>
          @endif
          <a href="{{ $orders->url($lastPage) }}" class="progga-page-num">{{ $lastPage }}</a>
        @endif

        <a href="{{ $orders->nextPageUrl() ?: '#' }}"
           class="progga-page-btn {{ !$orders->hasMorePages() ? 'disabled' : '' }}"
           aria-label="Next page">
          Next <i class="bi bi-chevron-right"></i>
        </a>

        <a href="{{ $orders->url($lastPage) }}"
           class="progga-page-btn {{ $currentPage == $lastPage ? 'disabled' : '' }}"
           aria-label="Last page">
          Last <i class="bi bi-chevron-double-right"></i>
        </a>
      </div>
    </nav>
  @endif
</div>
