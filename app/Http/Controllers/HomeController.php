<?php

namespace App\Http\Controllers;

use App\Exports\ArrayReportExport;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Table;
use App\Models\RestaurantSetting;
use App\Support\OrderVisibility;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Restaurant opening/closing time is the single source of truth for Dashboard dates.
     * If the setting is missing, the old calendar-day behaviour is preserved as a fallback.
     */
    private function restaurantBusinessHours(): array
    {
        $setting = RestaurantSetting::query()->first(['opening_time', 'closing_time']);

        return [
            'opening' => $this->normalizeBusinessTime($setting?->opening_time, '00:00:00'),
            'closing' => $this->normalizeBusinessTime($setting?->closing_time, '23:59:59'),
        ];
    }

    private function normalizeBusinessTime($value, string $fallback): string
    {
        if (empty($value)) {
            return $fallback;
        }

        try {
            return Carbon::parse((string) $value)->format('H:i:s');
        } catch (\Throwable $exception) {
            return $fallback;
        }
    }

    /**
     * Build one business-day window from its opening date.
     * Example: 2026-07-28 12:01:00 to 2026-07-29 06:00:00.
     */
    private function businessWindowForDate(Carbon $businessDate, ?array $hours = null): array
    {
        $hours = $hours ?? $this->restaurantBusinessHours();
        $date = $businessDate->copy()->startOfDay();
        $start = $date->copy()->setTimeFromTimeString($hours['opening']);
        $end = $date->copy()->setTimeFromTimeString($hours['closing']);

        // Closing time before/equal opening time means the shift ends on the next date.
        if ($hours['closing'] <= $hours['opening']) {
            $end->addDay();
        }

        return [
            'business_date' => $date,
            'start' => $start,
            'end' => $end,
            'hours' => $hours,
        ];
    }

    /**
     * Return the currently active business day, or null while the restaurant is closed.
     */
    private function currentBusinessWindow(?Carbon $moment = null): ?array
    {
        $now = ($moment ?? Carbon::now())->copy();
        $hours = $this->restaurantBusinessHours();
        $today = $now->copy()->startOfDay();
        $todayOpening = $today->copy()->setTimeFromTimeString($hours['opening']);
        $todayClosing = $today->copy()->setTimeFromTimeString($hours['closing']);

        // Same opening/closing time is treated as a continuous 24-hour business day.
        if ($hours['opening'] === $hours['closing']) {
            $businessDate = $now->format('H:i:s') >= $hours['opening']
                ? $today
                : $today->copy()->subDay();

            return $this->businessWindowForDate($businessDate, $hours);
        }

        // Normal same-date shift, e.g. 09:00 to 23:00.
        if ($hours['opening'] < $hours['closing']) {
            if ($now->greaterThanOrEqualTo($todayOpening) && $now->lessThanOrEqualTo($todayClosing)) {
                return $this->businessWindowForDate($today, $hours);
            }

            return null;
        }

        // Overnight shift, e.g. 12:01 PM to 06:00 AM next day.
        if ($now->greaterThanOrEqualTo($todayOpening)) {
            return $this->businessWindowForDate($today, $hours);
        }

        if ($now->lessThanOrEqualTo($todayClosing)) {
            return $this->businessWindowForDate($today->copy()->subDay(), $hours);
        }

        // Between closing and the next opening, the Dashboard must show zero/empty data.
        return null;
    }

    /**
     * Return the active business window, or the latest completed business window while closed.
     * Long-range Dashboard sections use this window so they remain visible at every login time.
     */
    private function reportingBusinessWindow(?Carbon $moment = null): array
    {
        $now = ($moment ?? Carbon::now())->copy();
        $activeWindow = $this->currentBusinessWindow($now);

        if ($activeWindow !== null) {
            return $activeWindow;
        }

        $hours = $this->restaurantBusinessHours();
        $today = $now->copy()->startOfDay();
        $time = $now->format('H:i:s');

        // For a normal same-date shift, use yesterday before opening and today after closing.
        if ($hours['opening'] < $hours['closing']) {
            $businessDate = $time < $hours['opening']
                ? $today->copy()->subDay()
                : $today;

            return $this->businessWindowForDate($businessDate, $hours);
        }

        // For an overnight shift, the closed gap belongs after yesterday's completed business day.
        return $this->businessWindowForDate($today->copy()->subDay(), $hours);
    }

    /**
     * Convert an order timestamp to the opening date of its restaurant business day.
     * Historical/imported rows are never discarded solely because the current
     * restaurant opening/closing setting differs from the old setting.
     */
    private function businessDateForTimestamp(Carbon $timestamp, array $hours): ?Carbon
    {
        $time = $timestamp->format('H:i:s');
        $date = $timestamp->copy()->startOfDay();

        // A 24-hour business day changes at the configured opening time.
        if ($hours['opening'] === $hours['closing']) {
            return $time >= $hours['opening'] ? $date : $date->subDay();
        }

        // Same-day shifts keep the sale on its calendar date. We intentionally
        // do not drop legacy rows that are outside today's configured hours.
        if ($hours['opening'] < $hours['closing']) {
            return $date;
        }

        // Overnight shift: after-midnight sales through closing belong to the
        // previous opening date. All other timestamps remain on their date.
        if ($time <= $hours['closing']) {
            return $date->subDay();
        }

        return $date;
    }

    /**
     * Exclude records created while the restaurant was closed from long-range metrics.
     */
    private function applyBusinessHoursFilter($query, string $column, array $hours)
    {
        if ($hours['opening'] === $hours['closing']) {
            return $query;
        }

        if ($hours['opening'] < $hours['closing']) {
            return $query
                ->whereTime($column, '>=', $hours['opening'])
                ->whereTime($column, '<=', $hours['closing']);
        }

        return $query->where(function ($timeQuery) use ($column, $hours) {
            $timeQuery
                ->whereTime($column, '>=', $hours['opening'])
                ->orWhereTime($column, '<=', $hours['closing']);
        });
    }

    private function businessMonthRange(Carbon $businessDate, array $hours): array
    {
        $firstDate = $businessDate->copy()->startOfMonth();
        $lastDate = $businessDate->copy()->endOfMonth();

        return [
            'start' => $this->businessWindowForDate($firstDate, $hours)['start'],
            'end' => $this->businessWindowForDate($lastDate, $hours)['end'],
        ];
    }

    /**
     * Return all timestamp columns that may identify when an order belongs to a
     * business month. `created_at` is used by the POS/Combined business-day
     * reports, while `order_time` preserves the original sale time for synced
     * historical orders. `completed_at` is used when available as an additional
     * compatibility source.
     */
    private function dashboardOrderTimestampColumns(): array
    {
        $columns = ['created_at', 'order_time'];

        if (Schema::hasColumn('orders', 'completed_at')) {
            $columns[] = 'completed_at';
        }

        return $columns;
    }

    /**
     * Resolve the business date for an order specifically inside the requested
     * calendar month. We intentionally try every available historical timestamp
     * instead of trusting only one field, because imported/offline orders can
     * have a sync timestamp in one column and the real transaction date in another.
     */
    private function businessDateForOrderMonth($order, string $periodKey, array $hours): ?Carbon
    {
        foreach ($this->dashboardOrderTimestampColumns() as $column) {
            $value = $order->{$column} ?? null;
            if (!$value) {
                continue;
            }

            try {
                $businessDate = $this->businessDateForTimestamp(Carbon::parse($value), $hours);
            } catch (\Throwable $exception) {
                Log::warning('[SalesCalendar] Unable to parse historical order timestamp', [
                    'column' => $column,
                    'value' => $value,
                    'message' => $exception->getMessage(),
                ]);
                continue;
            }

            if ($businessDate !== null && $businessDate->format('Y-m') === $periodKey) {
                return $businessDate;
            }
        }

        return null;
    }

    /**
     * Completed order rows for one restaurant business month.
     *
     * The month selector itself is calendar based (October -> September), but
     * each row is assigned to a restaurant business date. Candidate rows are
     * accepted when ANY supported order timestamp belongs to the target month.
     * This makes the dashboard resilient to historical/offline sync differences
     * between created_at, order_time and completed_at.
     */
    private function businessMonthCompletedOrderRows(Carbon $monthDate, array $hours)
    {
        $periodKey = $monthDate->format('Y-m');
        $queryStart = $monthDate->copy()->startOfMonth()->startOfDay()->subDay();
        $queryEnd = $monthDate->copy()->endOfMonth()->endOfDay()->addDay();
        $timestampColumns = $this->dashboardOrderTimestampColumns();

        $candidateQuery = Order::query()
            ->whereRaw('LOWER(TRIM(orders.status)) = ?', ['completed'])
            ->where(function ($dateQuery) use ($timestampColumns, $queryStart, $queryEnd) {
                foreach ($timestampColumns as $index => $column) {
                    $qualified = 'orders.' . $column;
                    if ($index === 0) {
                        $dateQuery->whereBetween($qualified, [$queryStart, $queryEnd]);
                    } else {
                        $dateQuery->orWhereBetween($qualified, [$queryStart, $queryEnd]);
                    }
                }
            });

        $selectColumns = ['orders.id', 'orders.grand_total', 'orders.created_at', 'orders.order_time'];
        if (in_array('completed_at', $timestampColumns, true)) {
            $selectColumns[] = 'orders.completed_at';
        }

        $candidateRows = $candidateQuery
            ->get($selectColumns)
            ->filter(function ($order) use ($hours, $periodKey) {
                return $this->businessDateForOrderMonth($order, $periodKey, $hours) !== null;
            })
            ->values();

        if (!OrderVisibility::isRandomHalfEnabled() || $candidateRows->isEmpty()) {
            return $candidateRows;
        }

        // Keep Dashboard Order Visibility behavior, but apply it only after the
        // exact business-month candidate set has been established.
        $visibleIds = OrderVisibility::visibleIds(
            Order::query()->whereIn('orders.id', $candidateRows->pluck('id')->all()),
            [
                'dashboard_metric' => 'monthly_revenue',
                'period' => $periodKey,
            ]
        );

        return $candidateRows
            ->whereIn('id', $visibleIds)
            ->values();
    }

    /** Completed revenue for one restaurant business month. */
    private function businessMonthCompletedSales(Carbon $monthDate, array $hours): float
    {
        return (float) $this->businessMonthCompletedOrderRows($monthDate, $hours)
            ->sum('grand_total');
    }

    private function businessYearRange(Carbon $businessDate, array $hours): array
    {
        $firstDate = $businessDate->copy()->startOfYear();
        $lastDate = $businessDate->copy()->endOfYear();

        return [
            'start' => $this->businessWindowForDate($firstDate, $hours)['start'],
            'end' => $this->businessWindowForDate($lastDate, $hours)['end'],
        ];
    }

    /**
     * Dashboard aggregates use the same globally visible order sample as Order List.
     */
    private function dashboardVisibleOrderIds(): ?array
    {
        return OrderVisibility::globalVisibleIds();
    }

    /**
     * Build one deterministic visibility sample for a specific reporting range.
     * Calendar-today orders stay fully visible; the configured hide percentage
     * is applied only to non-today orders inside the reporting range.
     */
    private function rangeVisibleOrderIds(
        Carbon $start,
        Carbon $end,
        array $seedContext
    ): ?array {
        if (!OrderVisibility::isRandomHalfEnabled()) {
            return null;
        }

        return OrderVisibility::visibleIds(
            Order::query()->whereBetween('orders.created_at', [$start, $end]),
            $seedContext
        );
    }

    private function emptyDashboardChartPayload(string $period = '7'): array
    {
        $period = in_array($period, ['1', 'yesterday', '7', '14', '21', '30', '60', '90', '180', '12m'], true) ? $period : '7';
        $chartLabels = [];
        $chartData = [];

        if ($period === 'yesterday') {
            $chartLabels[] = Carbon::today()->subDay()->format('d M');
            $chartData[] = 0;
        } elseif ($period === '12m') {
            $startMonth = Carbon::now()->subMonths(11)->startOfMonth();

            for ($i = 0; $i < 12; $i++) {
                $chartLabels[] = $startMonth->copy()->addMonths($i)->format('M y');
                $chartData[] = 0;
            }
        } else {
            $days = (int) $period;

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $chartLabels[] = $days <= 7 ? $date->format('D') : $date->format('d M');
                $chartData[] = 0;
            }
        }

        return [
            'period' => $period,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'statusLabels' => ['Pending', 'Cooking', 'Ready', 'Completed', 'Cancelled'],
            'statusData' => [0, 0, 0, 0, 0],
            'topItemsLabels' => [],
            'topItemsData' => [],
        ];
    }

    private function dashboardChartPayload(
        string $period = '7',
        ?array $visibleOrderIds = null,
        ?array $reportingWindow = null
    ): array {
        $period = in_array($period, ['1', 'yesterday', '7', '14', '21', '30', '60', '90', '180', '12m'], true) ? $period : '7';
        $reportingWindow = $reportingWindow ?? $this->reportingBusinessWindow();

        $hours = $reportingWindow['hours'];
        $currentBusinessDate = $reportingWindow['business_date'];
        $chartLabels = [];
        $chartData = [];

        if ($period === 'yesterday') {
            $businessDate = $currentBusinessDate->copy()->subDay();
            $window = $this->businessWindowForDate($businessDate, $hours);
            $revenue = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
                ->where('status', 'Completed')
                ->whereBetween('created_at', [$window['start'], $window['end']])
                ->sum('grand_total');

            $chartLabels[] = $businessDate->format('d M');
            $chartData[] = round((float) $revenue, 2);
        } elseif ($period === '12m') {
            $startMonth = $currentBusinessDate->copy()->subMonths(11)->startOfMonth();
            $endMonthDate = $currentBusinessDate->copy()->endOfMonth();
            $overallStart = $this->businessWindowForDate($startMonth, $hours)['start'];
            $overallEnd = $this->businessWindowForDate($endMonthDate, $hours)['end'];

            $salesRowsQuery = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
                ->where('status', 'Completed')
                ->whereBetween('created_at', [$overallStart, $overallEnd]);

            $salesRows = $this->applyBusinessHoursFilter($salesRowsQuery, 'created_at', $hours)
                ->get(['created_at', 'grand_total']);

            $monthlyTotals = [];

            foreach ($salesRows as $row) {
                $businessDate = $this->businessDateForTimestamp(Carbon::parse($row->created_at), $hours);

                if ($businessDate === null) {
                    continue;
                }

                $key = $businessDate->format('Y-m');
                $monthlyTotals[$key] = ($monthlyTotals[$key] ?? 0) + (float) $row->grand_total;
            }

            for ($i = 0; $i < 12; $i++) {
                $date = $startMonth->copy()->addMonths($i);
                $key = $date->format('Y-m');
                $chartLabels[] = $date->format('M y');
                $chartData[] = round((float) ($monthlyTotals[$key] ?? 0), 2);
            }
        } else {
            $days = (int) $period;
            $firstBusinessDate = $currentBusinessDate->copy()->subDays($days - 1);
            $overallStart = $this->businessWindowForDate($firstBusinessDate, $hours)['start'];
            $overallEnd = $reportingWindow['end'];

            $salesRowsQuery = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
                ->where('status', 'Completed')
                ->whereBetween('created_at', [$overallStart, $overallEnd]);

            $salesRows = $this->applyBusinessHoursFilter($salesRowsQuery, 'created_at', $hours)
                ->get(['created_at', 'grand_total']);

            $dailyTotals = [];

            foreach ($salesRows as $row) {
                $businessDate = $this->businessDateForTimestamp(Carbon::parse($row->created_at), $hours);

                if ($businessDate === null) {
                    continue;
                }

                $key = $businessDate->format('Y-m-d');
                $dailyTotals[$key] = ($dailyTotals[$key] ?? 0) + (float) $row->grand_total;
            }

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = $currentBusinessDate->copy()->subDays($i);
                $key = $date->format('Y-m-d');
                $chartLabels[] = $days <= 7 ? $date->format('D') : $date->format('d M');
                $chartData[] = round((float) ($dailyTotals[$key] ?? 0), 2);
            }
        }

        $monthRange = $this->businessMonthRange($currentBusinessDate, $hours);
        $orderStatusQuery = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
            ->whereBetween('created_at', [$monthRange['start'], $monthRange['end']]);

        $orderStatuses = $this->applyBusinessHoursFilter($orderStatusQuery, 'created_at', $hours)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $preferredStatusLabels = ['Pending', 'Processing', 'Cooking', 'Ready', 'Completed', 'Delivered', 'Cancelled'];
        $extraStatusLabels = collect(array_keys($orderStatuses))
            ->filter(fn ($status) => $status && !in_array($status, $preferredStatusLabels, true))
            ->values()
            ->toArray();

        $statusLabels = collect(array_merge($preferredStatusLabels, $extraStatusLabels))
            ->filter(fn ($status) => (int) ($orderStatuses[$status] ?? 0) > 0)
            ->values()
            ->toArray();

        if (empty($statusLabels)) {
            $statusLabels = ['Pending', 'Cooking', 'Ready', 'Completed', 'Cancelled'];
        }

        $statusData = array_map(fn ($status) => (int) ($orderStatuses[$status] ?? 0), $statusLabels);

        return [
            'period' => $period,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'statusLabels' => $statusLabels,
            'statusData' => $statusData,
        ];
    }

    private function isSuperAdminUser(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Owner and Director use the same dashboard experience and dashboard reports as Super Admin.
        // Role matching is case-insensitive, so Owner/owner and Director/director are both supported.
        // This does not grant these roles Super Admin permissions outside HomeController.
        return $user->getRoleNames()->contains(function ($roleName) {
            return in_array(strtolower(trim((string) $roleName)), ['super admin', 'owner', 'director'], true);
        });
    }

    /**
     * Non-super-admin users always see the current restaurant business day's data.
     * During an overnight shift the active opening date is used; outside opening hours,
     * today's configured business window is used so the Dashboard never falls back to a month.
     */
    private function todayDashboardWindow(?Carbon $moment = null): array
    {
        $now = ($moment ?? Carbon::now())->copy();
        $activeWindow = $this->currentBusinessWindow($now);

        if ($activeWindow !== null) {
            return $activeWindow;
        }

        return $this->businessWindowForDate($now->copy()->startOfDay(), $this->restaurantBusinessHours());
    }

    private function todayDashboardPayload(array $todayWindow, ?array $visibleOrderIds): array
    {
        $statusQuery = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
            ->whereBetween('created_at', [$todayWindow['start'], $todayWindow['end']]);

        $orderStatuses = $statusQuery
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $preferredStatusLabels = ['Pending', 'Processing', 'Cooking', 'Ready', 'Completed', 'Delivered', 'Cancelled'];
        $extraStatusLabels = collect(array_keys($orderStatuses))
            ->filter(fn ($status) => $status && !in_array($status, $preferredStatusLabels, true))
            ->values()
            ->toArray();

        $statusLabels = collect(array_merge($preferredStatusLabels, $extraStatusLabels))
            ->filter(fn ($status) => (int) ($orderStatuses[$status] ?? 0) > 0)
            ->values()
            ->toArray();

        if (empty($statusLabels)) {
            $statusLabels = ['Pending', 'Cooking', 'Ready', 'Completed', 'Cancelled'];
        }

        $statusData = array_map(fn ($status) => (int) ($orderStatuses[$status] ?? 0), $statusLabels);

        $todayRevenue = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
            ->whereBetween('created_at', [$todayWindow['start'], $todayWindow['end']])
            ->where('status', 'Completed')
            ->sum('grand_total');

        return [
            'period' => 'today',
            'chartLabels' => [$todayWindow['business_date']->format('d M')],
            'chartData' => [round((float) $todayRevenue, 2)],
            'statusLabels' => $statusLabels,
            'statusData' => $statusData,
        ];
    }

    /**
     * Build grouped Cash/Card/MFS income bars for 7, 30, 60, 90, 180 days or 12 months.
     * Split orders are naturally allocated to their stored paid_in_* components.
     * Due collections are moved to their actual paid_at business date when the
     * order_due_payments ledger is available, so the same money is not counted twice.
     */
    private function dashboardIncomeChartPayload(
        string $period = '7',
        ?array $visibleOrderIds = null,
        ?array $reportingWindow = null
    ): array {
        $period = in_array($period, ['1', 'yesterday', '7', '14', '21', '30', '60', '90', '180', '12m'], true) ? $period : '7';
        $reportingWindow = $reportingWindow ?? $this->reportingBusinessWindow();
        $hours = $reportingWindow['hours'];
        $currentBusinessDate = $reportingWindow['business_date'];

        $labels = [];
        $bucketKeys = [];

        if ($period === '12m') {
            $startMonth = $currentBusinessDate->copy()->subMonths(11)->startOfMonth();
            $endMonthDate = $currentBusinessDate->copy()->endOfMonth();
            $overallStart = $this->businessWindowForDate($startMonth, $hours)['start'];
            $overallEnd = $this->businessWindowForDate($endMonthDate, $hours)['end'];

            for ($i = 0; $i < 12; $i++) {
                $date = $startMonth->copy()->addMonths($i);
                $bucketKeys[] = $date->format('Y-m');
                $labels[] = $date->format('M y');
            }
        } elseif ($period === 'yesterday') {
            $date = $currentBusinessDate->copy()->subDay();
            $window = $this->businessWindowForDate($date, $hours);
            $overallStart = $window['start'];
            $overallEnd = $window['end'];
            $bucketKeys[] = $date->format('Y-m-d');
            $labels[] = $date->format('d M');
        } else {
            $days = (int) $period;
            $firstBusinessDate = $currentBusinessDate->copy()->subDays($days - 1);
            $overallStart = $this->businessWindowForDate($firstBusinessDate, $hours)['start'];
            $overallEnd = $reportingWindow['end'];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = $currentBusinessDate->copy()->subDays($i);
                $bucketKeys[] = $date->format('Y-m-d');
                $labels[] = $days <= 7 ? $date->format('D') : $date->format('d M');
            }
        }

        $cashTotals = array_fill_keys($bucketKeys, 0.0);
        $cardTotals = array_fill_keys($bucketKeys, 0.0);
        $mfsTotals = array_fill_keys($bucketKeys, 0.0);

        $ordersQuery = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$overallStart, $overallEnd]);

        $orders = $this->applyBusinessHoursFilter($ordersQuery, 'created_at', $hours)
            ->get([
                'id',
                'created_at',
                'payment_type',
                'total_paid_amount',
                'paid_in_cash',
                'paid_in_card',
                'paid_in_mfc',
            ]);

        $dueByOrder = [];
        $duePaymentHasBreakdown = Schema::hasTable('order_due_payments')
            && Schema::hasColumn('order_due_payments', 'paid_in_cash')
            && Schema::hasColumn('order_due_payments', 'paid_in_card')
            && Schema::hasColumn('order_due_payments', 'paid_in_mfc');

        if (Schema::hasTable('order_due_payments') && $orders->isNotEmpty()) {
            $dueColumns = ['order_id', 'payment_type', 'amount'];
            if ($duePaymentHasBreakdown) {
                array_push($dueColumns, 'paid_in_cash', 'paid_in_card', 'paid_in_mfc');
            }

            $dueRows = DB::table('order_due_payments')
                ->whereIn('order_id', $orders->pluck('id')->all())
                ->get($dueColumns);

            foreach ($dueRows as $dueRow) {
                $orderId = (int) $dueRow->order_id;
                $type = strtolower(trim((string) $dueRow->payment_type));
                $amount = max(0, (float) $dueRow->amount);

                $dueByOrder[$orderId] ??= ['cash' => 0.0, 'card' => 0.0, 'mfs' => 0.0, 'total' => 0.0];

                $cashPart = $duePaymentHasBreakdown ? max(0, (float) ($dueRow->paid_in_cash ?? 0)) : 0.0;
                $cardPart = $duePaymentHasBreakdown ? max(0, (float) ($dueRow->paid_in_card ?? 0)) : 0.0;
                $mfsPart = $duePaymentHasBreakdown ? max(0, (float) ($dueRow->paid_in_mfc ?? 0)) : 0.0;

                if (($cashPart + $cardPart + $mfsPart) > 0) {
                    $dueByOrder[$orderId]['cash'] += $cashPart;
                    $dueByOrder[$orderId]['card'] += $cardPart;
                    $dueByOrder[$orderId]['mfs'] += $mfsPart;
                } elseif ($type === 'cash') {
                    $dueByOrder[$orderId]['cash'] += $amount;
                } elseif ($type === 'card') {
                    $dueByOrder[$orderId]['card'] += $amount;
                } elseif (in_array($type, ['mobile banking', 'mfc', 'mfs'], true)) {
                    $dueByOrder[$orderId]['mfs'] += $amount;
                }

                $dueByOrder[$orderId]['total'] += $amount;
            }
        }

        foreach ($orders as $order) {
            $businessDate = $this->businessDateForTimestamp(Carbon::parse($order->created_at), $hours);

            if ($businessDate === null) {
                continue;
            }

            $key = $period === '12m' ? $businessDate->format('Y-m') : $businessDate->format('Y-m-d');

            if (!array_key_exists($key, $cashTotals)) {
                continue;
            }

            $due = $dueByOrder[(int) $order->id] ?? ['cash' => 0.0, 'card' => 0.0, 'mfs' => 0.0, 'total' => 0.0];
            $cash = max(0, (float) ($order->paid_in_cash ?? 0) - $due['cash']);
            $card = max(0, (float) ($order->paid_in_card ?? 0) - $due['card']);
            $mfs = max(0, (float) ($order->paid_in_mfc ?? 0) - $due['mfs']);

            // Backward compatibility for old single-payment orders without paid_in_* values.
            if (($cash + $card + $mfs) <= 0) {
                $legacyInitialAmount = max(0, (float) ($order->total_paid_amount ?? 0) - $due['total']);
                $legacyType = strtolower(trim((string) $order->payment_type));

                if ($legacyType === 'cash') {
                    $cash = $legacyInitialAmount;
                } elseif ($legacyType === 'card') {
                    $card = $legacyInitialAmount;
                } elseif (in_array($legacyType, ['mobile banking', 'mfc', 'mfs'], true)) {
                    $mfs = $legacyInitialAmount;
                }
            }

            $cashTotals[$key] += $cash;
            $cardTotals[$key] += $card;
            $mfsTotals[$key] += $mfs;
        }

        // Due payments are income on the date they are actually collected.
        if (Schema::hasTable('order_due_payments')) {
            $dueIncomeQuery = DB::table('order_due_payments')
                ->join('orders', 'order_due_payments.order_id', '=', 'orders.id')
                ->whereBetween('order_due_payments.paid_at', [$overallStart, $overallEnd]);

            $dueIncomeQuery = OrderVisibility::constrain($dueIncomeQuery, $visibleOrderIds);
            $dueIncomeColumns = [
                'order_due_payments.paid_at',
                'order_due_payments.payment_type',
                'order_due_payments.amount',
            ];
            if ($duePaymentHasBreakdown) {
                $dueIncomeColumns[] = 'order_due_payments.paid_in_cash';
                $dueIncomeColumns[] = 'order_due_payments.paid_in_card';
                $dueIncomeColumns[] = 'order_due_payments.paid_in_mfc';
            }

            $dueIncomeRows = $this->applyBusinessHoursFilter(
                $dueIncomeQuery,
                'order_due_payments.paid_at',
                $hours
            )->get($dueIncomeColumns);

            foreach ($dueIncomeRows as $dueIncome) {
                $businessDate = $this->businessDateForTimestamp(Carbon::parse($dueIncome->paid_at), $hours);

                if ($businessDate === null) {
                    continue;
                }

                $key = $period === '12m' ? $businessDate->format('Y-m') : $businessDate->format('Y-m-d');

                if (!array_key_exists($key, $cashTotals)) {
                    continue;
                }

                $amount = max(0, (float) $dueIncome->amount);
                $type = strtolower(trim((string) $dueIncome->payment_type));
                $cashPart = $duePaymentHasBreakdown ? max(0, (float) ($dueIncome->paid_in_cash ?? 0)) : 0.0;
                $cardPart = $duePaymentHasBreakdown ? max(0, (float) ($dueIncome->paid_in_card ?? 0)) : 0.0;
                $mfsPart = $duePaymentHasBreakdown ? max(0, (float) ($dueIncome->paid_in_mfc ?? 0)) : 0.0;

                if (($cashPart + $cardPart + $mfsPart) > 0) {
                    $cashTotals[$key] += $cashPart;
                    $cardTotals[$key] += $cardPart;
                    $mfsTotals[$key] += $mfsPart;
                } elseif ($type === 'cash') {
                    $cashTotals[$key] += $amount;
                } elseif ($type === 'card') {
                    $cardTotals[$key] += $amount;
                } elseif (in_array($type, ['mobile banking', 'mfc', 'mfs'], true)) {
                    $mfsTotals[$key] += $amount;
                }
            }
        }

        return [
            'incomePeriod' => $period,
            'incomeChartLabels' => $labels,
            'incomeCashData' => array_map(fn ($key) => round((float) $cashTotals[$key], 2), $bucketKeys),
            'incomeCardData' => array_map(fn ($key) => round((float) $cardTotals[$key], 2), $bucketKeys),
            'incomeMfsData' => array_map(fn ($key) => round((float) $mfsTotals[$key], 2), $bucketKeys),
        ];
    }

    private function todayPaymentBreakdown(array $todayWindow, ?array $visibleOrderIds): array
    {
        $orders = OrderVisibility::constrain(Order::query(), $visibleOrderIds)
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$todayWindow['start'], $todayWindow['end']])
            ->get();

        $amounts = [
            'Cash' => 0.0,
            'Card' => 0.0,
            'Mobile Banking / MFS' => 0.0,
        ];
        $counts = [
            'Cash' => 0,
            'Card' => 0,
            'Mobile Banking / MFS' => 0,
        ];

        foreach ($orders as $order) {
            $cash = (float) ($order->paid_in_cash ?? 0);
            $card = (float) ($order->paid_in_card ?? 0);
            $mfs = (float) ($order->paid_in_mfc ?? 0);

            // Backward compatibility for old orders that only stored payment_type + total_paid_amount.
            if (($cash + $card + $mfs) <= 0 && (float) ($order->total_paid_amount ?? 0) > 0) {
                $legacyAmount = (float) $order->total_paid_amount;

                if (strcasecmp((string) $order->payment_type, 'Cash') === 0) {
                    $cash = $legacyAmount;
                } elseif (strcasecmp((string) $order->payment_type, 'Card') === 0) {
                    $card = $legacyAmount;
                } elseif (in_array(strtolower((string) $order->payment_type), ['mobile banking', 'mfc', 'mfs'], true)) {
                    $mfs = $legacyAmount;
                }
            }

            $amounts['Cash'] += $cash;
            $amounts['Card'] += $card;
            $amounts['Mobile Banking / MFS'] += $mfs;

            if ($cash > 0) {
                $counts['Cash']++;
            }
            if ($card > 0) {
                $counts['Card']++;
            }
            if ($mfs > 0) {
                $counts['Mobile Banking / MFS']++;
            }
        }

        $totalCollected = array_sum($amounts);
        $icons = [
            'Cash' => 'bi-cash-coin',
            'Card' => 'bi-credit-card',
            'Mobile Banking / MFS' => 'bi-phone',
        ];

        $paymentRows = collect($amounts)->map(function ($amount, $label) use ($counts, $icons, $totalCollected) {
            return [
                'label' => $label,
                'icon' => $icons[$label],
                'amount' => (float) $amount,
                'orders_count' => (int) $counts[$label],
                'percentage' => $totalCollected > 0 ? ((float) $amount / $totalCollected) * 100 : 0,
            ];
        })->values()->all();

        return compact('paymentRows', 'totalCollected');
    }

    private function salesCalendarChartPayload(Request $request, array $reportingWindow): array
    {
        $filter = (string) $request->get('sales_filter', 'this_month');
        if (!in_array($filter, ['this_month', 'previous_month'], true)) {
            $filter = 'this_month';
        }

        $hours = $reportingWindow['hours'];

        // The selector is calendar-month based: October => previous month is September.
        $calendarMonth = Carbon::now('Asia/Dhaka')->startOfMonth();
        $periodDate = $filter === 'previous_month'
            ? $calendarMonth->copy()->subMonthNoOverflow()
            : $calendarMonth;

        $periodKey = $periodDate->format('Y-m');
        $queryStart = $periodDate->copy()->startOfMonth()->startOfDay()->subDay();
        $queryEnd = $periodDate->copy()->endOfMonth()->endOfDay()->addDay();
        $timestampColumns = $this->dashboardOrderTimestampColumns();

        // Diagnostic counts are intentionally logged even on successful requests.
        // This lets us identify "HTTP 200 but empty chart" cases from laravel.log.
        $timestampCandidateCounts = [];
        foreach ($timestampColumns as $column) {
            try {
                $timestampCandidateCounts[$column] = [
                    'all_statuses' => Order::query()
                        ->whereBetween('orders.' . $column, [$queryStart, $queryEnd])
                        ->count(),
                    'completed' => Order::query()
                        ->whereRaw('LOWER(TRIM(orders.status)) = ?', ['completed'])
                        ->whereBetween('orders.' . $column, [$queryStart, $queryEnd])
                        ->count(),
                ];
            } catch (\Throwable $exception) {
                $timestampCandidateCounts[$column] = ['query_error' => $exception->getMessage()];
            }
        }

        $rangeAnyStatusQuery = Order::query()
            ->where(function ($dateQuery) use ($timestampColumns, $queryStart, $queryEnd) {
                foreach ($timestampColumns as $index => $column) {
                    $qualified = 'orders.' . $column;
                    if ($index === 0) {
                        $dateQuery->whereBetween($qualified, [$queryStart, $queryEnd]);
                    } else {
                        $dateQuery->orWhereBetween($qualified, [$queryStart, $queryEnd]);
                    }
                }
            });
        $rangeStatusCounts = (clone $rangeAnyStatusQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $rawCandidates = Order::query()
            ->whereRaw('LOWER(TRIM(orders.status)) = ?', ['completed'])
            ->where(function ($dateQuery) use ($timestampColumns, $queryStart, $queryEnd) {
                foreach ($timestampColumns as $index => $column) {
                    $qualified = 'orders.' . $column;
                    if ($index === 0) {
                        $dateQuery->whereBetween($qualified, [$queryStart, $queryEnd]);
                    } else {
                        $dateQuery->orWhereBetween($qualified, [$queryStart, $queryEnd]);
                    }
                }
            });

        $rawCandidateCount = (clone $rawCandidates)->count();
        $sampleColumns = ['orders.id', 'orders.status', 'orders.grand_total', 'orders.created_at', 'orders.order_time'];
        if (in_array('completed_at', $timestampColumns, true)) {
            $sampleColumns[] = 'orders.completed_at';
        }
        $rawCandidateSamples = (clone $rawCandidates)
            ->orderByDesc('orders.id')
            ->limit(8)
            ->get($sampleColumns)
            ->map(fn ($row) => $row->only(['id', 'status', 'grand_total', 'created_at', 'order_time', 'completed_at']))
            ->values()
            ->all();

        $orders = $this->businessMonthCompletedOrderRows($periodDate, $hours);

        $days = [];
        $cursor = $periodDate->copy()->startOfMonth()->startOfDay();
        $lastDay = $periodDate->copy()->endOfMonth()->startOfDay();
        while ($cursor->lte($lastDay)) {
            $days[$cursor->format('Y-m-d')] = 0.0;
            $cursor->addDay();
        }

        $mappedCount = 0;
        $unmappedCount = 0;
        $mappedSamples = [];
        foreach ($orders as $order) {
            $businessDate = $this->businessDateForOrderMonth($order, $periodKey, $hours);
            if ($businessDate === null) {
                $unmappedCount++;
                continue;
            }

            $key = $businessDate->format('Y-m-d');
            if (array_key_exists($key, $days)) {
                $days[$key] += (float) $order->grand_total;
                $mappedCount++;
                if (count($mappedSamples) < 8) {
                    $mappedSamples[] = [
                        'id' => $order->id,
                        'created_at' => $order->created_at ?? null,
                        'order_time' => $order->order_time ?? null,
                        'completed_at' => $order->completed_at ?? null,
                        'business_date' => $key,
                        'grand_total' => (float) $order->grand_total,
                    ];
                }
            } else {
                $unmappedCount++;
            }
        }

        $salesCalendarTotal = round((float) array_sum($days), 2);
        $nonZeroDays = collect($days)->filter(fn ($value) => (float) $value != 0.0)->count();

        Log::info('[SalesCalendar] payload built', [
            'sales_filter' => $filter,
            'period_key' => $periodKey,
            'period_label' => $periodDate->format('F Y'),
            'business_hours' => $hours,
            'query_start' => $queryStart->toDateTimeString(),
            'query_end' => $queryEnd->toDateTimeString(),
            'timestamp_columns' => $timestampColumns,
            'timestamp_candidate_counts' => $timestampCandidateCounts,
            'range_status_counts' => $rangeStatusCounts,
            'random_half_visibility_enabled' => OrderVisibility::isRandomHalfEnabled(),
            'raw_candidate_count' => $rawCandidateCount,
            'raw_candidate_samples' => $rawCandidateSamples,
            'resolved_order_count' => $orders->count(),
            'mapped_order_count' => $mappedCount,
            'unmapped_order_count' => $unmappedCount,
            'non_zero_days' => $nonZeroDays,
            'sales_calendar_total' => $salesCalendarTotal,
            'mapped_samples' => $mappedSamples,
        ]);

        return [
            'salesCalendarFilter' => $filter,
            'salesCalendarPeriodLabel' => $periodDate->format('F Y') . ' · Business day',
            'salesCalendarLabels' => array_map(fn ($date) => Carbon::parse($date)->format('d M'), array_keys($days)),
            'salesCalendarData' => array_values(array_map(fn ($value) => round((float) $value, 2), $days)),
            'salesCalendarTotal' => $salesCalendarTotal,
            'debug' => [
                'period_key' => $periodKey,
                'raw_candidate_count' => $rawCandidateCount,
                'range_status_counts' => $rangeStatusCounts,
                'resolved_order_count' => $orders->count(),
                'mapped_order_count' => $mappedCount,
                'non_zero_days' => $nonZeroDays,
                'total' => $salesCalendarTotal,
            ],
        ];
    }

    public function chartData(Request $request)
    {
        // Sales Calendar AJAX filter request. Log both success and failure so
        // an empty HTTP-200 response can be diagnosed from storage/logs/laravel.log.
        if ($request->has('sales_filter')) {
            $requestId = 'sales-calendar-' . now()->format('YmdHisv') . '-' . substr(md5((string) microtime(true)), 0, 6);
            Log::info('[SalesCalendar] AJAX request received', [
                'request_id' => $requestId,
                'sales_filter' => $request->get('sales_filter'),
                'event_source' => $request->get('event_source'),
                'is_ajax' => $request->ajax(),
                'user_id' => optional($request->user())->id,
                'url' => $request->fullUrl(),
            ]);

            try {
                $activeWindow = $this->currentBusinessWindow();
                $reportingWindow = $activeWindow ?? $this->reportingBusinessWindow();
                $payload = $this->salesCalendarChartPayload($request, $reportingWindow);
                $payload['request_id'] = $requestId;

                Log::info('[SalesCalendar] AJAX response ready', [
                    'request_id' => $requestId,
                    'sales_filter' => $payload['salesCalendarFilter'] ?? null,
                    'event_source' => $request->get('event_source'),
                    'period_label' => $payload['salesCalendarPeriodLabel'] ?? null,
                    'labels_count' => count($payload['salesCalendarLabels'] ?? []),
                    'data_count' => count($payload['salesCalendarData'] ?? []),
                    'non_zero_days' => collect($payload['salesCalendarData'] ?? [])->filter(fn ($value) => (float) $value != 0.0)->count(),
                    'total' => $payload['salesCalendarTotal'] ?? null,
                    'debug' => $payload['debug'] ?? null,
                ]);

                return response()->json($payload)
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                    ->header('Pragma', 'no-cache');
            } catch (\Throwable $exception) {
                Log::error('[SalesCalendar] AJAX request failed', [
                    'request_id' => $requestId,
                    'sales_filter' => $request->get('sales_filter'),
                    'event_source' => $request->get('event_source'),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                return response()->json([
                    'message' => 'Sales Calendar data loading failed.',
                    'request_id' => $requestId,
                    'error' => app()->environment('local') ? $exception->getMessage() : null,
                ], 500)->header('Cache-Control', 'no-store');
            }
        }

        if (!$this->isSuperAdminUser()) {
            $todayWindow = $this->todayDashboardWindow();
            $todayVisibleIds = $this->rangeVisibleOrderIds(
                $todayWindow['start'],
                $todayWindow['end'],
                [
                    'dashboard_metric' => 'today',
                    'business_date' => $todayWindow['business_date']->format('Y-m-d'),
                ]
            );

            $todayPayload = $this->todayDashboardPayload($todayWindow, $todayVisibleIds);
            return response()->json($todayPayload);
        }

        $activeWindow = $this->currentBusinessWindow();
        $reportingWindow = $activeWindow ?? $this->reportingBusinessWindow();

        $visibleOrderIds = $this->dashboardVisibleOrderIds();
        $period = (string) $request->get('period', '7');

        return response()->json(array_merge(
            $this->dashboardChartPayload($period, $visibleOrderIds, $reportingWindow),
            $this->dashboardIncomeChartPayload($period, $visibleOrderIds, $reportingWindow),
            $this->salesCalendarChartPayload($request, $reportingWindow)
        ));
    }

    private function dashboardPeriodRange(string $period, array $reportingWindow): array
    {
        $allowedPeriods = ['1', 'yesterday', '7', '14', '21', '30', '60', '90', '180', '12m'];
        $period = in_array($period, $allowedPeriods, true) ? $period : '7';

        $hours = $reportingWindow['hours'];
        $currentBusinessDate = $reportingWindow['business_date'];

        if ($period === 'yesterday') {
            $firstBusinessDate = $currentBusinessDate->copy()->subDay();
            $lastBusinessDate = $firstBusinessDate->copy();
            $window = $this->businessWindowForDate($firstBusinessDate, $hours);
            $rangeStart = $window['start'];
            $rangeEnd = $window['end'];
        } elseif ($period === '12m') {
            $firstBusinessDate = $currentBusinessDate->copy()->subMonths(11)->startOfMonth();
            $lastBusinessDate = $currentBusinessDate;
            $rangeStart = $this->businessWindowForDate($firstBusinessDate, $hours)['start'];
            $rangeEnd = $reportingWindow['end'];
        } else {
            $days = (int) $period;
            $firstBusinessDate = $currentBusinessDate->copy()->subDays($days - 1);
            $lastBusinessDate = $currentBusinessDate;
            $rangeStart = $this->businessWindowForDate($firstBusinessDate, $hours)['start'];
            $rangeEnd = $reportingWindow['end'];
        }

        return [
            'period' => $period,
            'start' => $rangeStart,
            'end' => $rangeEnd,
            'first_business_date' => $firstBusinessDate,
            'last_business_date' => $lastBusinessDate,
            'hours' => $hours,
        ];
    }

    private function topSellingItemsQuery(array $periodRange, ?array $visibleOrderIds)
    {
        $salesQuery = OrderVisibility::constrain(
            DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id'),
            $visibleOrderIds
        )
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$periodRange['start'], $periodRange['end']]);

        $salesSubQuery = $this->applyBusinessHoursFilter(
            $salesQuery,
            'orders.created_at',
            $periodRange['hours']
        )
            ->select(
                'order_details.product_id',
                DB::raw('SUM(order_details.quantity) as total_qty'),
                DB::raw('SUM(order_details.subtotal) as total_amount')
            )
            ->groupBy('order_details.product_id');

        return DB::table('food_items')
            ->leftJoinSub($salesSubQuery, 'sales', function ($join) {
                $join->on('food_items.id', '=', 'sales.product_id');
            })
            ->select(
                'food_items.id',
                'food_items.name as product_name',
                DB::raw('COALESCE(sales.total_qty, 0) as total_qty'),
                DB::raw('COALESCE(sales.total_amount, 0) as total_amount')
            )
            ->orderByDesc('total_qty')
            ->orderBy('food_items.name');
    }

    private function topSellingPeriodLabels(): array
    {
        return [
            '1' => 'Today',
            'yesterday' => 'Yesterday',
            '7' => '7 Days',
            '14' => '14 Days',
            '21' => '21 Days',
            '30' => '30 Days',
            '60' => '60 Days',
            '90' => '90 Days',
            '180' => '180 Days',
            '12m' => '12 Months',
        ];
    }

    private function topSellingReportContext(Request $request): array
    {
        $activeWindow = $this->currentBusinessWindow();
        $reportingWindow = $activeWindow ?? $this->reportingBusinessWindow();
        $periodRange = $this->dashboardPeriodRange((string) $request->get('period', '7'), $reportingWindow);
        $period = $periodRange['period'];
        $periodLabels = $this->topSellingPeriodLabels();
        $dateRangeLabel = $periodRange['first_business_date']->format('d M Y')
            . ' - ' . $periodRange['last_business_date']->format('d M Y');

        return [
            'periodRange' => $periodRange,
            'period' => $period,
            'periodLabels' => $periodLabels,
            'periodLabel' => $periodLabels[$period],
            'dateRangeLabel' => $dateRangeLabel,
            'visibleOrderIds' => $this->dashboardVisibleOrderIds(),
        ];
    }

    public function topSellingItems(Request $request)
    {
        abort_unless($this->isSuperAdminUser(), 403);

        $context = $this->topSellingReportContext($request);
        $perPage = (int) $request->get('per_page', 20);
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;

        $topSellingItems = $this->topSellingItemsQuery(
            $context['periodRange'],
            $context['visibleOrderIds']
        )
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.dashboard.top_selling_items', [
            'topSellingItems' => $topSellingItems,
            'period' => $context['period'],
            'periodLabel' => $context['periodLabel'],
            'periodLabels' => $context['periodLabels'],
            'dateRangeLabel' => $context['dateRangeLabel'],
            'perPage' => $perPage,
        ]);
    }

    public function downloadTopSellingItemsPdf(Request $request)
    {
        abort_unless($this->isSuperAdminUser(), 403);

        @ini_set('pcre.backtrack_limit', '10000000');
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '180');
        @set_time_limit(180);

        $context = $this->topSellingReportContext($request);
        $topSellingItems = $this->topSellingItemsQuery(
            $context['periodRange'],
            $context['visibleOrderIds']
        )->get();

        $mpdfTempDir = storage_path('app/mpdf-top-selling');
        if (!is_dir($mpdfTempDir)) {
            @mkdir($mpdfTempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 14,
            'margin_header' => 5,
            'margin_footer' => 7,
            'tempDir' => $mpdfTempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'freesans',
        ]);

        $restaurant = RestaurantSetting::query()->first();
        $fileName = 'top-selling-items-' . $context['period'] . '-' . now()->format('Ymd-His') . '.pdf';

        $mpdf->SetTitle('Top Selling Items - ' . $context['periodLabel']);
        $mpdf->SetFooter('Generated: ' . now()->format('d M Y, h:i A') . '||Page {PAGENO} of {nbpg}');
        $mpdf->WriteHTML(view('admin.dashboard.top_selling_items_pdf', [
            'topSellingItems' => $topSellingItems,
            'periodLabel' => $context['periodLabel'],
            'dateRangeLabel' => $context['dateRangeLabel'],
            'restaurant' => $restaurant,
        ])->render());

        return response($mpdf->Output($fileName, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Export the complete Top Selling Items result set for the selected period.
     * This intentionally ignores the page-size selector, matching the existing
     * "Export All" PDF behaviour.
     */
    public function downloadTopSellingItemsExcel(Request $request)
    {
        abort_unless($this->isSuperAdminUser(), 403);

        $context = $this->topSellingReportContext($request);
        $items = $this->topSellingItemsQuery(
            $context['periodRange'],
            $context['visibleOrderIds']
        )->get();

        $rows = $items->values()->map(function ($item, $index) {
            $hasSales = (int) $item->total_qty > 0;

            return [
                $index + 1,
                $item->product_name,
                (int) $item->total_qty,
                (float) $item->total_amount,
                $hasSales ? 'Sold' : 'No Sales',
            ];
        })->all();

        $fileName = 'top-selling-items-' . $context['period'] . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(
            new ArrayReportExport(
                ['Rank', 'Item', 'Quantity Sold', 'Sales Value', 'Status'],
                $rows,
                'Top Selling Items'
            ),
            $fileName
        );
    }

    public function index(Request $request)
    {
        $isSuperAdmin = $this->isSuperAdminUser();
        $activeWindow = $this->currentBusinessWindow();
        $reportingWindow = $activeWindow ?? $this->reportingBusinessWindow();
        $hours = $reportingWindow['hours'];
        $businessDate = $reportingWindow['business_date'];

        $todayWindow = $isSuperAdmin
            ? $activeWindow
            : $this->todayDashboardWindow();

        $todaySales = 0;
        $salesChange = 0;
        $todayOrdersCount = 0;
        $ordersChange = 0;
        $todayPendingAmount = 0;
        $todayBusinessWindowLabel = null;
        $todayVisibleIds = null;

        if ($todayWindow !== null) {
            $todayVisibleIds = $this->rangeVisibleOrderIds(
                $todayWindow['start'],
                $todayWindow['end'],
                [
                    'dashboard_metric' => 'today',
                    'business_date' => $todayWindow['business_date']->format('Y-m-d'),
                ]
            );

            $previousWindow = $this->businessWindowForDate(
                $todayWindow['business_date']->copy()->subDay(),
                $todayWindow['hours']
            );
            $previousVisibleIds = $this->rangeVisibleOrderIds(
                $previousWindow['start'],
                $previousWindow['end'],
                [
                    'dashboard_metric' => 'previous_day',
                    'business_date' => $previousWindow['business_date']->format('Y-m-d'),
                ]
            );

            $todaySales = OrderVisibility::constrain(Order::query(), $todayVisibleIds)
                ->whereBetween('created_at', [$todayWindow['start'], $todayWindow['end']])
                ->where('status', 'Completed')
                ->sum('grand_total');

            $yesterdaySales = OrderVisibility::constrain(Order::query(), $previousVisibleIds)
                ->whereBetween('created_at', [$previousWindow['start'], $previousWindow['end']])
                ->where('status', 'Completed')
                ->sum('grand_total');

            $salesChange = $yesterdaySales > 0
                ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100
                : ($todaySales > 0 ? 100 : 0);

            $todayOrdersCount = OrderVisibility::constrain(Order::query(), $todayVisibleIds)
                ->whereBetween('created_at', [$todayWindow['start'], $todayWindow['end']])
                ->count();

            // Pending Amount is the total order value of Pending orders in the active business day.
            // The same metric is shown to Super Admin and other dashboard users.
            $todayPendingAmount = OrderVisibility::constrain(Order::query(), $todayVisibleIds)
                ->whereBetween('created_at', [$todayWindow['start'], $todayWindow['end']])
                ->where('status', 'Pending')
                ->sum('grand_total');

            $todayBusinessWindowLabel = $todayWindow['start']->format('h:i A')
                . ' - ' . $todayWindow['end']->format('h:i A');

            $yesterdayOrdersCount = OrderVisibility::constrain(Order::query(), $previousVisibleIds)
                ->whereBetween('created_at', [$previousWindow['start'], $previousWindow['end']])
                ->count();

            $ordersChange = $todayOrdersCount - $yesterdayOrdersCount;
        }

        // Revenue month selection follows the calendar month, while each order
        // is still grouped by restaurant business day. In October, Last Month is
        // always September and its comparison month is always August.
        $calendarMonth = Carbon::now('Asia/Dhaka')->startOfMonth();
        $lastMonthBusinessDate = $calendarMonth->copy()->subMonthNoOverflow();
        $previousMonthBusinessDate = $calendarMonth->copy()->subMonthsNoOverflow(2);

        $lastMonthSales = $this->businessMonthCompletedSales($lastMonthBusinessDate, $hours);
        $previousMonthSales = $this->businessMonthCompletedSales($previousMonthBusinessDate, $hours);
        $lastMonthChange = $previousMonthSales > 0
            ? (($lastMonthSales - $previousMonthSales) / $previousMonthSales) * 100
            : ($lastMonthSales > 0 ? 100 : 0);
        $lastMonthComparisonLabel = $previousMonthBusinessDate->format('M Y');

        if ($isSuperAdmin) {
            $monthlySales = $this->businessMonthCompletedSales($calendarMonth, $hours);
            $monthlyChange = $lastMonthSales > 0
                ? (($monthlySales - $lastMonthSales) / $lastMonthSales) * 100
                : ($monthlySales > 0 ? 100 : 0);

            $chartPayload = $this->dashboardChartPayload(
                '7',
                $this->dashboardVisibleOrderIds(),
                $reportingWindow
            );
            $paymentRows = [];
            $totalCollected = 0;
        } else {
            $monthlySales = $todaySales;
            $monthlyChange = $salesChange;
            $chartPayload = $this->todayDashboardPayload($todayWindow, $todayVisibleIds);
            extract($this->todayPaymentBreakdown($todayWindow, $todayVisibleIds));
        }

        extract($chartPayload);

        // Initial page load renders ONLY This Month. Any later filter change,
        // including Previous Month, is loaded through dashboard.chart_data via AJAX.
        if ($isSuperAdmin) {
            $thisMonthSalesCalendarRequest = Request::create(request()->path(), 'GET', ['sales_filter' => 'this_month']);
            $salesCalendarPayload = $this->salesCalendarChartPayload($thisMonthSalesCalendarRequest, $reportingWindow);
        } else {
            $salesCalendarPayload = [
                'salesCalendarFilter' => 'this_month',
                'salesCalendarPeriodLabel' => Carbon::now('Asia/Dhaka')->format('F Y') . ' · Business day',
                'salesCalendarLabels' => [],
                'salesCalendarData' => [],
                'salesCalendarTotal' => 0,
            ];
        }

        extract($salesCalendarPayload);

        $incomePayload = $isSuperAdmin
            ? $this->dashboardIncomeChartPayload(
                '7',
                $this->dashboardVisibleOrderIds(),
                $reportingWindow
            )
            : [
                'incomePeriod' => '7',
                'incomeChartLabels' => [],
                'incomeCashData' => [],
                'incomeCardData' => [],
                'incomeMfsData' => [],
            ];

        extract($incomePayload);

        // Running Tables is intentionally unchanged for every role.
        $totalTables = Table::count();
        $runningTables = Table::whereHas('orders', function ($query) {
            $query->whereIn('status', ['Pending', 'Cooking']);
        })->count();
        $availableTables = max($totalTables - $runningTables, 0);

        // Operational dashboard sections requested for Super Admin only.
        // Non-super-admin users neither query nor receive these datasets.
        $topSellingItems = collect();
        $kitchenQueue = collect();
        $recentOrders = collect();

        if ($isSuperAdmin) {
            $dashboardVisibleIds = $this->dashboardVisibleOrderIds();

            $yearRange = $this->businessYearRange($businessDate, $hours);
            $topItemsQuery = OrderVisibility::constrain(
                OrderDetail::query()->join('orders', 'order_details.order_id', '=', 'orders.id'),
                $dashboardVisibleIds
            )
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.created_at', [$yearRange['start'], $yearRange['end']]);

            $topSellingItems = $this->applyBusinessHoursFilter($topItemsQuery, 'orders.created_at', $hours)
                ->select(
                    'order_details.product_name',
                    DB::raw('SUM(order_details.quantity) as total_qty'),
                    DB::raw('SUM(order_details.subtotal) as total_amount')
                )
                ->groupBy('order_details.product_name')
                ->orderByDesc('total_qty')
                ->take(5)
                ->get();

            if ($activeWindow !== null) {
                $kitchenQueueVisibleIds = $this->rangeVisibleOrderIds(
                    $activeWindow['start'],
                    $activeWindow['end'],
                    [
                        'dashboard_metric' => 'kitchen_queue',
                        'business_date' => $activeWindow['business_date']->format('Y-m-d'),
                    ]
                );

                $kitchenQueue = OrderVisibility::constrain(
                    Order::with(['table', 'orderDetails']),
                    $kitchenQueueVisibleIds
                )
                    ->whereBetween('created_at', [$activeWindow['start'], $activeWindow['end']])
                    ->whereIn('status', ['Pending', 'Processing', 'Cooking', 'Ready'])
                    ->orderBy('id', 'asc')
                    ->limit(5)
                    ->get();
            }

            $recentOrders = OrderVisibility::constrain(
                Order::with(['customer', 'table', 'waiter', 'orderDetails']),
                $dashboardVisibleIds
            )
                ->orderByDesc('id')
                ->limit(6)
                ->get();
        }

        $salesCalendarPayload = $this->salesCalendarChartPayload($request, $reportingWindow);

        return view('admin.dashboard.index', compact(
            'isSuperAdmin',
            'todaySales',
            'salesChange',
            'monthlySales',
            'lastMonthSales',
            'lastMonthChange',
            'lastMonthComparisonLabel',
            'monthlyChange',
            'todayOrdersCount',
            'ordersChange',
            'todayPendingAmount',
            'todayBusinessWindowLabel',
            'totalTables',
            'runningTables',
            'availableTables',
            'chartLabels',
            'chartData',
            'statusLabels',
            'statusData',
            'paymentRows',
            'totalCollected',
            'incomePeriod',
            'incomeChartLabels',
            'incomeCashData',
            'incomeCardData',
            'incomeMfsData',
            'salesCalendarFilter',
            'salesCalendarPeriodLabel',
            'salesCalendarLabels',
            'salesCalendarData',
            'salesCalendarTotal',
            'topSellingItems',
            'kitchenQueue',
            'recentOrders',
        ) + $salesCalendarPayload);
    }

}
