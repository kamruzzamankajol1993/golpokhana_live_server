<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Due Report</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #2f3437; }
        .header { text-align: center; margin-bottom: 12px; border-bottom: 2px solid #21352a; padding-bottom: 9px; }
        .header h2 { margin: 0 0 3px; color: #21352a; font-size: 20px; }
        .header .title { margin-top: 6px; font-size: 14px; font-weight: bold; }
        .header p { margin: 2px 0; color: #666; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 5px; margin-bottom: 10px; }
        .summary td { border: 1px solid #d8dedb; padding: 7px; width: 25%; }
        .summary .label { display: block; color: #66706b; font-size: 8px; text-transform: uppercase; }
        .summary .value { display: block; color: #21352a; font-size: 14px; font-weight: bold; margin-top: 2px; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { border: 1px solid #d9dddb; padding: 5px 4px; vertical-align: top; }
        .report-table th { background: #21352a; color: #fff; font-size: 8px; white-space: nowrap; }
        .report-table td { font-size: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 10px; padding-top: 6px; border-top: 1px dashed #bbb; text-align: center; color: #777; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $restaurant->name ?? $restaurantSettingName }}</h2>
        <p>{{ $restaurant->address ?? '' }}@if(!empty($restaurant->phone)) | Phone: {{ $restaurant->phone }}@endif</p>
        <div class="title">Due Report</div>
        <p>Period: {{ $periodLabel }} | Delivery Partner: {{ $deliveryPartnerLabel }}</p>
    </div>

    <table class="summary">
        <tr>
            <td><span class="label">Due Orders</span><span class="value">{{ $totalOrders }}</span></td>
            <td><span class="label">Grand Total</span><span class="value">৳{{ number_format($totalGrand, 2) }}</span></td>
            <td><span class="label">Paid Amount</span><span class="value">৳{{ number_format($totalPaid, 2) }}</span></td>
            <td><span class="label">Total Due</span><span class="value">৳{{ number_format($totalDue, 2) }}</span></td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th>SL</th>
                <th>Order #</th>
                <th>Date &amp; Time</th>
                <th>Order Type</th>
                <th>Delivery Partner</th>
                <th>Customer</th>
                <th>Phone</th>
                <th class="text-right">Grand Total</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Due</th>
                <th>Payment</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                @php
                    $partnerLabels = [
                        'foodpanda' => 'Foodpanda',
                        'foodi' => 'Foodi',
                        'pathao_food' => 'Pathao Food',
                    ];
                    $partnerKey = strtolower(trim((string) ($order->delivery_partner ?? '')));
                    $partnerLabel = $partnerKey !== '' ? ($partnerLabels[$partnerKey] ?? $order->delivery_partner) : '—';
                    $payment = $order->reportPaymentText(0, true);
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>#{{ $order->order_number }}</strong></td>
                    <td>{{ optional($order->created_at)->format('d M Y h:i A') }}</td>
                    <td>{{ $order->order_type ?? '—' }}</td>
                    <td>{{ $partnerLabel }}</td>
                    <td>{{ optional($order->customer)->name ?? 'Walk-in' }}</td>
                    <td>{{ optional($order->customer)->phone ?? optional($order->customer)->mobile ?? '—' }}</td>
                    <td class="text-right">৳{{ number_format((float)($order->grand_total ?? 0), 2) }}</td>
                    <td class="text-right">৳{{ number_format((float)($order->total_paid_amount ?? 0), 2) }}</td>
                    <td class="text-right"><strong>৳{{ number_format((float)($order->due ?? 0), 2) }}</strong></td>
                    <td>{{ $payment }}</td>
                    <td>{{ $order->status ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center">No due orders found for the selected filter.</td></tr>
            @endforelse
        </tbody>
        @if($orders->isNotEmpty())
        <tfoot>
            <tr>
                <th colspan="7" class="text-right">Total ({{ $orders->count() }} Orders)</th>
                <th class="text-right">৳{{ number_format($totalGrand, 2) }}</th>
                <th class="text-right">৳{{ number_format($totalPaid, 2) }}</th>
                <th class="text-right">৳{{ number_format($totalDue, 2) }}</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">Generated: {{ now()->format('d M Y h:i A') }} | {{ $restaurantSettingName }}</div>
</body>
</html>
