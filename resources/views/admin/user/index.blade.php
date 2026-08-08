@extends('admin.master.master')
@section('title', 'Users Management — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">User Management</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Users</span>
            </div>
        </div>
        <div>
            @can('user-create')
            <a href="{{ route('user.create') }}" class="progga-btn progga-btn-primary">
                <i class="bi bi-person-plus-fill"></i> Add New User
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="font-size:13.5px;"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="font-size:13.5px;"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="progga-card">
        <div class="progga-card-header">
            <div class="progga-card-title">System Users List</div>

            <div class="progga-input-group" style="width: 280px;">
                <input type="text" id="search-user" class="progga-form-control progga-form-control-sm" placeholder="Search name, email, or phone...">
                <span class="progga-input-addon"><i class="bi bi-search"></i></span>
            </div>
        </div>
        <div class="progga-card-body" style="padding:0;">

            <div id="user-table-container">
                @include('admin.user.table')
            </div>

        </div>
    </div>
</main>
@endsection

@section('script')
<script>
    $(document).ready(function() {

        // AJAX Pagination Function
        $(document).on('click', '.progga-pagination a', function(event) {
            event.preventDefault();
            let page = $(this).attr('href').split('page=')[1];
            fetch_users(page);
        });

        // AJAX Search Function
        $('#search-user').on('keyup', function() {
            fetch_users(1);
        });

        function fetch_users(page) {
            let search = $('#search-user').val();
            $.ajax({
                url: "{{ route('user.index') }}?page=" + page + "&search=" + search,
                success: function(data) {
                    $('#user-table-container').html(data);
                },
                error: function() {
                    console.error("Failed to load table data");
                }
            });
        }

    });
</script>
@endsection
