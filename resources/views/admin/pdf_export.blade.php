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
        .text-center { text-align: center; }
        .footer { margin-top: 30px; font-size: 11px; text-align: center; border-top: 1px dashed #ddd; padding-top: 10px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $restaurant->name ?? 'Progga RMS' }}</h2>
        <p>{{ $restaurant->address ?? '' }} | Phone: {{ $restaurant->phone ?? 'N/A' }}</p>
        <div class="report-title">
            @if($report === 'payment_type_sales')
                Payment Type Wise Sales Report
            @elseif($report === 'food_sales')
                Food Wise Sales Report
            @else
                Sales & Order Report
            @endif
        </div>
        <p>Period: {{ $startDate->format('d M, Y') }} to {{ $endDate->format('d M, Y') }}</p>
    </div>

    @if($report === 'payment_type_sales')
        @php $total = $dataRows->sum('amount'); @endphp
        <table class="table">
            <thead><tr><th>Payment Type</th><th class="text-center">Order Count</th><th class="text-right">Amount</th><th class="text-right">Share</th></tr></thead>
            <tbody>
            @foreach($dataRows as $row)
                <tr><td>{{ $row['label'] }}</td><td class="text-center">{{ $row['orders_count'] }}</td><td class="text-right">৳{{ number_format($row['amount'], 2) }}</td><td class="text-right">{{ number_format($row['percentage'], 2) }}%</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><th colspan="2" class="text-right">Total</th><th class="text-right">৳{{ number_format($total, 2) }}</th><th></th></tr></tfoot>
        </table>
    @elseif($report === 'food_sales')
        <table class="table">
            <thead><tr><th>SL</th><th>Food Item</th><th class="text-center">Qty Sold</th><th class="text-center">Order Count</th><th class="text-right">Total Sales</th></tr></thead>
            <tbody>
            @forelse($dataRows as $key => $row)
                <tr><td>{{ $key + 1 }}</td><td>{{ $row->product_name }}</td><td class="text-center">{{ number_format($row->total_qty) }}</td><td class="text-center">{{ number_format($row->orders_count) }}</td><td class="text-right">৳{{ number_format($row->total_sales, 2) }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center">No food sales found.</td></tr>
            @endforelse
            </tbody>
        </table>
    @else
        @php $totalRevenue = $dataRows->where('status', 'Completed')->sum('grand_total'); @endphp
        <table class="table">
            <thead><tr><th>SL</th><th>Order #</th><th>Date & Time</th><th>Customer</th><th>Table</th><th>Payment</th><th class="text-right">Total</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($dataRows as $key => $order)
                <tr><td>{{ $key + 1 }}</td><td>#{{ $order->order_number }}</td><td>{{ $order->created_at->format('d/m/Y h:i A') }}</td><td>{{ $order->customer->name ?? 'Walk-in' }}</td><td>{{ $order->table->table_number ?? 'Takeaway' }}</td><td>{{ $order->payment_type ?? 'N/A' }}</td><td class="text-right">৳{{ number_format($order->grand_total, 2) }}</td><td>{{ $order->status }}</td></tr>
            @empty
                <tr><td colspan="8" class="text-center">No orders found.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><th colspan="6" class="text-right">Total Revenue (Completed)</th><th class="text-right">৳{{ number_format($totalRevenue, 2) }}</th><th></th></tr></tfoot>
        </table>
    @endif

    <div class="footer">Generated on: {{ now()->format('d M, Y h:i A') }} | Tech Partner: Progga RMS</div>
</body>
</html>
