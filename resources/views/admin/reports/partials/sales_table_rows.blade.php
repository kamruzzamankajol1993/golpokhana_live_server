@forelse($orders as $order)
    @php
        $discountAmount = max(0, (float)($order->discount_amount ?? 0));
        $serviceCharge = max(0, (float)($order->service_charge ?? 0));
        $tipsAmount = max(0, (float)($order->tips_amount ?? 0));
        $givenMoney = max(0, (float)($order->given_money ?? 0));
        $changeAmount = max(0, (float)($order->change_amount ?? 0));
        $orderType = strtolower((string) $order->order_type);
        $tableText = in_array($orderType, ['takeaway', 'delivery'], true) ? ucfirst($orderType) : 'Table T-' . (optional($order->table)->table_number ?? 'N/A');

        $paymentText = $order->payment_type ?? 'N/A';
        if ($paymentText === 'Split') {
            $splits = [];
            if((float)$order->paid_in_cash > 0) $splits[] = 'Cash: ' . number_format($order->paid_in_cash, 0);
            if((float)$order->paid_in_card > 0) $splits[] = 'Card: ' . number_format($order->paid_in_card, 0);
            if((float)$order->paid_in_mfc > 0) $splits[] = 'MFC: ' . number_format($order->paid_in_mfc, 0);
            $paymentText .= count($splits) ? '<br><span style="font-size:10px;color:#666;">' . implode(', ', $splits) . '</span>' : '';
        }
    @endphp
    <tr>
        <td><strong>#{{ $order->order_number }}</strong></td>
        <td>
            <strong>{{ optional($order->customer)->name ?? 'Walk-in Customer' }}</strong><br>
            <span class="text-muted" style="font-size:11px;">{{ $tableText }}</span>
        </td>
        <td><strong>৳{{ number_format($order->subtotal, 0) }}</strong></td>
        <td><strong class="text-danger">৳{{ number_format($discountAmount, 0) }}</strong></td>
        <td>৳{{ number_format($serviceCharge, 0) }}</td>
        <td><strong class="text-success">৳{{ number_format($tipsAmount, 0) }}</strong></td>
        <td>৳{{ number_format($givenMoney, 0) }}</td>
        <td><strong class="text-success">৳{{ number_format($changeAmount, 0) }}</strong></td>
        <td><strong style="color:var(--progga-primary);">৳{{ number_format($order->grand_total, 0) }}</strong></td>
        <td>{!! $paymentText !!}</td>
        <td><span class="progga-badge progga-badge-primary">{{ $order->status }}</span></td>
        <td>{{ optional($order->created_at)->format('d M Y') }}</td>
        <td>{{ optional($order->created_at)->format('h:i A') }}</td>
        <td>{{ is_null($order->kitchen_to_payment_minutes) ? '—' : $order->kitchen_to_payment_minutes . ' min' }}</td>
    </tr>
@empty
    <tr>
        <td colspan="14" class="text-center py-4">No completed orders found for the selected filter.</td>
    </tr>
@endforelse
