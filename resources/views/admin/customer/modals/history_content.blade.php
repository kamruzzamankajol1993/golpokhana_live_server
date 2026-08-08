<div class="mb-3 text-center">
    <h6 class="fw-bold text-primary">{{ $customer->name }} ({{ $customer->phone }})</h6>
</div>
<div class="row g-3 mb-3">
    <div class="col-4"><div class="progga-stat-card" style="padding:12px;"><div class="progga-stat-icon secondary" style="width:32px;height:32px;font-size:14px;"><i class="bi bi-receipt"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Orders</div><div class="progga-stat-value" style="font-size:18px;">{{ $totalOrders }}</div></div></div></div>
    <div class="col-4"><div class="progga-stat-card" style="padding:12px;"><div class="progga-stat-icon success" style="width:32px;height:32px;font-size:14px;"><i class="bi bi-currency-dollar"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Total Spent</div><div class="progga-stat-value" style="font-size:18px;">৳{{ number_format($totalSpent, 2) }}</div></div></div></div>
    <div class="col-4"><div class="progga-stat-card" style="padding:12px;"><div class="progga-stat-icon warning" style="width:32px;height:32px;font-size:14px;"><i class="bi bi-star-fill"></i></div><div class="progga-stat-info"><div class="progga-stat-label">Loyalty Points</div><div class="progga-stat-value" style="font-size:18px;">{{ $customer->points }}</div></div></div></div>
</div>

<div class="progga-table-wrapper" style="max-height: 350px; overflow-y: auto;">
    <table class="progga-table table-sm">
        <thead style="position: sticky; top: 0; background: var(--progga-bg); z-index: 1;">
            <tr><th>#Order</th><th>Date</th><th>Type</th><th>Amount</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse($customer->orders as $order)
            <tr>
                <td><strong>#{{ $order->order_number }}</strong></td>
                <td>{{ $order->created_at->format('M d, Y h:i A') }}</td>
                <td>{{ $order->order_type }}</td>
                <td>৳{{ number_format($order->grand_total, 2) }}</td>
                <td>
                    @if($order->status == 'Completed')
                        <span class="progga-badge progga-badge-success">Completed</span>
                    @elseif($order->status == 'Pending')
                        <span class="progga-badge progga-badge-warning">Pending</span>
                    @elseif($order->status == 'Cancelled')
                        <span class="progga-badge progga-badge-danger">Cancelled</span>
                    @else
                        <span class="progga-badge progga-badge-neutral">{{ $order->status }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">No orders found for this customer.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
