<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Combined Work Period Closing Report</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; color: #000; font-weight: 900; }
        .receipt-card { width: 90mm; margin: 0 auto; padding: 1.2mm 1.5mm; font-weight: 900; }
        .text-center { text-align: center; }
        .header-title { font-size: 12.5px !important; font-weight: 900; color:#000; margin-bottom: .4px; line-height: 1.08; }
        .header-sub { font-size: 10px !important; font-weight: 900; color: #000; margin-bottom: .2px; line-height: 1.08; }
        .meta-section { margin: 3px 0; font-family: 'Courier New', monospace; font-size: 9.5px; line-height: 1.12; font-weight: 900; }
        .section-title { font-size: 10.5px; font-weight: 900; text-align: center; margin: 3px 0 2px; line-height: 1.08; text-transform: uppercase; letter-spacing: .25px; }
        .dashed-line { border-top: 1px dashed #000; margin: 2.5px 0; }
        .report-table { width: 100%; border-collapse: collapse; font-family: 'Courier New', monospace; font-size: 10px !important; line-height: 1.12; font-weight: 900; }
        .report-table tr, .report-table td, .report-table th { font-size: 10px !important; line-height: 1.12 !important; }
        .report-table td, .report-table th { padding: .9px 0; font-weight: 900; }
        .report-table th { text-align: left; border-bottom: 1px dotted #000; font-size: 9.5px !important; }
        .text-end { text-align: right; }
        .footer { font-family: 'Courier New', monospace; font-size: 9px; color:#000; text-align: center; margin-top: 3px; line-height: 1.12; font-weight: 900; }
        .report-table td[style*="padding-top:3px"] { padding-top: 1.4px !important; }
    </style>
</head>
<body>
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
        <div class="header-sub" style="margin-top:1.5px;font-size:10px;font-weight:900;line-height:1.1;">Work Period Closing Report</div>
        <div class="header-title" style="margin-top:1.5px;font-size:11.5px;line-height:1.1;">{{ $restaurant->name ?? $restaurant->restaurant_name ?? 'GOLPO KHANA' }}</div>
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
        <tr style="border-top:1px dotted #000;"><td style="padding-top:1.4px;">(Product Discount)</td><td class="text-end" style="padding-top:1.4px;">({{ round($salesSummary['product_discount'] ?? 0) }})</td></tr>
        <tr><td>(Honored)</td><td class="text-end">({{ round($salesSummary['honored'] ?? 0) }})</td></tr>
        <tr><td>(Discount Total)</td><td class="text-end">({{ round($salesSummary['discount_total'] ?? 0) }})</td></tr>
        <tr style="font-size:10px;border-top:1px solid #000;"><td style="padding-top:1.4px;">Total Sales</td><td class="text-end" style="padding-top:1.4px;">{{ round($salesSummary['grand_total'] ?? 0) }}</td></tr>
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
        <tr style="border-top:1px solid #000;"><td style="padding-top:1.4px;">Total Due</td><td class="text-end" style="padding-top:1.4px;">{{ round(($closingExtraSummary['due'] ?? 0) + collect($deliveryPartnerDue ?? [])->sum('due')) }}</td></tr>
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
            <tr @if(in_array($methodKey, ['Card', 'MFC'], true)) style="font-size:10px;font-weight:900;" @endif>
                <td>{{ $methodLabel }} &nbsp; {{ number_format($percentage, 2) }}%</td>
                <td class="text-end">{{ round($amount) }}</td>
            </tr>
            @foreach($providerRows as $providerName => $providerAmount)
                <tr style="font-size:10px;font-weight:900;">
                    <td style="padding-left:10px;">↳ {{ $providerName }}</td>
                    <td class="text-end">{{ round($providerAmount) }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr style="font-size:10px;border-top:1px solid #000;"><td style="padding-top:1.4px;">Total Collection</td><td class="text-end" style="padding-top:1.4px;">{{ round($totalIncome) }}</td></tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Sales Type</div>
    <table class="report-table">
        <thead><tr><th>Department</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            <tr><td>Dine In</td><td class="text-end">{{ round($departmentIncome['dine_in'] ?? 0) }}</td></tr>
            <tr><td>Delivery</td><td class="text-end">{{ round($departmentIncome['delivery'] ?? 0) }}</td></tr>
            <tr><td>Take Away</td><td class="text-end">{{ round($departmentIncome['takeaway'] ?? 0) }}</td></tr>
            <tr style="font-size:10px;border-top:1px solid #000;"><td style="padding-top:1.4px;">Total</td><td class="text-end" style="padding-top:1.4px;">{{ round(array_sum($departmentIncome ?? [])) }}</td></tr>
        </tbody>
    </table>

    <div class="dashed-line"></div>
    <div class="text-center" style="font-family:'Courier New',monospace;font-size:10px;margin:2.5px 0;font-weight:900;line-height:1.1;">Cash &amp; Bank / Card Summary</div>
    <div class="footer">
        <div>*** This is computer generated report and does not require any signature</div>
        <div style="margin-top:2px;">Print Date Time: {{ now()->format('l, F d, Y H:i:s A') }}</div>
    </div>
    <div style="margin-top:2px;text-align:center;font-size:9.5px;font-weight:900;color:#000;line-height:1.1;">Powered by : <span style="font-size:10.5px;font-weight:900;color:#000;">{{ $restaurant->name ?? $restaurant->restaurant_name ?? '' }}</span></div>
</div>
</body>
</html>
