<div class="progga-pos-screen active progga-pos-table-screen" id="posStep1">
    <div class="pos-s1-top">
      <div class="pos-s1-heading-wrap">
        <h2 class="pos-s1-title">Select a Table</h2>
        <p class="pos-s1-sub">Tap a table for dine-in or use New Order for any order type</p>
      </div>
      @php
          $pendingTakeawayDeliveryCount = collect($activeTakeawayDeliveryOrders ?? [])->filter(function ($order) {
              return strtolower($order->status ?? '') === 'pending';
          })->count();
      @endphp
      <div class="d-flex flex-wrap justify-content-end" style="gap:10px;">
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

    <div class="pos-table-stats">
      <span class="pos-tstat avail"><span class="pos-tstat-dot"></span>{{ $availCount }} Available</span>
      <span class="pos-tstat-sep">·</span>
      <span class="pos-tstat occ"><span class="pos-tstat-dot"></span>{{ $occCount }} Occupied</span>
      <span class="pos-tstat-sep">·</span>
      <span class="pos-tstat res"><span class="pos-tstat-dot"></span>{{ $resCount }} Reserved</span>
    </div>

    <div class="pos-s1-filters">
      <div class="progga-pos-table-filters" id="posTableSection">
        <button class="progga-pos-filter-btn active" data-table-filter="all">All Tables</button>
        <button class="progga-pos-filter-btn" data-table-filter="available">Available</button>
        <button class="progga-pos-filter-btn" data-table-filter="occupied">Occupied</button>
        <button class="progga-pos-filter-btn" data-table-filter="reserved">Reserved</button>
      </div>
    </div>

    <div class="pos-s1-grid" style="margin-top:20px;">
      <div class="progga-pos-table-grid" id="posTableGrid">
        @foreach($tables as $table)
            @php $statusClass = strtolower($table->initial_status); @endphp
            <div class="progga-pos-table-card {{ $statusClass }}" data-table-id="{{ $table->id }}" data-table-num="{{ $table->table_number }}" data-status="{{ $statusClass }}">
              <span class="progga-pos-table-icon">🪑</span>
              <div class="progga-pos-table-num">{{ $table->table_number }}</div>
              <div class="progga-pos-table-zone">{{ $table->zone->name ?? 'Main' }}</div>
              <div class="progga-pos-table-info"><i class="bi bi-people-fill"></i> {{ $table->seating_capacity }} seats</div>
              <span class="progga-badge progga-status-{{ $statusClass }}">{{ $table->initial_status }}</span>
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
