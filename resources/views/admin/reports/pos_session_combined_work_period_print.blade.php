<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Combined Work Period Closing Report</title>
    <style>
    @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family:'Segoe UI',system-ui,sans-serif; color:#000; font-weight:900; background:#e0e0e0; padding:20px; display:flex; flex-direction:column; align-items:center; }
        .report-actions { width:350px; max-width:100%; margin:0 auto 15px; display:flex; align-items:center; justify-content:center; gap:8px; }
        .report-action-btn { display:inline-flex; align-items:center; justify-content:center; min-width:112px; padding:8px 18px; border-radius:20px; border:1px solid #21352a; font-family:'Segoe UI',system-ui,sans-serif; font-size:13px; font-weight:800; text-decoration:none; cursor:pointer; }
        .report-action-print { background:#21352a; color:#fff; }
        .report-action-return { background:#fff; color:#21352a; }
        /* Thermal printer readability: keep small top/bottom text solid black and visibly heavier. */
        .receipt-card, .receipt-card * { color:#000 !important; font-weight:900 !important; }
        .header-title, .header-sub, .footer, .footer *,
        .receipt-card > div[style*="margin-top:8px"], .receipt-card > div[style*="margin-top: 8px"],
        .receipt-card > div[style*="margin-top:8px"] *, .receipt-card > div[style*="margin-top: 8px"] * {
            color:#000 !important; font-weight:900 !important; opacity:1 !important;
        }
        @media print {
            body { background:none !important; padding:0 !important; }
            .no-print { display:none !important; }
            .receipt-card { box-shadow:none !important; width:100% !important; max-width:320px !important; margin:0 auto !important; padding:5px 7px !important; }
            .header-title, .header-sub, .footer, .footer *, .receipt-card > div[style*="margin-top:8px"] *, .receipt-card > div[style*="margin-top: 8px"] * { -webkit-text-stroke:0.16px #000; text-shadow:0.12px 0 #000, -0.12px 0 #000; }
            .header-title { font-size:12.5px !important; margin-bottom:.4px !important; line-height:1.08 !important; font-weight:900 !important; color:#000 !important; }
            .header-sub { font-size:10px !important; margin-bottom:.2px !important; line-height:1.08 !important; font-weight:900 !important; color:#000 !important; }
            .meta-section { margin:3px 0 !important; font-size:9.5px !important; line-height:1.12 !important; }
            .section-title { margin:3px 0 2px !important; font-size:10.5px !important; line-height:1.08 !important; letter-spacing:.25px !important; }
            .dashed-line { margin:2.5px 0 !important; }
            .report-table { font-size:10px !important; line-height:1.12 !important; }
            .report-table tr, .report-table td, .report-table th { font-size:10px !important; line-height:1.12 !important; }
            .report-table td, .report-table th { padding:.9px 0 !important; }
            .report-table th { font-size:9.5px !important; }
            .report-table td[style*="padding-top:8px"], .report-table th[style*="padding-top:8px"] { padding-top:1.4px !important; }
            .footer { margin-top:3px !important; font-size:9px !important; line-height:1.12 !important; font-weight:900 !important; color:#000 !important; }
            .text-center[style*="margin:10px 0"] { margin:2.5px 0 !important; font-size:10px !important; line-height:1.12 !important; }
            .header-sub[style*="margin-top"], .header-title[style*="margin-top"] { margin-top:1.5px !important; }
            .footer div[style*="margin-top"] { margin-top:1px !important; }
            .receipt-card > div[style*="margin-top:8px"] { margin-top:2px !important; font-size:9.5px !important; line-height:1.1 !important; font-weight:900 !important; color:#000 !important; }
            .receipt-card > div[style*="margin-top:8px"] span { font-size:10.5px !important; line-height:1.1 !important; font-weight:900 !important; color:#000 !important; }
        }
        .receipt-card { width:350px; max-width:100%; margin:0 auto; padding:25px 20px; background:#fff; box-shadow:0 4px 10px rgba(0,0,0,.1); font-weight:900; color:#000; }
        .text-center { text-align: center; }
        .header-title { font-size: 15px; font-weight: 900; margin-bottom: 4px; }
        .header-sub { font-size: 12px; font-weight: 900; color: #000; margin-bottom: 2px; }
        .meta-section { margin: 12px 0; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.45; font-weight: 900; }
        .section-title { font-size: 14px; font-weight: 900; text-align: center; margin: 12px 0 7px; text-transform: uppercase; letter-spacing: .7px; }
        .dashed-line { border-top: 1px dashed #000; margin: 9px 0; }
        .report-table { width: 100%; border-collapse: collapse; font-family: 'Courier New', monospace; font-size: 13px; font-weight: 900; }
        .report-table td, .report-table th { padding: 4px 0; font-weight: 900; }
        .report-table th { text-align: left; border-bottom: 1px dotted #000; font-size: 12px; }
        .text-end { text-align: right; }
        .footer { font-family: 'Courier New', monospace; font-size: 10px; text-align: center; margin-top: 16px; line-height: 1.55; font-weight: 900; }
        /* Keep the Combined Blade preview receipt metrics identical to the print window. */
        .receipt-card { width:320px !important; max-width:100% !important; padding:5px 7px !important; }
        .header-title { font-size:12.5px !important; margin-bottom:.4px !important; line-height:1.08 !important; font-weight:900 !important; color:#000 !important; }
        .header-sub { font-size:10px !important; margin-bottom:.2px !important; line-height:1.08 !important; font-weight:900 !important; color:#000 !important; }
        .meta-section { margin:3px 0 !important; font-size:9.5px !important; line-height:1.12 !important; }
        .section-title { margin:3px 0 2px !important; font-size:10.5px !important; line-height:1.08 !important; letter-spacing:.25px !important; }
        .dashed-line { margin:2.5px 0 !important; }
        .report-table { font-size:10px !important; line-height:1.12 !important; }
        .report-table tr, .report-table td, .report-table th, .report-table span { font-size:10px !important; line-height:1.12 !important; }
        .report-table td, .report-table th { padding:.9px 0 !important; }
        .report-table th { font-size:9.5px !important; }
        .report-table td[style*="padding-top:8px"], .report-table th[style*="padding-top:8px"],
        .report-table td[style*="padding-top: 8px"], .report-table th[style*="padding-top: 8px"] { padding-top:1.4px !important; }
        .footer { margin-top:3px !important; font-size:9px !important; line-height:1.12 !important; font-weight:900 !important; color:#000 !important; }
        .text-center[style*="margin:10px 0"], .text-center[style*="margin: 10px 0"] { margin:2.5px 0 !important; font-size:10px !important; line-height:1.12 !important; }
        .header-sub[style*="margin-top"], .header-title[style*="margin-top"] { margin-top:1.5px !important; }
        .footer div[style*="margin-top"] { margin-top:1px !important; }
        .receipt-card > div[style*="margin-top:8px"], .receipt-card > div[style*="margin-top: 8px"] { margin-top:2px !important; font-size:9.5px !important; line-height:1.1 !important; font-weight:900 !important; color:#000 !important; }
        .receipt-card > div[style*="margin-top:8px"] span, .receipt-card > div[style*="margin-top: 8px"] span { font-size:10.5px !important; line-height:1.1 !important; font-weight:900 !important; color:#000 !important; }
    </style>
</head>
<body>
<div class="report-actions no-print">
    <button type="button" class="report-action-btn report-action-print" onclick="window.print()">Print Report</button>
    <a class="report-action-btn report-action-return" href="{{ $returnUrl ?? route('reports.pos_sessions') }}">Return</a>
</div>
@php
    $serviceRate = rtrim(rtrim(number_format((float) ($taxSetting->service_charge ?? 0), 2), '0'), '.');
    $vatRate = rtrim(rtrim(number_format((float) ($taxSetting->vat_rate ?? 0), 2), '0'), '.');
    $vatLabel = $taxSetting->tax_label ?? 'VAT';
    $vatRegistrationNo = trim((string) ($taxSetting->tax_registration_no ?? '')) ?: '-';
    $incomeRows = $reportIncomes ?? ['Cash' => 0, 'Card' => 0, 'MFC' => 0];
    $totalIncome = array_sum($incomeRows);
    $rangeText = $periodStart
        ? $periodStart->format('d M Y H:i') . ' To ' . ($periodEnd ? $periodEnd->format('d M Y H:i') : 'Now')
        : ($filterLabel ?? 'No matching orders');
@endphp
<div class="receipt-card">
    <div class="text-center">
        <div class="header-title">Work Period Report To Print - Combined</div>
        <div class="header-sub">Period: {{ $filterLabel ?? '-' }}</div>
        <div class="header-sub">Orders Combined: {{ $orderCount ?? 0 }}</div>
        <div class="header-sub" style="margin-top:5px;font-size:13px;font-weight:900;">Work Period Closing Report</div>
        <div class="header-title" style="margin-top:5px;font-size:16px;">{{ $restaurant->name ?? $restaurant->restaurant_name ?? 'GOLPO KHANA' }}</div>
    </div>

    <div class="meta-section">
        <div>Date Range: {{ $rangeText }}</div>
        <div>{{ $restaurant->address ?? 'Plot#08, Road#111, Gulshan 2, Dhaka 1212, Bangladesh' }}</div>
        <div>VAT Reg No: {{ $vatRegistrationNo }}</div>
        <div>Mushak: 6.3</div>
    </div>

    <div class="dashed-line"></div>
    <div class="section-title">Sales</div>
    <table class="report-table">
        <tr><td>Outlet Sales</td><td class="text-end">{{ round($salesSummary['outlet_sales'] ?? $salesSummary['sales_total'] ?? 0) }}</td></tr>
        @foreach(($deliveryPartnerIncome ?? []) as $partner)
            <tr><td>{{ $partner['name'] }}</td><td class="text-end">{{ round($partner['amount']) }}</td></tr>
        @endforeach
        <tr><td>Service Charge ({{ $serviceRate }}%)</td><td class="text-end">{{ round($salesSummary['service_charge'] ?? 0) }}</td></tr>
        <tr><td>{{ $vatLabel }} ({{ $vatRate }}%)</td><td class="text-end">{{ round($salesSummary['vat_total'] ?? 0) }}</td></tr>
        <tr style="border-top:1px dotted #000;"><td style="padding-top:8px;">(Product Discount)</td><td class="text-end" style="padding-top:8px;">({{ round($salesSummary['product_discount'] ?? 0) }})</td></tr>
        <tr><td>(Honored)</td><td class="text-end">({{ round($salesSummary['honored'] ?? 0) }})</td></tr>
        <tr><td>(Discount Total)</td><td class="text-end">({{ round($salesSummary['discount_total'] ?? 0) }})</td></tr>
        <tr style="font-size:14px;border-top:1px solid #000;"><td style="padding-top:8px;">Total Sales</td><td class="text-end" style="padding-top:8px;">{{ round($salesSummary['grand_total'] ?? 0) }}</td></tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Advance &amp; Complimentary</div>
    <table class="report-table">
        <tr><td>Complimentary</td><td class="text-end">{{ round($closingExtraSummary['complimentary'] ?? 0) }}</td></tr>
        <tr><td>Customer Advance</td><td class="text-end">{{ round($customerAdvance ?? 0) }}</td></tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Due</div>
    <table class="report-table">
        <tr><td>Customer Due (Dine In)</td><td class="text-end">{{ round($closingExtraSummary['due'] ?? 0) }}</td></tr>
        @foreach(($deliveryPartnerDue ?? []) as $partnerDue)
            <tr><td>{{ $partnerDue['name'] }} Due</td><td class="text-end">{{ round($partnerDue['due']) }}</td></tr>
        @endforeach
        <tr style="border-top:1px solid #000;"><td style="padding-top:8px;">Total Due</td><td class="text-end" style="padding-top:8px;">{{ round(($closingExtraSummary['due'] ?? 0) + collect($deliveryPartnerDue ?? [])->sum('due')) }}</td></tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Collection Methods</div>
    <table class="report-table">
        @foreach(['Cash' => 'Cash', 'Card' => 'Bank / Card', 'MFC' => 'MFS'] as $methodKey => $methodLabel)
            @php
                $amount = (float) ($incomeRows[$methodKey] ?? 0);
                $percentage = $totalIncome > 0 ? ($amount / $totalIncome) * 100 : 0;
                $providerRows = $methodKey === 'Card'
                    ? ($cardProviderIncome ?? [])
                    : ($methodKey === 'MFC' ? ($mfsProviderIncome ?? []) : []);
            @endphp
            <tr @if(in_array($methodKey, ['Card', 'MFC'], true)) style="font-size:12px;font-weight:900;" @endif>
                <td>{{ $methodLabel }} &nbsp; {{ number_format($percentage, 2) }}%</td>
                <td class="text-end">{{ round($amount) }}</td>
            </tr>
            @foreach($providerRows as $providerName => $providerAmount)
                <tr style="font-size:12px;font-weight:900;">
                    <td style="padding-left:10px;">↳ {{ $providerName }}</td>
                    <td class="text-end">{{ round($providerAmount) }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr style="font-size:14px;border-top:1px solid #000;"><td style="padding-top:8px;">Total Collection</td><td class="text-end" style="padding-top:8px;">{{ round($totalIncome) }}</td></tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Sales Type</div>
    <table class="report-table">
        <thead><tr><th>Department</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            <tr><td>Dine In</td><td class="text-end">{{ round($departmentIncome['dine_in'] ?? 0) }}</td></tr>
            <tr><td>Delivery</td><td class="text-end">{{ round($departmentIncome['delivery'] ?? 0) }}</td></tr>
            <tr><td>Take Away</td><td class="text-end">{{ round($departmentIncome['takeaway'] ?? 0) }}</td></tr>
            <tr style="font-size:14px;border-top:1px solid #000;"><td style="padding-top:8px;">Total</td><td class="text-end" style="padding-top:8px;">{{ round(array_sum($departmentIncome ?? [])) }}</td></tr>
        </tbody>
    </table>

    <div class="dashed-line"></div>
    <div class="text-center" style="font-family:'Courier New',monospace;font-size:13px;margin:10px 0;font-weight:900;">Cash &amp; Bank / Card Summary</div>
    <div class="footer">
        <div>*** This is computer generated report and does not require any signature</div>
        <div style="margin-top:5px;">Print Date Time: {{ now()->format('l, F d, Y H:i:s A') }}</div>
    </div>
    <div style="margin-top:8px;text-align:center;font-size:10px;font-weight:700;">Powered by : <span style="font-size:12px;font-weight:900;">{{ $restaurant->name ?? $restaurant->restaurant_name ?? '' }}</span></div>
</div>
</body>
</html>
