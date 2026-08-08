@extends('admin.master.master')
@section('title', 'Waiters — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Waiters</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Waiters</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('zone-view')
            <button class="progga-btn progga-btn-secondary" data-bs-toggle="modal" data-bs-target="#zoneModal">
                <i class="bi bi-geo-alt-fill"></i> Zones
            </button>
            @endcan

            @can('shift-view')
            <button class="progga-btn progga-btn-info" data-bs-toggle="modal" data-bs-target="#shiftModal">
                <i class="bi bi-clock-history"></i> Shifts
            </button>
            @endcan

            @can('waiter-create')
            <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#addWaiterModal">
                <i class="bi bi-plus-lg"></i> Add Waiter
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
                <div class="progga-stat-icon primary"><i class="bi bi-person-badge-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Total Waiters</div>
                    <div class="progga-stat-value">{{ $totalWaiters }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Active</div>
                    <div class="progga-stat-value">{{ $activeWaiters }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon warning"><i class="bi bi-clock-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">On Duty Now</div>
                    <div class="progga-stat-value">{{ $activeWaiters }}</div> </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="progga-stat-card">
                <div class="progga-stat-icon danger"><i class="bi bi-x-circle-fill"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Inactive</div>
                    <div class="progga-stat-value">{{ $inactiveWaiters }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card" style="margin-bottom:16px;">
        <div class="progga-filters-bar">
            <div class="progga-search" style="flex:1;min-width:200px;max-width:320px;">
                <i class="bi bi-search progga-search-icon"></i>
                <input type="text" id="waiterSearch" class="progga-form-control" placeholder="Search waiters...">
            </div>
            <div style="min-width:160px;">
                <select class="progga-select" id="zoneFilter">
                    <option value="">All Zones</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:160px;">
                <select class="progga-select" id="shiftFilter">
                    <option value="">All Shifts</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:140px;">
                <select class="progga-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="progga-card" id="waiterTableContainer">
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        </div>
    </div>

</main>

@include('admin.waiter.modals.add_waiter')
@include('admin.waiter.modals.edit_waiter')
@include('admin.waiter.modals.zone_modal')
@include('admin.waiter.modals.shift_modal')

@endsection

@section('script')
<script>
    $(document).ready(function() {

        // ==========================================
        // 1. Waiter Table Ajax & Filter Logic
        // ==========================================
        function fetchWaiters(page = 1) {
            let search = $('#waiterSearch').val();
            let zone_id = $('#zoneFilter').val();
            let shift_id = $('#shiftFilter').val();
            let status = $('#statusFilter').val();

            $.ajax({
                url: "{{ route('waiter.index') }}?page=" + page,
                type: "GET",
                data: { search: search, zone_id: zone_id, shift_id: shift_id, status: status },
                success: function(data) {
                    $('#waiterTableContainer').html(data);
                },
                error: function() {
                    $('#waiterTableContainer').html('<div class="text-center text-danger py-4">Failed to load data.</div>');
                }
            });
        }

        $('#waiterSearch').on('keyup', function() { fetchWaiters(); });
        $('#zoneFilter, #shiftFilter, #statusFilter').on('change', function() { fetchWaiters(); });

        $(document).on('click', '#waiterTableContainer .pagination a', function(e) {
            e.preventDefault();
            let page = $(this).attr('href').split('page=')[1];
            fetchWaiters(page);
        });

        // Initial Load
        fetchWaiters();


        function refreshZoneDropdowns() {
        $.get("{{ route('zone.index') }}?dropdown=1", function(data) {
            // ওয়েটার মোডাল এবং ফিল্টার ড্রপডাউনগুলো সিলেক্ট করা
            let selectors = $('select[name="zone_id"], #zoneFilter');
            selectors.each(function() {
                let currentSelect = $(this);
                let placeholder = currentSelect.find('option:first').text();
                currentSelect.empty().append(`<option value="">${placeholder}</option>`);

                data.forEach(item => {
                    currentSelect.append(`<option value="${item.id}">${item.name}</option>`);
                });

                // Select2 থাকলে সেটি রিফ্রেশ করা
                if (currentSelect.hasClass('select2-hidden-accessible')) {
                    currentSelect.select2('destroy').select2({ theme: 'progga-theme', width: '100%' });
                }
            });
        });
    }

    function refreshShiftDropdowns() {
        $.get("{{ route('shift.index') }}?dropdown=1", function(data) {
            let selectors = $('select[name="shift_id"], #shiftFilter');
            selectors.each(function() {
                let currentSelect = $(this);
                let placeholder = currentSelect.find('option:first').text();
                currentSelect.empty().append(`<option value="">${placeholder}</option>`);

                data.forEach(item => {
                    currentSelect.append(`<option value="${item.id}">${item.name}</option>`);
                });

                if (currentSelect.hasClass('select2-hidden-accessible')) {
                    currentSelect.select2('destroy').select2({ theme: 'progga-theme', width: '100%' });
                }
            });
        });
    }


        // ==========================================
        // 2. Zone Ajax Logic (Create, Update, Fetch)
        // ==========================================
        function loadZoneTable(page = 1) {
            $.get("{{ route('zone.index') }}?page=" + page, function(data) {
                $('#zoneTableBody').html(data);
            });
        }

        $('#zoneModal').on('shown.bs.modal', function () { loadZoneTable(); });

        $('#zoneForm').submit(function(e) {
            e.preventDefault();
            let id = $('#zone_id').val();
            let url = id ? "{{ route('zone.update', ':id') }}".replace(':id', id) : "{{ route('zone.store') }}";
            let method = id ? "PUT" : "POST";

            $.ajax({
                url: url,
                type: method,
                data: $(this).serialize(),
                success: function(res) {
                    if(res.success) {
                        $('#zoneForm')[0].reset();
                        $('#zone_id').val('');
                        $('#zone_status').prop('checked', true); // নতুন ডাটা অ্যাড করার পর চেকবক্স অটো একটিভ হবে
                        $('#zoneSubmitBtn').text('Save');
                        refreshZoneDropdowns();
                        loadZoneTable();
                        if(typeof window.proggaToast === 'function') {
                            window.proggaToast(res.message, 'success');
                        } else {
                            alert(res.message);
                        }
                    }
                },
                error: function(err) {
                    alert("Something went wrong!");
                }
            });
        });

        window.editZoneAjax = function(id, name, status) {
            $('#zone_id').val(id);
            $('#zone_name').val(name);
            $('#zone_status').prop('checked', status == 1); // এডিট করার সময় স্ট্যাটাস ঠিকমতো লোড হবে
            $('#zoneSubmitBtn').text('Update');
        }

        $(document).on('click', '#zoneTableBody .pagination a', function(e) {
            e.preventDefault();
            let page = $(this).attr('href').split('page=')[1];
            loadZoneTable(page);
        });


        // ==========================================
        // 3. Shift Ajax Logic (Create, Update, Fetch)
        // ==========================================
        function loadShiftTable(page = 1) {
            $.get("{{ route('shift.index') }}?page=" + page, function(data) {
                $('#shiftTableBody').html(data);
            });
        }

        $('#shiftModal').on('shown.bs.modal', function () { loadShiftTable(); });

        $('#shiftForm').submit(function(e) {
            e.preventDefault();
            let id = $('#shift_id').val();
            let url = id ? "{{ route('shift.update', ':id') }}".replace(':id', id) : "{{ route('shift.store') }}";
            let method = id ? "PUT" : "POST";

            $.ajax({
                url: url,
                type: method,
                data: $(this).serialize(),
                success: function(res) {
                    if(res.success) {
                        $('#shiftForm')[0].reset();
                        $('#shift_id').val('');
                        $('#shift_status').prop('checked', true); // নতুন ডাটা অ্যাড করার পর চেকবক্স অটো একটিভ হবে
                        $('#shiftSubmitBtn').text('Save');
                        loadShiftTable();
                        refreshShiftDropdowns();
                        if(typeof window.proggaToast === 'function') {
                            window.proggaToast(res.message, 'success');
                        } else {
                            alert(res.message);
                        }
                    }
                },
                error: function(err) {
                    alert("Something went wrong!");
                }
            });
        });

        window.editShiftAjax = function(id, name, status) {
            $('#shift_id').val(id);
            $('#shift_name').val(name);
            $('#shift_status').prop('checked', status == 1); // এডিট করার সময় স্ট্যাটাস ঠিকমতো লোড হবে
            $('#shiftSubmitBtn').text('Update');
        }

        $(document).on('click', '#shiftTableBody .pagination a', function(e) {
            e.preventDefault();
            let page = $(this).attr('href').split('page=')[1];
            loadShiftTable(page);
        });

        // ==========================================
        // Waiter Status Dynamic Update Logic
        // ==========================================
        $(document).on('change', '.waiter-status-toggle', function() {
            let statusCheckbox = $(this);
            let id = statusCheckbox.data('id');
            let status = statusCheckbox.prop('checked') ? 1 : 0;
            let label = statusCheckbox.siblings('.progga-toggle-label');

            $.ajax({
                url: "{{ route('waiter.status') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    id: id,
                    status: status
                },
                success: function(res) {
                    if(res.success) {
                        // লেবেলের টেক্সট পরিবর্তন করা (Active/Inactive)
                        label.text(status === 1 ? 'Active' : 'Inactive');

                        if(typeof window.proggaToast === 'function') {
                            window.proggaToast(res.message, 'success');
                        }

                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        // এরর হলে চেকবক্স আগের অবস্থায় ফিরিয়ে আনা
                        statusCheckbox.prop('checked', !status);
                        alert(res.message);
                    }
                },
                error: function() {
                    statusCheckbox.prop('checked', !status);
                    alert("Something went wrong!");
                }
            });
        });

    });
</script>
@endsection
