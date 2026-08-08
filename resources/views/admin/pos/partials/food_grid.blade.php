@foreach($foods as $food)
<div class="progga-pos-food-card" onclick="checkAddonAndCart({{ $food->id }}, {{ $food->addons->count() > 0 ? 1 : 0 }})">
    <div class="progga-pos-food-img-wrap">
        @if($food->main_image)
            <img src="{{ asset('public/uploads/foods/'.$food->main_image) }}" style="width:100%;height:100%;object-fit:cover;border-radius:10px 10px 0 0;" alt="">
        @else
            🍽️
        @endif
    </div>
    <div class="progga-pos-food-body">
        <div class="progga-pos-food-name">{{ $food->name }}</div>
        <div class="progga-pos-food-footer">
            <div class="progga-pos-food-price">৳{{ round($food->discount_price ?? $food->base_price) }}</div>
            <div class="progga-pos-food-add"><i class="bi bi-plus-lg"></i></div>
        </div>
    </div>
</div>
@endforeach
