<div class="col-md-3">
    <div class="progga-stat-card">
        <div class="progga-stat-icon secondary"><i class="bi bi-wallet2"></i></div>
        <div class="progga-stat-info">
            <div class="progga-stat-label">Total Collected</div>
            <div class="progga-stat-value">৳{{ number_format($totalCollected, 2) }}</div>
        </div>
    </div>
</div>
@foreach($paymentRows as $row)
<div class="col-md-3">
    <div class="payment-card" style="border:1px solid var(--progga-border-light); border-radius:14px; padding:18px; background:#fff;">
        <div class="payment-card-label" style="font-size:12px; font-weight:800; color:#888;">{{ $row['label'] }}</div>
        <div class="payment-card-amount" style="font-size:22px; font-weight:900; color:var(--progga-primary);">৳{{ number_format($row['amount'], 2) }}</div>
        <div class="payment-card-meta" style="display:flex; justify-content:space-between; font-size:11px; margin-top:5px; color:#666;">
            <span>{{ $row['orders_count'] }} order(s)</span>
            <span>{{ number_format($row['percentage'], 1) }}%</span>
        </div>
    </div>
</div>
@endforeach
