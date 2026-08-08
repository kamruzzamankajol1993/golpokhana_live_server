@extends('admin.master.master')

@section('title', 'Permissions — ' . ($restaurantSettingName ?? 'Progga RMS'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Permissions</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Permissions</span>
            </div>
        </div>
        <div>
            @can('permission-create')
    <a href="{{ route('permission.create') }}" class="progga-btn progga-btn-primary">
        <i class="bi bi-plus-lg"></i> Add New Permission
    </a>
    @endcan
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header">
            <div class="progga-card-title">Permission List</div>
            <div class="progga-input-group" style="width: 250px;">
                <input type="text" id="search-permission" class="progga-form-control progga-form-control-sm" placeholder="Search permission...">
                <span class="progga-input-addon"><i class="bi bi-search"></i></span>
            </div>
        </div>
        <div class="progga-card-body" style="padding:0;">
            <div id="permission-table-container">
                @include('admin.permission.table')
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        // Pagination এ ক্লিক করলে AJAX কল
        $(document).on('click', '.progga-pagination a', function(event) {
            event.preventDefault();
            let page = $(this).attr('href').split('page=')[1];
            fetch_data(page);
        });

        // সার্চ ইনপুট এর জন্য AJAX
        $('#search-permission').on('keyup', function() {
            fetch_data(1);
        });

        function fetch_data(page) {
            let search = $('#search-permission').val();
            $.ajax({
                url: "{{ route('permission.index') }}?page=" + page + "&search=" + search,
                success: function(data) {
                    $('#permission-table-container').html(data);
                }
            });
        }
    });
</script>
@endsection
