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
              <div class="progga-pos-table-num">{{ $table->table_number }}</div>
              <div class="progga-pos-table-seats"><i class="bi bi-people" aria-hidden="true"></i> Total Seats: {{ (int) ($table->seating_capacity ?? 0) }}</div>
              <span class="progga-badge {{ $isBillPrinted ? 'progga-status-bill-printed' : 'progga-status-' . $statusClass }}">{{ $isBillPrinted ? 'Bill Printed' : $table->initial_status }}</span>
            </div>
        @endforeach
      </div>

      <div id="posTakeawayDeliveryPanel" style="display:none;">
        <div class="pos-td-panel-heading mb-3">
          <div class="pos-td-heading-text">
            <h5 class="m-0 fw-bold" style="color: var(--progga-primary);">Takeaway / Delivery Orders</h5>
            <small class="text-muted">Active takeaway and delivery orders. Tap a card to open the same order offcanvas.</small>
          </div>
          <div class="pos-td-panel-actions">
            <button class="pos-s1-takeaway-btn" id="modeTakeawayOrdersView" type="button">
              <i class="bi bi-plus-circle-fill"></i> New Order
            </button>
            <button type="button" class="progga-btn progga-btn-outline progga-btn-sm" id="btnBackToTablesFromTdOrders">
              <i class="bi bi-arrow-left"></i> Back to Tables
            </button>
          </div>
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
      /* /pos table cards: 11 per row on desktop, fewer on smaller screens.
         Keep takeaway/delivery cards and all existing table actions unchanged. */
      #posTableGrid.progga-pos-table-grid {
        /* Must NOT be !important: POS uses jQuery hide()/fadeIn() here. */
        display: grid;
        width: 100% !important;
        grid-template-columns: repeat(11, minmax(0, 1fr)) !important;
        justify-content: stretch !important;
        gap: 10px !important;
      }
      #posTableGrid .progga-pos-table-card {
        box-sizing: border-box !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        height: 106px !important;
        min-height: 106px !important;
        padding: 12px 6px !important;
        border-radius: 12px !important;
        /* Allow JS status-filter tabs to hide and show each card. */
        display: flex;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        text-align: center !important;
      }
      #posTableGrid .progga-pos-table-seats {
        display: block;
        max-width: 100%;
        margin: 0 !important;
        color: #ffffff !important; /* Occupied, Reserved and other colored cards */
        font-size: 10.5px;
        font-weight: 650;
        line-height: 1.3;
        white-space: nowrap;
      }
      /* Seats remain dark on light Available cards and yellow Bill Printed cards. */
      #posTableGrid .progga-pos-table-card.available:not(.kitchen-unsent) .progga-pos-table-seats,
      #posTableGrid .progga-pos-table-card.bill-printed:not(.kitchen-unsent) .progga-pos-table-seats {
        color: #111111 !important;
      }
      #posTableGrid .progga-pos-table-seats i {
        font-size: 11px;
        margin-right: 2px;
      }
      #posTableGrid .progga-pos-table-num {
        margin: 0 !important;
        font-size: 16px !important;
        font-weight: 800 !important;
        line-height: 1.2 !important;
        overflow-wrap: anywhere;
      }
      #posTableGrid .progga-pos-table-card .progga-badge {
        margin: 0 !important;
        max-width: 100%;
        padding: 4px 7px !important;
        font-size: 10.5px !important;
        line-height: 1.2 !important;
        white-space: nowrap;
      }
      /* The 11-column layout applies to wide desktop POS screens only. */
      @media (max-width: 1199.98px) {
        #posTableGrid.progga-pos-table-grid {
          grid-template-columns: repeat(9, minmax(0, 1fr)) !important;
        }
      }
      @media (max-width: 991.98px) {
        #posTableGrid.progga-pos-table-grid {
          grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
        }
      }
      @media (max-width: 767.98px) {
        #posTableGrid.progga-pos-table-grid {
          grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
          gap: 9px !important;
        }
      }
      @media (max-width: 575.98px) {
        #posTableGrid.progga-pos-table-grid {
          grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
          gap: 8px !important;
        }
      }
      @media (max-width: 420px) {
        #posTableGrid.progga-pos-table-grid {
          grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
      }
      @media (max-width: 320px) {
        #posTableGrid.progga-pos-table-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
      }

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
        /* JS hides this element in Takeaway/Delivery view. Never force its display. */
        display: flex;
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

      /* In order-list mode, show only the panel's actions beside its heading.
         This avoids buttons touching the POS header or duplicating the list button. */
      #posStep1.pos-s1-orders-view .pos-s1-toolbar {
        display: none !important;
      }
      #posStep1.pos-s1-orders-view .pos-s1-grid {
        margin-top: 24px !important;
      }
      .pos-td-panel-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px 22px;
      }
      .pos-td-heading-text {
        flex: 1 1 300px;
        min-width: 0;
      }
      .pos-td-panel-actions {
        display: flex;
        flex: 0 0 auto;
        justify-content: flex-end;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
      }
      .pos-td-panel-actions > button {
        margin: 0 !important;
        white-space: nowrap;
      }
      @media (max-width: 575.98px) {
        .pos-td-panel-actions {
          width: 100%;
          justify-content: flex-start;
          gap: 10px;
        }
      }

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
      .progga-pos-table-card.kitchen-unsent .progga-pos-table-num {
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
