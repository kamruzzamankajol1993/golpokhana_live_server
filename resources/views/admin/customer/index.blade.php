@extends('admin.master.master')
@section('title', 'Customers — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Customers</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Customers</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('reward-edit')
            <a href="{{ route('reward-points.index') }}" class="progga-btn progga-btn-secondary">
                <i class="bi bi-star-fill"></i> Reward Settings
            </a>
            @endcan

            @can('customer-create')
            <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="bi bi-plus-lg"></i> Add Customer
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
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon primary"><i class="bi bi-people-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Total Customers</div>
                    <div class="progga-stat-value">{{ $totalCustomers }}</div>
                    <div class="progga-stat-change neutral"><i class="bi bi-dash"></i> lifetime</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon secondary"><i class="bi bi-star-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Loyalty Members</div>
                    <div class="progga-stat-value">{{ $loyaltyMembers }}</div>
                    <div class="progga-stat-change up" style="font-size: 11px;">Points > 5</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon success"><i class="bi bi-arrow-repeat"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Repeat Visitors</div>
                    <div class="progga-stat-value">{{ $repeatVisitors }}</div>
                    <div class="progga-stat-change neutral"><i class="bi bi-dash"></i> lifetime</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon warning"><i class="bi bi-coin"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Total Points Issued</div>
                    <div class="progga-stat-value">{{ $totalPointsIssued }}</div>
                    <div class="progga-stat-change neutral"><i class="bi bi-dash"></i> lifetime</div>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card" style="margin-bottom:16px;">
        <div class="progga-filters-bar">
            <div class="progga-search" style="flex:1;min-width:200px;max-width:320px;">
                <i class="bi bi-search progga-search-icon"></i>
                <input type="text" id="customerSearch" class="progga-form-control" placeholder="Search by name, phone, email...">
            </div>
            <div style="min-width:160px;">
                <select class="progga-select" id="customerFilter" data-placeholder="Filter">
                    <option value="">All Customers</option>
                    <option value="Loyalty Members">Loyalty Members</option>
                    <option value="New (30 days)">New (30 days)</option>
                </select>
            </div>
         <div style="margin-left:auto; display:flex; gap:8px;">
                <a href="{{ route('customer.export.pdf') }}" target="_blank" class="progga-btn progga-btn-outline progga-btn-sm" style="color: #dc3545; border-color: #dc3545;">
                    <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                </a>

                <a href="{{ route('customer.export.excel') }}" class="progga-btn progga-btn-outline progga-btn-sm" style="color: #198754; border-color: #198754;">
                    <i class="bi bi-file-earmark-excel-fill"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <div class="progga-card" id="customerTableContainer">
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        </div>
    </div>

</main>

@include('admin.customer.modals.add_customer')
@include('admin.customer.modals.edit_customer')
@include('admin.customer.modals.history_customer')

@endsection

@section('script')
<script>
$(document).ready(function() {
    function fetchCustomers(page = 1) {
        let search = $('#customerSearch').val();
        let filter = $('#customerFilter').val();

        $.ajax({
            url: "{{ route('customer.index') }}?page=" + page,
            type: "GET",
            data: { search: search, filter: filter },
            success: function(data) {
                $('#customerTableContainer').html(data);
            }
        });
    }

    $('#customerSearch').on('keyup', function() { fetchCustomers(); });
    $('#customerFilter').on('change', function() { fetchCustomers(); });

    $(document).on('click', '#customerTableContainer .pagination a', function(e) {
        e.preventDefault();
        let page = $(this).attr('href').split('page=')[1];
        fetchCustomers(page);
    });

    fetchCustomers(); // Initial load
});

// Edit Button Logic
// Edit Button Logic
window.editCustomerData = function(id, name, phone, email, dob, address, points) {
    let formAction = "{{ route('customer.update', ':id') }}".replace(':id', id);
    $('#editCustomerForm').attr('action', formAction);

    $('#edit_c_name').val(name);
    $('#edit_c_phone').val(phone);
    $('#edit_c_email').val(email);
    $('#edit_c_dob').val(dob);
    $('#edit_c_address').val(address);

    // বর্তমান পয়েন্ট শো করা
    $('#current_points_display').text(points);

    // ফর্ম ওপেন হওয়ার সময় অ্যাডজাস্টমেন্ট ফিল্ড ডিফল্টভাবে ০ এবং নোট ফাঁকা করে দেওয়া
    $('input[name="point_adjustment"]').val(0);
    $('input[name="point_note"]').val('');

    $('#editCustomerModal').modal('show');
}

// History Modal Logic
window.viewCustomerHistory = function(id) {
    // লোডিং স্পিনার দেখানো
    $('#customerHistoryContent').html('<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');

    // মোডাল ওপেন করা
    $('#customerHistoryModal').modal('show');

    // Ajax দিয়ে ডাটা নিয়ে আসা
   $.ajax({
        url: "{{ route('customer.history', ':id') }}".replace(':id', id),
        type: "GET",
        success: function(data) {
            $('#customerHistoryContent').html(data);
        },
        error: function() {
            $('#customerHistoryContent').html('<div class="text-center py-4 text-danger">Failed to load history!</div>');
        }
    });
}
</script>
@endsection
