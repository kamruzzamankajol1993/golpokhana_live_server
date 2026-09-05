@php
    $normalizedOrderType = strtolower(str_replace([' ', '-'], '_', $order->order_type ?? 'Dine-In'));
    $deliveryPartnerName = $order->delivery_partner_display_name;
    $currentDeliveryPartnerId = $order->resolved_delivery_partner_id;
    if(in_array($normalizedOrderType, ['dine_in', 'dinein'])) {
        $jsOrderType = 'dine_in';
        $orderHeaderLabel = 'Occupied Table';
        $orderDisplayName = $order->table->table_number ?? 'Table';
        $orderDisplayMeta = ($order->table->zone->name ?? 'Main') . ' · ' . ($order->table->seating_capacity ?? 0) . ' seats';
        $payDisplayLabel = $order->table->table_number ?? 'Table';
    } elseif($normalizedOrderType === 'delivery') {
        $jsOrderType = 'delivery';
        $orderHeaderLabel = 'Active Delivery Order';
        $orderDisplayName = 'Delivery #' . $order->order_number;
        $orderDisplayMeta = ($order->customer->name ?? 'Walk-in Customer') . ' · Delivery';
        $payDisplayLabel = 'Delivery #' . $order->order_number;
    } else {
        $jsOrderType = 'takeaway';
        $orderHeaderLabel = 'Active Takeaway Order';
        $orderDisplayName = 'Takeaway #' . $order->order_number;
        $orderDisplayMeta = ($order->customer->name ?? 'Walk-in Customer') . ' · Takeaway';
        $payDisplayLabel = 'Takeaway #' . $order->order_number;
    }
@endphp

<div class="progga-oc-header">
    <div>
        @if($jsOrderType === 'dine_in')
            <div class="progga-oc-chips" style="flex-direction: row; align-items: center; flex-wrap: nowrap; white-space: nowrap; margin-bottom: 6px;">
                <span class="progga-oc-chip"><i class="bi bi-receipt"></i> #{{ $order->order_number }}</span>
                <span class="progga-oc-chip"><i class="bi bi-bag-check"></i> Dine-In</span>
            </div>
        @else
            <div class="progga-oc-chips" style="flex-direction: row; align-items: center; flex-wrap: nowrap; white-space: nowrap; margin-bottom: 6px;">
                <span class="progga-oc-chip"><i class="bi bi-bag-check"></i> {{ $jsOrderType === 'delivery' ? 'Delivery' : 'Takeaway' }}</span>
                @if(!empty($deliveryPartnerName))
                    <span class="progga-oc-chip"><i class="bi bi-truck"></i> {{ $deliveryPartnerName }}</span>
                @endif
            </div>
        @endif

        @if($jsOrderType === 'dine_in')
            <div style="display:flex; align-items:baseline; gap:8px; white-space:nowrap; margin-bottom:14px;">
                <div class="progga-oc-table-num" id="ocTableNum" style="font-size:24px; margin-bottom:0;">{{ $orderDisplayName }}</div>
                <div class="progga-oc-table-meta" id="ocTableMeta" style="margin-bottom:0;">{{ $orderDisplayMeta }}</div>
            </div>
        @else
            <div class="progga-oc-table-num" id="ocTableNum" style="font-size:24px; margin-bottom:14px;">{{ $orderDisplayName }}</div>
            <div class="progga-oc-table-meta" id="ocTableMeta" style="display:none;">{{ $orderDisplayMeta }}</div>
        @endif
        <div class="progga-oc-chips" id="ocChips" style="flex-direction: row; align-items: center; flex-wrap: nowrap; white-space: nowrap;">
            @if(!empty($order->waiter_id) && !empty($order->waiter))
                <span class="progga-oc-chip"><i class="bi bi-person"></i> <span id="ocWaiterName">{{ $order->waiter->name }}</span></span>
            @endif

            <span class="progga-oc-chip"><i class="bi bi-person-check"></i> <span id="ocCustomerName">{{ $order->customer->name ?? 'Walk-in' }}</span></span>
        </div>
    </div>
    <div class="progga-oc-header-actions">
        <button type="button"
                class="btn btn-sm btn-light fw-bold js-pos-print-preview"
                data-url="{{ route('kitchen.print_order_kot', ['id' => $order->id]) }}"
                data-title="KOT — Order #{{ $order->order_number }}"
                title="Print merged KOT"
                style="padding-left:10px;padding-right:10px;">
            <i class="bi bi-printer me-1"></i> KOT
        </button>
        <button type="button"
                class="btn btn-sm btn-light fw-bold progga-oc-meta-trigger"
                data-bs-toggle="modal"
                data-bs-target="#activeOrderMetaModal"
                title="Update customer, waiter or delivery partner">
            <i class="bi bi-pencil-square me-1"></i> Update
        </button>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
</div>

<div class="progga-oc-body offcanvas-body" id="ocBody">

    <div class="progga-oc-section-label" style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #888; margin-bottom: 12px; letter-spacing: 0.5px;">Current Order Items</div>

    @php
        // Keep each KOT visually separate in the POS offcanvas.
        // Identical foods from different KOTs are merged only by the dedicated
        // merged-KOT print action, never in this operational order view.
        $displayItemsByKot = $order->orderDetails->groupBy('order_kot_id');
    @endphp

    @foreach($order->kots as $kot)
        @php
            $displayItems = $displayItemsByKot->get($kot->id, collect());
        @endphp

        @if($displayItems->isEmpty())
            @continue
        @endif

        <div class="progga-oc-kot">
            <div class="progga-oc-kot-head">
                <div>
                    <span class="progga-oc-kot-label">{{ $kot->kot_number }}</span>
                    <span class="progga-oc-kot-time"><i class="bi bi-clock me-1"></i>{{ $kot->created_at->format('h:i A') }}</span>
                </div>
                @if($kot->kitchen_status == 'Pending')
                    <span class="badge bg-warning text-dark" style="font-size: 10px;">Pending</span>
                @elseif($kot->kitchen_status == 'Hold')
                    <span class="badge bg-secondary text-white" style="font-size: 10px;">Hold</span>
                @elseif($kot->kitchen_status == 'Cooking')
                    <span class="badge bg-info text-white" style="font-size: 10px;">Cooking</span>
                @else
                    <span class="badge bg-success" style="font-size: 10px;">Ready</span>
                @endif
            </div>

            @foreach($displayItems as $item)
                @php
                    $addons = json_decode($item->addons, true) ?? [];
                    $isComplimentaryItem = (isset($item->is_complimentary) && $item->is_complimentary)
                        || ((float) $item->price <= 0 && (float) $item->subtotal <= 0);
                    // Offcanvas rows are KOT-specific, so actions must target only
                    // this exact order-detail row rather than matching rows in other KOTs.
                    $mergedDetailIds = (string) $item->id;
                @endphp

                <div class="progga-oc-item {{ $item->is_unavailable ? 'opacity-50' : '' }}">
                    <span class="progga-oc-item-name">
                        @if($item->is_unavailable)
                            <span class="badge bg-danger" style="font-size: 9px; margin-right: 5px;">Unavailable</span>
                            <del class="text-muted">{{ $item->product_name }}</del>
                        @else
                            {{ $item->product_name }}
                        @endif

                        @if($isComplimentaryItem)
                            <span class="progga-complimentary-food-label">Complimentary</span>
                        @endif

                        @if(count($addons) > 0)
                            <div style="font-size: 10px; color: #777; font-weight: normal; margin-top: 2px;">
                                + @foreach($addons as $addon) {{ $addon['name'] }}{{ !$loop->last ? ', ' : '' }} @endforeach
                            </div>
                        @endif
                        @php
                            $offcanvasComplimentaryNote = trim((string) ($item->complimentary_note ?? ''));
                            $offcanvasFoodNote = trim((string) ($item->food_note ?? ''));
                        @endphp
                        @if($isComplimentaryItem && $offcanvasComplimentaryNote !== '')
                            <div style="font-size: 10px; color: #d33; font-style: italic; margin-top: 2px;">
                                Complimentary Note: {{ $offcanvasComplimentaryNote }}
                            </div>
                        @endif
                        @if($offcanvasFoodNote !== '' && (!$isComplimentaryItem || $offcanvasFoodNote !== $offcanvasComplimentaryNote))
                            <div style="font-size: 10px; color: #d33; font-style: italic; margin-top: 2px;">* {{ $offcanvasFoodNote }}</div>
                        @endif
                    </span>
                    <span class="progga-oc-item-qty">&times;{{ $item->quantity }}</span>

                    <span class="progga-oc-item-price">
                        @if($item->is_unavailable)
                            <del class="text-danger">৳{{ round($item->subtotal) }}</del>
                        @else
                            ৳{{ round($item->subtotal) }}
                        @endif
                    </span>

                    @if(!$item->is_unavailable)
                        <button type="button"
                                class="btn btn-sm {{ $isComplimentaryItem ? 'btn-outline-secondary' : 'btn-outline-success' }} progga-oc-item-complimentary js-toggle-order-item-complimentary"
                                title="{{ $isComplimentaryItem ? 'Return to normal food' : 'Convert to complimentary' }}"
                                data-order-id="{{ $order->id }}"
                                data-order-detail-id="{{ $item->id }}"
                                data-order-detail-ids="{{ $mergedDetailIds }}"
                                data-table-id="{{ $order->table_id }}"
                                data-order-type="{{ $jsOrderType }}"
                                data-is-complimentary="{{ $isComplimentaryItem ? 1 : 0 }}"
                                data-product-name="{{ $item->product_name }}">
                            <i class="bi {{ $isComplimentaryItem ? 'bi-arrow-counterclockwise' : 'bi-gift' }}"></i>
                        </button>
                    @endif

                    @if(!$item->is_unavailable && !auth()->user()->hasRole('waiter'))
                        <button type="button"
                                class="btn btn-sm btn-outline-danger progga-oc-item-delete"
                                title="Delete item quantity"
                                onclick="openOrderItemDeleteModal({{ $order->id }}, '{{ $mergedDetailIds }}', '{{ addslashes($item->product_name) }}', {{ (int) $item->quantity }})">
                            <i class="bi bi-trash"></i>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>

<div class="progga-oc-footer">
    <div id="ocTotals" style="margin-bottom: 15px;">
        <div class="progga-oc-total-row">
            <span>Subtotal</span><span>৳{{ number_format($order->subtotal, 0) }}</span>
        </div>

        <div class="progga-oc-total-row">
            <span>Service Charge ({{ $taxSettingServiceCharge }}%)</span><span>৳{{ number_format($order->service_charge, 0) }}</span>
        </div>

        <div class="progga-oc-total-row">
            <span>{{ $taxSettingTaxLabel }} ({{ $taxSettingVatRate }}%)</span><span>৳{{ number_format($order->vat_tax, 0) }}</span>
        </div>

        @if(($order->product_discount_amount ?? 0) > 0)
        <div class="progga-oc-total-row" style="color: #d33;">
            <span>Product Discount</span>
            <span>−৳{{ number_format($order->product_discount_amount, 0) }}</span>
        </div>
        @endif

        @if($order->discount_amount > 0)
        <div class="progga-oc-total-row" style="color: #d33;">
            <span>Honored ({{ ucfirst($order->discount_type) }})</span>
            <span>−৳{{ number_format($order->discount_amount, 0) }}</span>
        </div>
        @endif

        <div class="progga-oc-total-row grand">
            <span>TOTAL</span><span>৳{{ number_format($order->grand_total, 0) }}</span>
        </div>
    </div>

    @if($jsOrderType === 'dine_in')
        <div class="progga-table-swap-box">
            <button type="button" class="progga-table-swap-toggle" id="btnToggleTableSwap">
                <span><i class="bi bi-arrow-left-right"></i> Table Swap</span>
                <small>Current: <strong id="tableSwapCurrentLabel">{{ $orderDisplayName }}</strong></small>
            </button>

            <form id="tableSwapForm" class="progga-table-swap-panel" style="display:none;">
                <input type="hidden" name="order_id" value="{{ $order->id }}">
                <input type="hidden" name="current_table_id" value="{{ $order->table_id }}">

                <label class="progga-table-swap-label" for="tableSwapNewTable">Move this order to</label>
                <div class="d-flex gap-2">
                    <select id="tableSwapNewTable" name="new_table_id" class="form-select form-select-sm" {{ ($availableSwapTables ?? collect())->count() < 1 ? 'disabled' : '' }} required>
                        @if(($availableSwapTables ?? collect())->count() > 0)
                            <option value="">— Select Available Table —</option>
                            @foreach($availableSwapTables as $swapTable)
                                <option value="{{ $swapTable->id }}"
                                        data-table-number="{{ $swapTable->table_number }}"
                                        data-table-meta="{{ ($swapTable->zone->name ?? 'Main') . ' · ' . ($swapTable->seating_capacity ?? 0) . ' seats' }}">
                                    {{ $swapTable->table_number }} — {{ $swapTable->zone->name ?? 'Main' }}
                                </option>
                            @endforeach
                        @else
                            <option value="">No available table found</option>
                        @endif
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold" id="btnConfirmTableSwap" {{ ($availableSwapTables ?? collect())->count() < 1 ? 'disabled' : '' }}>
                        Swap
                    </button>
                </div>
                <div class="progga-table-swap-help">Old table will become Available and selected table will become Occupied.</div>
            </form>
        </div>
    @endif

    <div class="progga-oc-actions" style="grid-template-columns: {{ $order->status == 'Waiter_Hold' ? '1fr' : 'minmax(0,.72fr) minmax(0,1.16fr) minmax(0,1fr) minmax(0,.82fr)' }}; gap:8px;">
        @if($order->status == 'Waiter_Hold')
            <button class="progga-btn progga-btn-secondary" disabled style="flex: 1; opacity: 0.8; cursor: not-allowed; font-weight:bold; font-size: 13px; padding: 10px 5px; white-space: normal; line-height: 1.2;">
                <i class="bi bi-check2-all"></i> Sent to Desk (Pending)
            </button>
        @else
            <button class="progga-btn progga-btn-outline" id="btnContinueOrdering" style="flex: 1;"
                    data-order-id="{{ $order->id }}"
                    data-table-id="{{ $order->table_id }}"
                    data-order-type="{{ $jsOrderType }}"
                    data-order-label="{{ $orderDisplayName }}"
                    data-delivery-partner="{{ $currentDeliveryPartnerId ?? '' }}"
                    data-delivery-partner-name="{{ $deliveryPartnerName ?? '' }}"
                    data-waiter-id="{{ $order->waiter_id }}"
                    data-waiter-name="{{ $order->waiter->name ?? '' }}"
                    data-customer-id="{{ $order->customer_id }}"
                    data-customer-name="{{ $order->customer->name ?? '' }}">
                <i class="bi bi-plus-circle"></i> Food
            </button>

            <button class="progga-btn progga-btn-secondary" id="btnAddComplimentary" style="flex: 1; font-size: 13px; padding-left: 5px; padding-right: 5px; white-space: nowrap;"
                    data-order-id="{{ $order->id }}"
                    data-table-id="{{ $order->table_id }}"
                    data-order-type="{{ $jsOrderType }}"
                    data-order-label="{{ $orderDisplayName }}"
                    data-delivery-partner="{{ $currentDeliveryPartnerId ?? '' }}"
                    data-delivery-partner-name="{{ $deliveryPartnerName ?? '' }}"
                    data-waiter-id="{{ $order->waiter_id }}"
                    data-waiter-name="{{ $order->waiter->name ?? '' }}"
                    data-customer-id="{{ $order->customer_id }}"
                    data-customer-name="{{ $order->customer->name ?? '' }}">
                <i class="bi bi-gift"></i> Complimentary
            </button>

            <button type="button" class="progga-btn progga-btn-outline js-oc-preinvoice"
                    style="min-width:0; font-size:12px; padding-left:5px; padding-right:5px; white-space:nowrap;"
                    data-order-id="{{ $order->id }}">
                <i class="bi bi-receipt"></i> Pre-Invoice
            </button>

            @if(auth()->user()->hasRole('waiter'))
                <button class="progga-btn progga-btn-secondary" disabled style="flex: 1; opacity: 0.6;">
                    <i class="bi bi-lock"></i> Payment at Desk
                </button>
            @else
                @php
                    $payItems = [];
                    foreach($order->kots as $kot) {
                        foreach($kot->orderDetails as $item) {
                            // POS workflow note.
                            if(!$item->is_unavailable) {
                                $payItems[] = [
                                    'id' => $item->id,
                                    'name' => $item->product_name,
                                    'qty' => $item->quantity,
                                    'total' => $item->subtotal,
                                    'product_discount_type' => $item->product_discount_type ?? 'fixed',
                                    'product_discount_value' => $item->product_discount_value ?? 0,
                                    'product_discount_amount' => $item->product_discount_amount ?? 0,
                                ];
                            }
                        }
                    }
                @endphp

                @if(!empty($kitchenBusy))
                    <button class="progga-btn progga-btn-secondary" id="ocPayBtn" disabled style="flex: 1; opacity: 0.75; cursor: not-allowed;">
                        <i class="bi bi-hourglass-split"></i> Kitchen Busy
                    </button>
                @else
                    <button class="progga-btn progga-btn-primary" id="ocPayBtn" style="flex: 1;"
                    onclick='openPaymentModal({
                        order_id: "{{ $order->id }}",
                        order_type: "{{ $jsOrderType }}",
                        table_no: "{{ $payDisplayLabel }}",
                        subtotal: {{ $order->subtotal ?? 0 }},
                        table_booking_id: {{ $order->table_booking_id ?? 'null' }},
                        booking_advance: {{ $order->tableBooking->advance_amount ?? $order->booking_advance ?? 0 }},
                        items: @json($payItems),
                        pre_invoice_snapshot: @json($order->pre_invoice_snapshot ?? null),
                        is_complimentary_order: {{ !empty($order->is_complimentary_order) ? 1 : 0 }}
                    })'>
                        <i class="bi bi-credit-card"></i> Payment
                    </button>
                @endif
            @endif
        @endif
    </div>
</div>



<div class="modal fade progga-modal active-order-meta-modal" id="activeOrderMetaModal" tabindex="-1" aria-labelledby="activeOrderMetaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: visible;">
            <div class="modal-header bg-dark text-white active-order-meta-modal-header">
                <h5 class="modal-title fw-bold" id="activeOrderMetaModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Update Order Info
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">

    <div class="progga-oc-meta-editor">
        @if(!$order->customer_id)
            <form id="ocCustomerUpdateForm"
                  class="progga-oc-meta-form"
                  data-order-id="{{ $order->id }}"
                  data-table-id="{{ $order->table_id }}"
                  data-order-type="{{ $jsOrderType }}">
                <div class="progga-oc-meta-title-row">
                    <div class="progga-oc-meta-title"><i class="bi bi-person-plus"></i> Add Customer</div>
                    <span class="badge bg-light text-dark border">Walk-in</span>
                </div>
                <div class="progga-oc-meta-help">Assign an existing customer or create a new customer before payment.</div>

                <div class="progga-oc-choice-row">
                    <label class="progga-oc-choice active-choice">
                        <input type="radio" name="oc_customer_mode" value="existing" checked>
                        Existing Customer
                    </label>
                    <label class="progga-oc-choice">
                        <input type="radio" name="oc_customer_mode" value="new">
                        New Customer
                    </label>
                </div>

                <div class="oc-existing-customer-wrap">
                    <select name="customer_id" class="form-select form-select-sm" data-placeholder="— Select Customer —">
                        <option value="">— Select Customer —</option>
                        @foreach(($customers ?? collect()) as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->phone }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="oc-new-customer-wrap" style="display:none;">
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Customer name">
                        </div>
                        <div class="col-6">
                            <input type="tel" name="customer_phone" class="form-control form-control-sm" placeholder="Phone number">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-sm btn-primary fw-bold mt-2 js-oc-customer-save">
                    <i class="bi bi-check2-circle me-1"></i> Save Customer
                </button>
            </form>
        @else
            <form id="ocCustomerWalkInForm"
                  class="progga-oc-meta-form"
                  data-order-id="{{ $order->id }}"
                  data-table-id="{{ $order->table_id }}"
                  data-order-type="{{ $jsOrderType }}">
                <div class="progga-oc-meta-title-row">
                    <div class="progga-oc-meta-title"><i class="bi bi-person-check"></i> Customer</div>
                    <span class="badge bg-light text-dark border">{{ $order->customer->name ?? 'Customer' }}</span>
                </div>
                <div class="progga-oc-meta-help">Remove this customer from the active order and change it back to Walk-in before payment.</div>
                <button type="submit" class="btn btn-sm btn-outline-secondary fw-bold js-oc-customer-walkin">
                    <i class="bi bi-person-dash me-1"></i> Make Walk-in
                </button>
            </form>
        @endif

        <form id="ocWaiterUpdateForm"
              class="progga-oc-meta-form mt-2"
              data-order-id="{{ $order->id }}"
              data-table-id="{{ $order->table_id }}"
              data-order-type="{{ $jsOrderType }}">
            <div class="progga-oc-meta-title-row">
                <div class="progga-oc-meta-title"><i class="bi bi-person-badge"></i> Waiter</div>
            </div>
            <div class="progga-oc-meta-help">Change the waiter assigned to this active order before payment.</div>
            <div class="d-flex gap-2">
                <select name="waiter_id" class="form-select form-select-sm " style="min-width:0;" required>
                    <option value="">— Select Waiter —</option>
                    @foreach(($waiters ?? collect()) as $waiterOption)
                        <option value="{{ $waiterOption->id }}" {{ (int) $order->waiter_id === (int) $waiterOption->id ? 'selected' : '' }}>{{ $waiterOption->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary fw-bold js-oc-waiter-save" style="white-space:nowrap;">
                    Update
                </button>
            </div>
        </form>

        @if($jsOrderType === 'delivery')
            <form id="ocDeliveryPartnerUpdateForm"
                  class="progga-oc-meta-form mt-2"
                  data-order-id="{{ $order->id }}"
                  data-table-id="{{ $order->table_id }}"
                  data-order-type="{{ $jsOrderType }}">
                <div class="progga-oc-meta-title-row">
                    <div class="progga-oc-meta-title"><i class="bi bi-truck"></i> Delivery Partner</div>
                </div>
                <div class="progga-oc-meta-help">Change the delivery partner for this active order before payment.</div>
                <div class="d-flex gap-2">
                    <select name="delivery_partner" class="form-select form-select-sm " data-search="false" style="min-width:0;" required>
                        <option value="">— Select Delivery Partner —</option>
                        @if($currentDeliveryPartnerId && !($deliveryPartners ?? collect())->contains('id', (int) $currentDeliveryPartnerId) && $deliveryPartnerName)
                            <option value="{{ $currentDeliveryPartnerId }}" selected>{{ $deliveryPartnerName }}</option>
                        @endif
                        @foreach(($deliveryPartners ?? collect()) as $partnerOption)
                            <option value="{{ $partnerOption->id }}" {{ (int) $currentDeliveryPartnerId === (int) $partnerOption->id ? 'selected' : '' }}>{{ $partnerOption->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold js-oc-partner-save" style="white-space:nowrap;">
                        Update
                    </button>
                </div>
            </form>
        @endif
    </div>

            </div>
        </div>
    </div>
</div>

<style>

    .progga-oc-actions > .progga-btn {
        min-width: 0;
        padding-left: 8px;
        padding-right: 8px;
        white-space: nowrap;
    }


    .progga-oc-actions .js-oc-preinvoice {
        font-size: 12px;
        padding-left: 5px;
        padding-right: 5px;
        white-space: nowrap;
    }

    .progga-oc-actions #btnContinueOrdering,
    .progga-oc-actions #ocPayBtn {
        font-size: 12px;
    }

    #btnAddComplimentary {
        font-size: 13px;
        padding-left: 5px;
        padding-right: 5px;
    }

    .progga-oc-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
    }

    .progga-oc-meta-trigger {
        white-space: nowrap;
        border-radius: 8px;
    }

    .active-order-meta-modal .progga-oc-meta-editor {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: 0;
    }

    .active-order-meta-modal .select2-container {
        width: 100% !important;
    }

    .active-order-meta-modal .modal-dialog {
        max-width: 760px;
    }

    .active-order-meta-modal .active-order-meta-modal-header {
        padding: 16px 20px;
    }

    .active-order-meta-modal .modal-title {
        font-size: 20px;
        line-height: 1.25;
    }

    .active-order-meta-modal .progga-oc-meta-form {
        padding: 16px;
        border-radius: 14px;
    }

    .active-order-meta-modal .progga-oc-meta-title {
        font-size: 16px;
        line-height: 1.35;
    }

    .active-order-meta-modal .progga-oc-meta-title-row .badge {
        font-size: 13px;
        padding: 7px 10px;
    }

    .active-order-meta-modal .progga-oc-meta-help {
        margin-bottom: 12px;
        font-size: 14px;
        line-height: 1.5;
    }

    .active-order-meta-modal .progga-oc-choice-row {
        gap: 10px;
        margin-bottom: 12px;
    }

    .active-order-meta-modal .progga-oc-choice {
        padding: 11px 12px;
        border-radius: 10px;
        font-size: 14px;
        line-height: 1.35;
    }

    .active-order-meta-modal .progga-oc-choice input {
        width: 16px;
        height: 16px;
        margin-right: 7px;
        vertical-align: -3px;
    }

    .active-order-meta-modal .form-control,
    .active-order-meta-modal .form-select {
        min-height: 42px;
        font-size: 15px;
        padding-top: 9px;
        padding-bottom: 9px;
    }

    .active-order-meta-modal .btn {
        min-height: 40px;
        font-size: 14px;
        padding: 8px 14px;
    }

    .active-order-meta-modal .select2-container--default .select2-selection--single {
        min-height: 42px;
        border-color: #dee2e6;
        border-radius: .375rem;
        font-size: 15px;
    }

    .active-order-meta-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
        padding-left: 12px;
        padding-right: 34px;
    }

    .active-order-meta-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }

    .active-order-meta-modal .select2-search__field,
    .active-order-meta-modal .select2-results__option {
        font-size: 14px;
    }

    .active-order-meta-modal .select2-selection__clear {
        display: none !important;
    }

    @media (max-width: 767.98px) {
        .active-order-meta-modal .modal-dialog {
            max-width: calc(100% - 20px);
            margin-left: auto;
            margin-right: auto;
        }

        .active-order-meta-modal .modal-body {
            padding: 16px !important;
        }

        .active-order-meta-modal .progga-oc-choice-row {
            flex-direction: column;
        }
    }

    .progga-oc-meta-editor {
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(33, 53, 42, .10);
    }

    .progga-oc-meta-form {
        padding: 11px;
        border: 1px solid rgba(33, 53, 42, .13);
        border-radius: 12px;
        background: #f8f9fa;
    }

    .progga-oc-meta-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 3px;
    }

    .progga-oc-meta-title {
        color: var(--progga-primary);
        font-size: 12px;
        font-weight: 900;
    }

    .progga-oc-meta-help {
        margin-bottom: 8px;
        color: #777;
        font-size: 10px;
        line-height: 1.35;
    }

    .progga-oc-choice-row {
        display: flex;
        gap: 7px;
        margin-bottom: 8px;
    }

    .progga-oc-choice {
        flex: 1;
        margin: 0;
        padding: 7px 8px;
        border: 1px solid rgba(33, 53, 42, .14);
        border-radius: 8px;
        background: #fff;
        color: #555;
        font-size: 10px;
        font-weight: 800;
        cursor: pointer;
    }

    .progga-oc-choice input {
        margin-right: 4px;
        vertical-align: -1px;
    }

    .progga-oc-choice.active-choice {
        border-color: var(--progga-primary);
        color: var(--progga-primary);
        background: rgba(33, 53, 42, .04);
    }

    .progga-oc-item-delete {
        padding: 3px 7px;
        line-height: 1;
        border-radius: 6px;
        margin-left: 6px;
        flex: 0 0 auto;
    }

    .progga-oc-item-complimentary {
        padding: 3px 7px;
        line-height: 1;
        border-radius: 6px;
        margin-left: 6px;
        flex: 0 0 auto;
    }

    .progga-complimentary-food-label {
        display: block;
        width: max-content;
        margin-top: 3px;
        color: #198754;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.1;
    }

    .progga-table-swap-box {
        margin-bottom: 12px;
        padding: 10px;
        border: 1px solid rgba(33, 53, 42, .14);
        border-radius: 12px;
        background: rgba(248, 249, 250, .9);
    }

    .progga-table-swap-toggle {
        width: 100%;
        border: 0;
        background: transparent;
        color: var(--progga-primary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 0;
        font-weight: 900;
        font-size: 13px;
        text-align: left;
    }

    .progga-table-swap-toggle small {
        color: #777;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .progga-table-swap-panel {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed rgba(33, 53, 42, .18);
    }

    .progga-table-swap-label {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        font-weight: 800;
        color: #666;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .progga-table-swap-help {
        margin-top: 6px;
        font-size: 11px;
        color: #777;
        line-height: 1.35;
    }
</style>

<div class="modal fade progga-modal" id="orderItemDeleteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold mb-0"><i class="bi bi-trash me-1"></i> Delete Product</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="orderItemDeleteForm">
                <div class="modal-body">
                    <input type="hidden" id="deleteOrderId" name="order_id">
                    <input type="hidden" id="deleteOrderDetailId" name="order_detail_id">
                    <input type="hidden" id="deleteOrderDetailIds" name="order_detail_ids">

                    <div class="fw-bold mb-1" id="deleteOrderItemName"></div>
                    <div class="text-muted mb-3" style="font-size: 12px;">Available quantity: <strong id="deleteOrderItemMaxQty">0</strong></div>

                    <label class="form-label fw-bold" style="font-size: 12px;">How many quantity do you want to delete?</label>
                    <input type="number" class="form-control text-center fw-bold" id="deleteOrderItemQty" name="qty" min="1" value="1" required>

                    <label class="form-label fw-bold mt-3" style="font-size: 12px;">Reason <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control" id="deleteOrderItemReason" name="reason" rows="3" placeholder="Write delete reason..." style="font-size: 13px; resize: vertical;"></textarea>

                    <button type="button" class="btn btn-link text-danger fw-bold p-0 mt-2" id="btnDeleteFullQty">Delete full product</button>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold" id="btnConfirmOrderItemDelete">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
