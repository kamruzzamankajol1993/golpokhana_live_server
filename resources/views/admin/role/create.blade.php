@extends('admin.master.master')
@section('title', 'Create Role — ' . ($restaurantSettingName ?? 'TableTrack RMS'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Create Role</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Add Role</span>
            </div>
        </div>
    </div>

    <form action="{{ route('role.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-xl-4">
                <div class="progga-card">
                    <div class="progga-card-body">
                        <div class="progga-form-group">
                            <label class="progga-form-label">Role Name <span class="progga-required">*</span></label>
                            <input type="text" name="name" class="progga-form-control @error('name') is-invalid @enderror" placeholder="e.g. Manager" required>
                            @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="progga-btn progga-btn-primary w-100 justify-content-center">
                            <i class="bi bi-save"></i> Save Role
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="progga-card">
                    <div class="progga-card-header">
                        <div class="progga-card-title">Assign Permissions</div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="checkAll">
                            <label class="form-check-label" for="checkAll">Select All Permissions</label>
                        </div>
                    </div>
                    <div class="progga-card-body">
                        @foreach($permissions as $groupName => $groupPermissions)
                        <div class="permission-group-wrapper mb-4" style="border: 1px solid var(--progga-border-light); border-radius: var(--progga-radius); padding: 15px;">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2" style="border-bottom: 1px solid var(--progga-border-light);">
                                <h6 class="mb-0 text-uppercase" style="font-weight: 800; color: var(--progga-primary);">{{ $groupName }}</h6>
                                <div class="form-check">
                                    <input class="form-check-input group-checkbox" type="checkbox" id="group-{{ Str::slug($groupName) }}">
                                    <label class="form-check-label" for="group-{{ Str::slug($groupName) }}">Select Group</label>
                                </div>
                            </div>
                            <div class="row">
                                @foreach($groupPermissions as $permission)
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input individual-checkbox" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm-{{ $permission->id }}">
                                        <label class="form-check-label" for="perm-{{ $permission->id }}">{{ $permission->name }}</label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        // Global Select All
        $('#checkAll').click(function() {
            $('.individual-checkbox, .group-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Group-wise Select All
        $('.group-checkbox').click(function() {
            $(this).closest('.permission-group-wrapper').find('.individual-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Individual checkbox handle
        $('.individual-checkbox').click(function() {
            let groupWrapper = $(this).closest('.permission-group-wrapper');
            let allIndividualChecked = groupWrapper.find('.individual-checkbox:checked').length === groupWrapper.find('.individual-checkbox').length;
            groupWrapper.find('.group-checkbox').prop('checked', allIndividualChecked);

            let allChecked = $('.individual-checkbox:checked').length === $('.individual-checkbox').length;
            $('#checkAll').prop('checked', allChecked);
        });
    });
</script>
@endsection
