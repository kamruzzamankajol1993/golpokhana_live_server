@extends('admin.master.master')
@section('title', 'Cuisine Types — Progga RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Cuisine Types</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Cuisine Types</span>
            </div>
        </div>
        @can('cuisine-type-create')
        <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#addCuisineModal">
            <i class="bi bi-plus-lg"></i> Add Cuisine
        </button>
        @endcan
    </div>

    <div class="progga-card" style="margin-bottom:16px;">
        <div class="progga-filters-bar">
            <div class="progga-search" style="flex:1;min-width:200px;max-width:300px;">
                <i class="bi bi-search progga-search-icon"></i>
                <input type="text" class="progga-form-control progga-table-search" placeholder="Search cuisine types...">
            </div>
            <div style="min-width:150px;">
                <select class="progga-select" id="cuiStatusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="progga-card" id="cuisine_data_container">
        @include('admin.cuisine_type.cuisine_table')
    </div>
</main>

@include('admin.cuisine_type.modals.add_cuisine')
@include('admin.cuisine_type.modals.edit_cuisine')
@endsection

@section('script')
<script>
// AJAX Fetch Data
function fetchCuisines(url) {
    let search = $('.progga-table-search').val();
    let status = $('#cuiStatusFilter').val();
    $('#cuisine_data_container').css('opacity', '0.6');
    $.ajax({
        url: url, data: { search: search, status: status },
        success: function(data) { $('#cuisine_data_container').html(data).css('opacity', '1'); }
    });
}

$(document).on('change', '#cuiStatusFilter', function() { fetchCuisines("{{ route('cuisine-type.index') }}"); });
let timer;
$(document).on('keyup', '.progga-table-search', function() {
    clearTimeout(timer);
    timer = setTimeout(() => { fetchCuisines("{{ route('cuisine-type.index') }}"); }, 500);
});
$(document).on('click', '.cuisine-pagination a', function(e) {
    e.preventDefault(); fetchCuisines($(this).attr('href'));
});

// Dynamic Toggle Label
$(document).on('change', '.progga-toggle input[type="checkbox"]', function() {
    let label = $(this).siblings('.progga-toggle-label');
    if(label.length) label.text($(this).is(':checked') ? $(this).data('on') : $(this).data('off'));
});

// Inline Status Update
window.toggleCuisineStatus = function(id, element) {
    let status = $(element).is(':checked') ? 1 : 0;
    $.ajax({
        url: "{{ url('cuisine-type-status') }}/" + id,
        type: 'POST',
        data: { _token: "{{ csrf_token() }}", status: status },
        success: function(res) {
            if(res.status === 'success') showToast('Success', res.message, 'success');
            else { showToast('Error', res.message, 'error'); $(element).prop('checked', !status).trigger('change'); }
        }
    });
}

// Store
$('#addCuisineForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: "{{ route('cuisine-type.store') }}",
        type: 'POST', data: $(this).serialize(),
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
                $('#addCuisineModal').modal('hide');
                $('#addCuisineForm')[0].reset();
                fetchCuisines("{{ route('cuisine-type.index') }}");
            }
        }
    });
});

// Edit Setup
window.editCuisine = function(id, name, country, desc, status) {
    $('#edit_cuisine_id').val(id);
    $('#edit_name').val(name);
    $('#edit_country').val(country);
    $('#edit_description').val(desc);
    $('#edit_status').prop('checked', status == 1).trigger('change');
    $('#editCuisineModal').modal('show');
}

// Update
$('#editCuisineForm').on('submit', function(e) {
    e.preventDefault();
    let id = $('#edit_cuisine_id').val();
    $.ajax({
        url: "{{ route('cuisine-type.update', ':id') }}".replace(':id', id),
        type: 'PUT', data: $(this).serialize(),
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
                $('#editCuisineModal').modal('hide');
                fetchCuisines("{{ route('cuisine-type.index') }}");
            }
        }
    });
});

// Delete (SweetAlert Form Submit)
window.deleteCuisine = function(id, name) {
    Swal.fire({
        title: 'Delete Cuisine?', text: "You are about to delete '" + name + "'.", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#21352a', confirmButtonText: 'Yes, Delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('cuisine-type.destroy', ':id') }}".replace(':id', id);
            form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="DELETE">`;
            document.body.appendChild(form); form.submit();
        }
    });
}
</script>
@endsection
