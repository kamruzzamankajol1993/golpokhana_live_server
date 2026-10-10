    <style>
        .taka-symbol{font-family:freesans,sans-serif!important;font-weight:normal!important;font-style:normal!important;}
        body { font-family: freesans, sans-serif; font-size: 13px; color: #222; }
        .header {
            text-align: center;
            margin-bottom: 14px;
            border-bottom: 2px solid #21352a;
            padding: 10px 0 12px;
            background: #f7faf7;
        }
        .header h2 { margin: 0 0 5px 0; color: #183125; font-size: 28px; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 0; color: #555; font-size: 14px; }
        .report-title { margin-top: 12px; color: #2a2a2a; font-size: 19px; font-weight: bold; }
        .report-meta { margin-top: 5px; font-size: 13px; color: #4a4a4a; }

        .table { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
        .table th, .table td { border: 1px solid #cfcfcf; padding: 8px 6px; vertical-align: top; }
        .table th { background-color: #21352a; color: #ffffff; font-size: 12px; font-weight: bold; }
        .table td { font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .sales-table th, .sales-table td { font-size: 10px; padding: 6px 5px; }
        .sales-table th { white-space: nowrap; }
        .sales-table td { word-wrap: break-word; }
        .sales-table tbody tr:nth-child(even) td { background: #fbfbfb; }
        .sales-table tfoot th { background: #eef3ef; color: #1b2c23; font-weight: 700; }

        .sales-table.with-products th,
        .sales-table.with-products td { font-size: 10.3px; padding: 7px 6px; }
        .sales-table.with-products th { font-size: 10.8px; }
        .sales-table.with-products .ordered-items-col { width: 20%; }
        .sales-table.with-products .money-col,
        .sales-table.with-products .status-col,
        .sales-table.with-products .date-col,
        .sales-table.with-products .time-col,
        .sales-table.with-products .kot-col { white-space: nowrap; }
        .sales-table.with-products .payment-col { font-size: 9.6px; line-height: 1.4; }
        .sales-table.with-products .customer-col { line-height: 1.45; }
        .sales-table .ordered-items { margin: 0; padding: 0 0 0 14px; }
        .sales-table .ordered-items li { line-height: 1.45; margin: 0 0 4px; }
        .sales-table .ordered-item-name { font-weight: bold; color: #1a1a1a; }
        .sales-table .ordered-item-discount { color: #b12626; font-size: 9.3px; font-weight: bold; }
        .sales-table .small-muted { font-size: 9.2px; color:#555; }

        .footer {
            margin-top: 22px;
            font-size: 10.5px;
            text-align: center;
            border-top: 1px dashed #cfcfcf;
            padding-top: 8px;
            color: #666;
            background: #fafafa;
        }
        .footer strong { color: #21352a; }
    </style>
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
                Sales &amp; Order Report
            @endif
        </div>
        <div class="report-meta">{{ $filterLabel ?? ('Period: ' . $startDate->format('d M, Y') . ' to ' . $endDate->format('d M, Y')) }}</div>
    </div>
