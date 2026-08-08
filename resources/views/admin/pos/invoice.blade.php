@php
    // Invoice can be opened directly after payment without POS index variables.
    // Keep optional POS session variables safe if any shared block checks them.
    $activeSession = $activeSession ?? null;
    $requirePreviousSessionClose = $requirePreviousSessionClose ?? false;
    $sessions = $sessions ?? collect();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Invoice #{{ $order->order_number }} — {{ $restaurantSettingName ?? 'TableTrack RMS' }}</title>
  <style>

    :root {
      --mono: Arial, Helvetica, sans-serif;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 900;
      background: #e8e8e8;
      width: 90mm;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 10px;
    }

    .page-label {
      font-size: 13px; font-weight: 900;
      color: #000; background: #fff;
      padding: 6px 20px; border-radius: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,.12);
      margin-bottom: 28px; letter-spacing: .4px;
      font-family: var(--mono);
    }

    .receipt-card {
      width: 100%; background: #fff;
      border-radius: 6px 6px 0 0;
      box-shadow: 0 8px 32px rgba(0,0,0,.18);
      position: relative; overflow: visible;
    }
    .receipt-card::after {
      content: '';
      position: absolute;
      bottom: -14px; left: 0; right: 0; height: 14px;
      background: radial-gradient(circle at 7px -1px, #e8e8e8 7px, transparent 0) 0 0 / 14px 14px repeat-x;
    }

    .bill-header {
      background: #fff; padding: 22px 20px 18px;
      text-align: center; border-radius: 6px 6px 0 0;
      border-bottom: 2px solid #000;
    }
    .bill-logo-circle {
      width: 52px; height: 52px; border-radius: 50%;
      background: #fff; border: 2px solid #000;
      display: flex; align-items: center; justify-content: center;
      font-size: 26px; font-weight: 900; color: #000;
      margin: 0 auto 10px; font-family: var(--mono);
    }
    .bill-restaurant-name {
      font-size: 18px; font-weight: 900; color: #000;
      letter-spacing: 2px; text-transform: uppercase;
      margin-bottom: 6px; font-family: var(--mono);
    }
    .bill-restaurant-addr {
      font-size: 11px; color: #000;
      line-height: 1.6; margin-bottom: 2px; font-family: var(--mono);
    }
    .bill-restaurant-phone {
      font-size: 11.5px; color: #000; font-weight: 900;
      margin-top: 4px; font-family: var(--mono);
    }

    .bill-title-bar {
      background: #fff; padding: 7px 20px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1.5px dashed #000;
    }
    .bill-title-text {
      font-size: 12px; font-weight: 900; color: #000;
      letter-spacing: 1.5px; text-transform: uppercase; font-family: var(--mono);
    }
    .bill-musak { font-size: 10.5px; font-weight: 900; color: #000; font-family: var(--mono); }

    .bill-vat-line {
      text-align: center; font-size: 10px; color: #000;
      padding: 6px 20px; border-bottom: 1px dashed #000; font-family: var(--mono);
    }

    .bill-body { padding: 10px; }

    .bill-meta-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 4px 12px; margin-bottom: 14px;
    }
    .bill-meta-row { display: flex; gap: 4px; font-size: 11.5px; font-family: var(--mono); }
    .bill-meta-label { color: #000; min-width: 56px; font-weight: 900; }
    .bill-meta-val   { color: #000; font-weight: 900; min-width: 80px; }

    .dashed-sep       { border: none; border-top: 1.5px dashed #000; margin: 10px 0; }
    .dashed-sep-thick { border: none; border-top: 2px dashed #000; margin: 12px 0; }

    .bill-items-table {
      width: 100%; border-collapse: collapse;
      font-family: var(--mono); font-size: 12px; font-weight: 900;
    }
    .bill-items-table thead th {
      font-size: 11px; font-weight: 900; color: #000;
      text-transform: uppercase; letter-spacing: .5px;
      padding: 4px 0 8px; border-bottom: 1.5px dashed #000;
      font-family: var(--mono);
    }
    .bill-items-table thead th:first-child { width: 32px; text-align: center; }
    .bill-items-table thead th:last-child  { text-align: right; width: 60px; }
    .bill-items-table tbody td {
      padding: 7px 0; vertical-align: top;
      border-bottom: 1px dashed #000;   /* FIX: was dotted #999 */
      font-family: var(--mono);
    }
    .bill-items-table tbody tr:last-child td { border-bottom: none; }
    .bill-items-table td:first-child { text-align: center; color: #000; font-weight: 900; }
    .bill-items-table td:last-child  { text-align: right; font-weight: 900; }
    .bill-item-name { font-weight: 900; color: #000; line-height: 1.3; font-family: var(--mono); }
    .bill-item-note {
      font-size: 11px; color: #000;   /* FIX: was #555 */
      margin-top: 2px; font-style: italic; font-weight: 900; font-family: var(--mono);
    }

    .bill-totals { padding: 0 2px; }
    .bill-total-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 4px 0; font-family: var(--mono); font-size: 12px;
      color: #000; font-weight: 900;
    }
    .bill-total-row.grand {
      font-size: 15px; font-weight: 900; color: #000;
      padding: 10px 0 6px; border-top: 2px solid #000; margin-top: 6px;
    }
    .bill-total-row.grand span:last-child { font-size: 17px; font-weight: 900; }

    .bill-payment {
      display: flex; align-items: center; justify-content: space-between;
      background: #fff; border: 1.5px dashed #000;
      border-radius: 8px; padding: 10px 14px; margin-top: 12px;
    }
    .bill-payment-label {
      font-size: 11px; color: #000; font-weight: 900;
      text-transform: uppercase; letter-spacing: .5px; font-family: var(--mono);
    }
    .bill-payment-val { font-size: 13px; font-weight: 900; color: #000; font-family: var(--mono); }
    .bill-payment-top {
      display: flex; justify-content: space-between; align-items: flex-start;
      gap: 12px; margin-bottom: 6px;
    }
    .bill-payment-metric { text-align: right; }
    .bill-payment-adjustments {
      margin-top: 8px; padding-top: 8px;
      border-top: 1.5px dashed #000;
    }
    .bill-payment-info-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 3px 0; font-family: var(--mono); font-size: 12px;
      color: #000; font-weight: 900;
    }
    .bill-payment-info-row span:first-child {
      text-transform: uppercase; letter-spacing: .4px;
    }
    .bill-payment-change {
      display: flex; justify-content: flex-end;
      margin-top: 6px; padding-top: 6px;
      border-top: 1px dashed #000;
    }
    .bill-payment-change .bill-payment-metric { min-width: 110px; }

    .bill-server {
      text-align: center; font-size: 11.5px; color: #000;
      margin-top: 12px; font-family: var(--mono);
    }

    .bill-footer {
      text-align: center; padding: 16px 20px 22px;
      border-top: 1.5px dashed #000; margin-top: 14px;
    }
    .bill-thankyou {
      font-size: 15px; font-weight: 900; color: #000;
      letter-spacing: 1px; margin-bottom: 10px; font-family: var(--mono);
    }
    .bill-partner { font-size: 9.5px; color: #000; margin-top: 6px; font-family: var(--mono); }

    .btn-print-wrap { margin-top: 28px; display: flex; justify-content: center; }
    .btn-print {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 11px 15px; border: none; cursor: pointer;
      border-radius: 10px; font-size: 13px; font-weight: 900;
      background: #000; color: #fff;
      box-shadow: 0 4px 16px rgba(0,0,0,.3);
      transition: transform .15s, box-shadow .15s; font-family: var(--mono);
      text-decoration: none;
      margin-left: 10px;
    }
    .btn-print:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.4); }
    .btn-print svg { width: 15px; height: 15px; }

    @media print {
      body {
        background: #fff !important; padding: 0 !important;
        display: block; width: 80mm !important; margin: 0 !important;
      }
      .no-print { display: none !important; }
      .receipt-card { box-shadow: none !important; width: 100% !important; margin: 0 auto; }
      .receipt-card::after { display: none; }
      .page-label { display: none; }
      * {
        color: #000 !important;
        font-weight: 900 !important;
        font-family: Arial, Helvetica, sans-serif !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .bill-restaurant-name,
      .bill-total-row.grand,
      .bill-total-row.grand span,
      .bill-payment-val,
      .bill-thankyou { font-weight: 900 !important; }
    }
  </style>
</head>
<body>

  <div class="page-label no-print">🧾 Customer Invoice</div>

  <div class="receipt-card">

    <div class="bill-header">
      <div class="bill-logo-circle">{{ substr($restaurantSettingName ?? 'P', 0, 1) }}</div>
      <div class="bill-restaurant-name">{{ $restaurantSettingName ?? 'Progga RMS' }}</div>
      <div class="bill-restaurant-addr">
        {!! nl2br(e($restaurantSettingAddress ?? 'Block I, House 52, Road No. 01\nBanani, Dhaka 1213')) !!}
      </div>
      <div class="bill-restaurant-phone">📞 {{ $restaurantSettingPhone ?? '01755 898 542' }}</div>
    </div>

    <div class="bill-title-bar">
      <span class="bill-title-text">Guest Bill</span>
      <span class="bill-musak">MUSAK – 6.3</span>
    </div>

    <div class="bill-vat-line">Bin No – {{ $taxSettingTaxRegistrationNo ?? '006334813-0101' }}</div>

    <div class="bill-body">

      <div class="bill-meta-grid">
        <div class="bill-meta-row">
          <span class="bill-meta-label">Order ID</span>
          <span class="bill-meta-val">: {{ $invoiceSettingPrefix ?? 'INV-' }}{{ $order->order_number }}</span>
        </div>
        <div class="bill-meta-row">
          <span class="bill-meta-label">Table No</span>
          <span class="bill-meta-val">: {{ $order->table->table_number ?? 'Takeaway' }}</span>
        </div>
        <div class="bill-meta-row">
          <span class="bill-meta-label">Date</span>
          <span class="bill-meta-val">: {{ $order->created_at->format('d/m/y') }}</span>
        </div>
        <div class="bill-meta-row">
          <span class="bill-meta-label">Customer</span>
          <span class="bill-meta-val">: {{ $order->customer->name ?? 'Walk-in' }}</span>
        </div>
        <div class="bill-meta-row" style="grid-column:1/-1;">
          <span class="bill-meta-label">Time</span>
          <span class="bill-meta-val">: {{ $order->created_at->format('h:i:s A') }}</span>
        </div>
      </div>

      <hr class="dashed-sep-thick">

      <table class="bill-items-table">
        <thead>
          <tr>
            <th>QTY</th>
            <th>Item</th>
            <th>Price</th>
          </tr>
        </thead>
        <tbody>

          @foreach($order->orderDetails as $item)

              {{-- যদি Unavailable না হয়, তবেই ইনভয়েসে প্রিন্ট হবে --}}
              @if(!$item->is_unavailable)
                  @php $addons = json_decode($item->addons, true) ?? []; @endphp
                  <tr>
                    <td>{{ $item->quantity }}</td>
                    <td>
                      <div class="bill-item-name">{{ $item->product_name }}</div>
                      @if((isset($item->is_complimentary) && $item->is_complimentary) || ((float) $item->price <= 0 && (float) $item->subtotal <= 0))
                        <div class="bill-item-note">Complimentary</div>
                      @endif
                      @if($item->food_note)
                        <div class="bill-item-note">{{ $item->food_note }}</div>
                      @endif
                      @if(count($addons) > 0)
                        <div class="bill-item-note">
                            @foreach($addons as $addon) +{{ $addon['name'] }} @endforeach
                        </div>
                      @endif
                    </td>
                    <td>{{ round($item->subtotal) }}</td>
                  </tr>
              @endif

          @endforeach

        </tbody>
      </table>

      <hr class="dashed-sep-thick">

      <div class="bill-totals">
        <div class="bill-total-row">
          <span>Sub Total</span>
          <span>{{ number_format($order->subtotal, 0) }}</span>
        </div>



        @if($order->service_charge > 0)
        <div class="bill-total-row">
          <span>Service Charge ({{ $taxSettingServiceCharge }}%)</span>
          <span>+ {{ number_format($order->service_charge, 0) }}</span>
        </div>
        @endif

        @if($order->vat_tax > 0)
        <div class="bill-total-row">
          <span>{{ $taxSettingTaxLabel }} ({{ $taxSettingVatRate }}%)</span>
          <span>+ {{ number_format($order->vat_tax, 0) }}</span>
        </div>
        @endif
 @if($order->discount_amount > 0)
        <div class="bill-total-row discount">
          <span>Honored</span>
          <span>− {{ number_format($order->discount_amount, 0) }}</span>
        </div>
        @endif
        <div class="bill-total-row grand">
          <span>Total Payable</span>
          <span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->grand_total, 0) }}</span>
        </div>
      </div>

      @php
          // Invoice payment display helper values.
          // Given Amount and Tips will show above Change when available.
          $invoiceGivenAmount = max(0, (float) ($order->given_money ?? 0));
          $invoiceTipsAmount = max(0, (float) ($order->tips_amount ?? 0));
      @endphp

      @if(($order->payment_type ?? 'Cash') === 'Split')
        <div class="bill-payment" style="display:block;">
          <div class="bill-payment-top">
            <div>
              <div class="bill-payment-label">Paid By</div>
              <div class="bill-payment-val">Split</div>
            </div>
            <div style="text-align:right;">
              <div class="bill-payment-label">Total Paid</div>
              <div class="bill-payment-val">{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->invoice_paid_amount ?? $order->total_paid_amount ?? 0, 0) }}</div>
            </div>
          </div>
          <div class="bill-total-row"><span>Cash</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->invoice_paid_in_cash ?? $order->paid_in_cash ?? 0, 0) }}</span></div>
          <div class="bill-total-row"><span>Card</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->invoice_paid_in_card ?? $order->paid_in_card ?? 0, 0) }}</span></div>
          <div class="bill-total-row"><span>MFC / Mobile</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->invoice_paid_in_mfc ?? $order->paid_in_mfc ?? 0, 0) }}</span></div>

          @if($invoiceGivenAmount > 0 || $invoiceTipsAmount > 0)
            <div class="bill-payment-adjustments">
              @if($invoiceGivenAmount > 0)
                <div class="bill-payment-info-row"><span>Given Amount</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($invoiceGivenAmount, 0) }}</span></div>
              @endif

                <div class="bill-payment-info-row"><span>Tips</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($invoiceTipsAmount, 0) }}</span></div>

            </div>
          @endif


            <div class="bill-payment-change">
              <div class="bill-payment-metric">
                <div class="bill-payment-label">Change</div>
                <div class="bill-payment-val">{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->change_amount, 0) }}</div>
              </div>
            </div>


          @if(($order->due ?? 0) > 0)
            <div class="bill-total-row"><span>Due</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->due, 0) }}</span></div>
          @endif
        </div>
      @else
        <div class="bill-payment" style="display:block;">
          <div class="bill-payment-top">
            <div>
              <div class="bill-payment-label">Paid By</div>
              <div class="bill-payment-val">{{ $order->payment_type ?? 'Cash' }}</div>
            </div>
            <div style="text-align:right;">
              <div class="bill-payment-label">Total Paid</div>
              <div class="bill-payment-val">{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->total_paid_amount ?? 0, 0) }}</div>
            </div>
          </div>

          @if($invoiceGivenAmount > 0 || $invoiceTipsAmount > 0)
            <div class="bill-payment-adjustments">
              @if($invoiceGivenAmount > 0)
                <div class="bill-payment-info-row"><span>Given Amount</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($invoiceGivenAmount, 0) }}</span></div>
              @endif

                <div class="bill-payment-info-row"><span>Tips</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($invoiceTipsAmount, 0) }}</span></div>

            </div>
          @endif


            <div class="bill-payment-change">
              <div class="bill-payment-metric">
                <div class="bill-payment-label">Change</div>
                <div class="bill-payment-val">{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->change_amount, 0) }}</div>
              </div>
            </div>


          @if(($order->due ?? 0) > 0)
            <div class="bill-total-row"><span>Due</span><span>{{ $restaurantSettingCurrency ?? '৳' }} {{ number_format($order->due, 0) }}</span></div>
          @endif
        </div>
      @endif

      <div class="bill-server">
        You Have Been Served By: <strong>{{ $order->waiter->name ?? $order->user->name ?? 'N/A' }}</strong>
      </div>

    </div><div class="bill-footer">
      {{-- <div class="bill-thankyou">✦ Thank You ✦</div> --}}
      <div class="bill-footer-links">
        {!! nl2br(e($invoiceSettingFooterNote ?? "Tech Partner — Progga RMS\nVisit our website to know more!")) !!}
      </div>
      <div class="bill-partner">::::::::::::::::::::::::::::::::::::::::::::</div>
    </div>

  </div><div class="btn-print-wrap no-print">
    <a href="{{ route('pos.index') }}" class="btn-print outline">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
      </svg>
      Back to POS
    </a>
    <button class="btn-print" onclick="window.print()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 6 2 18 2 18 9"/>
        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
        <rect x="6" y="14" width="12" height="8"/>
      </svg>
      Print Invoice
    </button>
  </div>

</body>
</html>
