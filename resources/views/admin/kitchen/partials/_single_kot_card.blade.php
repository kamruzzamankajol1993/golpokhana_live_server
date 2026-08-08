@php
    $minutesWaiting = \Carbon\Carbon::parse($kot->created_at)->diffInMinutes(now());
    $isUrgent = $minutesWaiting > 15 ? 'urgent' : '';

    $prepTime = (int) ($kot->order?->preparation_time ?? 20);

    if ($prepTime < 1) {
        $prepTime = 20;
    }

    $isCooking = $kot->kitchen_status === 'Cooking';
    $remainingSeconds = 0;

    if ($isCooking) {
        $targetTime = \Carbon\Carbon::parse($kot->updated_at)->addMinutes($prepTime);
        $remainingSeconds = now()->diffInSeconds($targetTime, false);
    }
@endphp

<div class="progga-kitchen-card {{ $isUrgent }}" data-order-id="{{ $kot->order_id }}">
    <div class="progga-kitchen-card-top">
        <div class="progga-kitchen-card-table">{{ $kot->order?->table?->table_number ?? 'Takeaway' }}</div>

        @if($isCooking)
            <div class="progga-kitchen-timer cooking-timer" data-seconds="{{ $remainingSeconds }}" style="background: #fff3cd; color: #856404; font-weight: bold; padding: 4px 8px; border-radius: 6px; display: flex; align-items: center; gap: 5px;">
                <i class="bi bi-stopwatch"></i> <span class="time-display">--:--</span>
            </div>
        @else
            <div class="progga-kitchen-timer {{ $isUrgent }}">
                <i class="bi bi-clock{{ $isUrgent ? '-fill' : '' }}"></i> {{ $minutesWaiting }} min {{ $isUrgent ? '⚠️' : '' }}
            </div>
        @endif
    </div>
    <div class="progga-kitchen-card-meta d-flex justify-content-between align-items-center">
        <span>Order #{{ $kot->order?->order_number ?? 'Unknown' }} &middot; Waiter: {{ $kot->order?->waiter?->name ?? 'N/A' }}</span>
        <a href="{{ route('kitchen.print_kot', $kot->id) }}" target="_blank" class="text-secondary" title="Print KOT"><i class="bi bi-printer"></i></a>
    </div>

    <div class="progga-kitchen-card-items">
        @foreach($kot->orderDetails as $item)
            @php $addons = json_decode($item->addons, true) ?? []; @endphp
            <div class="progga-kitchen-item">
                <div class="progga-kitchen-item-info">
                    <span class="progga-kitchen-item-name">
                        @if($item->is_unavailable)
                            <del class="text-muted">{{ $item->product_name }}</del>
                            <span class="badge bg-danger" style="font-size: 8px;">N/A</span>
                        @else
                            {{ $item->product_name }}
                        @endif
                    </span>
                    @if($item->food_note)
                        <div style="font-size: 11px; color: #d33; font-style: italic;">* {{ $item->food_note }}</div>
                    @endif
                    @if(count($addons) > 0)
                        <div style="font-size:10px; color:#666;">
                            @foreach($addons as $addon) +{{ $addon['name'] }} @endforeach
                        </div>
                    @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="progga-kitchen-item-qty">×{{ $item->quantity }}</span>
                    {{-- Pending থাকা অবস্থায় Unavailable করার ক্রস বাটন --}}
                    @if($kot->kitchen_status == 'Pending' && !$item->is_unavailable)
                        <button class="btn btn-sm btn-danger py-0 px-1 mark-unavailable-btn" data-id="{{ $item->id }}" title="Mark Unavailable">
                            <i class="bi bi-x"></i>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <button class="progga-kitchen-action-btn progga-kitchen-action-btn--{{ $btnClass }} update-kot-status" data-status="{{ $nextStatus }}" data-kot-id="{{ $kot->id }}" type="button">
        <i class="bi bi-{{ $icon }}"></i> {{ $actionLabel }}
    </button>
</div>
