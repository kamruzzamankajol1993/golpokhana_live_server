<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #333; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #21352a; padding-bottom: 15px; }
        .header h2 { margin: 0 0 5px 0; color: #21352a; font-size: 26px; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 0; color: #666; font-size: 13px; }
        .report-title { margin-top: 15px; color: #444; font-size: 16px; font-weight: bold; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #ddd; padding: 9px; text-align: left; }
        .table th { background-color: #21352a; color: #ffffff; font-size: 12px; font-weight: bold; }
        .table td { font-size: 11px; }
        .text-right { text-align: right; }
        .sales-table th, .sales-table td { font-size: 9px; padding: 5px 4px; }
        .sales-table th { white-space: nowrap; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; font-size: 11px; text-align: center; border-top: 1px dashed #ddd; padding-top: 10px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $restaurant->name ?? $restaurantSettingName }}</h2>
        <p>{{ $restaurant->address ?? '' }} | Phone: {{ $restaurant->phone ?? 'N/A' }}</p>
        <div class="report-title">
            @if($report === 'payment_type_sales')
                Payment Type Wise Sales Report
            @elseif($report === 'food_sales')
                Food Wise Sales Report
            @elseif($report === 'complimentary_orders')
                Complimentary Order Report
            @else
                Sales & Order Report
            @endif
        </div>
        <p>Period: {{ $startDate->format('d M, Y') }} to {{ $endDate->format('d M, Y') }}</p>
    </div>

    @if($report === 'payment_type_sales')
        @php $totalPaid = $dataRows->sum('total_paid'); @endphp
        <table class="table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Customer</th>
                    <th>Table</th>
                    <th class="text-right">Honored</th>
                    <th class="text-right">Product Discount</th>
                    <th>Payment Type</th>
                    <th class="text-right">Cash</th>
                    <th class="text-right">Bank / Card</th>
                    <th class="text-right">MFS</th>
                    <th class="text-right">Total Paid</th>
                </tr>
            </thead>
            <tbody>
            @forelse($dataRows as $row)
                <tr>
                    <td><strong>#{{ $row['order_number'] }}</strong></td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['time'] ?? '—' }}</td>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['table'] }}</td>
                    <td class="text-right">৳{{ number_format($row['other_discount'] ?? 0, 2) }}</td>
                    <td class="text-right">৳{{ number_format($row['product_discount'] ?? 0, 2) }}</td>
                    <td>{{ str_replace(['Mobile Banking / MFC', 'Mobile Banking / MFS', 'Mobile Banking'], 'MFS', $row['payment_type']) }}</td>
                    <td class="text-right">৳{{ number_format($row['cash'], 2) }}</td>
                    <td class="text-right">৳{{ number_format($row['card'], 2) }}</td>
                    <td class="text-right">৳{{ number_format($row['mfc'], 2) }}</td>
                    <td class="text-right"><strong>৳{{ number_format($row['total_paid'], 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center">No payment data found.</td></tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Total</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('other_discount'), 2) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('product_discount'), 2) }}</th>
                    <th></th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('cash'), 2) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('card'), 2) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('mfc'), 2) }}</th>
                    <th class="text-right">৳{{ number_format($totalPaid, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    @elseif($report === 'food_sales')
        <table class="table">
            <thead><tr><th>SL</th><th>Food Item</th><th class="text-center">Qty Sold</th><th class="text-center">Order Count</th><th class="text-right">Gross Sales</th><th class="text-right">Product Discount</th><th class="text-right">Net Sales</th></tr></thead>
            <tbody>
            @forelse($dataRows as $key => $row)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td class="text-center">{{ number_format($row->total_qty) }}</td>
                    <td class="text-center">{{ number_format($row->orders_count) }}</td>
                    <td class="text-right">৳{{ number_format($row->total_sales, 2) }}</td>
                    <td class="text-right">৳{{ number_format($row->product_discount ?? 0, 2) }}</td>
                    <td class="text-right"><strong>৳{{ number_format($row->net_sales ?? (($row->total_sales ?? 0) - ($row->product_discount ?? 0)), 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">No food sales found.</td></tr>
            @endforelse
            </tbody>
            @if($dataRows->count())
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total</th>
                    <th class="text-center">{{ number_format($dataRows->sum('total_qty')) }}</th>
                    <th></th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('total_sales'), 2) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('product_discount'), 2) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('net_sales'), 2) }}</th>
                </tr>
            </tfoot>
            @endif
        </table>
    @elseif($report === 'complimentary_orders')
        @php
            $totalComplimentaryQty = $dataRows->sum(function ($order) {
                $wholeOrderComplimentary = !empty($order->is_complimentary_order);
                return $order->orderDetails->filter(function ($item) use ($wholeOrderComplimentary) {
                    return (float) $item->quantity > 0 && (
                        $wholeOrderComplimentary
                        || !empty($item->is_complimentary)
                        || ((float) $item->price <= 0 && (float) $item->subtotal <= 0)
                    );
                })->sum('quantity');
            });
        @endphp
        <table class="table sales-table">
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Table / Type</th>
                    <th>Complimentary Food</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Order Total</th>
                    <th class="text-right">Honored</th>
                    <th class="text-right">Product Discount</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            @forelse($dataRows as $key => $order)
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
                    $paymentText = ($order->payment_type ?? '') === 'Card' ? 'Bank / Card' : ($order->payment_type ?? 'N/A');
                @endphp
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td><strong>#{{ $order->order_number }}</strong></td>
                    <td>{{ optional($order->customer)->name ?? 'Walk-in' }}</td>
                    <td>{{ $tableText }}</td>
                    <td>
                        @foreach($complimentaryItems as $item)
                            {{ $item->product_name }} × {{ (int) $item->quantity }}@if(!$loop->last), @endif
                        @endforeach
                    </td>
                    <td class="text-center">{{ $complimentaryQty }}</td>
                    <td class="text-right">৳{{ number_format($order->grand_total, 0) }}</td>
                    <td class="text-right">৳{{ number_format($order->discount_amount ?? 0, 0) }}</td>
                    <td class="text-right">৳{{ number_format($order->product_discount_amount ?? 0, 0) }}</td>
                    <td>{{ $paymentText }}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ optional($order->created_at)->format('d M Y') }}</td>
                    <td>{{ optional($order->created_at)->format('h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="text-center">No complimentary food orders found.</td></tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Total ({{ $dataRows->count() }} Orders)</th>
                    <th class="text-center">{{ number_format($totalComplimentaryQty) }}</th>
                    <th class="text-right">৳{{ number_format($periodTotalSale, 0) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('discount_amount'), 0) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('product_discount_amount'), 0) }}</th>
                    <th colspan="4"></th>
                </tr>
            </tfoot>
        </table>
   @else
        <table class="table sales-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th class="text-right">Subtotal</th>
                    <th class="text-right">Honored</th>
                    <th class="text-right">Product Discount</th>
                    <th class="text-right">Service</th>
                    <th class="text-right">Tips</th>
                    <th class="text-right">Given</th>
                    <th class="text-right">Change</th>
                    <th class="text-right">Grand Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>KOT to Pay</th>
                </tr>
            </thead>
            <tbody>
            @forelse($dataRows as $order)
                @php
                    $discountAmount = max(0, (float)($order->discount_amount ?? 0));
                    $productDiscountAmount = max(0, (float)($order->product_discount_amount ?? 0));
                    $serviceCharge = max(0, (float)($order->service_charge ?? 0));
                    $tipsAmount = max(0, (float)($order->tips_amount ?? 0));
                    $givenMoney = max(0, (float)($order->given_money ?? 0));
                    $changeAmount = max(0, (float)($order->change_amount ?? 0));
                    $orderType = strtolower((string) $order->order_type);
                    $tableText = in_array($orderType, ['takeaway', 'delivery'], true) ? ucfirst($orderType) : 'Table T-' . (optional($order->table)->table_number ?? 'N/A');

                    $paymentText = ($order->payment_type ?? '') === 'Card' ? 'Bank / Card' : ($order->payment_type ?? 'N/A');
                    if ($paymentText === 'Split') {
                        $splits = [];
                        if((float)$order->paid_in_cash > 0) $splits[] = 'Cash: ' . number_format($order->paid_in_cash, 0);
                        if((float)$order->paid_in_card > 0) $splits[] = 'Bank / Card: ' . number_format($order->paid_in_card, 0);
                        if((float)$order->paid_in_mfc > 0) $splits[] = 'MFS: ' . number_format($order->paid_in_mfc, 0);
                        $paymentText .= count($splits) ? '<br><span style="font-size:8px;color:#555;">' . implode(', ', $splits) . '</span>' : '';
                    }
                @endphp
                <tr>
                    <td><strong>#{{ $order->order_number }}</strong></td>
                    <td>
                        <strong>{{ optional($order->customer)->name ?? 'Walk-in' }}</strong><br>
                        <span style="font-size:8px; color:#555;">{{ $tableText }}</span>
                    </td>
                    <td class="text-right">৳{{ number_format($order->subtotal, 0) }}</td>
                    <td class="text-right" style="color: red;">৳{{ number_format($discountAmount, 0) }}</td>
                    <td class="text-right" style="color: red;">৳{{ number_format($productDiscountAmount, 0) }}</td>
                    <td class="text-right">৳{{ number_format($serviceCharge, 0) }}</td>
                    <td class="text-right" style="color: green;">৳{{ number_format($tipsAmount, 0) }}</td>
                    <td class="text-right">৳{{ number_format($givenMoney, 0) }}</td>
                    <td class="text-right" style="color: green;">৳{{ number_format($changeAmount, 0) }}</td>
                    <td class="text-right"><strong>৳{{ number_format($order->grand_total, 0) }}</strong></td>
                    <td>{!! $paymentText !!}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ optional($order->created_at)->format('d M Y') }}</td>
                    <td>{{ optional($order->created_at)->format('h:i A') }}</td>
                    <td>{{ is_null($order->kitchen_to_payment_minutes) ? '—' : $order->kitchen_to_payment_minutes . ' min' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" class="text-center py-4">No completed orders found for the selected filter.</td>
                </tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total ({{ $dataRows->count() }} Orders)</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('subtotal'), 0) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('discount_amount'), 0) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('product_discount_amount'), 0) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('service_charge'), 0) }}</th>
                    <th class="text-right">৳{{ number_format($dataRows->sum('tips_amount'), 0) }}</th>
                    <th colspan="2"></th>
                    <th class="text-right">৳{{ number_format($periodTotalSale, 0) }}</th>
                    <th colspan="5"></th>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">Generated on: {{ now()->format('d M, Y h:i A') }} | Tech Partner: {{ $restaurantSettingName }}</div>
</body>
</html>
