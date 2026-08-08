@extends('admin.master.master')
@section('title', 'Food Menu — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Food Menu</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Food Menu</span>
            </div>
        </div>
        @can('food-item-create')
        <a href="{{ route('food-item.create') }}" class="progga-btn progga-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Food Item
        </a>
        @endcan
    </div>

    <div class="progga-card progga-filters-card" style="margin-bottom:16px;">
        <div class="progga-filters-bar">
            <div class="progga-search progga-filters-search">
                <i class="bi bi-search progga-search-icon"></i>
                <input type="text" class="progga-form-control progga-table-search" placeholder="Search food items...">
            </div>

            <div class="progga-filters-select">
                <select class="progga-select" id="filterCategory">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="progga-filters-select">
                <select class="progga-select" id="filterCuisine">
                    <option value="">All Cuisines</option>
                    @foreach($cuisines as $cui)
                        <option value="{{ $cui->id }}">{{ $cui->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="progga-filters-select">
                <select class="progga-select" id="filterAvailability">
                    <option value="">All Availability</option>
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                </select>
            </div>

            <div class="progga-filters-select">
                <select class="progga-select" id="filterFeatured">
                    <option value="">All Items</option>
                    <option value="featured">Featured</option>
                    <option value="not-featured">Not Featured</option>
                </select>
            </div>
        </div>
    </div>

    <div class="progga-card" id="food_data_container">
        @include('admin.food_item.food_table')
    </div>
</main>
@endsection

@section('script')
<script>
// --- 1. AJAX Fetch Data (Filtering & Pagination) ---
function fetchFoods(url) {
    let search = $('.progga-table-search').val();
    let cat = $('#filterCategory').val();
    let cui = $('#filterCuisine').val();
    let ava = $('#filterAvailability').val();
    let fea = $('#filterFeatured').val();

    $('#food_data_container').css('opacity', '0.6');
    $.ajax({
        url: url,
        data: { search: search, category: cat, cuisine: cui, availability: ava, featured: fea },
        success: function(data) {
            $('#food_data_container').html(data).css('opacity', '1');
        }
    });
}

// Trigger Filters
$(document).on('change', '#filterCategory, #filterCuisine, #filterAvailability, #filterFeatured', function() {
    fetchFoods("{{ route('food-item.index') }}");
});

let timer;
$(document).on('keyup', '.progga-table-search', function() {
    clearTimeout(timer);
    timer = setTimeout(() => { fetchFoods("{{ route('food-item.index') }}"); }, 500);
});

$(document).on('click', '.progga-pagination a', function(e) {
    e.preventDefault();
    fetchFoods($(this).attr('href'));
});

// --- 2. Inline Status Toggle (AJAX) ---
window.toggleFoodStatus = function(id, el, field) {
    let status = $(el).is(':checked') ? 1 : 0;
    $(el).siblings('.progga-toggle-label').text(status ? $(el).data('on') : $(el).data('off'));

    $.ajax({
        url: "{{ route('food-item.status', ':id') }}".replace(':id', id),
        type: 'POST',
        data: { _token: "{{ csrf_token() }}", field: field, status: status },
        success: function(res) {
            if(res.status === 'success') {
                showToast('Success', res.message, 'success');
            } else {
                showToast('Error', res.message, 'error');
                $(el).prop('checked', !status);
            }
        }
    });
}

// --- 3. Normal Delete via SweetAlert ---
window.deleteFood = function(id, name) {
    Swal.fire({
        title: 'Delete Food Item?',
        text: "You are about to delete '" + name + "'. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#21352a',
        confirmButtonText: 'Yes, Delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('food-item.destroy', ':id') }}".replace(':id', id);
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
