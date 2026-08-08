@extends('admin.master.master')
@section('title', 'Table Booking — TableTrack RMS')

@section('body')
<main class="progga-content">

    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Table Booking</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Table Booking</span>
            </div>
        </div>

        <div class="d-flex gap-2">
            @can('occasion-create')
            <button class="progga-btn progga-btn-secondary" data-bs-toggle="modal" data-bs-target="#addOccasionModal">
                <i class="bi bi-star-fill"></i> Occasion
            </button>
            @endcan

            @can('table-booking-create')
            <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                <i class="bi bi-plus-lg"></i> New Booking
            </button>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="font-size:13px;"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="font-size:13px;"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon primary"><i class="bi bi-calendar-check-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Today's Bookings</div>
                    <div class="progga-stat-value">{{ $todayBookings }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon info"><i class="bi bi-clock-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Upcoming Bookings</div>
                    <div class="progga-stat-value">{{ $upcomingBookings }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Confirmed</div>
                    <div class="progga-stat-value">{{ $confirmedBookings }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon danger"><i class="bi bi-x-circle-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Cancelled</div>
                    <div class="progga-stat-value">{{ $cancelledBookings }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card" style="margin-bottom:16px;">
        <div class="progga-filters-bar">
            <div class="progga-search" style="flex:1;min-width:200px;max-width:320px;">
                <i class="bi bi-search progga-search-icon"></i>
                <input type="text" class="progga-form-control progga-table-search" placeholder="Search by name, phone or ID…">
            </div>
            <div style="min-width:160px;">
                <select class="progga-select" id="filterDate">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="tomorrow">Tomorrow</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>
            <div style="min-width:170px;">
                <select class="progga-select" id="filterOccasion">
                    <option value="">All Occasions</option>
                    @foreach($occasions as $occasion)
                        <option value="{{ $occasion->id }}">{{ $occasion->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:150px;">
                <select class="progga-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
    </div>

    <div class="progga-card" id="booking_data_container">
        @include('admin.table_booking.booking_table')
    </div>

</main>

@include('admin.table_booking.modals.add_booking')
@include('admin.table_booking.modals.edit_booking')
@include('admin.table_booking.modals.occasion_modal')

@endsection

@section('script')
<script>
// ==========================================
// 1. Table Booking AJAX Filter & Pagination
// ==========================================
function fetchBookings(url) {
    let search = $('.progga-table-search').val();
    let date = $('#filterDate').val();
    let occasion = $('#filterOccasion').val();
    let status = $('#filterStatus').val();

    $('#booking_data_container').css('opacity', '0.6');

    $.ajax({
        url: url,
        data: { search: search, date: date, occasion_id: occasion, status: status },
        success: function(data) {
            $('#booking_data_container').html(data).css('opacity', '1');
        },
        error: function() {
            showToast('Error', 'Failed to fetch data!', 'error');
            $('#booking_data_container').css('opacity', '1');
        }
    });
}

$(document).on('change', '#filterDate, #filterOccasion, #filterStatus', function() {
    fetchBookings("{{ route('table-booking.index') }}");
});

let timer;
$(document).on('keyup', '.progga-table-search', function() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        fetchBookings("{{ route('table-booking.index') }}");
    }, 500);
});

$(document).on('click', '.booking-pagination a', function(e) {
    e.preventDefault();
    fetchBookings($(this).attr('href'));
});

// ==========================================
// 2. Booking Modals - New Customer Toggle
// ==========================================
$(document).ready(function() {
    // Add Form Toggle
    $('#is_new_customer').on('change', function() {
        if($(this).is(':checked')) {
            $('#new_customer_fields').slideDown();
            $('#existing_customer_field').slideUp();
            $('#customer_id_select').prop('required', false);
            $('#new_c_name, #new_c_phone').prop('required', true);
        } else {
            $('#new_customer_fields').slideUp();
            $('#existing_customer_field').slideDown();
            $('#customer_id_select').prop('required', true);
            $('#new_c_name, #new_c_phone').prop('required', false);
        }
    });

    // Edit Form Toggle
    $('#edit_is_new_customer').on('change', function() {
        if($(this).is(':checked')) {
            $('#edit_new_customer_fields').slideDown();
            $('#edit_existing_customer_field').slideUp();
            $('#edit_customer_id_select').prop('required', false);
        } else {
            $('#edit_new_customer_fields').slideUp();
            $('#edit_existing_customer_field').slideDown();
            $('#edit_customer_id_select').prop('required', true);
        }
    });
});

// ==========================================
// 3. Edit Booking Function
// ==========================================
window.editBookingData = function(id, customer_id, c_name, c_phone, c_email, table_id, date, start_time, end_time, guests, occasion_id, requests, status) {
    let formAction = "{{ route('table-booking.update', ':id') }}".replace(':id', id);
    $('#editBookingForm').attr('action', formAction);

    $('#edit_is_new_customer').prop('checked', false).trigger('change');

    let choicesEl = document.getElementById('edit_customer_id_select');
    if(choicesEl && choicesEl.choices) {
        choicesEl.choices.setChoiceByValue(customer_id.toString());
    } else {
        $('#edit_customer_id_select').val(customer_id).trigger('change');
    }

    $('#edit_table').val(table_id);
    $('#edit_date').val(date);

    $('#edit_start_time').val(start_time ? start_time.substring(0, 5) : '');
    $('#edit_end_time').val(end_time ? end_time.substring(0, 5) : '');

    $('#edit_guests').val(guests);
    $('#edit_occasion').val(occasion_id);
    $('#edit_status').val(status);
    $('#edit_requests').val(requests);

    $('#edit_c_name').val(c_name);
    $('#edit_c_phone').val(c_phone);
    $('#edit_c_email').val(c_email);

    $('#editBookingModal').modal('show');
}

// ==========================================
// 4. Delete Booking (Normal Form Submit with SweetAlert)
// ==========================================
window.deleteBooking = function(id, bookingId) {
    Swal.fire({
        title: 'Cancel Booking?',
        text: "You are about to cancel booking " + bookingId + ". The table will be released.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#21352a',
        confirmButtonText: 'Yes, Cancel it!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('table-booking.destroy', ':id') }}".replace(':id', id);

            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="DELETE">
            `;

            document.body.appendChild(form);
            form.submit();
        }
    });
}

// ==========================================
// 5. Occasion Management (AJAX without Reload)
// ==========================================
function updateOccasionDropdowns(occasions) {
    let filterHtml = '<option value="">All Occasions</option>';
    let formHtml = '<option value="">Select occasion (optional)</option>';

    occasions.forEach(function(occ) {
        filterHtml += `<option value="${occ.id}">${occ.name}</option>`;
        formHtml += `<option value="${occ.id}">${occ.name}</option>`;
    });

    $('#filterOccasion').html(filterHtml);

    let addSelect = document.querySelector('select[name="occasion_id"]');
    if (addSelect && addSelect.choices) {
        let choiceList = occasions.map(occ => ({ value: occ.id.toString(), label: occ.name }));
        choiceList.unshift({ value: '', label: 'Select occasion (optional)', selected: true });
        addSelect.choices.clearChoices();
        addSelect.choices.setChoices(choiceList, 'value', 'label', true);
    } else {
        $('select[name="occasion_id"]').html(formHtml);
    }

    let editSelect = document.getElementById('edit_occasion');
    if (editSelect && editSelect.choices) {
        let choiceList = occasions.map(occ => ({ value: occ.id.toString(), label: occ.name }));
        choiceList.unshift({ value: '', label: 'Select occasion (optional)', selected: true });
        editSelect.choices.clearChoices();
        editSelect.choices.setChoices(choiceList, 'value', 'label', true);
    } else {
        $('#edit_occasion').html(formHtml);
    }
}

$('#addOccasionModal').on('show.bs.modal', function () {
    loadOccasions("{{ route('occasion.index') }}");
});

function loadOccasions(url) {
    $.ajax({
        url: url,
        type: "GET",
        success: function(response) {
            $('#occasionTableContent').html(response.html);
        }
    });
}

$(document).on('click', '.occasion-pagination .pagination a', function(e){
    e.preventDefault();
    loadOccasions($(this).attr('href'));
});

$('#occasionAjaxForm').on('submit', function(e) {
    e.preventDefault();

    let id = $('#occasion_id').val();
    let formData = $(this).serialize();
    let url = id ? "{{ url('admin/occasion') }}/" + id : "{{ route('occasion.store') }}";

    if(id) { formData += "&_method=PUT"; }

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
                resetOccasionForm();
                loadOccasions("{{ route('occasion.index') }}");

                if(res.occasions) {
                    updateOccasionDropdowns(res.occasions);
                }
            } else {
                showToast('Error', res.message, 'error');
            }
        },
        error: function(err) {
            showToast('Error', 'Something went wrong!', 'error');
        }
    });
});

window.editOccasion = function(id, name, status) {
    $('#occasionFormTitle').text('Edit Occasion');
    $('#occasionSubmitBtn').html('<i class="bi bi-arrow-repeat"></i> Update');
    $('#occasionResetBtn').show();

    $('#occasion_id').val(id);
    $('#occasion_name').val(name);
    $('#occasion_status').val(status);
}

window.resetOccasionForm = function() {
    $('#occasionFormTitle').text('Add New Occasion');
    $('#occasionSubmitBtn').html('<i class="bi bi-check-lg"></i> Save');
    $('#occasionResetBtn').hide();

    $('#occasionAjaxForm')[0].reset();
    $('#occasion_id').val('');
}

window.deleteOccasion = function(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#21352a',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "{{ url('admin/occasion') }}/" + id,
                type: 'POST',
                data: { _token: "{{ csrf_token() }}", _method: 'DELETE' },
                success: function(res) {
                    if(res.status === 'success') {
                        showToast('Deleted!', res.message, 'success');
                        loadOccasions("{{ route('occasion.index') }}");

                        if(res.occasions) {
                            updateOccasionDropdowns(res.occasions);
                        }
                    } else {
                        showToast('Error', res.message, 'error');
                    }
                }
            });
        }
    });
}
</script>
@endsection
