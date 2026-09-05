@forelse($kots as $key => $kot)
@php
    $order = $kot->order;
    $type = strtolower((string) optional($order)->order_type);
    $location = in_array($type, ['dine-in', 'dine_in'], true)
        ? 'Table ' . (optional(optional($order)->table)->table_number ?? 'N/A')
        : ucfirst(str_replace('_', ' ', (string) optional($order)->order_type));
    $itemQty = $kot->orderDetails->where('is_unavailable', 0)->sum('quantity');
@endphp
<tr>
    <td><span class="report-sl-badge">{{ ($kots->firstItem() ?? 1) + $key }}</span></td>
    <td><strong>{{ $kot->kot_number }}</strong></td>
    <td>#{{ optional($order)->order_number ?? 'N/A' }}</td>
    <td>{{ $location }}</td>
    <td>{{ optional(optional($order)->waiter)->name ?? 'Unassigned' }}</td>
    <td>{{ number_format($itemQty) }}</td>
    <td>{{ $kot->created_at ? $kot->created_at->format('d M y - h:i A') : '—' }}</td>
    <td><span class="progga-badge {{ $kot->kitchen_status === 'Delivered' ? 'progga-badge-success' : 'progga-badge-warning' }}">{{ $kot->kitchen_status }}</span></td>
    <td><span class="progga-badge progga-badge-secondary">{{ optional($order)->status ?? 'N/A' }}</span></td>
    <td>
        <a href="{{ route('kitchen.print_kot', ['id' => $kot->id, 'source' => 'pos']) }}" class="progga-btn progga-btn-primary progga-btn-sm js-pos-print-preview" data-title="KOT" data-return-pos="1">
            <i class="bi bi-printer"></i> Print
        </a>
    </td>
</tr>
@empty
<tr>
    <td colspan="10" class="text-center text-muted" style="padding:32px 12px;">No running or pending KOT found.</td>
</tr>
@endforelse
