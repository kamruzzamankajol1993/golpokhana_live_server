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

      @if($isSuperAdmin)
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
      @endif

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
    </div>

    <div class="row g-3 mb-4">
      <div class="col-xl-8">
        @if($isSuperAdmin)
        <div class="progga-card">
          <div class="progga-card-header">
            <div>
              <div class="progga-card-title">Revenue Overview</div>
              <div class="progga-card-subtitle" id="revenueChartSubtitle">Dynamic revenue trend from completed orders</div>
            </div>
            <div class="progga-chart-toggle" style="flex-wrap:wrap;justify-content:flex-end;">
              <button class="progga-chart-toggle-btn" data-revenue-period="1">1 Day</button>
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
        @else
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
        @endif
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

    @if($isSuperAdmin)
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="progga-card">
            <div class="progga-card-header">
              <div>
                <div class="progga-card-title">Income Overview</div>
                <div class="progga-card-subtitle" id="incomeChartSubtitle">Daily income by payment method for the last 7 days</div>
              </div>
              <div class="progga-chart-toggle" style="flex-wrap:wrap;justify-content:flex-end;">
                <button class="progga-chart-toggle-btn" data-income-period="1">1 Day</button>
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
    @endif

    @if($isSuperAdmin)
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
              @php
                $topMaxQty = max(1, (int) ($topSellingItems->max('total_qty') ?? 1));
              @endphp
              @forelse($topSellingItems as $index => $item)
                @php $progress = min(100, ((int) $item->total_qty / $topMaxQty) * 100); @endphp
                <div style="padding:10px 0;{{ $loop->last ? '' : 'border-bottom:1px solid rgba(0,0,0,.06);' }}">
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
              @empty
                <div class="text-center text-muted" style="padding:32px 12px;">No completed item sales found.</div>
              @endforelse
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
                  @forelse($kitchenQueue as $order)
                    @php
                      $queueStatus = strtolower((string) $order->status);
                      $queueBadge = in_array($queueStatus, ['ready'], true) ? 'primary' : ($queueStatus === 'cooking' ? 'secondary' : 'warning');
                      $queueType = strtolower((string) $order->order_type);
                      $queueLocation = $queueType === 'dine-in'
                          ? 'T-' . ($order->table->table_number ?? 'N/A')
                          : ($queueType === 'delivery'
                              ? ($order->delivery_partner ?: 'Delivery')
                              : ($order->order_type ?? 'N/A'));
                      $waitMinutes = $order->created_at ? (int) max(0, floor($order->created_at->diffInMinutes(now()))) : 0;
                    @endphp
                    <tr>
                      <td><a href="{{ route('order.show', $order->id) }}" style="font-weight:800;text-decoration:none;">#{{ $order->order_number }}</a></td>
                      <td>{{ $queueLocation }}</td>
                      <td>{{ number_format($order->orderDetails->sum('quantity')) }}</td>
                      <td>{{ $waitMinutes }} min</td>
                      <td><span class="progga-badge progga-badge-{{ $queueBadge }}">{{ $order->status }}</span></td>
                    </tr>
                  @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px 12px;">Kitchen queue is clear.</td></tr>
                  @endforelse
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
                  @forelse($recentOrders as $order)
                    @php
                      $recentStatus = strtolower((string) $order->status);
                      $recentBadge = $recentStatus === 'completed' ? 'primary' : ($recentStatus === 'cancelled' ? 'danger' : 'warning');
                      $recentType = strtolower((string) $order->order_type);
                      $recentLocation = $recentType === 'dine-in'
                          ? 'Dine-In · T-' . ($order->table->table_number ?? 'N/A')
                          : ($recentType === 'delivery'
                              ? 'Delivery' . ($order->delivery_partner ? ' · ' . $order->delivery_partner : '')
                              : ($order->order_type ?? 'N/A'));
                    @endphp
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
                  @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:30px 12px;">No recent orders found.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    @endif

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

    function revenueSubtitle(period) {
        if (period === '1') return 'Revenue for the current business day';
        if (period === '12m') return 'Monthly revenue trend from the last 12 months';
        if (['7', '14', '21', '30', '60', '90', '180'].includes(period)) {
            return 'Daily revenue trend from the last ' + period + ' days';
        }
        return 'Daily revenue trend from the last 7 days';
    }

    function incomeSubtitle(period) {
        if (period === '1') return 'Income by payment method for the current business day';
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
    @if($isSuperAdmin)
      buildIncomeChart(@json($incomeChartLabels), @json($incomeCashData), @json($incomeCardData), @json($incomeMfsData));
    @endif

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
