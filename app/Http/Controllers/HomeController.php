<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Table;
use App\Models\RestaurantSetting;
use App\Support\OrderVisibility;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Closed-period timestamps return null and are excluded from Dashboard calculations.
     */
    private function businessDateForTimestamp(Carbon $timestamp, array $hours): ?Carbon
    {
        $time = $timestamp->format('H:i:s');
        $date = $timestamp->copy()->startOfDay();

        if ($hours['opening'] === $hours['closing']) {
            return $time >= $hours['opening'] ? $date : $date->subDay();
        }

        if ($hours['opening'] < $hours['closing']) {
            return ($time >= $hours['opening'] && $time <= $hours['closing']) ? $date : null;
        }

        if ($time >= $hours['opening']) {
            return $date;
        }

        if ($time <= $hours['closing']) {
            return $date->subDay();
        }

        return null;
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
        $period = in_array($period, ['1', '7', '30', '60', '90', '180', '12m'], true) ? $period : '7';
        $chartLabels = [];
        $chartData = [];

        if ($period === '12m') {
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
        $period = in_array($period, ['1', '7', '30', '60', '90', '180', '12m'], true) ? $period : '7';
        $reportingWindow = $reportingWindow ?? $this->reportingBusinessWindow();

        $hours = $reportingWindow['hours'];
        $currentBusinessDate = $reportingWindow['business_date'];
        $chartLabels = [];
        $chartData = [];

        if ($period === '12m') {
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

        return $user->getRoleNames()->contains(function ($roleName) {
            return strcasecmp((string) $roleName, 'Super Admin') === 0;
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
        $period = in_array($period, ['1', '7', '30', '60', '90', '180', '12m'], true) ? $period : '7';
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

        if (Schema::hasTable('order_due_payments') && $orders->isNotEmpty()) {
            $dueRows = DB::table('order_due_payments')
                ->whereIn('order_id', $orders->pluck('id')->all())
                ->get(['order_id', 'payment_type', 'amount']);

            foreach ($dueRows as $dueRow) {
                $orderId = (int) $dueRow->order_id;
                $type = strtolower(trim((string) $dueRow->payment_type));
                $amount = max(0, (float) $dueRow->amount);

                $dueByOrder[$orderId] ??= ['cash' => 0.0, 'card' => 0.0, 'mfs' => 0.0, 'total' => 0.0];

                if ($type === 'cash') {
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
            $dueIncomeRows = $this->applyBusinessHoursFilter(
                $dueIncomeQuery,
                'order_due_payments.paid_at',
                $hours
            )->get([
                'order_due_payments.paid_at',
                'order_due_payments.payment_type',
                'order_due_payments.amount',
            ]);

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

                if ($type === 'cash') {
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

    public function chartData(Request $request)
    {
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
            $this->dashboardIncomeChartPayload($period, $visibleOrderIds, $reportingWindow)
        ));
    }

    public function index()
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

            // Non-super-admin dashboard pending amount uses the same configured business-day window.
            // Only orders whose current order status is exactly Pending are included.
            $todayPendingAmount = OrderVisibility::constrain(Order::query(), $todayVisibleIds)
                ->whereBetween('created_at', [$todayWindow['start'], $todayWindow['end']])
                ->where('status', 'Pending')
                ->sum('due');

            $todayBusinessWindowLabel = $todayWindow['start']->format('h:i A')
                . ' - ' . $todayWindow['end']->format('h:i A');

            $yesterdayOrdersCount = OrderVisibility::constrain(Order::query(), $previousVisibleIds)
                ->whereBetween('created_at', [$previousWindow['start'], $previousWindow['end']])
                ->count();

            $ordersChange = $todayOrdersCount - $yesterdayOrdersCount;
        }

        if ($isSuperAdmin) {
            $thisMonthRange = $this->businessMonthRange($businessDate, $hours);
            $lastMonthBusinessDate = $businessDate->copy()->subMonthNoOverflow();
            $lastMonthRange = $this->businessMonthRange($lastMonthBusinessDate, $hours);

            $thisMonthVisibleIds = $this->rangeVisibleOrderIds(
                $thisMonthRange['start'],
                $thisMonthRange['end'],
                [
                    'dashboard_metric' => 'monthly_revenue',
                    'period' => $businessDate->format('Y-m'),
                ]
            );

            $lastMonthVisibleIds = $this->rangeVisibleOrderIds(
                $lastMonthRange['start'],
                $lastMonthRange['end'],
                [
                    'dashboard_metric' => 'monthly_revenue',
                    'period' => $lastMonthBusinessDate->format('Y-m'),
                ]
            );

            $monthlySalesQuery = OrderVisibility::constrain(Order::query(), $thisMonthVisibleIds)
                ->whereBetween('created_at', [$thisMonthRange['start'], $thisMonthRange['end']])
                ->where('status', 'Completed');

            $monthlySales = $this->applyBusinessHoursFilter($monthlySalesQuery, 'created_at', $hours)
                ->sum('grand_total');

            $lastMonthSalesQuery = OrderVisibility::constrain(Order::query(), $lastMonthVisibleIds)
                ->whereBetween('created_at', [$lastMonthRange['start'], $lastMonthRange['end']])
                ->where('status', 'Completed');

            $lastMonthSales = $this->applyBusinessHoursFilter($lastMonthSalesQuery, 'created_at', $hours)
                ->sum('grand_total');

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

        return view('admin.dashboard.index', compact(
            'isSuperAdmin',
            'todaySales',
            'salesChange',
            'monthlySales',
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
            'topSellingItems',
            'kitchenQueue',
            'recentOrders',
        ));
    }

}
