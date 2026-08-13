@forelse($orders as $order)
    @php
        $partnerLabels = [
            'inhouse' => 'In-house Delivery',
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];
        $partnerKey = strtolower(trim((string) ($order->delivery_partner ?: 'inhouse')));
        $partnerLabel = $partnerLabels[$partnerKey] ?? ($order->delivery_partner ?: 'N/A');
        $discount = max(0, (float) ($order->product_discount_amount ?? 0)) + max(0, (float) ($order->discount_amount ?? 0));
        $paymentText = $order->payment_type ?? 'N/A';
        if ($paymentText === 'Split') {
            $parts = [];
            if ((float) ($order->paid_in_cash ?? 0) > 0) $parts[] = 'Cash ' . number_format($order->paid_in_cash, 0);
            if ((float) ($order->paid_in_card ?? 0) > 0) $parts[] = 'Card ' . number_format($order->paid_in_card, 0);
            if ((float) ($order->paid_in_mfc ?? 0) > 0) $parts[] = 'MFS ' . number_format($order->paid_in_mfc, 0);
            if ($parts) $paymentText .= ' (' . implode(', ', $parts) . ')';
        }
    @endphp
    <tr>
        <td><strong>#{{ $order->order_number }}</strong></td>
        <td>{{ optional($order->created_at)->format('d M Y') }}<br><span class="text-muted">{{ optional($order->created_at)->format('h:i A') }}</span></td>
        <td><strong>{{ $partnerLabel }}</strong></td>
        <td>{{ optional($order->customer)->name ?? 'Walk-in Customer' }}</td>
        <td>{{ optional($order->customer)->phone ?? optional($order->customer)->mobile ?? 'N/A' }}</td>
        <td>৳{{ number_format($order->subtotal ?? 0, 0) }}</td>
        <td>৳{{ number_format($order->vat_tax ?? 0, 0) }}</td>
        <td>৳{{ number_format($discount, 0) }}</td>
        <td><strong>৳{{ number_format($order->grand_total ?? 0, 0) }}</strong></td>
        <td><strong>৳{{ number_format(max(0, (float) ($order->due ?? 0)), 0) }}</strong></td>
        <td>{{ $paymentText }}</td>
        <td><span class="progga-badge progga-badge-{{ $order->status === 'Completed' ? 'primary' : (strtolower((string) $order->status) === 'cancelled' ? 'danger' : 'warning') }}">{{ $order->status ?? 'N/A' }}</span></td>
    </tr>
@empty
    <tr>
        <td colspan="12" class="text-center py-4">No Delivery orders found for the selected filter.</td>
    </tr>
@endforelse
