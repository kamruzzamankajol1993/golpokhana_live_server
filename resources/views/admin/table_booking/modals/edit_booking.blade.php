<div class="modal fade progga-modal" id="editBookingModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Booking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="editBookingForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--progga-text-muted);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">
              Customer Information
              <div class="form-check form-switch" style="margin: 0;">
                <input class="form-check-input" type="checkbox" name="is_new_customer" id="edit_is_new_customer" value="1" style="cursor:pointer;">
                <label class="form-check-label text-primary" for="edit_is_new_customer" style="cursor:pointer;text-transform:none;">Change to New Customer?</label>
              </div>
          </div>

          <div class="row g-3">
            <div class="col-12" id="edit_existing_customer_field">
              <div class="progga-form-group">
                <label class="progga-form-label">Search Customer <span class="progga-required">*</span></label>
                <select name="customer_id" id="edit_customer_id_select" class="progga-select progga-choices">
                    <option value="">Select a customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                    @endforeach
                </select>
              </div>
            </div>

            <div class="col-12" id="edit_new_customer_fields" style="display:none;">
                <div class="row g-3">
                    <div class="col-md-4">
                      <div class="progga-form-group">
                        <label class="progga-form-label">Name <span class="progga-required">*</span></label>
                        <input type="text" name="name" id="edit_c_name" class="progga-form-control">
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="progga-form-group">
                        <label class="progga-form-label">Phone <span class="progga-required">*</span></label>
                        <input type="tel" name="phone" id="edit_c_phone" class="progga-form-control">
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="progga-form-group">
                        <label class="progga-form-label">Email Address</label>
                        <input type="email" name="email" id="edit_c_email" class="progga-form-control">
                      </div>
                    </div>
                </div>
            </div>
          </div>

          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--progga-text-muted);margin:20px 0 12px;">Reservation Details</div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Table <span class="progga-required">*</span></label>
                <select name="table_id" id="edit_table" class="progga-select" required>
                  @foreach($zonesWithTables as $zone)
                      <optgroup label="{{ $zone->name }}">
                          @foreach($zone->tables as $table)
                              <option value="{{ $table->id }}">{{ $table->table_number }} — ({{ $table->seating_capacity }} seats)</option>
                          @endforeach
                      </optgroup>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Number of Guests <span class="progga-required">*</span></label>
                <input type="number" name="number_of_guests" id="edit_guests" class="progga-form-control" min="1" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Booking Date <span class="progga-required">*</span></label>
                <input type="date" name="booking_date" id="edit_date" class="progga-form-control progga-datepicker" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Booking Start Time <span class="progga-required">*</span></label>
                <input type="time" name="booking_start_time" id="edit_start_time" class="progga-form-control" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Booking End Time <span class="progga-required">*</span></label>
                <input type="time" name="booking_end_time" id="edit_end_time" class="progga-form-control" required>
              </div>
            </div>
          </div>

          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--progga-text-muted);margin:20px 0 12px;">Additional Information</div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Occasion</label>
                <select name="occasion_id" id="edit_occasion" class="progga-select progga-choices">
                  <option value="">Select occasion (optional)</option>
                  @foreach($occasions as $occasion)
                      <option value="{{ $occasion->id }}">{{ $occasion->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">Status</label>
                <select name="status" id="edit_status" class="progga-select">
                  <option value="upcoming">Upcoming</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
            </div>
            <div class="col-12">
              <div class="progga-form-group">
                <label class="progga-form-label">Special Requests</label>
                <textarea name="special_request" id="edit_requests" class="progga-form-control progga-form-textarea" rows="3"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
