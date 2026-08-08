@extends('admin.master.master')

@section('title', 'Create Permission — ' . ($restaurantSettingName ?? 'Progga RMS'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Add Permissions</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <a href="{{ route('permission.index') }}" class="progga-breadcrumb-item">Permissions</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Add Multiple</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="progga-card">
                <div class="progga-card-body">
                    <form action="{{ route('permission.store') }}" method="POST">
                        @csrf
                        <div class="progga-form-group mb-4">
                            <label class="progga-form-label">Group Name <span class="progga-required">*</span></label>
                            <input type="text" name="group_name" class="progga-form-control" placeholder="e.g. User Management" required>
                        </div>

                        <div id="permissions-wrapper">
                            <label class="progga-form-label">Permission Names <span class="progga-required">*</span></label>
                            <div class="input-group mb-3 permission-row">
                                <input type="text" name="permissions[]" class="progga-form-control" placeholder="e.g. user-create" required>
                                <button class="progga-btn progga-btn-danger remove-field" type="button" style="display:none;"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>

                        <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm mb-4" id="add-field-btn">
                            <i class="bi bi-plus-circle"></i> Add Another
                        </button>

                        <div class="progga-divider"></div>
                        <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-save"></i> Save Permissions</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        $('#add-field-btn').click(function() {
            let html = `
                <div class="input-group mb-3 permission-row progga-animate-fadein">
                    <input type="text" name="permissions[]" class="progga-form-control" placeholder="e.g. next-permission" required>
                    <button class="progga-btn progga-btn-danger remove-field" type="button"><i class="bi bi-trash"></i></button>
                </div>`;
            $('#permissions-wrapper').append(html);
        });

        $(document).on('click', '.remove-field', function() {
            $(this).closest('.permission-row').remove();
        });
    });
</script>
@endsection
