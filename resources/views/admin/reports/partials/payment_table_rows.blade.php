@php
    $filterMethod = request('payment_method');
@endphp

@forelse($paymentOrders as $order)
@php
    // Legacy-safe payment amount calculation.
    // New payments save paid_in_cash/card/mfc. Old payments may have only payment_type + total_paid_amount.
    $cashAmount = (float) ($order->paid_in_cash ?? 0);
    $cardAmount = (float) ($order->paid_in_card ?? 0);
    $mfcAmount = (float) ($order->paid_in_mfc ?? 0);

    if (($cashAmount + $cardAmount + $mfcAmount) <= 0 && (float) ($order->total_paid_amount ?? 0) > 0) {
        if ($order->payment_type === 'Cash') {
            $cashAmount = (float) $order->total_paid_amount;
        } elseif ($order->payment_type === 'Card') {
            $cardAmount = (float) $order->total_paid_amount;
        } elseif ($order->payment_type === 'Mobile Banking') {
            $mfcAmount = (float) $order->total_paid_amount;
        }
    }

    // Filter wise badge and row total logic.
    $showCash = $cashAmount > 0 && (!$filterMethod || $filterMethod == 'Cash');
    $showCard = $cardAmount > 0 && (!$filterMethod || $filterMethod == 'Card');
    $showMfc = $mfcAmount > 0 && (!$filterMethod || $filterMethod == 'Mobile Banking');

    if ($filterMethod == 'Cash') {
        $rowTotal = $cashAmount;
    } elseif ($filterMethod == 'Card') {
        $rowTotal = $cardAmount;
    } elseif ($filterMethod == 'Mobile Banking') {
        $rowTotal = $mfcAmount;
    } else {
        $rowTotal = (float) ($order->total_paid_amount ?? ($cashAmount + $cardAmount + $mfcAmount));
    }
@endphp
<tr>
    <td><strong>#{{ $order->order_number }}</strong></td>
    <td>{{ $order->created_at->format('d M, h:i A') }}</td>
    <td>{{ $order->customer->name ?? 'Walk-in' }}</td>
    <td>{{ $order->table->table_number ?? 'Takeaway' }}</td>
    <td>
        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
            @if($showCash) <span class="progga-badge progga-badge-neutral">Cash</span> @endif
            @if($showCard) <span class="progga-badge progga-badge-neutral">Card</span> @endif
            @if($showMfc) <span class="progga-badge progga-badge-neutral">Mobile Banking</span> @endif
            @if(!$showCash && !$showCard && !$showMfc)
                <span class="progga-badge progga-badge-neutral">{{ $order->payment_type ?? 'N/A' }}</span>
            @endif
        </div>
    </td>
    <td>৳{{ number_format($cashAmount, 2) }}</td>
    <td>৳{{ number_format($cardAmount, 2) }}</td>
    <td>৳{{ number_format($mfcAmount, 2) }}</td>
    <td class="text-primary"><strong>৳{{ number_format($rowTotal, 2) }}</strong></td>
</tr>
@empty
<tr><td colspan="9" class="text-center py-4 text-muted">No data found.</td></tr>
@endforelse
