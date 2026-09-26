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

                @if ($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success mb-3">{{ session('success') }}</div>
                @endif


                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--progga-text-muted);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">
                    Customer Information
                    <div class="form-check form-switch" style="margin: 0;">
                        <input class="form-check-input" type="checkbox" name="is_new_customer" id="is_new_customer" value="1" style="cursor:pointer;">
                        <label class="form-check-label text-primary" for="is_new_customer" style="cursor:pointer;text-transform:none;">New Customer?</label>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12" id="existing_customer_field_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Search Customer <span class="progga-required">*</span></label>
                            <select name="customer_id" id="customer_id_select" class="progga-select js-select2" required data-placeholder="Select option">
                                <option value="">Select a customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-12 d-none" id="new_customer_fields_wrapper">
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
                                    <input type="email" name="email" id="new_c_email" class="progga-form-control" placeholder="Optional">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Address</label>
                                    <textarea name="address" id="new_c_address" class="progga-form-control progga-form-textarea" rows="2" placeholder="Customer address"></textarea>
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
                            <select name="table_id" class="progga-select js-select2" required data-placeholder="Select option">
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
                            <label class="progga-form-label">Occasion</label>
                            <select name="occasion_id" class="progga-select js-select2" data-placeholder="Select option">
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
                            <select name="status" class="progga-select js-select2" data-placeholder="Select option">
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
                            <select name="advance_payment_method" id="advance_payment_method" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="">Select</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Bank / Card</option>
                                <option value="MFS">MFS</option>
                                <option value="Split">Split</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 d-none" id="card_provider_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Card Provider</label>
                            <select name="advance_card_provider" id="advance_card_provider" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="">Select Card Provider</option>
                                <option value="Visa">Visa</option>
                                <option value="Mastercard">Mastercard</option>
                                <option value="American Express">American Express</option>
                                <option value="UnionPay">UnionPay</option>
                                <option value="JCB">JCB</option>
                                <option value="Nexus">Nexus</option>
                                <option value="Diners Club">Diners Club</option>
                                <option value="GPay">GPay</option>
                                <option value="Bangla QR Card">Bangla QR Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 d-none" id="mfs_provider_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">MFS Provider</label>
                            <select name="advance_mfs_provider" id="advance_mfs_provider" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="">Select MFS Provider</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Rocket">Rocket</option>
                                <option value="Bangla QR">Bangla QR</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4" id="reference_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Reference Number</label>
                            <input type="text" name="advance_payment_reference" id="advance_payment_reference" class="progga-form-control" placeholder="Required for Bank / Card / MFS">
                        </div>
                    </div>
                    <div class="col-12 d-none" id="advance_split_wrapper">
                        <div class="progga-form-group" style="border:1px dashed var(--progga-border);border-radius:10px;padding:12px;background:var(--progga-bg-soft,#f8f9fa);">
                            <label class="progga-form-label">Split Advance Payment</label>
                            <div class="row g-2">
                                <div class="col-md-4"><label class="progga-form-label">Cash</label><input type="number" step="0.01" min="0" name="advance_paid_in_cash" id="advance_paid_in_cash" class="progga-form-control" value="{{ old('advance_paid_in_cash',0) }}"></div>
                                <div class="col-md-4"><label class="progga-form-label">Bank / Card</label><input type="number" step="0.01" min="0" name="advance_paid_in_card" id="advance_paid_in_card" class="progga-form-control" value="{{ old('advance_paid_in_card',0) }}"></div>
                                <div class="col-md-4"><label class="progga-form-label">MFS</label><input type="number" step="0.01" min="0" name="advance_paid_in_mfs" id="advance_paid_in_mfs" class="progga-form-control" value="{{ old('advance_paid_in_mfs',0) }}"></div>
                                <div class="col-md-6"><label class="progga-form-label">Card Reference</label><input type="text" name="advance_split_card_reference" id="advance_split_card_reference" class="progga-form-control" value="{{ old('advance_split_card_reference') }}" placeholder="Required when card amount is entered"></div>
                                <div class="col-md-6"><label class="progga-form-label">MFS Reference</label><input type="text" name="advance_split_mfs_reference" id="advance_split_mfs_reference" class="progga-form-control" value="{{ old('advance_split_mfs_reference') }}" placeholder="Required when MFS amount is entered"></div>
                            </div>
                            <small class="text-muted">Use at least two methods. Split amounts automatically set the Advance Amount.</small>
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
                    <a href="{{ route('table-booking.index') }}" class="progga-btn progga-btn-outline">Cancel</a>
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
    function initSelect2(scope) {
        if (!$.fn.select2) return;
        $(scope).find('.js-select2').each(function(){
            const el = $(this);
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

    function toggleNewCustomerFields() {
        const enabled = $('#is_new_customer').is(':checked');
        $('#existing_customer_field_wrapper').toggleClass('d-none', enabled);
        $('#new_customer_fields_wrapper').toggleClass('d-none', !enabled);

        $('#customer_id_select').prop('required', !enabled);
        $('#new_c_name, #new_c_phone').prop('required', enabled);

        if (enabled) {
            $('#customer_id_select').val(null).trigger('change');
        } else {
            $('#new_customer_fields_wrapper').find('input, textarea').val('');
        }
    }

    function togglePaymentProvider() {
        const method = $('#advance_payment_method').val();
        const isSplit = method === 'Split';
        const cash = parseFloat($('#advance_paid_in_cash').val()) || 0;
        const card = parseFloat($('#advance_paid_in_card').val()) || 0;
        const mfs = parseFloat($('#advance_paid_in_mfs').val()) || 0;
        const showCard = method === 'Card' || (isSplit && card > 0);
        const showMfs = method === 'MFS' || (isSplit && mfs > 0);

        $('#card_provider_wrapper').toggleClass('d-none', !showCard);
        $('#mfs_provider_wrapper').toggleClass('d-none', !showMfs);
        $('#advance_split_wrapper').toggleClass('d-none', !isSplit);
        $('#reference_wrapper').toggleClass('d-none', isSplit);

        $('#advance_card_provider').prop('required', showCard);
        $('#advance_mfs_provider').prop('required', showMfs);
        $('#advance_payment_reference').prop('required', method === 'Card' || method === 'MFS');
        $('#advance_split_card_reference').prop('required', isSplit && card > 0);
        $('#advance_split_mfs_reference').prop('required', isSplit && mfs > 0);

        $('#advance_amount').prop('readonly', isSplit);
        if(isSplit) $('#advance_amount').val((cash + card + mfs).toFixed(2));

        if (!showCard) $('#advance_card_provider').val('').trigger('change');
        if (!showMfs) $('#advance_mfs_provider').val('').trigger('change');
    }

    initSelect2(document);
    toggleNewCustomerFields();
    togglePaymentProvider();

    $('#is_new_customer').on('change', toggleNewCustomerFields);
    $('#advance_payment_method').on('change', togglePaymentProvider);
    $('#advance_paid_in_cash,#advance_paid_in_card,#advance_paid_in_mfs').on('input', togglePaymentProvider);
});
</script>
@endsection
