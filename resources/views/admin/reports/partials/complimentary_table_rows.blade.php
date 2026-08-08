@forelse($orders as $order)
    @php
        $wholeOrderComplimentary = !empty($order->is_complimentary_order);
        $complimentaryItems = $order->orderDetails->filter(function ($item) use ($wholeOrderComplimentary) {
            return (float) $item->quantity > 0 && (
                $wholeOrderComplimentary
                || !empty($item->is_complimentary)
                || ((float) $item->price <= 0 && (float) $item->subtotal <= 0)
            );
        });
        $complimentaryQty = (int) $complimentaryItems->sum('quantity');
        $orderType = strtolower((string) $order->order_type);
        $tableText = in_array($orderType, ['takeaway', 'delivery'], true)
            ? ucfirst($orderType)
            : 'Table T-' . (optional($order->table)->table_number ?? 'N/A');

        $paymentText = $order->payment_type ?? 'N/A';
        if ($paymentText === 'Split') {
            $splits = [];
            if ((float) $order->paid_in_cash > 0) $splits[] = 'Cash: ' . number_format($order->paid_in_cash, 0);
            if ((float) $order->paid_in_card > 0) $splits[] = 'Card: ' . number_format($order->paid_in_card, 0);
            if ((float) $order->paid_in_mfc > 0) $splits[] = 'MFC: ' . number_format($order->paid_in_mfc, 0);
            $paymentText .= count($splits)
                ? '<br><span style="font-size:10px;color:#666;">' . implode(', ', $splits) . '</span>'
                : '';
        }
    @endphp
    <tr>
        <td>{{ ($orders->firstItem() ?? 1) + $loop->index }}</td>
        <td><strong>#{{ $order->order_number }}</strong></td>
        <td>
            <strong>{{ optional($order->customer)->name ?? 'Walk-in Customer' }}</strong><br>
            <span class="text-muted" style="font-size:11px;">{{ $tableText }}</span>
        </td>
        <td>
            @foreach($complimentaryItems as $item)
                <div>
                    <strong>{{ $item->product_name }}</strong>
                    <span class="text-muted">× {{ (int) $item->quantity }}</span>
                </div>
            @endforeach
        </td>
        <td><strong>{{ $complimentaryQty }}</strong></td>
        <td><strong style="color:var(--progga-primary);">৳{{ number_format($order->grand_total, 0) }}</strong></td>
        <td>{!! $paymentText !!}</td>
        <td><span class="progga-badge progga-badge-primary">{{ $order->status }}</span></td>
        <td>{{ optional($order->created_at)->format('d M Y') }}</td>
        <td>{{ optional($order->created_at)->format('h:i A') }}</td>
    </tr>
@empty
    <tr>
        <td colspan="10" class="text-center py-4">No complimentary food orders found for the selected filter.</td>
    </tr>
@endforelse
