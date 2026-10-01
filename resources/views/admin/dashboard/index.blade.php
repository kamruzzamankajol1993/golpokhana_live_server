@extends('admin.master.master')

@section('title')
Dashboard — {{ $restaurantSettingName }}
@endsection

@section('body')

<main class="progga-content">

    <div class="progga-page-header">
      <div>
        <h1 class="progga-page-title">Dashboard</h1>
        <div class="progga-breadcrumb"><span class="progga-breadcrumb-item active">Home</span></div>
      </div>
      <div style="display:flex;gap:8px;">
        <span class="progga-live-indicator"><span class="progga-live-dot"></span> Live</span>
        <button class="progga-btn progga-btn-outline progga-btn-sm" type="button" onclick="window.location.reload()">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
      </div>
    </div>

    <div class="row g-3 mb-4 dashboard-stat-row">
      <div class="col-xl col-md-6">
        <div class="progga-stat-card">
          <div class="progga-stat-icon secondary"><i class="bi bi-currency-dollar"></i></div>
          <div class="progga-stat-info">
            <div class="progga-stat-label">Businessday Revenue</div>
            <div class="progga-stat-value">৳{{ number_format($todaySales) }}</div>
            <div class="progga-stat-change {{ $salesChange >= 0 ? 'up' : 'down' }}">
                <i class="bi {{ $salesChange >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                {{ $salesChange > 0 ? '+' : '' }}{{ number_format($salesChange, 1) }}% vs yesterday
            </div>
          </div>
        </div>
      </div>

      <?php if ($isSuperAdmin): ?>
      <div class="col-xl col-md-6">
        <div class="progga-stat-card">
          <div class="progga-stat-icon success"><i class="bi bi-graph-up-arrow"></i></div>
          <div class="progga-stat-info">
            <div class="progga-stat-label">Monthly Revenue</div>
            <div class="progga-stat-value">৳{{ number_format($monthlySales) }}</div>
            <div class="progga-stat-change {{ $monthlyChange >= 0 ? 'up' : 'down' }}">
                <i class="bi {{ $monthlyChange >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                {{ $monthlyChange > 0 ? '+' : '' }}{{ number_format($monthlyChange, 1) }}% this month
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="col-xl col-md-6">
        <div class="progga-stat-card">
          <div class="progga-stat-icon success"><i class="bi bi-calendar3"></i></div>
          <div class="progga-stat-info">
            <div class="progga-stat-label">Previous Month Revenue</div>
            <div class="progga-stat-value">৳{{ number_format($lastMonthSales ?? 0) }}</div>
            <?php $lastMonthDelta = (float)($lastMonthChange ?? 0); ?>
            <div class="progga-stat-change {{ $lastMonthDelta > 0 ? 'up' : ($lastMonthDelta < 0 ? 'down' : 'neutral') }}">
              <i class="bi {{ $lastMonthDelta > 0 ? 'bi-arrow-up-right' : ($lastMonthDelta < 0 ? 'bi-arrow-down-right' : 'bi-dash') }}"></i>
              {{ $lastMonthDelta > 0 ? '+' : '' }}{{ number_format($lastMonthDelta, 1) }}%
              {{ $lastMonthDelta > 0 ? 'increase' : ($lastMonthDelta < 0 ? 'decrease' : 'no change') }}
              vs {{ $lastMonthComparisonLabel ?? 'previous month' }}
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl col-md-6">
        <div class="progga-stat-card">
          <div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div>
          <div class="progga-stat-info">
            <div class="progga-stat-label">TOTAL ORDER (BUSINESS DAY)</div>
            <div class="progga-stat-value">{{ $todayOrdersCount }}</div>
            <div class="progga-stat-change {{ $ordersChange >= 0 ? 'up' : 'down' }}">
                <i class="bi {{ $ordersChange >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                {{ $ordersChange > 0 ? '+' : '' }}{{ $ordersChange }} vs yesterday
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl col-md-6">
        <div class="progga-stat-card">
          <div class="progga-stat-icon warning"><i class="bi bi-layout-wtf"></i></div>
          <div class="progga-stat-info">
            <div class="progga-stat-label">Running Tables</div>
            <div class="progga-stat-value">{{ $runningTables }} / {{ $totalTables }}</div>
            <div class="progga-stat-change neutral"><i class="bi bi-dash"></i> {{ $availableTables }} available</div>
          </div>
        </div>
      </div>
      <?php if ($isSuperAdmin): ?>
      <div class="col-xl col-md-6">
        <div class="progga-stat-card">
          <div class="progga-stat-icon warning"><i class="bi bi-hourglass-split"></i></div>
          <div class="progga-stat-info">
            <div class="progga-stat-label">Pending Amount (Business Day)</div>
            <div class="progga-stat-value">৳{{ number_format($todayPendingAmount, 0) }}</div>
            <div class="progga-stat-change neutral">
                <i class="bi bi-clock"></i> Pending orders total amount
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-xl-8">
        <?php if ($isSuperAdmin): ?>
        <div class="progga-card">
          <div class="progga-card-header">
            <div>
              <div class="progga-card-title">Revenue Aging Overview</div>
              <div class="progga-card-subtitle" id="revenueChartSubtitle">Dynamic revenue trend from completed orders</div>
            </div>
            <div class="progga-chart-toggle" style="flex-wrap:wrap;justify-content:flex-end;">
              <button class="progga-chart-toggle-btn" data-revenue-period="1">Today</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="yesterday">Yesterday</button>
              <button class="progga-chart-toggle-btn active" data-revenue-period="7">7 Days</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="14">14 Days</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="21">21 Days</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="30">30 Days</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="60">60 Days</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="90">90 Days</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="180">180 Days</button>
              <button class="progga-chart-toggle-btn" data-revenue-period="12m">12 Months</button>
            </div>
          </div>
          <div class="progga-card-body">
            <div class="progga-chart-container" style="height:260px;">
              <canvas id="revenueChart" data-dashboard-dynamic="true"></canvas>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="progga-card" style="height:100%;">
          <div class="progga-card-header">
            <div>
              <div class="progga-card-title">Today's Payment Collection</div>
              <div class="progga-card-subtitle">Amount received in the current system day by payment type</div>
            </div>
            <span class="progga-badge progga-badge-secondary">Total: ৳{{ number_format($totalCollected, 2) }}</span>
          </div>
          <div class="progga-card-body">
            <div class="progga-chart-container" style="height:280px;">
              <canvas id="paymentCollectionChart" data-dashboard-dynamic="true"></canvas>
            </div>
            <div class="text-center text-muted" style="font-size:12px;margin-top:10px;">
              Split payment amounts are included in their respective Cash, Bank / Card and MFS bars.
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="col-xl-4">
        <div class="progga-card" style="height:100%;">
          <div class="progga-card-header">
            <div class="progga-card-title">{{ $isSuperAdmin ? 'Order Status (This Month)' : 'Order Status (Today)' }}</div>
          </div>
          <div class="progga-card-body" style="display:flex;flex-direction:column;align-items:center;">
            <div class="progga-chart-container" style="height:220px;width:100%;">
              <canvas id="orderStatusChart" data-dashboard-dynamic="true"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($isSuperAdmin): ?>
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="progga-card">
            <div class="progga-card-header">
              <div>
                <div class="progga-card-title">Payment Collection Aging Overview</div>
                <div class="progga-card-subtitle" id="incomeChartSubtitle">Daily income by payment method for the last 7 days</div>
              </div>
              <div class="progga-chart-toggle" style="flex-wrap:wrap;justify-content:flex-end;">
                <button class="progga-chart-toggle-btn" data-income-period="1">Today</button>
                <button class="progga-chart-toggle-btn" data-income-period="yesterday">Yesterday</button>
                <button class="progga-chart-toggle-btn active" data-income-period="7">7 Days</button>
                <button class="progga-chart-toggle-btn" data-income-period="14">14 Days</button>
                <button class="progga-chart-toggle-btn" data-income-period="21">21 Days</button>
                <button class="progga-chart-toggle-btn" data-income-period="30">30 Days</button>
                <button class="progga-chart-toggle-btn" data-income-period="60">60 Days</button>
                <button class="progga-chart-toggle-btn" data-income-period="90">90 Days</button>
                <button class="progga-chart-toggle-btn" data-income-period="180">180 Days</button>
                <button class="progga-chart-toggle-btn" data-income-period="12m">12 Months</button>
              </div>
            </div>
            <div class="progga-card-body">
              <div class="progga-chart-container" style="height:320px;">
                <canvas id="incomeChart" data-dashboard-dynamic="true"></canvas>
              </div>
              <div class="text-center text-muted" style="font-size:12px;margin-top:10px;">
                Split payment amounts are included in their respective Cash, Bank / Card and Mobile (MFS) bars.
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>


    <?php if ($isSuperAdmin): ?>
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="progga-card">
            <div class="progga-card-header">
              <div>
                <div class="progga-card-title">Sales Comparison Overview</div>
                <div class="progga-card-subtitle" id="salesCalendarSubtitle">{{ $salesCalendarPeriodLabel ?? 'Business day' }}</div>
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <select id="salesCalendarFilter" class="progga-select" onchange="if(window.salesCalendarFilterChanged){window.salesCalendarFilterChanged('inline-onchange');}else{console.error('[SalesCalendar] Inline onchange fired but handler is unavailable.', this.value);}">
                  <option value="this_month" {{ ($salesCalendarFilter ?? 'this_month') === 'this_month' ? 'selected' : '' }}>This Month</option>
                  <option value="previous_month" {{ ($salesCalendarFilter ?? '') === 'previous_month' ? 'selected' : '' }}>Previous Month</option>
                </select>
              </div>
            </div>
            <div class="progga-card-body" id="salesCalendarBody" style="position:relative;">
              <div id="salesCalendarLoader" aria-hidden="true" style="display:none;position:absolute;inset:0;z-index:20;align-items:center;justify-content:center;background:rgba(255,255,255,.78);backdrop-filter:blur(1px);border-radius:inherit;">
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:18px 22px;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,.12);">
                  <div class="spinner-border" role="status" aria-label="Loading sales calendar"></div>
                  <div id="salesCalendarLoaderText" style="font-size:13px;font-weight:700;">Loading sales data...</div>
                  <div style="font-size:11px;color:#6c757d;">Please wait while the selected month is calculated.</div>
                </div>
              </div>
              <div id="salesCalendarTotal" class="text-end" style="font-size:12px;font-weight:800;margin-bottom:8px;">
                ৳{{ number_format($salesCalendarTotal ?? 0, 2) }} Total Sales
              </div>
              <div id="salesCalendarChartContainer" class="progga-chart-container" style="height:320px;transition:opacity .2s ease;">
                <canvas id="salesCalendarChart" data-dashboard-dynamic="true"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($isSuperAdmin): ?>
      <div class="row g-3 mb-4">
        <div class="col-xl-4">
          <div class="progga-card" style="height:100%;">
            <div class="progga-card-header">
              <div>
                <div class="progga-card-title">Top Selling Items</div>
                <div class="progga-card-subtitle">Top 5 items by quantity sold this year</div>
              </div>
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                <span class="progga-badge progga-badge-secondary">YTD</span>
                <a href="{{ route('dashboard.top_selling_items') }}" class="progga-btn progga-btn-outline progga-btn-sm">View More</a>
              </div>
            </div>
            <div class="progga-card-body">
              <?php
                $topItemsSafe = $topSellingItems ?? collect();
                $progressBaseQty = max(1, (int) ($topItemsSafe->max('total_qty') ?? 0));
                $topItemCount = $topItemsSafe->count();
                $topItemPosition = 0;
              ?>
              <?php if ($topItemsSafe->isNotEmpty()): ?>
                <?php foreach ($topItemsSafe as $index => $item): ?>
                  <?php
                    $topItemPosition++;
                    $itemQty = max(0, (int) ($item->total_qty ?? 0));
                    $progress = min(100, ($itemQty / $progressBaseQty) * 100);
                    $topItemBorder = $topItemPosition >= $topItemCount ? '' : 'border-bottom:1px solid rgba(0,0,0,.06);';
                  ?>
                  <div style="padding:10px 0;{{ $topItemBorder }}">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:7px;">
                      <div style="min-width:0;display:flex;align-items:center;gap:9px;">
                        <span class="progga-badge progga-badge-neutral" style="min-width:26px;text-align:center;">{{ $index + 1 }}</span>
                        <strong style="font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item->product_name }}</strong>
                      </div>
                      <span style="font-size:12px;font-weight:800;white-space:nowrap;">{{ number_format($item->total_qty) }} sold</span>
                    </div>
                    <div style="height:6px;border-radius:999px;background:rgba(33,53,42,.10);overflow:hidden;">
                      <div style="height:100%;width:{{ $progress }}%;background:#21352a;border-radius:999px;"></div>
                    </div>
                    <div class="text-muted" style="font-size:11px;margin-top:5px;">৳{{ number_format($item->total_amount ?? 0, 0) }} sales value</div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center text-muted" style="padding:32px 12px;">No completed item sales found.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-xl-8">
          <div class="progga-card" style="height:100%;">
            <div class="progga-card-header">
              <div>
                <div class="progga-card-title">Kitchen Queue</div>
                <div class="progga-card-subtitle">Active orders in the current business day</div>
              </div>
              <span class="progga-badge progga-badge-warning">{{ $kitchenQueue->count() }} active</span>
            </div>
            <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
              <table class="progga-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Table</th>
                    <th>Items</th>
                    <th>Wait</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (($kitchenQueue ?? collect())->isNotEmpty()): ?>
                    <?php foreach ($kitchenQueue as $order): ?>
                      <?php
                        $queueStatus = strtolower((string) $order->status);
                        $queueBadge = in_array($queueStatus, ['ready'], true) ? 'primary' : ($queueStatus === 'cooking' ? 'secondary' : 'warning');
                        $queueType = strtolower((string) $order->order_type);
                        $queueLocation = $queueType === 'dine-in'
                            ? 'T-' . ($order->table->table_number ?? 'N/A')
                            : ($queueType === 'delivery'
                                ? ($order->delivery_partner ?: 'Delivery')
                                : ($order->order_type ?? 'N/A'));
                        $waitMinutes = $order->created_at ? (int) max(0, floor($order->created_at->diffInMinutes(now()))) : 0;
                      ?>
                      <tr>
                        <td><a href="{{ route('order.show', $order->id) }}" style="font-weight:800;text-decoration:none;">#{{ $order->order_number }}</a></td>
                        <td>{{ $queueLocation }}</td>
                        <td>{{ number_format($order->orderDetails->sum('quantity')) }}</td>
                        <td>{{ $waitMinutes }} min</td>
                        <td><span class="progga-badge progga-badge-{{ $queueBadge }}">{{ $order->status }}</span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px 12px;">Kitchen queue is clear.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="progga-card">
            <div class="progga-card-header">
              <div>
                <div class="progga-card-title">Recent Orders</div>
                <div class="progga-card-subtitle">Latest orders across the system</div>
              </div>
              <a href="{{ route('order.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">View All</a>
            </div>
            <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
              <table class="progga-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (($recentOrders ?? collect())->isNotEmpty()): ?>
                    <?php foreach ($recentOrders as $order): ?>
                      <?php
                        $recentStatus = strtolower((string) $order->status);
                        $recentBadge = $recentStatus === 'completed' ? 'primary' : ($recentStatus === 'cancelled' ? 'danger' : 'warning');
                        $recentType = strtolower((string) $order->order_type);
                        $recentLocation = $recentType === 'dine-in'
                            ? 'Dine-In · T-' . ($order->table->table_number ?? 'N/A')
                            : ($recentType === 'delivery'
                                ? 'Delivery' . ($order->delivery_partner ? ' · ' . $order->delivery_partner : '')
                                : ($order->order_type ?? 'N/A'));
                      ?>
                      <tr>
                        <td>
                          <a href="{{ route('order.show', $order->id) }}" style="font-weight:800;text-decoration:none;">#{{ $order->order_number }}</a>
                          <div class="text-muted" style="font-size:10px;">{{ optional($order->created_at)->format('d M, h:i A') }}</div>
                        </td>
                        <td>{{ optional($order->customer)->name ?? 'Walk-in' }}</td>
                        <td>{{ $recentLocation }}</td>
                        <td><strong>৳{{ number_format($order->grand_total ?? 0, 0) }}</strong></td>
                        <td><span class="progga-badge progga-badge-{{ $recentBadge }}">{{ $order->status ?? 'N/A' }}</span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px 12px;">No recent orders found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </main>
@endsection

@section('script')
<script>
document.addEventListener("DOMContentLoaded", function() {
    const chartDataUrl = "{{ route('dashboard.chart_data') }}";
    let revenueChart;
    let orderStatusChart;
    let paymentCollectionChart;
    let incomeChart;
    let salesCalendarChart;

    // Sales Calendar: This Month is rendered with the initial dashboard page.
    // Filter changes stay AJAX-based. Bind this listener BEFORE chart initialization
    // so an unrelated chart error cannot prevent Previous Month from firing.
    let salesCalendarRequestSequence = 0;
    let salesCalendarLastTriggerAt = 0;
    let salesCalendarLastTriggerValue = null;

    function triggerSalesCalendarFilter(source) {
        const filterElement = document.getElementById('salesCalendarFilter');
        const filter = filterElement ? filterElement.value : 'this_month';
        const now = Date.now();

        // Native change + jQuery/Select2 can both fire for the same user action.
        if (salesCalendarLastTriggerValue === filter && (now - salesCalendarLastTriggerAt) < 150) {
            console.debug('[SalesCalendar] Duplicate dropdown event ignored.', { source, filter });
            return;
        }

        salesCalendarLastTriggerAt = now;
        salesCalendarLastTriggerValue = filter;
        console.info('[SalesCalendar] Dropdown event fired.', { source, filter, at: new Date().toISOString() });
        refreshSalesCalendarChart(source);
    }

    window.salesCalendarFilterChanged = function(source) {
        triggerSalesCalendarFilter(source || 'external-change');
    };

    const salesFilterEarly = document.getElementById('salesCalendarFilter');
    console.info('[SalesCalendar] Dashboard script booted.', {
        dropdown_found: !!salesFilterEarly,
        current_filter: salesFilterEarly ? salesFilterEarly.value : null,
        jquery_available: typeof window.jQuery !== 'undefined',
        select2_available: !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)
    });

    if (salesFilterEarly) {
        salesFilterEarly.addEventListener('change', function() {
            triggerSalesCalendarFilter('native-change');
        });
        console.info('[SalesCalendar] Native dropdown listener attached.');
    } else {
        console.error('[SalesCalendar] #salesCalendarFilter was not found; AJAX filter cannot be attached.');
    }

    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(document)
            .off('change.salesCalendarFilter select2:select.salesCalendarFilter', '#salesCalendarFilter')
            .on('change.salesCalendarFilter select2:select.salesCalendarFilter', '#salesCalendarFilter', function(event) {
                triggerSalesCalendarFilter(event.type === 'select2:select' ? 'select2:select' : 'jquery-change');
            });
        console.info('[SalesCalendar] jQuery/Select2 delegated dropdown listener attached.');
    }

    function revenueSubtitle(period) {
        if (period === '1') return 'Revenue for the current business day';
        if (period === 'yesterday') return "Revenue for yesterday's business day";
        if (period === '12m') return 'Monthly revenue trend from the last 12 months';
        if (['7', '14', '21', '30', '60', '90', '180'].includes(period)) {
            return 'Daily revenue trend from the last ' + period + ' days';
        }
        return 'Daily revenue trend from the last 7 days';
    }

    function incomeSubtitle(period) {
        if (period === '1') return 'Income by payment method for the current business day';
        if (period === 'yesterday') return "Income by payment method for yesterday's business day";
        if (period === '12m') return 'Monthly income by payment method for the last 12 months';
        if (['7', '14', '21', '30', '60', '90', '180'].includes(period)) {
            return 'Daily income by payment method for the last ' + period + ' days';
        }
        return 'Daily income by payment method for the last 7 days';
    }

    function buildPaymentCollectionChart(rows) {
        const paymentCanvas = document.getElementById('paymentCollectionChart');
        if (!paymentCanvas || typeof Chart === 'undefined') return;

        const labels = rows.map(row => row.label);
        const amounts = rows.map(row => Number(row.amount || 0));
        const orderCounts = rows.map(row => Number(row.orders_count || 0));

        const oldPaymentChart = Chart.getChart(paymentCanvas);
        if (oldPaymentChart) oldPaymentChart.destroy();

        const ctxPayment = paymentCanvas.getContext('2d');
        paymentCollectionChart = new Chart(ctxPayment, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Collected Amount (৳)',
                    data: amounts,
                    backgroundColor: ['#1e7a4a', '#21352a', '#d5aa65'],
                    borderColor: ['#1e7a4a', '#21352a', '#d5aa65'],
                    borderWidth: 1,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 90
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const amount = Number(context.raw || 0).toLocaleString('en-BD', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                const count = orderCounts[context.dataIndex] || 0;
                                return ['Collected: ৳' + amount, 'Orders: ' + count];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '৳' + Number(value).toLocaleString('en-BD');
                            }
                        },
                        grid: { borderDash: [4, 4] }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { autoSkip: false, maxRotation: 0, minRotation: 0 }
                    }
                }
            }
        });
    }

    function buildIncomeChart(labels, cashData, cardData, mfsData) {
        const incomeCanvas = document.getElementById('incomeChart');
        if (!incomeCanvas || typeof Chart === 'undefined') return;

        const oldIncomeChart = Chart.getChart(incomeCanvas);
        if (oldIncomeChart) oldIncomeChart.destroy();

        const ctxIncome = incomeCanvas.getContext('2d');
        incomeChart = new Chart(ctxIncome, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Cash',
                        data: cashData,
                        backgroundColor: '#1e7a4a',
                        borderColor: '#1e7a4a',
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false
                    },
                    {
                        label: 'Bank / Card',
                        data: cardData,
                        backgroundColor: '#21352a',
                        borderColor: '#21352a',
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false
                    },
                    {
                        label: 'Mobile (MFS)',
                        data: mfsData,
                        backgroundColor: '#d5aa65',
                        borderColor: '#d5aa65',
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, usePointStyle: true, padding: 18 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const amount = Number(context.raw || 0).toLocaleString('en-BD', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                return context.dataset.label + ': ৳' + amount;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '৳' + Number(value).toLocaleString('en-BD');
                            }
                        },
                        grid: { borderDash: [4, 4] }
                    },
                    x: {
                        stacked: false,
                        grid: { display: false },
                        ticks: {
                            autoSkip: false,
                            maxRotation: labels.length > 12 ? 45 : 0,
                            minRotation: labels.length > 12 ? 45 : 0
                        }
                    }
                }
            }
        });
    }

    function buildRevenueChart(labels, data) {
        const revenueCanvas = document.getElementById('revenueChart');
        if (!revenueCanvas || typeof Chart === 'undefined') return;

        const oldRevenueChart = Chart.getChart(revenueCanvas);
        if (oldRevenueChart) oldRevenueChart.destroy();

        const ctxRevenue = revenueCanvas.getContext('2d');
        revenueChart = new Chart(ctxRevenue, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (৳)',
                    data: data,
                    borderColor: '#21352a',
                    backgroundColor: 'rgba(33, 53, 42, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#d5aa65',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [4, 4] } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function buildOrderStatusChart(labels, data) {
        const statusCanvas = document.getElementById('orderStatusChart');
        if (!statusCanvas || typeof Chart === 'undefined') return;

        const oldStatusChart = Chart.getChart(statusCanvas);
        if (oldStatusChart) oldStatusChart.destroy();

        const ctxStatus = statusCanvas.getContext('2d');
        orderStatusChart = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#d5aa65', '#6c757d', '#17a2b8', '#1e7a4a', '#21352a', '#0d6efd', '#dc3545', '#6610f2', '#fd7e14'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, padding: 20 } }
                }
            }
        });
    }

    function refreshIncomeChart(period) {
        fetch(chartDataUrl + '?period=' + encodeURIComponent(period), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(payload => {
            if (!incomeChart) {
                buildIncomeChart(
                    payload.incomeChartLabels,
                    payload.incomeCashData,
                    payload.incomeCardData,
                    payload.incomeMfsData
                );
            } else {
                incomeChart.data.labels = payload.incomeChartLabels;
                incomeChart.data.datasets[0].data = payload.incomeCashData;
                incomeChart.data.datasets[1].data = payload.incomeCardData;
                incomeChart.data.datasets[2].data = payload.incomeMfsData;
                incomeChart.options.scales.x.ticks.maxRotation = payload.incomeChartLabels.length > 12 ? 45 : 0;
                incomeChart.options.scales.x.ticks.minRotation = payload.incomeChartLabels.length > 12 ? 45 : 0;
                incomeChart.update();
            }

            const subtitle = document.getElementById('incomeChartSubtitle');
            if (subtitle) subtitle.textContent = incomeSubtitle(payload.incomePeriod || period);
        })
        .catch(error => console.error('Dashboard income chart data loading failed.', error));
    }

    function setSalesCalendarLoading(isLoading, filter = null) {
        const loader = document.getElementById('salesCalendarLoader');
        const loaderText = document.getElementById('salesCalendarLoaderText');
        const chartContainer = document.getElementById('salesCalendarChartContainer');
        const totalEl = document.getElementById('salesCalendarTotal');

        if (loader) {
            loader.style.display = isLoading ? 'flex' : 'none';
            loader.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
        }

        if (loaderText && isLoading) {
            loaderText.textContent = filter === 'previous_month'
                ? 'Loading previous month sales...'
                : 'Loading this month sales...';
        }

        if (chartContainer) {
            chartContainer.style.opacity = isLoading ? '0.32' : '1';
        }
        if (totalEl) {
            totalEl.style.opacity = isLoading ? '0.45' : '1';
        }
    }

    function updateSalesCalendarTotal(total) {
        const el = document.getElementById('salesCalendarTotal');
        if (el) {
            el.textContent = '৳' + Number(total || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) + ' Total Sales';
        }
    }

    function buildSalesCalendarChart(labels, data, total = 0) {
        const canvas = document.getElementById('salesCalendarChart');
        if (!canvas || typeof Chart === 'undefined') return;
        const old = Chart.getChart(canvas);
        if (old) old.destroy();
        updateSalesCalendarTotal(total);
        const totalLabel = '৳' + Number(total || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) + ' Total Sales';
        salesCalendarChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: totalLabel, data: data, backgroundColor: '#1e7a4a', borderColor: '#1e7a4a', borderWidth: 1, borderRadius: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true }, x: { grid: { display:false } } } }
        });
    }

    function applySalesCalendarPayload(payload) {
        if (!payload || !Array.isArray(payload.salesCalendarLabels) || !Array.isArray(payload.salesCalendarData)) {
            console.error('Invalid Sales Calendar payload', payload);
            return;
        }

        const salesSubtitle = document.getElementById('salesCalendarSubtitle');
        if (salesSubtitle && payload.salesCalendarPeriodLabel) {
            salesSubtitle.textContent = payload.salesCalendarPeriodLabel;
        }

        if (!salesCalendarChart) {
            buildSalesCalendarChart(payload.salesCalendarLabels, payload.salesCalendarData, payload.salesCalendarTotal);
            return;
        }

        updateSalesCalendarTotal(payload.salesCalendarTotal);
        salesCalendarChart.data.datasets[0].label = '৳' + Number(payload.salesCalendarTotal || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) + ' Total Sales';
        salesCalendarChart.data.labels = payload.salesCalendarLabels;
        salesCalendarChart.data.datasets[0].data = payload.salesCalendarData;
        salesCalendarChart.update();
    }

    window.refreshSalesCalendarChartNow = function(){ refreshSalesCalendarChart('manual-console'); };

    function refreshSalesCalendarChart(eventSource = 'manual') {
        const filterElement = document.getElementById('salesCalendarFilter');
        const filter = filterElement ? filterElement.value : 'this_month';
        const requestSequence = ++salesCalendarRequestSequence;
        const requestUrl = chartDataUrl
            + '?sales_filter=' + encodeURIComponent(filter)
            + '&event_source=' + encodeURIComponent(eventSource)
            + '&_ts=' + encodeURIComponent(Date.now());
        const salesSubtitle = document.getElementById('salesCalendarSubtitle');

        console.groupCollapsed('[SalesCalendar] AJAX request #' + requestSequence + ' · ' + filter);
        console.log('Request URL:', requestUrl);
        console.log('Selected filter:', filter);
        console.log('Event source:', eventSource);
        console.log('Started at:', new Date().toISOString());
        console.groupEnd();

        if (salesSubtitle) {
            salesSubtitle.textContent = (filter === 'previous_month' ? 'Previous Month' : 'This Month') + ' · Loading...';
        }
        setSalesCalendarLoading(true, filter);

        fetch(requestUrl, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        })
        .then(async response => {
            const rawText = await response.text();

            console.groupCollapsed('[SalesCalendar] AJAX response #' + requestSequence + ' · HTTP ' + response.status);
            console.log('OK:', response.ok);
            console.log('Status:', response.status, response.statusText);
            console.log('Content-Type:', response.headers.get('content-type'));
            console.log('Raw response:', rawText);
            console.groupEnd();

            let payload;
            try {
                payload = rawText ? JSON.parse(rawText) : {};
            } catch (parseError) {
                console.error('[SalesCalendar] JSON parse failed.', {
                    requestSequence,
                    filter,
                    parseError,
                    rawText
                });
                throw new Error('Sales Calendar returned invalid JSON. Check console raw response and laravel.log.');
            }

            if (!response.ok) {
                const serverMessage = payload && (payload.message || payload.error)
                    ? (payload.message || payload.error)
                    : ('HTTP ' + response.status);
                console.error('[SalesCalendar] Server returned an error.', payload);
                throw new Error(serverMessage);
            }

            console.info('[SalesCalendar] Parsed payload.', {
                requestSequence,
                filter,
                request_id: payload.request_id || null,
                period: payload.salesCalendarPeriodLabel || null,
                labels_count: Array.isArray(payload.salesCalendarLabels) ? payload.salesCalendarLabels.length : null,
                data_count: Array.isArray(payload.salesCalendarData) ? payload.salesCalendarData.length : null,
                non_zero_days: Array.isArray(payload.salesCalendarData)
                    ? payload.salesCalendarData.filter(value => Number(value || 0) !== 0).length
                    : null,
                total: payload.salesCalendarTotal,
                debug: payload.debug || null
            });

            return payload;
        })
        .then(payload => {
            // Ignore a slower stale request if the user changed the filter again.
            if (requestSequence !== salesCalendarRequestSequence) {
                console.warn('[SalesCalendar] Ignored stale AJAX response.', {
                    requestSequence,
                    latestSequence: salesCalendarRequestSequence,
                    request_id: payload.request_id || null
                });
                return;
            }

            applySalesCalendarPayload(payload);
        })
        .catch(error => {
            console.error('[SalesCalendar] AJAX loading failed.', {
                requestSequence,
                filter,
                message: error && error.message ? error.message : String(error),
                error
            });

            if (salesSubtitle && requestSequence === salesCalendarRequestSequence) {
                salesSubtitle.textContent = 'Sales Calendar load failed · check Console and laravel.log';
            }
        })
        .finally(() => {
            // Only the latest request owns the loader. A stale response must not hide
            // the loader while a newer filter request is still running.
            if (requestSequence === salesCalendarRequestSequence) {
                setSalesCalendarLoading(false, filter);
            }
        });
    }

    function refreshIncomeChart(period) {
        fetch(chartDataUrl + '?period=' + encodeURIComponent(period), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(payload => {
            if (!incomeChart) {
                buildIncomeChart(
                    payload.incomeChartLabels,
                    payload.incomeCashData,
                    payload.incomeCardData,
                    payload.incomeMfsData
                );
            } else {
                incomeChart.data.labels = payload.incomeChartLabels;
                incomeChart.data.datasets[0].data = payload.incomeCashData;
                incomeChart.data.datasets[1].data = payload.incomeCardData;
                incomeChart.data.datasets[2].data = payload.incomeMfsData;
                incomeChart.options.scales.x.ticks.maxRotation = payload.incomeChartLabels.length > 12 ? 45 : 0;
                incomeChart.options.scales.x.ticks.minRotation = payload.incomeChartLabels.length > 12 ? 45 : 0;
                incomeChart.update();
            }

            const subtitle = document.getElementById('incomeChartSubtitle');
            if (subtitle) subtitle.textContent = incomeSubtitle(payload.incomePeriod || period);
        })
        .catch(error => console.error('Dashboard income chart data loading failed.', error));
    }

    function refreshDashboardCharts(period) {
        fetch(chartDataUrl + '?period=' + encodeURIComponent(period), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(payload => {
            if (!revenueChart) {
                buildRevenueChart(payload.chartLabels, payload.chartData);
            } else {
                revenueChart.data.labels = payload.chartLabels;
                revenueChart.data.datasets[0].data = payload.chartData;
                revenueChart.update();
            }

            if (!orderStatusChart) {
                buildOrderStatusChart(payload.statusLabels, payload.statusData);
            } else {
                orderStatusChart.data.labels = payload.statusLabels;
                orderStatusChart.data.datasets[0].data = payload.statusData;
                orderStatusChart.update();
            }

            const subtitle = document.getElementById('revenueChartSubtitle');
            if (subtitle) subtitle.textContent = revenueSubtitle(payload.period);
        })
        .catch(error => console.error('Dashboard chart data loading failed.', error));
    }

    buildRevenueChart(@json($chartLabels), @json($chartData));
    buildPaymentCollectionChart(@json($paymentRows));
    buildOrderStatusChart(@json($statusLabels), @json($statusData));
    <?php if ($isSuperAdmin): ?>
      buildIncomeChart(@json($incomeChartLabels), @json($incomeCashData), @json($incomeCardData), @json($incomeMfsData));
      buildSalesCalendarChart(@json($salesCalendarLabels), @json($salesCalendarData), @json($salesCalendarTotal ?? array_sum($salesCalendarData)));
    <?php endif; ?>

    document.querySelectorAll('[data-revenue-period]').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('[data-revenue-period]').forEach(item => item.classList.remove('active'));
            this.classList.add('active');
            refreshDashboardCharts(this.getAttribute('data-revenue-period'));
        });
    });


    document.querySelectorAll('[data-income-period]').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('[data-income-period]').forEach(item => item.classList.remove('active'));
            this.classList.add('active');
            refreshIncomeChart(this.getAttribute('data-income-period'));
        });
    });
});
</script>
@endsection
