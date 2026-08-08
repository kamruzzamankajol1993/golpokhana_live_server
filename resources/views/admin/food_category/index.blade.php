@extends('admin.master.master')
@section('title', 'Food Categories — TableTrack RMS')

@section('body')
<main class="progga-content">

    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Food Categories</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Food Categories</span>
            </div>
        </div>
        <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-lg"></i> Add Category
        </button>
    </div>

    <div class="progga-card" style="margin-bottom:16px;">
        <div class="progga-filters-bar">
            <div class="progga-search" style="flex:1;min-width:200px;max-width:320px;">
                <i class="bi bi-search progga-search-icon"></i>
                <input type="text" class="progga-form-control progga-table-search" placeholder="Search categories...">
            </div>
            <div style="min-width:160px;">
                <select class="progga-select" id="catStatusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="progga-card" id="category_data_container">
        @include('admin.food_category.category_table')
    </div>

</main>

@include('admin.food_category.modals.add_category')
@include('admin.food_category.modals.edit_category')

@endsection

@section('script')
<script>
// ==========================================
// 1. Image Preview Helper
// ==========================================
function previewImage(input, previewId, placeholderId) {
    var f = input.files[0];
    if(!f) return;
    var r = new FileReader();
    r.onload = function(e) {
        var p = document.getElementById(previewId);
        var pl = document.getElementById(placeholderId);
        p.src = e.target.result;
        p.style.display = 'block';
        pl.style.display = 'none';
    };
    r.readAsDataURL(f);
}

// ==========================================
// 2. AJAX Fetch, Filter & Pagination
// ==========================================
function fetchCategories(url) {
    let search = $('.progga-table-search').val();
    let status = $('#catStatusFilter').val();
    $('#category_data_container').css('opacity', '0.6');

    $.ajax({
        url: url,
        data: { search: search, status: status },
        success: function(data) {
            $('#category_data_container').html(data).css('opacity', '1');
        }
    });
}

$(document).on('change', '#catStatusFilter', function() {
    fetchCategories("{{ route('food-category.index') }}");
});

let timer;
$(document).on('keyup', '.progga-table-search', function() {
    clearTimeout(timer);
    timer = setTimeout(() => { fetchCategories("{{ route('food-category.index') }}"); }, 500);
});

$(document).on('click', '.category-pagination a', function(e) {
    e.preventDefault();
    fetchCategories($(this).attr('href'));
});

// ==========================================
// 3. Store Category (AJAX)
// ==========================================
$('#addCategoryForm').on('submit', function(e) {
    e.preventDefault();
    let formData = new FormData(this);

    $.ajax({
        url: "{{ route('food-category.store') }}",
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
                $('#addCategoryModal').modal('hide');
                $('#addCategoryForm')[0].reset();
                $('#addCatPreview').hide();
                $('#addCatPlaceholder').show();
                fetchCategories("{{ route('food-category.index') }}");
                setTimeout(() => { window.location.reload(); }, 1500); // Reload to update Parent Category dropdowns
            } else {
                showToast('Error', res.message, 'error');
            }
        }
    });
});

// ==========================================
// 4. Edit Category
// ==========================================
window.editCategory = function(id, name, slug, parent_id, status, imageUrl, sort_order) {
    $('#edit_category_id').val(id);
    $('#edit_name').val(name);
    $('#edit_slug').val(slug);
    $('#edit_parent_id').val(parent_id);
    $('#edit_sort_order').val(sort_order);
    $('#edit_status').prop('checked', status == 1).trigger('change');

    if(imageUrl) {
        $('#editCatPreview').attr('src', imageUrl).show();
        $('#editCatPlaceholder').hide();
    } else {
        $('#editCatPreview').hide();
        $('#editCatPlaceholder').show();
    }

    $('#editCategoryModal').modal('show');
}

// Update Category (AJAX)
$('#editCategoryForm').on('submit', function(e) {
    e.preventDefault();
    let id = $('#edit_category_id').val();
    let url = "{{ route('food-category.update', ':id') }}".replace(':id', id);
    let formData = new FormData(this);

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
                $('#editCategoryModal').modal('hide');
                fetchCategories("{{ route('food-category.index') }}");
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                showToast('Error', res.message, 'error');
            }
        }
    });
});

// ==========================================
// 6. Dynamic Toggle Label (Active/Inactive text change)
// ==========================================
$(document).on('change', '.progga-toggle input[type="checkbox"]', function() {
    let label = $(this).siblings('.progga-toggle-label');
    if(label.length) {
        label.text($(this).is(':checked') ? $(this).data('on') : $(this).data('off'));
    }
});

// ==========================================
// 7. Edit Modal fix for Toggle
// ==========================================
// editCategory ফাংশনের ভেতরের এই লাইনটি আপডেট করুন:
// আগে ছিল: $('#edit_status').prop('checked', status == 1);
// এখন দিন:

// (trigger('change') দেওয়ার ফলে মোডাল ওপেন হওয়ার সাথেই টেক্সট 'Active' বা 'Inactive' অটো আপডেট হয়ে যাবে)

// ==========================================
// 8. Inline Status Update from Table
// ==========================================
window.toggleCategoryStatus = function(id, element) {
    let status = $(element).is(':checked') ? 1 : 0;

    $.ajax({
        url: "{{ url('food-category-status') }}/" + id,
        type: 'POST',
        data: {
            _token: "{{ csrf_token() }}",
            status: status
        },
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
            } else {
                showToast('Error', res.message, 'error');
                // ফেইল হলে আগের অবস্থায় ফিরিয়ে দেওয়া
                $(element).prop('checked', !status).trigger('change');
            }
        },
        error: function() {
            showToast('Error', 'Something went wrong!', 'error');
            $(element).prop('checked', !status).trigger('change');
        }
    });
}

// ==========================================
// 5. Delete Category (Normal with SweetAlert)
// ==========================================
window.deleteCategory = function(id, categoryName) {
    Swal.fire({
        title: 'Delete Category?',
        text: "You are about to delete '" + categoryName + "'. This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#21352a',
        confirmButtonText: 'Yes, Delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('food-category.destroy', ':id') }}".replace(':id', id);

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
