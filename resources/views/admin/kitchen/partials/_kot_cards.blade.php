@forelse($kots as $kot)
    @php
        // কতক্ষণ ধরে কিচেনে আছে তা বের করা
        $minutesWaiting = \Carbon\Carbon::parse($kot->created_at)->diffInMinutes(now());
        $statusClass = $kot->kitchen_status == 'Pending' ? 'pending' : 'cooking';
    @endphp

    <div class="progga-kitchen-card progga-kitchen-card--{{ $statusClass }}">
      <div class="progga-kitchen-card-header">
        <div>
          <div class="progga-kitchen-card-title">{{ $kot->order->table->table_number ?? 'Takeaway' }}</div>
          <div class="progga-kitchen-card-timer"><i class="bi bi-clock-history"></i> {{ $minutesWaiting }} min ago</div>
        </div>
        <div class="progga-kitchen-card-badge">{{ $kot->kitchen_status }}</div>
      </div>

      <div class="progga-kitchen-card-meta">
          Order #{{ $kot->order->order_number }} &middot; Waiter: {{ $kot->order->waiter->name ?? 'N/A' }}
      </div>

      <div class="progga-kitchen-card-items">
        @foreach($kot->orderDetails as $item)
            @php $addons = json_decode($item->addons, true) ?? []; @endphp
            <div class="progga-kitchen-item">
                <div class="progga-kitchen-item-info">
                    <span class="progga-kitchen-item-name">{{ $item->product_name }}</span>
                    @if(count($addons) > 0)
                        <div style="font-size:11px; color:#666;">
                            @foreach($addons as $addon) +{{ $addon['name'] }} @endforeach
                        </div>
                    @endif
                </div>
                <span class="progga-kitchen-item-qty">×{{ $item->quantity }}</span>
            </div>
        @endforeach
      </div>

      @if($kot->kitchen_status == 'Pending')
        <button class="progga-kitchen-action-btn progga-kitchen-action-btn--accept update-kot-status" data-kot-id="{{ $kot->id }}" data-status="Cooking" type="button">
          <i class="bi bi-fire"></i> Start Cooking
        </button>
      @elseif($kot->kitchen_status == 'Cooking')
        <button class="progga-kitchen-action-btn progga-kitchen-action-btn--deliver update-kot-status" data-kot-id="{{ $kot->id }}" data-status="Ready" type="button">
          <i class="bi bi-bag-check-fill"></i> Mark Ready (Delivered)
        </button>
      @endif
    </div>
@empty
    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
        <i class="bi bi-cup-hot" style="font-size: 3rem; color: #ccc;"></i>
        <h4 class="mt-3">Kitchen is clear!</h4>
        <p>No pending orders at the moment.</p>
    </div>
@endforelse
