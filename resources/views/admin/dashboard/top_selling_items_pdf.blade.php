<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:dejavusans,sans-serif;color:#223028;font-size:10px}
        .header{border-bottom:2px solid #21352a;padding-bottom:10px;margin-bottom:14px}
        .brand{font-size:18px;font-weight:bold;color:#21352a;margin:0 0 3px}
        .subtitle{font-size:10px;color:#66736d;margin:0}
        .meta{width:100%;margin:12px 0 14px;border-collapse:collapse}
        .meta td{width:33.33%;background:#f3f6f4;border:1px solid #dfe7e2;padding:8px}
        .meta-label{font-size:8px;color:#75817b;text-transform:uppercase;font-weight:bold}
        .meta-value{font-size:11px;color:#21352a;font-weight:bold;margin-top:3px}
        table.items{width:100%;border-collapse:collapse}
        table.items th{background:#21352a;color:#fff;padding:7px 6px;text-align:left;font-size:9px}
        table.items td{border-bottom:1px solid #e4e9e6;padding:7px 6px;vertical-align:middle}
        table.items tr:nth-child(even) td{background:#f9fbfa}
        .right{text-align:right}.center{text-align:center}.muted{color:#7a867f}.sold{font-weight:bold;color:#21352a}.status{font-size:8px;font-weight:bold;border:1px solid #d8e1dc;padding:3px 5px;border-radius:3px}
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $restaurant->restaurant_name ?? $restaurant->name ?? $restaurantSettingName }}</div>
        <p class="subtitle">Top Selling Items Report</p>
    </div>

    <table class="meta">
        <tr>
            <td><div class="meta-label">Period</div><div class="meta-value">{{ $periodLabel }}</div></td>
            <td><div class="meta-label">Date Range</div><div class="meta-value">{{ $dateRangeLabel }}</div></td>
            <td><div class="meta-label">Total Products</div><div class="meta-value">{{ number_format($topSellingItems->count()) }}</div></td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:8%;">Rank</th>
                <th style="width:42%;">Item</th>
                <th class="right" style="width:16%;">Quantity Sold</th>
                <th class="right" style="width:20%;">Sales Value</th>
                <th class="center" style="width:14%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topSellingItems as $index => $item)
                @php $hasSales = (int) $item->total_qty > 0; @endphp
                <tr>
                    <td>#{{ $index + 1 }}</td>
                    <td class="sold">{{ $item->product_name }}</td>
                    <td class="right {{ $hasSales ? '' : 'muted' }}">{{ number_format((int) $item->total_qty) }}</td>
                    <td class="right {{ $hasSales ? '' : 'muted' }}">৳{{ number_format((float) $item->total_amount, 0) }}</td>
                    <td class="center"><span class="status">{{ $hasSales ? 'Sold' : 'No Sales' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="center muted">No products found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
