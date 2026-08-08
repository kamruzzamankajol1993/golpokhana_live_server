@extends('admin.master.master')
@section('title', 'Roles — ' . ($restaurantSettingName ?? 'TableTrack RMS'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Role Management</h1>
        </div>
        @can('role-create')
        <a href="{{ route('role.create') }}" class="progga-btn progga-btn-primary"><i class="bi bi-plus-lg"></i> Create Role</a>
        @endcan
    </div>

    <div class="progga-card">
        <div id="role-table-container">
            @include('admin.role.table')
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
    $(document).on('click', '.progga-pagination a', function(e) {
        e.preventDefault();
        let page = $(this).attr('href').split('page=')[1];
        $.ajax({
            url: "{{ route('role.index') }}?page=" + page,
            success: function(data) { $('#role-table-container').html(data); }
        });
    });
</script>
@endsection
