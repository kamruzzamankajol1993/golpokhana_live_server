<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KOT — Kitchen Order Ticket</title>
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 900;
      background: #e8e8e8;
      width: 80mm;
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


    .running-order-banner {
      width: 100%;
      background: #000;
      color: #fff;
      text-align: center;
      font-size: 22px;
      font-weight: 900;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      padding: 10px 8px;
      border-radius: 6px 6px 0 0;
      border-bottom: 2px solid #000;
    }
    .running-order-banner + .kot-header { border-radius: 0; }

    .kot-header {
      background: #fff;
      padding: 18px 20px 14px;
      border-radius: 6px 6px 0 0;
      border-bottom: 2px solid #000;
    }
    .kot-label {
      font-size: 10.5px; font-weight: 900; color: #000;
      letter-spacing: 2.5px; text-transform: uppercase;
      margin-bottom: 6px;
    }
    .kot-header-row {
      display: flex; align-items: flex-end; justify-content: space-between;
    }
    .kot-number {
      font-size: 15px; font-weight: 900; color: #000; line-height: 1;
    }
    .kot-number-label {
      font-size: 18px; color: #000; font-weight: 900;
      margin-top: 3px; letter-spacing: .5px;
    }
    .kot-time {
      font-size: 20px; font-weight: 900; color: #000; text-align: right;
    }
    .kot-date {
      font-size: 10px; color: #000; font-weight: 900;
      margin-top: 3px; text-align: right;
    }

    .kot-info-strip {
      background: #fff; display: flex; border-bottom: 2px dashed #000;
    }
    .kot-info-cell {
      flex: 1; padding: 10px 14px; border-right: 1.5px dashed #000;
    }
    .kot-info-cell:last-child { border-right: none; }
    .kot-info-label {
      font-size: 9px; color: #000; font-weight: 900;
      text-transform: uppercase; letter-spacing: .8px; margin-bottom: 3px;
    }
    .kot-info-val {
      font-size: 18px; font-weight: 900; color: #000; line-height: 1;
    }
    .kot-info-val.sm {
      font-size: 13px; font-weight: 900; padding-top: 3px;
    }

    .kot-body { padding: 16px 20px; background: #fff; }

    .kot-section-head {
      font-size: 9.5px; font-weight: 900;
      text-transform: uppercase; letter-spacing: 1.5px;
      color: #000; margin-bottom: 12px;
      display: flex; align-items: center; gap: 8px;
    }
    .kot-section-head::after {
      content: ''; flex: 1; height: 1.5px; background: #000; opacity: .4;
    }

    .kot-items { display: flex; flex-direction: column; gap: 10px; }

    .kot-item {
      display: flex; align-items: center; gap: 14px;
      background: #fff; border-radius: 8px; padding: 5px 10px;
      border: 1px solid #000;
    }

    .kot-qty-wrap {
      display: flex; flex-direction: column; align-items: center; flex-shrink: 0;
    }
    .kot-qty {
      font-size: 26px; font-weight: 900; color: #000; line-height: 1;
    }
    .kot-qty-label {
      font-size: 9px; color: #000; font-weight: 900;
      text-transform: uppercase; letter-spacing: .3px; margin-top: 1px;
    }

    .kot-item-info { flex: 1; }
    .kot-item-name {
      font-size: 14.5px; font-weight: 900; color: #000; line-height: 1.2;
    }
    .kot-item-note {
      font-size: 11px; color: #000; font-weight: 900;
      margin-top: 5px; font-style: italic;
    }
    .kot-item-note::before { content: '⚠ '; }

    .kot-instructions {
      background: #fff; border: 1.5px dashed #000;
      border-radius: 8px; padding: 11px 14px; margin-top: 14px;
    }
    .kot-instructions-label {
      font-size: 9.5px; font-weight: 900;
      text-transform: uppercase; letter-spacing: 1px;
      color: #000; margin-bottom: 6px;
    }
    .kot-instructions-text {
      font-size: 12px; color: #000; font-weight: 900; line-height: 1.6;
    }

    .kot-footer {
      background: #fff; padding: 16px 20px 22px;
      border-top: 1.5px dashed #000;
    }
    .kot-sign-row {
      display: flex; align-items: flex-end; justify-content: space-between;
    }
    .kot-sign-box { text-align: center; }
    .kot-sign-line { width: 120px; height: 1px; background: #000; margin-bottom: 5px; }
    .kot-sign-label {
      font-size: 9.5px; color: #000; font-weight: 900;
      text-transform: uppercase; letter-spacing: .5px;
    }

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
        display: block; width: 80mm !important; margin: 0 auto;
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
      .running-order-banner {
        background: #000 !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
  </style>
</head>
<body onload="window.print()">

  <div class="page-label no-print">👨‍🍳 Kitchen Order Ticket</div>

  <div class="receipt-card">
    @if(!empty($isRunningOrder))
      <div class="running-order-banner">RUNNING ORDER</div>
    @endif

    <div class="kot-header">
      <div class="kot-label">Kitchen Order Ticket</div>
      <div class="kot-header-row">
        <div>
          <div class="kot-number">{{ $kot->kot_number }}</div>
          <div class="kot-number-label">Order #{{ $kot->order->order_number }}</div>
        </div>
        <div>
          <div class="kot-time">{{ $kot->created_at->format('h:i A') }}</div>
          <div class="kot-date">{{ $kot->created_at->format('d M Y') }}</div>
        </div>
      </div>
    </div>

    <div class="kot-info-strip">
      <div class="kot-info-cell">
        <div class="kot-info-label">Table</div>
        <div class="kot-info-val">{{ $kot->order->table->table_number ?? 'N/A' }}</div>
      </div>
      <div class="kot-info-cell">
        <div class="kot-info-label">Type</div>
        <div class="kot-info-val sm">{{ $kot->order->order_type }}</div>
      </div>
      <div class="kot-info-cell">
        <div class="kot-info-label">Waiter</div>
        <div class="kot-info-val sm">{{ $kot->order->waiter->name ?? 'N/A' }}</div>
      </div>
    </div>

    <div class="kot-body">
      <div class="kot-section-head">Order Items</div>
      <div class="kot-items">
        @foreach($kot->orderDetails as $item)
          @if(!$item->is_unavailable)
            @php $addons = json_decode($item->addons, true) ?? []; @endphp
            <div class="kot-item">
              <div class="kot-qty-wrap">
                  <div class="kot-qty">{{ $item->quantity }}</div>
              </div>
              <div class="kot-item-info">
                  <div class="kot-item-name">{{ $item->product_name }}</div>
                  @if($item->food_note)
                      <div class="kot-item-note">{{ $item->food_note }}</div>
                  @endif
                  @if(count($addons) > 0)
                      <div class="kot-item-note" style="color: #666; font-style: normal;">
                          @foreach($addons as $addon) +{{ $addon['name'] }} @endforeach
                      </div>
                  @endif
              </div>
            </div>
          @endif
        @endforeach
      </div>

      @if($kot->order->notes)
      <div class="kot-instructions">
        <div class="kot-instructions-label">⚑ Order Notes</div>
        <div class="kot-instructions-text">{{ $kot->order->notes }}</div>
      </div>
      @endif
    </div>

    <div class="kot-footer">
      <div class="kot-sign-row">
        <div class="kot-sign-box"><div class="kot-sign-line"></div><div class="kot-sign-label">Prepared By</div></div>
        <div class="kot-sign-box"><div class="kot-sign-line"></div><div class="kot-sign-label">Checked By</div></div>
      </div>
    </div>
  </div>

  <div class="btn-print-wrap no-print" style="gap: 10px; flex-wrap: wrap;">
    <button class="btn-print" onclick="window.print()">Print KOT</button>

    @if(request()->query('source') === 'pos')
      <a href="{{ route('pos.index') }}" class="btn-print">
        Return to POS
      </a>
    @endif
  </div>
</body>
</html>
