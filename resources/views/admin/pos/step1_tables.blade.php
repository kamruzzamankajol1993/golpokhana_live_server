<div class="progga-pos-screen active progga-pos-table-screen" id="posStep1">
    @php
        $pendingTakeawayDeliveryCount = collect($activeTakeawayDeliveryOrders ?? [])->filter(function ($order) {
            return strtolower($order->status ?? '') === 'pending';
        })->count();
    @endphp

    <div class="pos-s1-toolbar">
      <div class="pos-s1-filters">
        <div class="progga-pos-table-filters" id="posTableSection">
          <button class="progga-pos-filter-btn active" data-table-filter="all">All Tables</button>
          <button class="progga-pos-filter-btn" data-table-filter="available">Available ({{ $availCount }})</button>
          <button class="progga-pos-filter-btn" data-table-filter="occupied">Occupied ({{ $occCount }})</button>
          <button class="progga-pos-filter-btn" data-table-filter="reserved">Reserved ({{ $resCount }})</button>
        </div>
      </div>

      <div class="d-flex justify-content-end pos-s1-actions">
        <button class="pos-s1-takeaway-btn" id="modeTakeawayDeliveryList" type="button">
          <i class="bi bi-card-list"></i> Takeaway / Delivery Orders
        </button>
        {{-- <button class="pos-s1-takeaway-btn pos-s1-complete-pending-btn" id="btnCompletePendingTakeawayDelivery" type="button" data-pending-count="{{ $pendingTakeawayDeliveryCount }}">
          <i class="bi bi-check2-circle"></i> Complete Pending Payment ({{ $pendingTakeawayDeliveryCount }})
        </button> --}}
        <button class="pos-s1-takeaway-btn" id="modeTakeaway" type="button">
          <i class="bi bi-plus-circle-fill"></i> New Order
        </button>
      </div>
    </div>

    <div class="pos-s1-grid" style="margin-top:20px;">
      <div class="progga-pos-table-grid" id="posTableGrid">
        @foreach($tables as $table)
            @php
                $statusClass = strtolower($table->initial_status);
                $isBillPrinted = $statusClass === 'occupied' && !empty($table->bill_printed);
                $isKitchenUnsent = !empty($table->kitchen_unsent);
            @endphp
            <div class="progga-pos-table-card {{ $statusClass }}{{ $isBillPrinted ? ' bill-printed' : '' }}{{ $isKitchenUnsent ? ' kitchen-unsent' : '' }}" data-table-id="{{ $table->id }}" data-table-num="{{ $table->table_number }}" data-reserved-customer-id="{{ $table->reserved_customer_id ?? '' }}" data-reserved-booking-id="{{ $table->reserved_booking_id ?? '' }}" data-status="{{ $statusClass }}" data-bill-printed="{{ $isBillPrinted ? '1' : '0' }}" data-kitchen-unsent="{{ $isKitchenUnsent ? '1' : '0' }}">
              <span class="progga-pos-table-icon" style="display:none;">🪑</span>
              <div class="progga-pos-table-num">{{ $table->table_number }}</div>
              <div class="progga-pos-table-zone">{{ $table->zone->name ?? 'Main' }}</div>
              <div class="progga-pos-table-info"><i class="bi bi-people-fill"></i> {{ $table->seating_capacity }} seats</div>
              <span class="progga-badge {{ $isBillPrinted ? 'progga-status-bill-printed' : 'progga-status-' . $statusClass }}">{{ $isBillPrinted ? 'Bill Printed' : $table->initial_status }}</span>
            </div>
        @endforeach
      </div>

      <div id="posTakeawayDeliveryPanel" style="display:none;">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
          <div>
            <h5 class="m-0 fw-bold" style="color: var(--progga-primary);">Takeaway / Delivery Orders</h5>
            <small class="text-muted">Active takeaway and delivery orders. Tap a card to open the same order offcanvas.</small>
          </div>
          <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="btnBackToTablesFromTdOrders">
            <i class="bi bi-arrow-left"></i> Back to Tables
          </button>
        </div>

        <div class="progga-pos-table-grid" id="posTakeawayDeliveryGrid">
          @forelse(($activeTakeawayDeliveryOrders ?? []) as $runningOrder)
              @php
                  $runningType = strtolower(str_replace([' ', '-'], '_', $runningOrder->order_type ?? 'takeaway'));
                  $runningTypeClass = $runningType === 'delivery' ? 'delivery' : 'takeaway';
                  $runningTypeLabel = $runningType === 'delivery' ? 'Delivery' : 'Takeaway';
                  $runningCustomer = $runningOrder->customer->name ?? 'Walk-in Customer';
                  $runningItemCount = $runningOrder->orderDetails->sum('quantity');
                  $runningOrderDate = optional($runningOrder->created_at)->format('d M Y, h:i A');
                  $runningDeliveryPartnerName = $runningTypeClass === 'delivery'
                      ? ($runningOrder->delivery_partner_display_name ?: 'Not selected')
                      : null;
                  $statusKey = strtolower(str_replace([' ', '_'], '-', $runningOrder->status ?? 'pending'));
              @endphp
              <div class="progga-pos-running-order-card {{ $runningTypeClass }}"
                   data-order-id="{{ $runningOrder->id }}"
                   data-order-type="{{ $runningTypeClass }}"
                   data-order-status="{{ strtolower($runningOrder->status ?? '') }}">
                <span class="progga-pos-table-icon">{{ $runningTypeClass === 'delivery' ? '🚚' : '🛍️' }}</span>
                <div class="progga-pos-table-num">#{{ $runningOrder->order_number }}</div>
                <div class="progga-pos-table-zone"><i class="bi bi-person-check"></i> {{ $runningCustomer }}</div>
                <div class="progga-pos-table-info"><i class="bi bi-calendar-event"></i> {{ $runningOrderDate }}</div>
                <div class="progga-pos-table-info"><i class="bi bi-bag-check-fill"></i> {{ $runningTypeLabel }} · {{ (int) $runningItemCount }} items</div>
                @if($runningTypeClass === 'delivery')
                  <div class="progga-pos-table-info fw-bold"><i class="bi bi-truck"></i> Partner: {{ $runningDeliveryPartnerName }}</div>
                @endif
                <span class="progga-badge progga-status-occupied js-td-order-status-badge">{{ str_replace('_', ' ', $runningOrder->status) }}</span>
              </div>
          @empty
              <div class="text-center text-muted py-5 w-100" style="background:#fff; border:1px dashed var(--progga-border); border-radius:14px; grid-column:1/-1;">
                <i class="bi bi-inbox" style="font-size:28px;"></i>
                <div class="fw-bold mt-2">No active Takeaway / Delivery orders</div>
              </div>
          @endforelse
        </div>
      </div>
    </div>

    <style>
      /* One desktop toolbar: table tabs far left, order actions far right. */
      .pos-s1-toolbar {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: nowrap !important;
        gap: 14px !important;
      }
      .pos-s1-filters {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        margin: 0 !important;
      }
      .pos-s1-toolbar .progga-pos-table-filters {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        gap: 8px !important;
        margin: 0 !important;
      }
      .pos-s1-actions {
        width: auto !important;
        flex: 0 0 auto !important;
        margin: 0 0 0 auto !important;
        justify-content: flex-end !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
      }
      /* Keep the original large POS action-button sizing from progga-style.css. */

      /* Printed guest bill: still Occupied operationally, but use a clear yellow state. */
      .progga-pos-table-card.bill-printed {
        background: #ffd62e !important;
        border-color: #ffd62e !important;
        box-shadow: 0 8px 22px rgba(255, 214, 46, .22) !important;
      }
      .progga-pos-table-card.bill-printed:hover {
        background: #ffd62e !important;
        border-color: #ffd62e !important;
        box-shadow: 0 12px 28px rgba(255, 214, 46, .28) !important;
      }
      .progga-pos-table-card.bill-printed .progga-pos-table-num {
        color: #7a5200 !important;
      }
      .progga-pos-table-card.bill-printed .progga-pos-table-zone,
      .progga-pos-table-card.bill-printed .progga-pos-table-info,
      .progga-pos-table-card.bill-printed .progga-pos-table-info i {
        color: #7c6a2d !important;
      }
      .progga-status-bill-printed {
        background: #ffd62e !important;
        border-color: #ffd62e !important;
        color: #3b2a00 !important;
      }

      /* QR/Waiter order received but not sent to kitchen yet. */
      .progga-pos-table-card.kitchen-unsent,
      .progga-pos-table-card.kitchen-unsent:hover {
        background: #b42318 !important;
        border-color: #991b1b !important;
        box-shadow: 0 10px 26px rgba(153, 27, 27, .30) !important;
      }
      .progga-pos-table-card.kitchen-unsent .progga-pos-table-num,
      .progga-pos-table-card.kitchen-unsent .progga-pos-table-zone,
      .progga-pos-table-card.kitchen-unsent .progga-pos-table-info,
      .progga-pos-table-card.kitchen-unsent .progga-pos-table-info i {
        color: #fff !important;
      }

      @media (max-width: 767.98px) {
        .pos-s1-toolbar {
          align-items: flex-start !important;
          flex-wrap: wrap !important;
          gap: 8px !important;
        }
        .pos-s1-filters {
          width: 100% !important;
          overflow-x: auto !important;
          padding-bottom: 2px !important;
        }
        .pos-s1-toolbar .progga-pos-table-filters {
          width: max-content !important;
          min-width: 100% !important;
        }
        .pos-s1-actions {
          margin-left: auto !important;
        }
      }

      @media (max-width: 575.98px) {
        .pos-s1-actions {
          flex-wrap: nowrap !important;
        }
      }

      .progga-pos-running-order-card {
        position: relative;
        background: #fff;
        border: 1.5px solid var(--progga-border-light, #edf1ee);
        border-radius: 16px;
        padding: 16px;
        min-height: 168px;
        cursor: pointer;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
      }
      .progga-pos-running-order-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(15, 23, 42, .11);
        border-color: var(--progga-primary, #21352a);
      }
      .progga-pos-running-order-card.takeaway {
        border-left: 5px solid #dc3545;
      }
      .progga-pos-running-order-card.delivery {
        border-left: 5px solid #ffc107;
      }
      .progga-pos-running-order-card.completed {
        border-left-color: #198754;
        opacity: .75;
      }
      .pos-s1-complete-pending-btn {
        background: #198754 !important;
        color: #fff !important;
        border-color: #198754 !important;
      }
      .progga-pos-running-order-card .progga-pos-table-zone,
      .progga-pos-running-order-card .progga-pos-table-info {
        white-space: normal;
      }
    </style>
</div>
