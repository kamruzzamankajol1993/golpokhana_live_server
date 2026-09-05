@forelse($orders as $order)
    @php
        $partnerLabels = [
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];
        $partnerKey = strtolower(trim((string) ($order->delivery_partner ?? '')));
        $partnerLabel = $partnerKey !== '' ? ($partnerLabels[$partnerKey] ?? $order->delivery_partner) : '—';
        $orderType = ucfirst(str_replace(['_', '-'], ' ', strtolower((string) $order->order_type)));
        $paidAmount = max(0, (float) ($order->total_paid_amount ?? 0));
    @endphp
    <tr>
        <td><span class="report-sl-badge">{{ ($orders->firstItem() ?? 1) + $loop->index }}</span></td>
        <td><strong>#{{ $order->order_number }}</strong></td>
        <td>{{ optional($order->created_at)->format('d M Y') }}<br><span class="text-muted">{{ optional($order->created_at)->format('h:i A') }}</span></td>
        <td>{{ $orderType ?: 'N/A' }}</td>
        <td>{{ $partnerLabel }}</td>
        <td>{{ optional($order->customer)->name ?? 'Walk-in Customer' }}</td>
        <td><strong>৳{{ number_format((float) ($order->grand_total ?? 0), 0) }}</strong></td>
        <td>৳{{ number_format($paidAmount, 0) }}</td>
        <td><strong class="text-danger">৳{{ number_format(max(0, (float) ($order->due ?? 0)), 0) }}</strong></td>
        <td>{{ $order->reportPaymentText(0, true) }}</td>
        <td><span class="progga-badge progga-badge-warning">{{ $order->status ?? 'N/A' }}</span></td>
        <td><a href="{{ route('order.show', $order->id) }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-eye"></i> View</a></td>
    </tr>
@empty
    <tr>
        <td colspan="12" class="text-center py-4 text-muted">No due orders found for the selected filter.</td>
    </tr>
@endforelse
