@extends('admin.master.master')
@section('title','Create Table Booking')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Create Table Booking</h1>
            <div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Create Booking</span></div>
        </div>
    </div>
    <div class="progga-card">
        <div class="progga-card-header"><h5 class="mb-0"><i class="bi bi-calendar-plus-fill me-2"></i>New Table Booking</h5></div>
        <div class="progga-card-body">
          <form action="{{ route('table-booking.store') }}" method="POST">
        @csrf

          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--progga-text-muted);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">
              Customer Information
              <div class="form-check form-switch" style="margin: 0;">
                <input class="form-check-input" type="checkbox" name="is_new_customer" id="is_new_customer" value="1" style="cursor:pointer;">
                <label class="form-check-label text-primary" for="is_new_customer" style="cursor:pointer;text-transform:none;">New Customer?</label>
              </div>
          </div>

          <div class="row g-3">
            <div class="col-12" id="existing_customer_field">
              <div class="progga-form-group">
                <label class="progga-form-label">Search Customer <span class="progga-required">*</span></label>
                <select name="customer_id" id="customer_id_select" class="progga-select js-select2" required>
                    <option value="">Select a customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                    @endforeach
                </select>
              </div>
            </div>

            <div class="col-12" id="new_customer_fields" style="display:none;">
                <div class="row g-3">
                    <div class="col-md-4">
                      <div class="progga-form-group">
                        <label class="progga-form-label">Name <span class="progga-required">*</span></label>
                        <input type="text" name="name" id="new_c_name" class="progga-form-control" placeholder="Full name">
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="progga-form-group">
                        <label class="progga-form-label">Phone <span class="progga-required">*</span></label>
                        <input type="tel" name="phone" id="new_c_phone" class="progga-form-control" placeholder="017...">
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="progga-form-group">
                        <label class="progga-form-label">Email Address</label>
                        <input type="email" name="email" class="progga-form-control" placeholder="Optional">
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
                <select name="table_id" class="progga-select js-select2" required>
                  <option value="">Select a table</option>
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
                <input type="number" name="number_of_guests" class="progga-form-control" placeholder="e.g. 4" min="1" max="50" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Booking Date <span class="progga-required">*</span></label>
                <input type="date" name="booking_date" class="progga-form-control progga-datepicker" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Booking Start Time <span class="progga-required">*</span></label>
                <input type="time" name="booking_start_time" class="progga-form-control" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Booking End Time <span class="progga-required">*</span></label>
                <input type="time" name="booking_end_time" class="progga-form-control" required>
              </div>
            </div>
          </div>

          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--progga-text-muted);margin:20px 0 12px;">Additional Information</div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="progga-form-group">
                <label class="progga-form-label">
                    Occasion
                </label>
                <select name="occasion_id" class="progga-select js-select2">
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
                <select name="status" class="progga-select js-select2">
                  <option value="upcoming">Upcoming</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Advance Amount</label>
                <input type="number" step="0.01" name="advance_amount" class="progga-form-control" id="advance_amount" placeholder="0.00">
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Payment Method</label>
                <select name="advance_payment_method" class="progga-select js-select2">
                  <option value="">Select</option>
                  <option value="Cash">Cash</option>
                  <option value="Card">Bank / Card</option>
                  <option value="MFS">MFS</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="progga-form-group">
                <label class="progga-form-label">Reference Number</label>
                <input type="text" name="advance_payment_reference" class="progga-form-control" placeholder="Required for Bank / Card / MFS">
              </div>
            </div>
            <div class="col-12">
              <div class="progga-form-group">
                <label class="progga-form-label">Special Requests</label>
                <textarea name="special_request" class="progga-form-control progga-form-textarea" rows="3" placeholder="Dietary restrictions, accessibility needs, special arrangements…"></textarea>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-3">
          <button type="button" class="progga-btn progga-btn-outline" >Cancel</button>
          <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Confirm Booking</button>
          </div>
      </form>
      </div>
    </div>
</main>
@endsection

@section('script')
<script>
$(document).ready(function(){
    if ($.fn.select2) {
        $('.js-select2').each(function(){
            let el = $(this);
            if (el.hasClass('select2-hidden-accessible')) {
                el.select2('destroy');
            }
            el.select2({
                width: '100%',
                theme: 'progga-theme',
                allowClear: true,
                placeholder: el.attr('data-placeholder') || 'Select option'
            });
        });
    }
});
</script>
@endsection
