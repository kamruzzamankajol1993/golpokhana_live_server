@extends('admin.master.master')
@section('title', 'Food Attributes — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Food Attributes</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Allergens & Courses</span>
            </div>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div> @endif

    <div class="row g-4">
        <div class="col-12">
            <div class="progga-card">
                <div class="progga-card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold"><i class="bi bi-exclamation-circle text-danger me-2"></i>Allergens</h5>
                    @can('allergen-create')
                    <button class="progga-btn progga-btn-primary" onclick="addModal('allergen')">
                        <i class="bi bi-plus-lg"></i> Add New
                    </button>
                    @endcan
                </div>
                <div id="allergen_data_container">
                    @include('admin.food_attributes.allergen_table')
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="progga-card">
                <div class="progga-card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold"><i class="bi bi-ui-radios text-info me-2"></i>Course Types</h5>
                    @can('course-type-create')
                    <button class="progga-btn progga-btn-primary" onclick="addModal('course-type')">
                        <i class="bi bi-plus-lg"></i> Add New
                    </button>
                    @endcan
                </div>
                <div id="course_type_data_container">
                    @include('admin.food_attributes.course_type_table')
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade progga-modal" id="attributeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="attributeForm">
                @csrf
                <input type="hidden" id="item_id">
                <input type="hidden" id="item_type">
                <div class="modal-body">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Name <span class="progga-required">*</span></label>
                        <input type="text" name="name" id="item_name" class="progga-form-control" required>
                    </div>
                    <div class="progga-form-group mb-0">
                        <label class="progga-form-label">Status</label>
                        <label class="progga-toggle mt-1">
                            <input type="checkbox" name="is_active" id="item_status" value="1" checked>
                            <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary" id="saveBtn"><i class="bi bi-check-lg"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
// --- AJAX Fetch Data ---
function fetchTable(url, type) {
    let container = type === 'allergen' ? '#allergen_data_container' : '#course_type_data_container';
    $(container).css('opacity', '0.6');
    $.ajax({
        url: url,
        success: function(data) { $(container).html(data).css('opacity', '1'); }
    });
}

$(document).on('click', '.allergen-pagination a', function(e) {
    e.preventDefault(); fetchTable($(this).attr('href'), 'allergen');
});

$(document).on('click', '.course-pagination a', function(e) {
    e.preventDefault(); fetchTable($(this).attr('href'), 'course-type');
});

// --- Modal Helpers ---
window.addModal = function(type) {
    $('#attributeForm')[0].reset();
    $('#item_id').val('');
    $('#item_type').val(type);
    $('#modalTitle').text(type === 'allergen' ? 'Add Allergen' : 'Add Course Type');
    $('#attributeModal').modal('show');
}

window.editModal = function(type, id, name, status) {
    $('#item_id').val(id);
    $('#item_type').val(type);
    $('#item_name').val(name);
    $('#item_status').prop('checked', status == 1);
    $('#modalTitle').text(type === 'allergen' ? 'Edit Allergen' : 'Edit Course Type');
    $('#attributeModal').modal('show');
}

// --- Submit Form (AJAX) using route() ---
$('#attributeForm').on('submit', function(e) {
    e.preventDefault();
    let type = $('#item_type').val();
    let id = $('#item_id').val();
    let url = "";

    // সঠিক রুট নির্ধারণ
    if (type === 'allergen') {
        url = id ? "{{ route('allergen.update', ':id') }}".replace(':id', id) : "{{ route('allergen.store') }}";
    } else {
        url = id ? "{{ route('course-type.update', ':id') }}".replace(':id', id) : "{{ route('course-type.store') }}";
    }

    let formData = $(this).serialize();
    if(id) { formData += "&_method=PUT"; }

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
                $('#attributeModal').modal('hide');
                // রিলোড করার জন্য ইনডেক্স রুট ব্যবহার
                let reloadUrl = type === 'allergen' ? "{{ route('allergen.index') }}" : "{{ route('course-type.index') }}";
                fetchTable(reloadUrl, type);
            }
        }
    });
});

// --- Inline Status Toggle (AJAX) using route() ---
window.toggleStatus = function(id, element, type) {
    let status = $(element).is(':checked') ? 1 : 0;
    let url = "";

    if (type === 'allergen') {
        url = "{{ route('allergen.status', ':id') }}".replace(':id', id);
    } else {
        url = "{{ route('course-type.status', ':id') }}".replace(':id', id);
    }

    $.ajax({
        url: url,
        type: 'POST',
        data: { _token: "{{ csrf_token() }}", status: status },
        success: function(res) {
            if(res.status === 'success') showToast('Success', res.message, 'success');
            else { showToast('Error', res.message, 'error'); $(element).prop('checked', !status); }
        }
    });
}

// --- Normal Delete with SweetAlert using route() ---
window.normalDelete = function(id, name, type) {
    Swal.fire({
        title: 'Delete Item?',
        text: "You are about to delete '" + name + "'.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#21352a',
        confirmButtonText: 'Yes, Delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';

            // রুট নির্ধারণ
            let actionUrl = "";
            if (type === 'allergen') {
                actionUrl = "{{ route('allergen.destroy', ':id') }}".replace(':id', id);
            } else {
                actionUrl = "{{ route('course-type.destroy', ':id') }}".replace(':id', id);
            }

            form.action = actionUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
@endsection
