@forelse($orders as $order)
    @php
        $statusKey = strtolower((string) $order->status);
        $badge = $statusKey === 'completed' ? 'success' : ($statusKey === 'cancelled' ? 'danger' : 'warning');
        $orderUserName = optional($order->user)->name ?: trim((optional($order->user)->first_name ?? '') . ' ' . (optional($order->user)->last_name ?? ''));
        $orderUserName = $orderUserName ?: ('User #' . $order->user_id);
    @endphp
    <tr>
        <td><span class="report-sl-badge">{{ ($orders->firstItem() ?? 1) + $loop->index }}</span></td>
        <td><strong>#{{ $order->order_number }}</strong></td>
        <td>{{ $orderUserName }}</td>
        <td>{{ optional($order->created_at)->format('d M Y, h:i A') }}</td>
        <td>{{ optional($order->table)->table_number ?: 'N/A' }}</td>
        <td>{{ $order->order_type ?: 'N/A' }}</td>
        <td><span class="progga-badge progga-badge-{{ $badge }}">{{ $order->status ?: 'N/A' }}</span></td>
        <td class="text-danger">৳{{ number_format((float)($order->discount_amount ?? 0),0) }}</td>
        <td class="text-danger">৳{{ number_format((float)($order->product_discount_amount ?? 0),0) }}</td>
        <td><strong>৳{{ number_format((float)($order->grand_total ?? 0),0) }}</strong></td>
    </tr>
@empty
    <tr><td colspan="10" class="text-center py-4 text-muted">No waiter orders found for the selected filter.</td></tr>
@endforelse
