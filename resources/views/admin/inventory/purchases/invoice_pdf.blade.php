<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Invoice {{ $purchase->purchase_no }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; color:#1f2933; font-size:11px; margin:0; }
        .header { border-bottom:2px solid #21352a; padding-bottom:14px; margin-bottom:18px; }
        .brand { font-size:22px; font-weight:700; color:#21352a; }
        .muted { color:#6b7280; }
        .title { font-size:20px; font-weight:700; text-align:right; color:#21352a; }
        .meta-table, .items, .summary { width:100%; border-collapse:collapse; }
        .meta-table td { vertical-align:top; padding:5px 0; }
        .box { border:1px solid #d9dedb; border-radius:6px; padding:12px; margin-bottom:16px; }
        .items th { background:#21352a; color:#fff; font-weight:700; padding:9px 7px; text-align:left; }
        .items td { border-bottom:1px solid #e5e7eb; padding:9px 7px; vertical-align:top; }
        .items .num, .items th.num { text-align:right; white-space:nowrap; }
        .summary { width:44%; margin-left:auto; margin-top:14px; }
        .summary td { padding:6px 4px; }
        .summary .total td { border-top:2px solid #21352a; padding-top:9px; font-size:13px; font-weight:700; }
        .status { display:inline-block; padding:4px 9px; border:1px solid #cbd5d1; border-radius:12px; font-size:10px; font-weight:700; }
        .section-title { font-size:12px; font-weight:700; color:#21352a; margin-bottom:5px; }
        .notes { border-top:1px solid #e5e7eb; margin-top:18px; padding-top:12px; }
        .footer-note { margin-top:22px; color:#6b7280; font-size:9px; text-align:center; }
    </style>
</head>
<body>
    <table class="meta-table header">
        <tr>
            <td style="width:62%">
                <div class="brand">{{ $restaurant?->name ?: 'Restaurant' }}</div>
                @if($branch)<div><strong>{{ $branch->name }}</strong></div>@endif
                @if($restaurant?->address)<div class="muted">{{ $restaurant->address }}</div>@endif
                @if($restaurant?->phone)<div class="muted">Phone: {{ $restaurant->phone }}</div>@endif
                @if($restaurant?->email)<div class="muted">{{ $restaurant->email }}</div>@endif
            </td>
            <td style="width:38%; text-align:right">
                <div class="title">PURCHASE INVOICE</div>
                <div style="margin-top:6px"><strong>{{ $purchase->purchase_no }}</strong></div>
                <div class="muted">{{ optional($purchase->purchase_date)->format('d M Y') }}</div>
                <div style="margin-top:6px"><span class="status">{{ $purchase->status }}</span></div>
            </td>
        </tr>
    </table>

    <div class="box">
        <table class="meta-table">
            <tr>
                <td style="width:50%">
                    <div class="section-title">Vendor</div>
                    <div><strong>{{ $purchase->vendor?->name ?: '—' }}</strong></div>
                    @if($purchase->vendor?->phone)<div class="muted">{{ $purchase->vendor->phone }}</div>@endif
                    @if($purchase->vendor?->address)<div class="muted">{{ $purchase->vendor->address }}</div>@endif
                </td>
                <td style="width:50%">
                    <div><span class="muted">Supplier Invoice No:</span> {{ $purchase->invoice_no ?: '—' }}</div>
                    <div><span class="muted">Reference:</span> {{ $purchase->reference_no ?: '—' }}</div>
                    <div><span class="muted">Created By:</span> {{ $purchase->creator?->name ?: '—' }}</div>
                    @if($purchase->status === \App\Models\Purchase::STATUS_RECEIVED)
                        <div><span class="muted">Received By:</span> {{ $purchase->receiver?->name ?: '—' }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:31%">Ingredient</th>
                <th style="width:22%">Purchased Qty</th>
                <th class="num" style="width:20%">Purchase Price</th>
                <th class="num" style="width:22%">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $item->ingredient?->name }}</strong></td>
                <td>{{ rtrim(rtrim((string)$item->quantity, '0'), '.') }} {{ $item->packageConversion?->label ?: $item->unit?->symbol }}</td>
                <td class="num">
                    @if($item->package_conversion_id || $item->unit?->dimension === 'PACKAGE')
                        ৳{{ number_format((float)$item->unit_price, 2) }}<br><span class="muted">per package</span>
                    @else
                        ৳{{ number_format((float)$item->line_total, 2) }}<br><span class="muted">for entered qty</span>
                    @endif
                </td>
                <td class="num">৳{{ number_format((float)$item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr><td>Subtotal</td><td style="text-align:right">৳{{ number_format((float)$purchase->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td style="text-align:right">৳{{ number_format((float)$purchase->discount, 2) }}</td></tr>
        <tr><td>Tax</td><td style="text-align:right">৳{{ number_format((float)$purchase->tax, 2) }}</td></tr>
        <tr class="total"><td>Total</td><td style="text-align:right">৳{{ number_format((float)$purchase->total, 2) }}</td></tr>
    </table>

    @if($purchase->notes)
    <div class="notes">
        <div class="section-title">Notes</div>
        <div>{{ $purchase->notes }}</div>
    </div>
    @endif

    <div class="footer-note">System-generated purchase invoice. Generated {{ now()->format('d M Y, h:i A') }}.</div>
</body>
</html>
