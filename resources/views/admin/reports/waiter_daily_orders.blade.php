@extends('admin.master.master')
@section('title', 'Waiter Daily Order Report — TableTrack RMS')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .waiter-report-filter {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px;
    }
    .waiter-report-filter .progga-form-group { margin: 0; }
    .waiter-report-filter .progga-form-control,
    .waiter-report-filter .progga-select { min-width: 205px; }
    .business-window-box {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        padding: 12px 16px;
        margin-bottom: 16px;
        border: 1px solid var(--progga-border-light);
        border-radius: 12px;
        background: rgba(33, 53, 42, .035);
    }
    .business-window-label {
        color: var(--progga-text-muted);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .business-window-value {
        color: var(--progga-primary);
        font-size: 14px;
        font-weight: 900;
    }
    .waiter-summary-table th,
    .waiter-summary-table td,
    .waiter-order-table th,
    .waiter-order-table td {
        white-space: nowrap;
        vertical-align: middle;
    }
    .waiter-name { font-weight: 900; color: var(--progga-primary); }
    .waiter-meta { margin-top: 2px; font-size: 11px; color: var(--progga-text-muted); }
    .order-status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 78px;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        background: rgba(108, 117, 125, .12);
        color: #596168;
    }
    .order-status-pill.completed { background: rgba(25, 135, 84, .12); color: #198754; }
    .order-status-pill.cancelled { background: rgba(220, 53, 69, .12); color: #dc3545; }
    .order-status-pill.active { background: rgba(255, 193, 7, .18); color: #8a6500; }
    .report-pagination-wrap { padding: 14px 16px; border-top: 1px solid var(--progga-border-light); }
    @media print {
        .no-print, .progga-sidebar, .progga-topbar, .progga-header { display: none !important; }
        .progga-content { margin: 0 !important; padding: 0 !important; }
        .progga-card { box-shadow: none !important; }
    }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Waiter Daily Order Report</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">Reports</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Waiter Daily Orders</span>
            </div>
        </div>
        <button type="button" onclick="window.print()" class="progga-btn progga-btn-outline progga-btn-sm no-print">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>

    <div class="progga-card no-print" style="margin-bottom:16px;">
        <form action="{{ route('reports.waiter_daily_orders') }}" method="GET" class="waiter-report-filter">
            <div class="progga-form-group">
                <label class="progga-form-label">Business Date</label>
                <input
                    type="text"
                    name="business_date"
                    id="waiterBusinessDate"
                    class="progga-form-control"
                    value="{{ $businessDate->format('d-m-Y') }}"
                    placeholder="DD-MM-YYYY"
                    autocomplete="off"
                >
            </div>

            <div class="progga-form-group">
                <label class="progga-form-label">Waiter User</label>
                <select name="user_id" class="progga-select">
                    <option value="">All Waiters</option>
                    @foreach($waiters as $waiter)
                        @php
                            $waiterName = $waiter->name ?: trim(($waiter->first_name ?? '') . ' ' . ($waiter->last_name ?? ''));
                            $waiterName = $waiterName ?: ('User #' . $waiter->id);
                        @endphp
                        <option value="{{ $waiter->id }}" {{ (int) $selectedUserId === (int) $waiter->id ? 'selected' : '' }}>
                            {{ $waiterName }}{{ $waiter->user_id ? ' — ' . $waiter->user_id : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="progga-btn progga-btn-primary progga-btn-sm" style="height:38px;">
                    <i class="bi bi-funnel"></i> Apply Filter
                </button>
                <a href="{{ route('reports.waiter_daily_orders') }}" class="progga-btn progga-btn-outline progga-btn-sm" style="height:38px;display:inline-flex;align-items:center;">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <div class="business-window-box">
        <div class="business-window-label">Business Window</div>
        <div class="business-window-value">
            {{ $windowStart->format('d/m/Y h:i A') }} — {{ $windowEnd->format('d/m/Y h:i A') }}
        </div>
        <span class="progga-badge progga-badge-secondary">1 Business Day</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="progga-stat-card">
                <div class="progga-stat-icon primary"><i class="bi bi-receipt"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Total Orders</div>
                    <div class="progga-stat-value">{{ number_format($totalOrders) }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="progga-stat-card">
                <div class="progga-stat-icon success"><i class="bi bi-check-circle"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Completed</div>
                    <div class="progga-stat-value">{{ number_format($completedOrders) }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="progga-stat-card">
                <div class="progga-stat-icon warning"><i class="bi bi-hourglass-split"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Active / Pending</div>
                    <div class="progga-stat-value">{{ number_format($activeOrders) }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="progga-stat-card">
                <div class="progga-stat-icon danger"><i class="bi bi-x-circle"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Cancelled</div>
                    <div class="progga-stat-value">{{ number_format($cancelledOrders) }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="progga-stat-card">
                <div class="progga-stat-icon secondary"><i class="bi bi-people"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Waiters with Orders</div>
                    <div class="progga-stat-value">{{ number_format($waitersWithOrders) }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="progga-stat-card">
                <div class="progga-stat-icon secondary"><i class="bi bi-cash-stack"></i></div>
                <div class="progga-stat-info">
                    <div class="progga-stat-label">Completed Sales</div>
                    <div class="progga-stat-value">৳{{ number_format($completedSales, 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card" style="margin-bottom:18px;">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Waiter-wise Daily Summary</div>
                <div class="progga-card-subtitle">Orders are matched by <strong>orders.user_id</strong> only.</div>
            </div>
        </div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
            <table class="progga-table waiter-summary-table">
                <thead>
                    <tr>
                        <th>Waiter User</th>
                        <th>User ID</th>
                        <th>Total Orders</th>
                        <th>Completed</th>
                        <th>Active / Pending</th>
                        <th>Cancelled</th>
                        <th>Completed Sales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportRows as $row)
                        @php
                            $user = $row['user'];
                            $userName = $user->name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                            $userName = $userName ?: ('User #' . $user->id);
                        @endphp
                        <tr>
                            <td>
                                <div class="waiter-name">{{ $userName }}</div>
                                <div class="waiter-meta">{{ $user->email ?: ($user->phone ?: 'No contact information') }}</div>
                            </td>
                            <td>{{ $user->user_id ?: $user->id }}</td>
                            <td><strong>{{ number_format($row['total_orders']) }}</strong></td>
                            <td>{{ number_format($row['completed_orders']) }}</td>
                            <td>{{ number_format($row['active_orders']) }}</td>
                            <td>{{ number_format($row['cancelled_orders']) }}</td>
                            <td><strong>৳{{ number_format($row['completed_sales'], 0) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No user with the waiter role was found.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Daily Total</th>
                        <th>{{ number_format($totalOrders) }}</th>
                        <th>{{ number_format($completedOrders) }}</th>
                        <th>{{ number_format($activeOrders) }}</th>
                        <th>{{ number_format($cancelledOrders) }}</th>
                        <th>৳{{ number_format($completedSales, 0) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header">
            <div>
                <div class="progga-card-title">Order Details</div>
                <div class="progga-card-subtitle">All orders created inside the selected business window.</div>
            </div>
        </div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;overflow-x:auto;">
            <table class="progga-table waiter-order-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Waiter User</th>
                        <th>Order Time</th>
                        <th>Table</th>
                        <th>Order Type</th>
                        <th>Status</th>
                        <th>Grand Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $statusKey = strtolower((string) $order->status);
                            $statusClass = $statusKey === 'completed' ? 'completed' : ($statusKey === 'cancelled' ? 'cancelled' : 'active');
                            $orderUserName = optional($order->user)->name
                                ?: trim((optional($order->user)->first_name ?? '') . ' ' . (optional($order->user)->last_name ?? ''));
                            $orderUserName = $orderUserName ?: ('User #' . $order->user_id);
                        @endphp
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $orderUserName }}</td>
                            <td>{{ optional($order->created_at)->format('d/m/Y h:i A') }}</td>
                            <td>{{ optional($order->table)->table_number ?: 'N/A' }}</td>
                            <td>{{ $order->order_type ?: 'N/A' }}</td>
                            <td><span class="order-status-pill {{ $statusClass }}">{{ $order->status ?: 'N/A' }}</span></td>
                            <td><strong>৳{{ number_format((float) $order->grand_total, 0) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No waiter orders found for this business window.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="report-pagination-wrap no-print">
                @include('admin.reports.partials.custom_pagination', ['paginator' => $orders])
            </div>
        @endif
    </div>
</main>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr === 'function') {
        flatpickr('#waiterBusinessDate', {
            dateFormat: 'd-m-Y',
            allowInput: true
        });
    }
});
</script>
@endsection
