<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Work Period Report #{{ $session->id }}</title>
  <style>
    :root {
      --text:       #000000;
      --muted:      #555555;
      --mono:       'Courier New', Courier, monospace;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
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
    .report-table td { padding: 4px 0; }
    .text-end { text-align: right; }
    .fw-bold { font-weight: bold; }

    .footer { font-family: var(--mono); font-size: 11px; color: var(--muted); text-align: center; margin-top: 20px; line-height: 1.6; }

    @media print {
      body { background: none; padding: 0; }
      .receipt-card { box-shadow: none; width: 100%; max-width: 320px; margin: 0 auto; }
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
    <div class="text-center">
        <div class="header-title">Work Period Report To Print- {{ $session->id }}</div>
        <div class="header-sub">Period: {{ $session->start_time->format('d M Y H:i') }} - {{ $session->end_time ? $session->end_time->format('d M Y H:i') : 'Running' }}</div>
        <div class="header-sub fw-bold" style="margin-top: 5px; font-size: 13px;">Work Period Closing Report</div>
        <div class="header-title" style="margin-top: 5px; font-size: 16px;">{{ $restaurant->name ?? 'GOLPO KHANA' }}</div>
    </div>

    <div class="meta-section">
        <div>Date Range: {{ $session->start_time->format('d May Y H:i') }} To {{ $session->end_time ? $session->end_time->format('d May Y H:i') : 'Now' }}</div>
        <div>{{ $restaurant->address ?? 'Plot#08, Road#111, Gulshan 2, Dhaka 1212, Bangladesh' }}</div>
        <div>BIN: {{ $taxSetting->tax_registration_no ?? 'Applied, Mushak-6.3' }}</div>
    </div>

    <div class="dashed-line"></div>
    <div class="section-title">Sales</div>

    <table class="report-table">
        <tr>
            <td>SALES TOTAL</td>
            <td class="text-end fw-bold">{{ round($session->sales_total) }}</td>
        </tr>
        <tr>
            <td>Service Charge</td>
            <td class="text-end">{{ round($session->service_charge) }}</td>
        </tr>
        <tr>
            <td>Vat</td>
            <td class="text-end">{{ round($session->vat_total) }}</td>
        </tr>
        <tr class="fw-bold" style="font-size: 14px;">
            <td style="padding-top: 8px;">GRAND TOTAL</td>
            <td class="text-end" style="padding-top: 8px;">{{ round($session->grand_total) }}</td>
        </tr>
    </table>

    <div class="dashed-line"></div>
    <div class="section-title">Incomes</div>

    <table class="report-table">
        @php
            $incomes = $session->incomes_summary ?? [];
            $grand = $session->grand_total > 0 ? $session->grand_total : 1;
        @endphp

        @foreach($incomes as $method => $amount)
            @if($amount > 0)
            <tr>
                <td>{{ $method }} &nbsp; {{ number_format(($amount / $grand) * 100, 2) }}%</td>
                <td class="text-end fw-bold">{{ round($amount) }}</td>
            </tr>
            @endif
        @endforeach

        <tr class="fw-bold" style="font-size: 14px; border-top: 1px dotted #000;">
            <td style="padding-top: 8px;">TOTAL INCOME</td>
            <td class="text-end" style="padding-top: 8px;">{{ round($session->grand_total) }}</td>
        </tr>
    </table>

    <div class="dashed-line"></div>
    <div class="text-center fw-bold" style="font-family: var(--mono); font-size: 13px; margin: 10px 0;">Cash & Card Summary</div>

    <div class="footer">
        <div>*** This is computer generated report and does not require any signature</div>
        <div style="margin-top: 5px;">Print Date Time: {{ now()->format('l, F d, Y H:i:s A') }}</div>
        {{-- <div class="fw-bold" style="color: #000; margin-top: 3px;">Powered by: 3S</div> --}}
    </div>
  </div>

</body>
</html>
