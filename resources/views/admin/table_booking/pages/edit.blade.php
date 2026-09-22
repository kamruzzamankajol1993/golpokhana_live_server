@extends('admin.master.master')
@section('title','Edit Table Booking')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Edit Table Booking</h1>
            <div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Edit Booking</span></div>
        </div>
    </div>
    <div class="progga-card">
        <div class="progga-card-header"><h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Booking</h5></div>
        <div class="progga-card-body">
            <form action="{{ route('table-booking.update', $booking->id) }}" method="POST">
                @csrf
                @method('PUT')

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
                        <input class="form-check-input" type="checkbox" name="is_new_customer" id="edit_is_new_customer" value="1" style="cursor:pointer;">
                        <label class="form-check-label text-primary" for="edit_is_new_customer" style="cursor:pointer;text-transform:none;">Change to New Customer?</label>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12" id="edit_existing_customer_field_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Search Customer <span class="progga-required">*</span></label>
                            <select name="customer_id" id="edit_customer_id_select" class="progga-select js-select2" required data-placeholder="Select option">
                                <option value="">Select a customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" {{ $booking->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }} ({{ $customer->phone }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-12 d-none" id="edit_new_customer_fields_wrapper">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Name <span class="progga-required">*</span></label>
                                    <input type="text" name="name" id="edit_c_name" class="progga-form-control" placeholder="Full name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Phone <span class="progga-required">*</span></label>
                                    <input type="tel" name="phone" id="edit_c_phone" class="progga-form-control" placeholder="017...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Email Address</label>
                                    <input type="email" name="email" id="edit_c_email" class="progga-form-control" placeholder="Optional">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Address</label>
                                    <textarea name="address" id="edit_c_address" class="progga-form-control progga-form-textarea" rows="2" placeholder="Customer address"></textarea>
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
                            <select name="table_id" id="edit_table" class="progga-select js-select2" required data-placeholder="Select option">
                                @foreach($zonesWithTables as $zone)
                                    <optgroup label="{{ $zone->name }}">
                                        @foreach($zone->tables as $table)
                                            <option value="{{ $table->id }}" {{ $booking->table_id == $table->id ? 'selected' : '' }}>{{ $table->table_number }} — ({{ $table->seating_capacity }} seats)</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Number of Guests <span class="progga-required">*</span></label>
                            <input type="number" name="number_of_guests" id="edit_guests" class="progga-form-control" min="1" value="{{ old('number_of_guests', $booking->number_of_guests) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Booking Date <span class="progga-required">*</span></label>
                            <input type="date" name="booking_date" id="edit_date" class="progga-form-control progga-datepicker" value="{{ old('booking_date', $booking->booking_date ? \Illuminate\Support\Carbon::parse($booking->booking_date)->format('Y-m-d') : '') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Booking Start Time <span class="progga-required">*</span></label>
                            <input type="time" name="booking_start_time" id="edit_start_time" class="progga-form-control" value="{{ old('booking_start_time', $booking->booking_start_time ? \Illuminate\Support\Carbon::parse($booking->booking_start_time)->format('H:i') : '') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Booking End Time <span class="progga-required">*</span></label>
                            <input type="time" name="booking_end_time" id="edit_end_time" class="progga-form-control" value="{{ old('booking_end_time', $booking->booking_end_time ? \Illuminate\Support\Carbon::parse($booking->booking_end_time)->format('H:i') : '') }}" required>
                        </div>
                    </div>
                </div>

                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--progga-text-muted);margin:20px 0 12px;">Additional Information</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Occasion</label>
                            <select name="occasion_id" id="edit_occasion" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="">Select occasion (optional)</option>
                                @foreach($occasions as $occasion)
                                    <option value="{{ $occasion->id }}" {{ $booking->occasion_id == $occasion->id ? 'selected' : '' }}>{{ $occasion->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Status</label>
                            <select name="status" id="edit_status" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="upcoming" {{ $booking->status=='upcoming' ? 'selected' : '' }}>Upcoming</option>
                                <option value="confirmed" {{ $booking->status=='confirmed' ? 'selected' : '' }}>Confirmed</option>
                                <option value="cancelled" {{ $booking->status=='cancelled' ? 'selected' : '' }}>Cancelled</option>
                                <option value="completed" {{ $booking->status=='completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Advance Amount</label>
                            <input type="number" step="0.01" name="advance_amount" class="progga-form-control" id="edit_advance_amount" value="{{ old('advance_amount', $booking->advance_amount) }}" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Payment Method</label>
                            <select name="advance_payment_method" id="edit_advance_payment_method" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="">Select</option>
                                <option value="Cash" {{ old('advance_payment_method', $booking->advance_payment_method)=='Cash' ? 'selected' : '' }}>Cash</option>
                                <option value="Card" {{ old('advance_payment_method', $booking->advance_payment_method)=='Card' ? 'selected' : '' }}>Bank / Card</option>
                                <option value="MFS" {{ old('advance_payment_method', $booking->advance_payment_method)=='MFS' ? 'selected' : '' }}>MFS</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 d-none" id="edit_card_provider_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Card Provider</label>
                            <select name="advance_card_provider" id="edit_advance_card_provider" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="">Select Card Provider</option>
                                <option value="Visa" {{ old('advance_card_provider', $booking->advance_card_provider)=='Visa' ? 'selected' : '' }}>Visa</option>
                                <option value="Mastercard" {{ old('advance_card_provider', $booking->advance_card_provider)=='Mastercard' ? 'selected' : '' }}>Mastercard</option>
                                <option value="American Express" {{ old('advance_card_provider', $booking->advance_card_provider)=='American Express' ? 'selected' : '' }}>American Express</option>
                                <option value="UnionPay" {{ old('advance_card_provider', $booking->advance_card_provider)=='UnionPay' ? 'selected' : '' }}>UnionPay</option>
                                <option value="JCB" {{ old('advance_card_provider', $booking->advance_card_provider)=='JCB' ? 'selected' : '' }}>JCB</option>
                                <option value="Nexus" {{ old('advance_card_provider', $booking->advance_card_provider)=='Nexus' ? 'selected' : '' }}>Nexus</option>
                                <option value="Diners Club" {{ old('advance_card_provider', $booking->advance_card_provider)=='Diners Club' ? 'selected' : '' }}>Diners Club</option>
                                <option value="GPay" {{ old('advance_card_provider', $booking->advance_card_provider)=='GPay' ? 'selected' : '' }}>GPay</option>
                                <option value="Bangla QR Card" {{ old('advance_card_provider', $booking->advance_card_provider)=='Bangla QR Card' ? 'selected' : '' }}>Bangla QR Card</option>
                                <option value="Other" {{ old('advance_card_provider', $booking->advance_card_provider)=='Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 d-none" id="edit_mfs_provider_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">MFS Provider</label>
                            <select name="advance_mfs_provider" id="edit_advance_mfs_provider" class="progga-select js-select2" data-placeholder="Select option">
                                <option value="">Select MFS Provider</option>
                                <option value="bKash" {{ old('advance_mfs_provider', $booking->advance_mfs_provider)=='bKash' ? 'selected' : '' }}>bKash</option>
                                <option value="Nagad" {{ old('advance_mfs_provider', $booking->advance_mfs_provider)=='Nagad' ? 'selected' : '' }}>Nagad</option>
                                <option value="Rocket" {{ old('advance_mfs_provider', $booking->advance_mfs_provider)=='Rocket' ? 'selected' : '' }}>Rocket</option>
                                <option value="Bangla QR" {{ old('advance_mfs_provider', $booking->advance_mfs_provider)=='Bangla QR' ? 'selected' : '' }}>Bangla QR</option>
                                <option value="Other" {{ old('advance_mfs_provider', $booking->advance_mfs_provider)=='Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4" id="edit_reference_wrapper">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Reference Number</label>
                            <input type="text" name="advance_payment_reference" id="edit_advance_payment_reference" class="progga-form-control" value="{{ old('advance_payment_reference', $booking->advance_payment_reference) }}" placeholder="Required for Bank / Card / MFS">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Special Requests</label>
                            <textarea name="special_request" id="edit_requests" class="progga-form-control progga-form-textarea" rows="3">{{ old('special_request', $booking->special_request) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('table-booking.index') }}" class="progga-btn progga-btn-outline">Cancel</a>
                    <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
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

    function toggleEditNewCustomerFields() {
        const enabled = $('#edit_is_new_customer').is(':checked');
        $('#edit_existing_customer_field_wrapper').toggleClass('d-none', enabled);
        $('#edit_new_customer_fields_wrapper').toggleClass('d-none', !enabled);

        $('#edit_customer_id_select').prop('required', !enabled);
        $('#edit_c_name, #edit_c_phone').prop('required', enabled);

        if (enabled) {
            $('#edit_customer_id_select').val(null).trigger('change');
        } else {
            $('#edit_new_customer_fields_wrapper').find('input, textarea').val('');
        }
    }

    function toggleEditPaymentProvider() {
        const method = $('#edit_advance_payment_method').val();
        const showCard = method === 'Card';
        const showMfs = method === 'MFS';
        const needsReference = showCard || showMfs;

        $('#edit_card_provider_wrapper').toggleClass('d-none', !showCard);
        $('#edit_mfs_provider_wrapper').toggleClass('d-none', !showMfs);

        $('#edit_advance_card_provider').prop('required', showCard);
        $('#edit_advance_mfs_provider').prop('required', showMfs);
        $('#edit_advance_payment_reference').prop('required', needsReference);

        if (!showCard) {
            $('#edit_advance_card_provider').val('').trigger('change');
        }
        if (!showMfs) {
            $('#edit_advance_mfs_provider').val('').trigger('change');
        }
    }

    initSelect2(document);
    toggleEditNewCustomerFields();
    toggleEditPaymentProvider();

    $('#edit_is_new_customer').on('change', toggleEditNewCustomerFields);
    $('#edit_advance_payment_method').on('change', toggleEditPaymentProvider);
});
</script>
@endsection
