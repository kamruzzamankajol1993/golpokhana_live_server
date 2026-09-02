@forelse($activeKots as $kot)
@php
    $order = $kot->order;
    $activeQty = $kot->orderDetails->filter(fn ($item) => (int) ($item->is_unavailable ?? 0) !== 1)->sum('quantity');
    $kotBadge = match($kot->kitchen_status) {
        'Pending' => 'progga-badge-warning',
        'Cooking' => 'progga-badge-primary',
        'Ready' => 'progga-badge-success',
        default => 'progga-badge-neutral',
    };
@endphp
<tr>
    <td><strong>{{ $kot->kot_number }}</strong></td>
    <td>#{{ $order->order_number ?? 'N/A' }}</td>
    <td>{{ $kot->created_at ? $kot->created_at->format('d M Y, h:i A') : '—' }}</td>
    <td>{{ $order->table->table_number ?? ucfirst(str_replace('_', ' ', (string) ($order->order_type ?? 'N/A'))) }}</td>
    <td>{{ $order->waiter->name ?? 'N/A' }}</td>
    <td>{{ number_format((float) $activeQty) }}</td>
    <td><span class="progga-badge {{ $kotBadge }}">{{ $kot->kitchen_status }}</span></td>
    <td><span class="progga-badge progga-badge-neutral">{{ $order->status ?? 'N/A' }}</span></td>
    <td>
        <a href="{{ route('kitchen.print_kot', ['id' => $kot->id, 'source' => 'pos']) }}"
           target="_blank" rel="noopener"
           class="progga-btn progga-btn-primary progga-btn-sm">
            <i class="bi bi-printer"></i> Print
        </a>
    </td>
</tr>
@empty
<tr>
    <td colspan="9" class="text-center py-5 text-muted">No running or pending KOT found.</td>
</tr>
@endforelse
