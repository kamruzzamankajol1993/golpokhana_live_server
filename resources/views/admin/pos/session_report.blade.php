<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Work Period Report #{{ $session->id }}</title>
  <style>
    :root {
      --text: #000000;
      --muted: #555555;
      --mono: 'Courier New', Courier, monospace;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    .receipt-card, .receipt-card * { font-weight: 700 !important; }
    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #e0e0e0;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .receipt-card {
      width: 350px;
      background: #fff;
      padding: 25px 20px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .text-center { text-align: center; }
    .header-title { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
    .header-sub { font-size: 12px; color: var(--muted); margin-bottom: 2px; }
    .meta-section { margin: 15px 0; font-family: var(--mono); font-size: 12px; line-height: 1.5; }
    .section-title { font-size: 14px; font-weight: bold; text-align: center; margin: 15px 0 8px; text-transform: uppercase; letter-spacing: 1px; }
    .dashed-line { border-top: 1px dashed #000; margin: 10px 0; }
    .report-table { width: 100%; border-collapse: collapse; font-family: var(--mono); font-size: 13px; }
    .report-table td, .report-table th { padding: 4px 0; }
    .report-table td, .report-table td * {   font-weight: 900 !important; }
    .report-table th { text-align: left; border-bottom: 1px dotted #000; font-size: 12px; }
    .text-end { text-align: right !important; }
    .fw-bold { font-weight: bold; }
    .footer { font-family: var(--mono); font-size: 11px; color: var(--muted); text-align: center; margin-top: 20px; line-height: 1.6; }
    @media print {
      body { background: none;    font-weight: 900 !important; padding: 0; }
      .receipt-card { box-shadow: none; width: 100%; max-width: 320px; margin: 0 auto; }
      .receipt-card,
      .receipt-card * {
          font-weight: 900 !important;
      }
      .report-table td,
      .report-table td * {
          font-weight: 900 !important;
      }
      .no-print { display: none; }
    }
    .print-btn {
      margin-bottom: 15px; padding: 8px 20px; background: #21352a; color: #fff; border: none; border-radius: 20px; cursor: pointer; font-weight: bold;
    }
  </style>
</head>
<body>

  <button class="print-btn no-print" onclick="window.print()">Print Report</button>

  <div class="receipt-card">
    <div class="text-center"  style="font-weight: 900 !important;">
        <div class="header-title">Work Period Report To Print- {{ $session->id }}</div>
        <div class="header-sub">Period: {{ $session->start_time->format('d M Y H:i') }} - {{ $session->end_time ? $session->end_time->format('d M Y H:i') : 'Running' }}</div>
        <div class="header-sub fw-bold" style="margin-top: 5px; font-size: 13px;">Work Period Closing Report</div>
        <div class="header-title" style="margin-top: 5px; font-size: 16px;">{{ $restaurant->name ?? 'GOLPO KHANA' }}</div>
    </div>

    @php
        $serviceRate = rtrim(rtrim(number_format((float) ($taxSetting->service_charge ?? 0), 2), '0'), '.');
        $vatRate = rtrim(rtrim(number_format((float) ($taxSetting->vat_rate ?? 0), 2), '0'), '.');
        $vatLabel = $taxSetting->tax_label ?? 'VAT';
        $vatRegistrationNo = trim((string) ($taxSetting->tax_registration_no ?? ''));
        $vatRegistrationNo = $vatRegistrationNo !== '' ? $vatRegistrationNo : '-';
        $incomeRows = $reportIncomes ?? ['Cash' => 0, 'Card' => 0, 'MFC' => 0];
        $totalIncome = array_sum($incomeRows);
    @endphp

    <div class="meta-section" style="font-weight: 900 !important;">
        <div>Date Range: {{ $session->start_time->format('d M Y H:i') }} To {{ $session->end_time ? $session->end_time->format('d M Y H:i') : 'Now' }}</div>
        <div>{{ $restaurant->address ?? 'Plot#08, Road#111, Gulshan 2, Dhaka 1212, Bangladesh' }}</div>
        <div>VAT Reg No: {{ $vatRegistrationNo }}</div>
        <div>Mushak: 6.3</div>
    </div>

    <div class="dashed-line"></div>
    <div class="section-title">Sales</div>

    <table class="report-table">
        <tr>
            <td>Outlet Sales</td>
            <td class="text-end fw-bold">{{ round($salesSummary['sales_total'] ?? $session->sales_total ?? 0) }}</td>
        </tr>
        @foreach(($deliveryPartnerIncome ?? []) as $partner)
        <tr>
            <td>{{ $partner['name'] }}</td>
            <td class="text-end fw-bold">{{ round($partner['amount']) }}</td>
        </tr>
        @endforeach
        <tr>
            <td>Product Discount</td>
            <td class="text-end">{{ round($salesSummary['product_discount'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Honored</td>
            <td class="text-end">{{ round($salesSummary['honored'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Discount Total</td>
            <td class="text-end">{{ round($salesSummary['discount_total'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Service Charge ({{ $serviceRate }}%)</td>
            <td class="text-end">{{ round($salesSummary['service_charge'] ?? $session->service_charge ?? 0) }}</td>
        </tr>
        <tr>
            <td>{{ $vatLabel }} ({{ $vatRate }}%)</td>
            <td class="text-end">{{ round($salesSummary['vat_total'] ?? $session->vat_total ?? 0) }}</td>
        </tr>
        <tr class="fw-bold" style="font-size: 14px;">
            <td style="padding-top: 8px;">Total Sales</td>
            <td class="text-end" style="padding-top: 8px;">{{ round($salesSummary['grand_total'] ?? $session->grand_total ?? 0) }}</td>
        </tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Advance &amp; Complimentary</div>

    <table class="report-table">
        <tr>
            <td>Complimentary</td>
            <td class="text-end fw-bold">{{ round($closingExtraSummary['complimentary'] ?? 0) }}</td>
        </tr>
        {{-- Due is intentionally hidden from the Session Report PDF.
        <tr>
            <td>Due</td>
            <td class="text-end fw-bold">{{ round($closingExtraSummary['due'] ?? 0) }}</td>
        </tr>
        --}}
        <tr>
            <td>Customer Advance</td>
            <td class="text-end fw-bold">{{ round($customerAdvance ?? 0) }}</td>
        </tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Due</div>

    <table class="report-table">
        <tr><td>Customer Due (Dine In)</td><td class="text-end fw-bold">{{ round($closingExtraSummary['due'] ?? 0) }}</td></tr>
        @foreach(($deliveryPartnerDue ?? []) as $partnerDue)
        <tr><td>{{ $partnerDue['name'] }} Due</td><td class="text-end fw-bold">{{ round($partnerDue['due']) }}</td></tr>
        @endforeach
        <tr><td>Total Due</td><td class="text-end fw-bold">{{ round(($closingExtraSummary['due'] ?? 0) + collect($deliveryPartnerDue ?? [])->sum('due')) }}</td></tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Collection Methods</div>

    <table class="report-table">
        @foreach(['Cash' => 'Cash', 'Card' => 'Bank / Card', 'MFC' => 'MFS'] as $methodKey => $methodLabel)
            @php
                $amount = (float) ($incomeRows[$methodKey] ?? 0);
                $percentage = $totalIncome > 0 ? ($amount / $totalIncome) * 100 : 0;
            @endphp
            <tr>
                <td>{{ $methodLabel }} &nbsp; {{ number_format($percentage, 2) }}%</td>
                <td class="text-end fw-bold">{{ round($amount) }}</td>
            </tr>
        @endforeach
        <tr class="fw-bold" style="font-size: 14px; border-top: 1px dotted #000;">
            <td style="padding-top: 8px;">Total collection</td>
            <td class="text-end" style="padding-top: 8px;">{{ round($totalIncome) }}</td>
        </tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Sales Type</div>

    <table class="report-table">
        <thead>
            <tr><th>Department</th><th class="text-end">Amount</th></tr>
        </thead>
        <tbody>
            <tr><td>Dine In</td><td class="text-end fw-bold">{{ round($departmentIncome['dine_in'] ?? 0) }}</td></tr>
            <tr><td>Delivery</td><td class="text-end fw-bold">{{ round($departmentIncome['delivery'] ?? 0) }}</td></tr>
            <tr><td>Take Away</td><td class="text-end fw-bold">{{ round($departmentIncome['takeaway'] ?? 0) }}</td></tr>
        </tbody>
    </table>

    <div class="dashed-line"></div>
    <div class="text-center fw-bold" style="font-family: var(--mono); font-size: 13px; margin: 10px 0;">Cash &amp; Bank / Card Summary</div>

    <div class="footer" style="font-weight: 900 !important;">
        <div>*** This is computer generated report and does not require any signature</div>
        <div style="margin-top: 5px;">Print Date Time: {{ now()->format('l, F d, Y H:i:s A') }}</div>
    </div>
  </div>

</body>
</html>
