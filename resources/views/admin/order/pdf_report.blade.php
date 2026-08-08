@php
    $mode = $mode ?? 'full';
@endphp

@if($mode === 'styles')
body { font-family: sans-serif; font-size: 9px; color: #333333; }
.header { text-align: center; margin-bottom: 8px; border-bottom: 2px solid #21352a; padding-bottom: 7px; }
.header h2 { margin: 0 0 4px 0; color: #21352a; font-size: 17px; }
.header p { margin: 0; color: #666666; font-size: 9px; }
.header h3 { margin: 6px 0 0 0; color: #444444; font-size: 12px; }
.report-meta { margin-top: 4px; color: #777777; font-size: 8px; }
.report-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.report-table th, .report-table td { border: 1px solid #dddddd; padding: 4px 5px; vertical-align: top; }
.report-table th { background-color: #21352a; color: #ffffff; font-size: 8px; font-weight: bold; text-align: left; }
.report-table td { font-size: 8px; }
.summary-table { width: 45%; border-collapse: collapse; margin-top: 10px; margin-left: auto; }
.summary-table th, .summary-table td { border: 1px solid #dddddd; padding: 5px 6px; font-size: 9px; }
.summary-table th { background-color: #f1f4f2; color: #21352a; text-align: left; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.text-danger { color: #dc3545; font-weight: bold; }
.text-success { color: #198754; font-weight: bold; }
.muted { color: #777777; font-size: 7px; }
.strong { font-weight: bold; }
.footer { margin-top: 12px; font-size: 8px; text-align: center; border-top: 1px dashed #dddddd; padding-top: 7px; color: #888888; }
@endif

@if($mode === 'header')
    <div class="header">
        <h2>{{ optional($restaurant)->name ?? 'Progga RMS' }}</h2>
        <p>{{ optional($restaurant)->address ?? 'Dhaka, Bangladesh' }} | Phone: {{ optional($restaurant)->phone ?? 'N/A' }}</p>
        <h3>Order Report — {{ $dateFilterLabel ?? now()->format('d M, Y') }}</h3>
        <div class="report-meta">Total Orders: {{ number_format((int)($totalOrders ?? 0)) }}</div>
    </div>
@endif

@if($mode === 'table')
    <table class="report-table">
        <thead>
            <tr>
                <th style="width:7%;">Order #</th>
                <th style="width:13%;">Customer</th>
                <th style="width:7%;" class="text-right">Subtotal</th>
                <th style="width:7%;" class="text-right">Discount</th>
                <th style="width:7%;" class="text-right">Service</th>
                <th style="width:6%;" class="text-right">Tips</th>
                <th style="width:6%;" class="text-right">Given</th>
                <th style="width:6%;" class="text-right">Change</th>
                <th style="width:7%;" class="text-right">Grand</th>
                <th style="width:8%;">Payment</th>
                <th style="width:7%;" class="text-center">Status</th>
                <th style="width:7%;">Date</th>
                <th style="width:6%;">Time</th>
                <th style="width:6%;">KOT-Pay</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($orders ?? []) as $order)
                @php
                    $discountAmount = max(0, (float)($order->discount_amount ?? 0));
                    $serviceCharge = max(0, (float)($order->service_charge ?? 0));
                    $tipsAmount = max(0, (float)($order->tips_amount ?? ((float)($order->total_paid_amount ?? 0) - (float)($order->grand_total ?? 0))));
                    $givenMoney = max(0, (float)($order->given_money ?? 0));
                    $changeAmount = max(0, (float)($order->change_amount ?? 0));

                    $orderType = strtolower(str_replace('_', '-', (string)($order->order_type ?? '')));
                    $tableText = in_array($orderType, ['takeaway', 'take-away'], true)
                        ? 'Takeaway'
                        : 'Table T-' . (optional($order->table)->table_number ?? 'N/A');

                    $paymentText = $order->payment_type ?? 'N/A';
                    $splitText = '';
                    if ($paymentText === 'Split') {
                        $splits = [];
                        if ((float)($order->paid_in_cash ?? 0) > 0) {
                            $splits[] = 'Cash: ' . number_format((float)$order->paid_in_cash, 0);
                        }
                        if ((float)($order->paid_in_card ?? 0) > 0) {
                            $splits[] = 'Card: ' . number_format((float)$order->paid_in_card, 0);
                        }
                        if ((float)($order->paid_in_mfc ?? 0) > 0) {
                            $splits[] = 'MFS: ' . number_format((float)$order->paid_in_mfc, 0);
                        }
                        $splitText = implode(', ', $splits);
                    }
                @endphp
                <tr>
                    <td><span class="strong">#{{ $order->order_number }}</span></td>
                    <td>
                        <span class="strong">{{ optional($order->customer)->name ?? 'Walk-in Customer' }}</span><br>
                        <span class="muted">{{ $tableText }}</span>
                    </td>
                    <td class="text-right"><span class="strong">{{ number_format((float)($order->subtotal ?? 0), 0) }}</span></td>
                    <td class="text-right text-danger">{{ number_format($discountAmount, 0) }}</td>
                    <td class="text-right">{{ number_format($serviceCharge, 0) }}</td>
                    <td class="text-right text-success">{{ number_format($tipsAmount, 0) }}</td>
                    <td class="text-right">{{ number_format($givenMoney, 0) }}</td>
                    <td class="text-right text-success">{{ number_format($changeAmount, 0) }}</td>
                    <td class="text-right"><span class="strong">{{ number_format((float)($order->grand_total ?? 0), 0) }}</span></td>
                    <td>
                        {{ $paymentText }}
                        @if($splitText !== '')
                            <br><span class="muted">{{ $splitText }}</span>
                        @endif
                    </td>
                    <td class="text-center"><span class="strong">{{ $order->status }}</span></td>
                    <td>{{ $order->created_at ? $order->created_at->format('d M Y') : '—' }}</td>
                    <td>{{ $order->created_at ? $order->created_at->format('h:i A') : '—' }}</td>
                    <td>{{ is_null($order->kitchen_to_payment_minutes) ? '—' : $order->kitchen_to_payment_minutes . ' min' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($mode === 'empty')
    <table class="report-table">
        <tbody>
            <tr>
                <td class="text-center">No orders found for the selected criteria.</td>
            </tr>
        </tbody>
    </table>
@endif

@if($mode === 'summary')
    @if((int)($ordersCount ?? 0) > 0)
        <table class="summary-table">
            <tr>
                <th colspan="2">Total Summary</th>
            </tr>
            <tr>
                <td>Completed Subtotal</td>
                <td class="text-right">{{ number_format((float)($totals['subtotal'] ?? 0), 0) }}</td>
            </tr>
            <tr>
                <td>Total Discount</td>
                <td class="text-right">{{ number_format((float)($totals['discount'] ?? 0), 0) }}</td>
            </tr>
            <tr>
                <td>Total Service Charge</td>
                <td class="text-right">{{ number_format((float)($totals['service_charge'] ?? 0), 0) }}</td>
            </tr>
            <tr>
                <td>Total Tips</td>
                <td class="text-right">{{ number_format((float)($totals['tips'] ?? 0), 0) }}</td>
            </tr>
            <tr>
                <td>Total Given</td>
                <td class="text-right">{{ number_format((float)($totals['given'] ?? 0), 0) }}</td>
            </tr>
            <tr>
                <td>Total Change</td>
                <td class="text-right">{{ number_format((float)($totals['change'] ?? 0), 0) }}</td>
            </tr>
            <tr>
                <td><strong>Completed Revenue</strong></td>
                <td class="text-right"><strong>{{ number_format((float)($totals['revenue'] ?? 0), 0) }}</strong></td>
            </tr>
        </table>
    @endif

    <div class="footer">
        Generated on: {{ now()->format('d M, Y h:i A') }} | System: Table Track RMS
    </div>
@endif

@if($mode === 'full')
    @php
        $ordersCount = isset($orders) ? $orders->count() : 0;
        $totals = [
            'subtotal' => 0,
            'revenue' => 0,
            'discount' => 0,
            'service_charge' => 0,
            'tips' => 0,
            'given' => 0,
            'change' => 0,
        ];

        foreach(($orders ?? []) as $order) {
            $discountAmount = max(0, (float)($order->discount_amount ?? 0));
            $serviceCharge = max(0, (float)($order->service_charge ?? 0));
            $tipsAmount = max(0, (float)($order->tips_amount ?? ((float)($order->total_paid_amount ?? 0) - (float)($order->grand_total ?? 0))));
            $givenMoney = max(0, (float)($order->given_money ?? 0));
            $changeAmount = max(0, (float)($order->change_amount ?? 0));

            if($order->status === 'Completed') {
                $totals['subtotal'] += (float)($order->subtotal ?? 0);
                $totals['revenue'] += (float)($order->grand_total ?? 0);
            }

            $totals['discount'] += $discountAmount;
            $totals['service_charge'] += $serviceCharge;
            $totals['tips'] += $tipsAmount;
            $totals['given'] += $givenMoney;
            $totals['change'] += $changeAmount;
        }
    @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Report</title>
    <style>{!! view('admin.order.pdf_report', ['mode' => 'styles'])->render() !!}</style>
</head>
<body>
    {!! view('admin.order.pdf_report', [
        'mode' => 'header',
        'restaurant' => $restaurant ?? null,
        'dateFilterLabel' => $dateFilterLabel ?? now()->format('d M, Y'),
        'totalOrders' => $ordersCount,
    ])->render() !!}

    @if($ordersCount > 0)
        {!! view('admin.order.pdf_report', ['mode' => 'table', 'orders' => $orders])->render() !!}
    @else
        {!! view('admin.order.pdf_report', ['mode' => 'empty'])->render() !!}
    @endif

    {!! view('admin.order.pdf_report', [
        'mode' => 'summary',
        'ordersCount' => $ordersCount,
        'totals' => $totals,
    ])->render() !!}
</body>
</html>
@endif
