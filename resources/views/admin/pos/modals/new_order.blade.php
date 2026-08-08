<div class="modal fade progga-modal" id="newOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Order Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <form id="newOrderForm" class="pos-order-form">

            <div class="pos-modal-section">
              <div class="pos-modal-label">Order Type</div>
              <div class="pos-type-wrap d-flex" style="gap: 5px;">
                <input type="radio" id="posTypeDineIn" name="orderType" value="dine_in" checked>
                <input type="radio" id="posTypeTakeaway" name="orderType" value="takeaway">
                <input type="radio" id="posTypeDelivery" name="orderType" value="delivery">

                <label for="posTypeDineIn" id="labelDineIn" style="flex: 1; text-align: center;"><i class="bi bi-layout-wtf"></i> Dine-In</label>
                <label for="posTypeTakeaway" id="labelTakeaway" style="flex: 1; text-align: center;"><i class="bi bi-bag-fill"></i> Takeaway</label>
                <label for="posTypeDelivery" id="labelDelivery" style="flex: 1; text-align: center;"><i class="bi bi-truck"></i> Delivery</label>
              </div>
            </div>

            <div class="pos-modal-section" id="modalTableSelectSection" style="margin-top:14px;">
              <div class="pos-modal-label">Select Table <span class="text-danger dine-required-mark">*</span></div>
              <select id="modalTableSelect" class="progga-select w-100">
                <option value="">— Select Table —</option>
                @foreach($tables as $table)
                  @php
                    $tableStatus = strtolower($table->initial_status ?? 'available');
                    $isBlockedTable = in_array($tableStatus, ['occupied', 'reserved']);
                    $tableLabelStatus = $tableStatus === 'occupied' ? ' — Occupied' : ($tableStatus === 'reserved' ? ' — Reserved' : '');
                  @endphp
                  <option value="{{ $table->id }}"
                          data-table-name="{{ $table->table_number }}"
                          data-status="{{ $tableStatus }}"
                          {{ $isBlockedTable ? 'disabled' : '' }}>
                    {{ $table->table_number }}{{ $tableLabelStatus }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted d-block mt-1">Available table selection is required for Dine-In orders. Occupied or Reserved tables cannot be selected.</small>
            </div>

            <div class="text-center p-3 mb-3 rounded" id="modalTableDisplaySection" style="display:none;background: rgba(213, 170, 101, 0.1); border: 1px dashed var(--progga-secondary);">
                <span class="text-muted" style="font-size: 13px;">Selected Table</span><br>
                <strong id="modalSelectedTableNum" class="text-primary" style="font-size: 24px;">T-00</strong>
            </div>

            <div class="row g-3">
              <div class="col-5">
                <div class="pos-modal-label">Guests</div>
                <div class="pos-guest-counter">
                  <button type="button" class="pos-guest-btn" onclick="$('#posGuestCount').text(Math.max(1, parseInt($('#posGuestCount').text())-1))">−</button>
                  <span class="pos-guest-num" id="posGuestCount">2</span>
                  <button type="button" class="pos-guest-btn" onclick="$('#posGuestCount').text(parseInt($('#posGuestCount').text())+1)">+</button>
                </div>
              </div>
              <div class="col-7">
                <div class="pos-modal-label">Assign Waiter</div>
                <select id="posWaiterSelect" class="progga-select w-100">
                  @if(auth()->check() && auth()->user()->hasRole('waiter'))
                      @php
                          $loggedInWaiter = collect($waiters)->firstWhere('user_id', auth()->id());
                      @endphp
                      @if($loggedInWaiter)
                          <option value="{{ $loggedInWaiter->id }}" selected>{{ $loggedInWaiter->name }}</option>
                      @else
                          <option value="">— Profile Not Found —</option>
                      @endif
                  @else
                      <option value="">— Unassigned —</option>
                      @foreach($waiters as $waiter)
                        <option value="{{ $waiter->id }}">{{ $waiter->name }}</option>
                      @endforeach
                  @endif
                </select>
              </div>
            </div>

            <div class="pos-modal-section" style="margin-top:14px;">
              <div class="pos-modal-label">Customer</div>
              <div class="pos-walkin-row">
                <div class="pos-walkin-row-text"><strong>Walk-in Customer</strong><small>No contact needed</small></div>
                <label class="progga-toggle"><input type="checkbox" id="posWalkIn" checked><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span></label>
              </div>

              <div class="pos-customer-fields" id="posCustomerFields" style="display:none; margin-top: 10px;">
                <div id="customerSearchContainer">
                    <select id="posCustomerSelect" class="progga-select w-100">
                        <option value="">Search Customer...</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-2 text-end">
                    <button type="button" id="showNewCustomerFormBtn" class="progga-btn progga-btn-primary">+ Add New Customer</button>
                </div>

                <div id="newCustomerForm" style="display:none; margin-top:8px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                    <div class="row g-2">
                        <div class="col-12"><input type="text" id="new_cus_name" name="customer_name" class="progga-form-control" placeholder="Full Name"></div>
                        <div class="col-12"><input type="tel" id="new_cus_phone" name="customer_phone" class="progga-form-control" placeholder="Phone Number"></div>
                    </div>
                </div>
              </div>
            </div>

            <div class="pos-modal-section" style="margin-top:14px;">
              <div class="pos-walkin-row" style="border: 1px dashed #198754; background: rgba(25, 135, 84, 0.06);">
                <div class="pos-walkin-row-text">
                  <strong><i class="bi bi-gift-fill text-success me-1"></i>Complimentary Order</strong>
                  <small>Turn this on to make all food and addon prices 0 for this order.</small>
                </div>
                <label class="progga-toggle">
                  <input type="checkbox" id="posComplimentaryOrder" name="is_complimentary_order" value="1">
                  <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                </label>
              </div>
            </div>

            <div class="pos-modal-section">
              <div class="pos-modal-label">Notes</div>
              <textarea id="order_notes" name="order_notes" class="progga-form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer p-2">
          <button type="button" class="progga-btn progga-btn-primary w-100 py-2" id="posStartOrderBtn" style="font-size: 15px;"><i class="bi bi-arrow-right-circle-fill"></i> Start Order</button>
        </div>
      </div>
    </div>
</div>
