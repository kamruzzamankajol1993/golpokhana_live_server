<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KOT — Order #{{ $order->order_number }}</title>
  <style>
    @page { margin: 0; }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:Arial,Helvetica,sans-serif; font-weight:900; background:#e8e8e8; width:80mm; min-height:100%; display:flex; flex-direction:column; align-items:center; padding:10px; }
    .page-label { font-size:13px; font-weight:900; color:#000; background:#fff; padding:6px 20px; border-radius:20px; box-shadow:0 2px 8px rgba(0,0,0,.12); margin-bottom:28px; letter-spacing:.4px; }
    .receipt-card { width:100%; background:#fff; border-radius:6px 6px 0 0; box-shadow:0 8px 32px rgba(0,0,0,.18); position:relative; overflow:visible; }
    .receipt-card::after { content:''; position:absolute; bottom:-14px; left:0; right:0; height:14px; background:radial-gradient(circle at 7px -1px,#e8e8e8 7px,transparent 0) 0 0/14px 14px repeat-x; }
    .kot-header { background:#fff; padding:18px 20px 14px; border-radius:6px 6px 0 0; border-bottom:2px solid #000; }
    .kot-label { font-size:10.5px; font-weight:900; letter-spacing:2.5px; text-transform:uppercase; margin-bottom:6px; }
    .kot-header-row { display:flex; align-items:flex-end; justify-content:space-between; gap:10px; }
    .kot-number { font-size:12px; font-weight:900; line-height:1.25; max-width:46mm; word-break:break-word; }
    .kot-number-label { font-size:18px; font-weight:900; margin-top:4px; }
    .kot-time { font-size:18px; font-weight:900; text-align:right; white-space:nowrap; }
    .kot-date { font-size:10px; font-weight:900; margin-top:3px; text-align:right; white-space:nowrap; }
    .kot-info-strip { background:#fff; display:flex; border-bottom:2px dashed #000; }
    .kot-info-cell { flex:1; padding:10px 10px; border-right:1.5px dashed #000; min-width:0; }
    .kot-info-cell:last-child { border-right:none; }
    .kot-info-label { font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.8px; margin-bottom:3px; }
    .kot-info-val { font-size:16px; font-weight:900; line-height:1.1; word-break:break-word; }
    .kot-info-val.sm { font-size:12px; padding-top:3px; }
    .kot-body { padding:16px 20px; background:#fff; }
    .kot-section-head { font-size:9.5px; font-weight:900; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .kot-section-head::after { content:''; flex:1; height:1.5px; background:#000; opacity:.4; }
    .kot-items { display:flex; flex-direction:column; gap:10px; }
    .kot-item { display:flex; align-items:center; gap:14px; background:#fff; border-radius:8px; padding:7px 10px; border:1px solid #000; }
    .kot-qty { font-size:26px; font-weight:900; line-height:1; min-width:24px; text-align:center; }
    .kot-item-info { flex:1; min-width:0; }
    .kot-item-name { font-size:14.5px; font-weight:900; line-height:1.2; }
    .kot-item-note { font-size:11px; font-weight:900; margin-top:5px; font-style:italic; }
    .kot-item-note::before { content:'• '; }
    .kot-instructions { background:#fff; border:1.5px dashed #000; border-radius:8px; padding:11px 14px; margin-top:14px; }
    .kot-instructions-label { font-size:9.5px; font-weight:900; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
    .kot-instructions-text { font-size:12px; font-weight:900; line-height:1.6; }
    .kot-footer { background:#fff; padding:16px 20px 22px; border-top:1.5px dashed #000; }
    .kot-sign-row { display:flex; align-items:flex-end; justify-content:space-between; }
    .kot-sign-box { text-align:center; }
    .kot-sign-line { width:120px; height:1px; background:#000; margin-bottom:5px; }
    .kot-sign-label { font-size:9.5px; font-weight:900; text-transform:uppercase; letter-spacing:.5px; }
    .btn-print-wrap { margin-top:28px; display:flex; justify-content:center; gap:10px; }
    .btn-print { display:inline-flex; align-items:center; gap:7px; padding:11px 15px; border:none; cursor:pointer; border-radius:10px; font-size:13px; font-weight:900; background:#000; color:#fff; text-decoration:none; }
    @media print {
      body { background:#fff !important; padding:0 !important; display:block; width:80mm !important; margin:0 auto; }
      .no-print { display:none !important; }
      .receipt-card { box-shadow:none !important; width:100% !important; margin:0 auto; }
      .receipt-card::after, .page-label { display:none; }
      * { color:#000 !important; font-weight:900 !important; font-family:Arial,Helvetica,sans-serif !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }

      /* Compact thermal print: keep all data, reduce only vertical whitespace. */
      .running-order-banner { padding: 5px 6px !important; line-height: 1.05 !important; }
      .kot-header { padding: 7px 10px 5px !important; }
      .kot-label { margin-bottom: 2px !important; line-height: 1 !important; }
      .kot-number, .kot-number-label, .kot-time, .kot-date { line-height: 1.05 !important; }
      .kot-number-label, .kot-date { margin-top: 1px !important; }
      .kot-info-cell { padding: 4px 6px !important; }
      .kot-info-label { margin-bottom: 1px !important; line-height: 1 !important; }
      .kot-info-val, .kot-info-val.sm { line-height: 1.05 !important; padding-top: 0 !important; }
      .kot-body { padding: 6px 10px !important; }
      .kot-section-head { margin-bottom: 4px !important; line-height: 1 !important; }
      .kot-items { gap: 3px !important; }
      .kot-item { gap: 7px !important; padding: 3px 6px !important; }
      .kot-qty, .kot-item-name { line-height: 1.08 !important; }
      .kot-item-note { margin-top: 1px !important; line-height: 1.12 !important; }
      .kot-instructions { padding: 5px 8px !important; margin-top: 5px !important; }
      .kot-instructions-label { margin-bottom: 2px !important; line-height: 1 !important; }
      .kot-instructions-text { line-height: 1.18 !important; }
      .kot-footer { padding: 7px 10px 8px !important; }
      .kot-sign-line { margin-bottom: 2px !important; }
      .kot-sign-label { line-height: 1 !important; }
      .receipt-card > div[style*="padding:8px 10px 10px"] { padding: 3px 8px 4px !important; line-height: 1.05 !important; }
    }
  </style>
</head>
<body>
  <div class="page-label no-print">Kitchen Order Ticket</div>

  <div class="receipt-card">
    <div class="kot-header">
      <div class="kot-label">Merged Kitchen Order Ticket</div>
      <div class="kot-header-row">
        <div>
          <div class="kot-number">{{ $kotNumbers->isNotEmpty() ? $kotNumbers->implode(' + ') : 'KOT' }}</div>
          <div class="kot-number-label">Order #{{ $order->order_number }}</div>
        </div>
        <div>
          <div class="kot-time">{{ $lastKotAt ? \Carbon\Carbon::parse($lastKotAt)->format('h:i A') : '' }}</div>
          <div class="kot-date">{{ $lastKotAt ? \Carbon\Carbon::parse($lastKotAt)->format('d M Y') : '' }}</div>
        </div>
      </div>
    </div>

    <div class="kot-info-strip">
      <div class="kot-info-cell">
        <div class="kot-info-label">Table</div>
        <div class="kot-info-val">{{ $order->table->table_number ?? ($order->order_type ?: 'N/A') }}</div>
      </div>
      <div class="kot-info-cell">
        <div class="kot-info-label">Type</div>
        <div class="kot-info-val sm">{{ $order->order_type }}</div>
      </div>
      <div class="kot-info-cell">
        <div class="kot-info-label">Waiter</div>
        <div class="kot-info-val sm">{{ $order->waiter->name ?? 'N/A' }}</div>
      </div>
    </div>

    <div class="kot-body">
      <div class="kot-section-head">Order Items</div>
      <div class="kot-items">
        @forelse($mergedKotItems as $item)
          @php
            $addons = json_decode($item->addons ?? '[]', true) ?: [];
            $kotFoodNote = trim((string) ($item->food_note ?? ''));
            $kotComplimentaryNote = trim((string) ($item->complimentary_note ?? ''));
            $showKotFoodNote = $kotFoodNote !== ''
                && !($kotComplimentaryNote !== '' && $kotFoodNote === $kotComplimentaryNote);
          @endphp
          <div class="kot-item">
            <div class="kot-qty">{{ $item->quantity }}</div>
            <div class="kot-item-info">
              <div class="kot-item-name">{{ $item->product_name }}</div>
              @if(!empty($item->is_complimentary))
                <div class="kot-item-note">Complimentary</div>
              @endif
              @if($showKotFoodNote)
                <div class="kot-item-note">{{ $kotFoodNote }}</div>
              @endif
              @if(count($addons) > 0)
                <div class="kot-item-note" style="font-style:normal;">
                  @foreach($addons as $addon)+{{ $addon['name'] ?? '' }}@if(!$loop->last), @endif @endforeach
                </div>
              @endif
            </div>
          </div>
        @empty
          <div style="font-size:12px;text-align:center;padding:12px 0;">No KOT items found.</div>
        @endforelse
      </div>

      @if($order->notes)
        <div class="kot-instructions">
          <div class="kot-instructions-label">Order Notes</div>
          <div class="kot-instructions-text">{{ $order->notes }}</div>
        </div>
      @endif
    </div>

    <div class="kot-footer">
      <div class="kot-sign-row">
        <div class="kot-sign-box"><div class="kot-sign-line"></div><div class="kot-sign-label">Prepared By</div></div>
        <div class="kot-sign-box"><div class="kot-sign-line"></div><div class="kot-sign-label">Checked By</div></div>
      </div>
    </div>
    <div style="padding:8px 10px 10px;text-align:center;font-size:10px;font-weight:700 !important;">Powered by : <span style="font-size:12px;font-weight:900 !important;">{{ $poweredBySystemName ?? $restaurantSettingName ?? '' }}</span></div>
  </div>

  @unless(request()->boolean('embedded'))
    <div class="btn-print-wrap no-print">
      <a href="{{ route('pos.index') }}" class="btn-print">Return to POS</a>
      <button class="btn-print" onclick="window.print()">Print KOT</button>
    </div>
  @endunless
</body>
</html>
