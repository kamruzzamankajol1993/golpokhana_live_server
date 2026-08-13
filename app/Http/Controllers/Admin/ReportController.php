<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RestaurantSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:report-sales-order-view', ['only' => ['salesOrder', 'deliveryReport', 'deliveryReportPdf']]);
        $this->middleware('permission:report-complimentary-orders-view', ['only' => ['complimentaryOrders']]);
        $this->middleware('permission:report-payment-type-sales-view', ['only' => ['paymentTypeSales']]);
        $this->middleware('permission:report-food-sales-view', ['only' => ['foodSales']]);
        $this->middleware('permission:report-waiter-daily-orders-view', ['only' => ['waiterDailyOrders']]);
    }

    /**
     * Export endpoints are shared, so authorize the requested report explicitly.
     */
    private function authorizeRequestedReportExport(Request $request): void
    {
        $permission = match ($request->get('report', 'sales_order')) {
            'complimentary_orders' => 'report-complimentary-orders-view',
            'payment_type_sales' => 'report-payment-type-sales-view',
            'food_sales' => 'report-food-sales-view',
            'waiter_daily_orders' => 'report-waiter-daily-orders-view',
            default => 'report-sales-order-view',
        };

        abort_unless(auth()->user()?->can($permission), 403);
    }
    /**
     * Report filter date parser.
     * Frontend datepicker shows/submits day-month-year (DD-MM-YYYY),
     * but this also keeps old Y-m-d links working.
     */
    private function parseReportDate(?string $date): Carbon
    {
        $date = trim((string) $date);

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd M Y', 'd F Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                // Try the next known date format.
            }
        }

        return Carbon::parse($date);
    }

    private function resolveReportFilters(Request $request): array
    {
        $currentYear = Carbon::now()->year;
        $filterType = $request->filter_type ?: 'year';
        $year = (int) ($request->year ?: $currentYear);
        $month = (int) ($request->month ?: Carbon::now()->month);

        if ($filterType === 'date' && $request->start_date && $request->end_date) {
            try {
                $startDate = $this->parseReportDate($request->start_date)->startOfDay();
                $endDate = $this->parseReportDate($request->end_date)->endOfDay();

                if ($startDate->gt($endDate)) {
                    [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
                }
            } catch (\Throwable $e) {
                $filterType = 'year';
                $startDate = Carbon::create($year, 1, 1)->startOfYear()->startOfDay();
                $endDate = Carbon::create($year, 12, 31)->endOfYear()->endOfDay();
            }
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth()->startOfDay();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        } else {
            $filterType = 'year';
            $startDate = Carbon::create($year, 1, 1)->startOfYear()->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfYear()->endOfDay();
        }

        return [
            'filterType' => $filterType,
            'year' => $year,
            'month' => $month,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'paymentMethod' => $request->payment_method,
            'yearOptions' => range($currentYear + 1, $currentYear - 10),
        ];
    }

    private function applyPaymentCollectionFilter($query, ?string $paymentMethod)
    {
        if (!$paymentMethod) return $query;

        return match ($paymentMethod) {
            'Cash' => $query->where(function ($q) {
                $q->where('paid_in_cash', '>', 0)
                  ->orWhere(function ($nested) {
                      $nested->where('payment_type', 'Cash')
                             ->where('total_paid_amount', '>', 0);
                  });
            }),
            'Card' => $query->where(function ($q) {
                $q->where('paid_in_card', '>', 0)
                  ->orWhere(function ($nested) {
                      $nested->where('payment_type', 'Card')
                             ->where('total_paid_amount', '>', 0);
                  });
            }),
            'Mobile Banking' => $query->where(function ($q) {
                $q->where('paid_in_mfc', '>', 0)
                  ->orWhere(function ($nested) {
                      $nested->where('payment_type', 'Mobile Banking')
                             ->where('total_paid_amount', '>', 0);
                  });
            }),
            'Split' => $query->where('payment_type', 'Split'),
            default => $query->where('payment_type', $paymentMethod),
        };
    }

    /** ১. সেলস ও অর্ডার রিপোর্ট — completed order details with custom pagination. */
    public function salesOrder(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        // Same filtered completed order query will drive summary cards, table, AJAX and export filters.
        $baseQuery = Order::with(['customer', 'table', 'orderDetails'])
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Summary cards.
        $totalRevenue = (clone $baseQuery)->sum('grand_total');
        $totalDiscount = (clone $baseQuery)->sum('discount_amount');
        $totalProductDiscount = (clone $baseQuery)->sum('product_discount_amount');
        $totalOrders = (clone $baseQuery)->count();
        $avgOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;
        $uniqueCustomers = (clone $baseQuery)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        // Detailed order rows used by resources/views/admin/reports/partials/sales_table_rows.blade.php.
        $orders = (clone $baseQuery)
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->appends($request->query());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.sales_table_rows', compact('orders'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $orders])->render(),
                'summary' => [
                    'revenue' => '৳' . number_format($totalRevenue, 0),
                    'other_discount' => '৳' . number_format($totalDiscount, 0),
                    'product_discount' => '৳' . number_format($totalProductDiscount, 0),
                    'orders' => $totalOrders,
                    'avg' => '৳' . number_format($avgOrderValue, 0),
                    'customers' => $uniqueCustomers,
                ],
            ]);
        }

        return view('admin.reports.sales_order', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'totalRevenue', 'totalDiscount', 'totalProductDiscount', 'totalOrders', 'avgOrderValue', 'uniqueCustomers', 'orders'
        ));
    }

    /** Delivery Report — show only Delivery order types. */
    public function deliveryReport(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $baseQuery = Order::with(['customer', 'table', 'waiter', 'user'])
            ->whereIn('order_type', ['Delivery', 'delivery'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalOrders = (clone $baseQuery)->count();
        $completedOrders = (clone $baseQuery)->where('status', 'Completed')->count();
        $totalValue = (float) (clone $baseQuery)->sum('grand_total');
        $totalDue = (float) (clone $baseQuery)->sum('due');

        $orders = (clone $baseQuery)
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->appends($request->query());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.delivery_table_rows', compact('orders'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $orders])->render(),
                'summary' => [
                    'orders' => $totalOrders,
                    'completed' => $completedOrders,
                    'value' => '৳' . number_format($totalValue, 0),
                    'due' => '৳' . number_format($totalDue, 0),
                ],
            ]);
        }

        return view('admin.reports.delivery_report', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'totalOrders', 'completedOrders', 'totalValue', 'totalDue', 'orders'
        ));
    }

    /** Open the filtered Delivery Report as an inline PDF in a new browser tab. */
    public function deliveryReportPdf(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $orders = Order::with(['customer', 'table', 'waiter', 'user'])
            ->whereIn('order_type', ['Delivery', 'delivery'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('id')
            ->get();

        $totalOrders = $orders->count();
        $completedOrders = $orders->where('status', 'Completed')->count();
        $totalValue = (float) $orders->sum('grand_total');
        $totalDue = (float) $orders->sum('due');
        $restaurant = RestaurantSetting::first();

        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        $html = view('admin.reports.delivery_pdf', compact(
            'orders', 'startDate', 'endDate', 'totalOrders', 'completedOrders',
            'totalValue', 'totalDue', 'restaurant'
        ))->render();

        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $fileName = 'Delivery_Report_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf';
        $mpdf->SetTitle($fileName);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Add the complimentary-food condition to an Order query.
     * Whole complimentary orders and legacy zero-priced complimentary rows are included.
     */
    private function applyComplimentaryOrderFilter($query)
    {
        return $query->where(function ($orderQuery) {
            $orderQuery->where(function ($wholeOrderQuery) {
                $wholeOrderQuery->where('is_complimentary_order', 1)
                    ->whereHas('orderDetails', function ($detailQuery) {
                        $detailQuery->where('quantity', '>', 0);
                    });
            })->orWhereHas('orderDetails', function ($detailQuery) {
                $detailQuery->where('quantity', '>', 0)
                    ->where(function ($complimentaryQuery) {
                        $complimentaryQuery->where('is_complimentary', 1)
                            ->orWhere(function ($zeroPriceQuery) {
                                $zeroPriceQuery->where('price', '<=', 0)
                                    ->where('subtotal', '<=', 0);
                            });
                    });
            });
        });
    }

    /** Complimentary Order Report: every order containing complimentary food. */
    public function complimentaryOrders(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $baseQuery = Order::with(['customer', 'table', 'orderDetails'])
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->applyComplimentaryOrderFilter($baseQuery);

        $totalOrders = (clone $baseQuery)->count();
        $completedOrders = (clone $baseQuery)->where('status', 'Completed')->count();
        $totalOrderValue = (float) (clone $baseQuery)->sum('grand_total');

        $complimentaryFoodQtyQuery = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('order_details.quantity', '>', 0)
            ->where(function ($query) {
                $query->where('orders.is_complimentary_order', 1)
                    ->orWhere('order_details.is_complimentary', 1)
                    ->orWhere(function ($zeroPriceQuery) {
                        $zeroPriceQuery->where('order_details.price', '<=', 0)
                            ->where('order_details.subtotal', '<=', 0);
                    });
            });
        $complimentaryFoodQty = (int) $complimentaryFoodQtyQuery->sum('order_details.quantity');

        $orders = (clone $baseQuery)
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.complimentary_table_rows', compact('orders'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $orders])->render(),
                'summary' => [
                    'orders' => $totalOrders,
                    'completed' => $completedOrders,
                    'food_qty' => number_format($complimentaryFoodQty),
                    'value' => '৳' . number_format($totalOrderValue, 0),
                ],
            ]);
        }

        return view('admin.reports.complimentary_orders', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'totalOrders', 'completedOrders', 'complimentaryFoodQty', 'totalOrderValue', 'orders'
        ));
    }

    public function index(Request $request)
    {
        return $this->salesOrder($request);
    }

    /**
     * Normalize restaurant setting time values to a database-safe 24-hour time.
     */
    private function normalizeBusinessTime(?string $time, string $fallback): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($time)->format('H:i:s');
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /**
     * Resolve the opening date that should be selected by default.
     * For an overnight restaurant, early-morning orders still belong to the
     * business date that started on the previous calendar day.
     */
    private function defaultBusinessDate(?RestaurantSetting $restaurant): Carbon
    {
        $now = Carbon::now();
        $openingTime = $this->normalizeBusinessTime($restaurant?->opening_time, '12:01:00');
        $closingTime = $this->normalizeBusinessTime($restaurant?->closing_time, '06:00:00');

        $todayOpening = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $now->format('Y-m-d') . ' ' . $openingTime
        );
        $todayClosing = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $now->format('Y-m-d') . ' ' . $closingTime
        );

        if ($todayClosing->lte($todayOpening) && $now->lte($todayClosing)) {
            return $now->copy()->subDay()->startOfDay();
        }

        return $now->copy()->startOfDay();
    }

    /**
     * Build one restaurant business window from its opening date.
     * Example: 29-07-2026 12:01 PM to 30-07-2026 06:00 AM.
     */
    private function resolveBusinessWindow(Carbon $businessDate, ?RestaurantSetting $restaurant): array
    {
        $openingTime = $this->normalizeBusinessTime($restaurant?->opening_time, '12:01:00');
        $closingTime = $this->normalizeBusinessTime($restaurant?->closing_time, '06:00:00');

        $windowStart = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $businessDate->format('Y-m-d') . ' ' . $openingTime
        );
        $windowEnd = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $businessDate->format('Y-m-d') . ' ' . $closingTime
        );

        if ($windowEnd->lte($windowStart)) {
            $windowEnd->addDay();
        }

        return [$windowStart, $windowEnd];
    }

    /**
     * Daily waiter order report.
     * Important: waiter ownership is taken only from orders.user_id and the
     * matching user must have the waiter role. orders.waiter_id is not used.
     */
    public function waiterDailyOrders(Request $request)
    {
        $restaurant = RestaurantSetting::first();
        $businessDate = $this->defaultBusinessDate($restaurant);

        if ($request->filled('business_date')) {
            try {
                $businessDate = $this->parseReportDate($request->business_date)->startOfDay();
            } catch (\Throwable $e) {
                // Keep the correctly resolved default business date.
            }
        }

        [$windowStart, $windowEnd] = $this->resolveBusinessWindow($businessDate, $restaurant);

        $waiters = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['waiter']);
            })
            ->select('id', 'name', 'user_id', 'first_name', 'last_name', 'email', 'phone')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $waiterIds = $waiters->pluck('id')->map(fn ($id) => (int) $id)->values();
        $selectedUserId = $request->filled('user_id') ? (int) $request->user_id : null;

        if ($selectedUserId && !$waiterIds->contains($selectedUserId)) {
            $selectedUserId = null;
        }

        $aggregateRows = Order::query()
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereIn('user_id', $waiterIds->all())
            ->when($selectedUserId, fn ($query) => $query->where('user_id', $selectedUserId))
            ->select('user_id')
            ->selectRaw('COUNT(*) AS total_orders')
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) AS completed_orders")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders")
            ->selectRaw("SUM(CASE WHEN LOWER(status) NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS active_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(status) = 'completed' THEN grand_total ELSE 0 END), 0) AS completed_sales")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(status) = 'completed' THEN discount_amount ELSE 0 END), 0) AS completed_other_discount")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(status) = 'completed' THEN product_discount_amount ELSE 0 END), 0) AS completed_product_discount")
            ->groupBy('user_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->user_id);

        $reportUsers = $selectedUserId
            ? $waiters->where('id', $selectedUserId)->values()
            : $waiters;

        $reportRows = $reportUsers->map(function ($user) use ($aggregateRows) {
            $aggregate = $aggregateRows->get((int) $user->id);

            return [
                'user' => $user,
                'total_orders' => (int) ($aggregate->total_orders ?? 0),
                'completed_orders' => (int) ($aggregate->completed_orders ?? 0),
                'active_orders' => (int) ($aggregate->active_orders ?? 0),
                'cancelled_orders' => (int) ($aggregate->cancelled_orders ?? 0),
                'completed_sales' => (float) ($aggregate->completed_sales ?? 0),
                'completed_other_discount' => (float) ($aggregate->completed_other_discount ?? 0),
                'completed_product_discount' => (float) ($aggregate->completed_product_discount ?? 0),
            ];
        });

        $orders = Order::with(['user', 'table'])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereIn('user_id', $waiterIds->all())
            ->when($selectedUserId, fn ($query) => $query->where('user_id', $selectedUserId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $totalOrders = $reportRows->sum('total_orders');
        $completedOrders = $reportRows->sum('completed_orders');
        $activeOrders = $reportRows->sum('active_orders');
        $cancelledOrders = $reportRows->sum('cancelled_orders');
        $completedSales = $reportRows->sum('completed_sales');
        $completedOtherDiscount = $reportRows->sum('completed_other_discount');
        $completedProductDiscount = $reportRows->sum('completed_product_discount');
        $waitersWithOrders = $reportRows->where('total_orders', '>', 0)->count();

        return view('admin.reports.waiter_daily_orders', compact(
            'restaurant',
            'businessDate',
            'windowStart',
            'windowEnd',
            'waiters',
            'selectedUserId',
            'reportRows',
            'orders',
            'totalOrders',
            'completedOrders',
            'activeOrders',
            'cancelledOrders',
            'completedSales',
            'completedOtherDiscount',
            'completedProductDiscount',
            'waitersWithOrders'
        ));
    }

    /** ২. পেমেন্ট টাইপ ওয়াইজ রিপোর্ট */
    public function paymentTypeSales(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $ordersQuery = Order::where('status', 'Completed')->whereBetween('created_at', [$startDate, $endDate]);
        if ($paymentMethod) {
            $this->applyPaymentCollectionFilter($ordersQuery, $paymentMethod);
        }

        $orders = $ordersQuery->get();

        // Legacy-safe collection breakdown. New payments use paid_in_* columns; old payments may have only total_paid_amount.
        $cashAmount = 0;
        $cardAmount = 0;
        $mfcAmount = 0;
        $cashOrders = 0;
        $cardOrders = 0;
        $mfcOrders = 0;

        foreach ($orders as $order) {
            $cash = (float) ($order->paid_in_cash ?? 0);
            $card = (float) ($order->paid_in_card ?? 0);
            $mfc = (float) ($order->paid_in_mfc ?? 0);

            if (($cash + $card + $mfc) <= 0 && (float) ($order->total_paid_amount ?? 0) > 0) {
                if ($order->payment_type === 'Cash') {
                    $cash = (float) $order->total_paid_amount;
                } elseif ($order->payment_type === 'Card') {
                    $card = (float) $order->total_paid_amount;
                } elseif ($order->payment_type === 'Mobile Banking') {
                    $mfc = (float) $order->total_paid_amount;
                }
            }

            $cashAmount += (!$paymentMethod || $paymentMethod === 'Cash') ? $cash : 0;
            $cardAmount += (!$paymentMethod || $paymentMethod === 'Card') ? $card : 0;
            $mfcAmount += (!$paymentMethod || $paymentMethod === 'Mobile Banking') ? $mfc : 0;

            if ($cash > 0 && (!$paymentMethod || $paymentMethod === 'Cash')) $cashOrders++;
            if ($card > 0 && (!$paymentMethod || $paymentMethod === 'Card')) $cardOrders++;
            if ($mfc > 0 && (!$paymentMethod || $paymentMethod === 'Mobile Banking')) $mfcOrders++;
        }

        $totalCollected = $cashAmount + $cardAmount + $mfcAmount;

        $paymentRows = [];
        if (!$paymentMethod || $paymentMethod == 'Cash') {
            $paymentRows[] = ['label' => 'Cash', 'icon' => 'bi-cash-coin', 'amount' => $cashAmount, 'orders_count' => $cashOrders, 'percentage' => $totalCollected > 0 ? ($cashAmount / $totalCollected) * 100 : 0];
        }
        if (!$paymentMethod || $paymentMethod == 'Card') {
            $paymentRows[] = ['label' => 'Card', 'icon' => 'bi-credit-card', 'amount' => $cardAmount, 'orders_count' => $cardOrders, 'percentage' => $totalCollected > 0 ? ($cardAmount / $totalCollected) * 100 : 0];
        }
        if (!$paymentMethod || $paymentMethod == 'Mobile Banking') {
            $paymentRows[] = ['label' => 'Mobile Banking / MFC', 'icon' => 'bi-phone', 'amount' => $mfcAmount, 'orders_count' => $mfcOrders, 'percentage' => $totalCollected > 0 ? ($mfcAmount / $totalCollected) * 100 : 0];
        }

        $paymentOrders = Order::with(['customer', 'table'])
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($paymentMethod, fn($q) => $this->applyPaymentCollectionFilter($q, $paymentMethod))
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->appends($request->query());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.payment_table_rows', compact('paymentOrders'))->render(),
                'cards' => view('admin.reports.partials.payment_cards', compact('paymentRows', 'totalCollected'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $paymentOrders])->render()
            ]);
        }

        return view('admin.reports.payment_type_sales', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'paymentMethod', 'yearOptions',
            'paymentRows', 'totalCollected', 'paymentOrders'
        ));
    }

    /** ৩. ফুড ওয়াইজ সেলস রিপোর্ট */
    public function foodSales(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $foodRows = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->select(
                'order_details.product_id',
                'order_details.product_name',
                DB::raw('SUM(order_details.quantity) as total_qty'),
                DB::raw('COUNT(DISTINCT order_details.order_id) as orders_count'),
                DB::raw('SUM(order_details.subtotal) as total_sales'),
                DB::raw('SUM(order_details.product_discount_amount) as product_discount'),
                DB::raw('SUM(order_details.subtotal - order_details.product_discount_amount) as net_sales')
            )
            ->groupBy('order_details.product_id', 'order_details.product_name')
            ->orderByDesc('total_qty')
            ->paginate(20)
            ->appends($request->query());

        $baseFoodQuery = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate]);

        $totalFoodQty = (clone $baseFoodQuery)->sum('order_details.quantity');
        $totalFoodSales = (float) (clone $baseFoodQuery)->sum('order_details.subtotal');
        $totalProductDiscount = (float) (clone $baseFoodQuery)->sum('order_details.product_discount_amount');
        $totalNetFoodSales = max(0, $totalFoodSales - $totalProductDiscount);

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.food_table_rows', compact('foodRows'))->render(),
                'qty' => number_format($totalFoodQty),
                'sales' => '৳' . number_format($totalFoodSales, 2),
                'product_discount' => '৳' . number_format($totalProductDiscount, 2),
                'net_sales' => '৳' . number_format($totalNetFoodSales, 2),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $foodRows])->render()
            ]);
        }

        return view('admin.reports.food_sales', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'foodRows', 'totalFoodQty', 'totalFoodSales', 'totalProductDiscount', 'totalNetFoodSales'
        ));
    }

    private function salesOrderExportRows(string $filterType, int $year, Carbon $startDate, Carbon $endDate)
    {
        $query = Order::where('status', 'Completed')->whereBetween('created_at', [$startDate, $endDate]);
        $periodRows = [];

        if ($filterType === 'year') {
            $sales = (clone $query)
                ->select(DB::raw('MONTH(created_at) as m'), DB::raw('YEAR(created_at) as y'), DB::raw('SUM(grand_total) as total_sale'), DB::raw('SUM(discount_amount) as total_discount'), DB::raw('SUM(product_discount_amount) as total_product_discount'), DB::raw('COUNT(id) as total_order'))
                ->groupBy('y', 'm')
                ->get();

            for ($m = 1; $m <= 12; $m++) {
                $carbonObj = Carbon::create($year, $m, 1);
                $row = $sales->where('m', $m)->first();
                $periodRows[] = [
                    'period' => $carbonObj->format('M Y'),
                    'total_sale' => $row ? (float) $row->total_sale : 0,
                    'total_discount' => $row ? (float) $row->total_discount : 0,
                    'total_product_discount' => $row ? (float) $row->total_product_discount : 0,
                    'total_order' => $row ? (int) $row->total_order : 0,
                ];
            }
        } else {
            $sales = (clone $query)
                ->select(DB::raw('DATE(created_at) as d'), DB::raw('SUM(grand_total) as total_sale'), DB::raw('SUM(discount_amount) as total_discount'), DB::raw('SUM(product_discount_amount) as total_product_discount'), DB::raw('COUNT(id) as total_order'))
                ->groupBy('d')
                ->get();

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateStr = $date->format('Y-m-d');
                $row = $sales->where('d', $dateStr)->first();
                $periodRows[] = [
                    'period' => $date->format('d/m/Y'),
                    'total_sale' => $row ? (float) $row->total_sale : 0,
                    'total_discount' => $row ? (float) $row->total_discount : 0,
                    'total_product_discount' => $row ? (float) $row->total_product_discount : 0,
                    'total_order' => $row ? (int) $row->total_order : 0,
                ];
            }
        }

        return collect($periodRows);
    }

    private function paymentExportRows(Carbon $startDate, Carbon $endDate, ?string $paymentMethod)
    {
        $ordersQuery = Order::with(['customer', 'table'])
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($paymentMethod) {
            $this->applyPaymentCollectionFilter($ordersQuery, $paymentMethod);
        }

        return $ordersQuery->orderBy('id', 'desc')->get()->map(function ($order) use ($paymentMethod) {
            $cashAmount = (float) ($order->paid_in_cash ?? 0);
            $cardAmount = (float) ($order->paid_in_card ?? 0);
            $mfcAmount = (float) ($order->paid_in_mfc ?? 0);

            // Legacy order fallback: old records may have only payment_type + total_paid_amount.
            if (($cashAmount + $cardAmount + $mfcAmount) <= 0 && (float) ($order->total_paid_amount ?? 0) > 0) {
                if ($order->payment_type === 'Cash') {
                    $cashAmount = (float) $order->total_paid_amount;
                } elseif ($order->payment_type === 'Card') {
                    $cardAmount = (float) $order->total_paid_amount;
                } elseif ($order->payment_type === 'Mobile Banking') {
                    $mfcAmount = (float) $order->total_paid_amount;
                }
            }

            if ($paymentMethod === 'Cash') {
                $paymentText = 'Cash';
                $rowTotal = $cashAmount;
            } elseif ($paymentMethod === 'Card') {
                $paymentText = 'Card';
                $rowTotal = $cardAmount;
            } elseif ($paymentMethod === 'Mobile Banking') {
                $paymentText = 'Mobile Banking';
                $rowTotal = $mfcAmount;
            } else {
                $paymentParts = [];
                if ($cashAmount > 0) $paymentParts[] = 'Cash';
                if ($cardAmount > 0) $paymentParts[] = 'Card';
                if ($mfcAmount > 0) $paymentParts[] = 'Mobile Banking';

                $paymentText = count($paymentParts) > 0
                    ? implode(' + ', $paymentParts)
                    : ($order->payment_type ?? 'N/A');

                $rowTotal = (float) ($order->total_paid_amount ?? ($cashAmount + $cardAmount + $mfcAmount));
            }

            return [
                'order_number' => $order->order_number,
                'date' => optional($order->created_at)->format('d M, h:i A'),
                'customer' => optional($order->customer)->name ?? 'Walk-in',
                'table' => optional($order->table)->table_number ?? 'Takeaway',
                'other_discount' => (float) ($order->discount_amount ?? 0),
                'product_discount' => (float) ($order->product_discount_amount ?? 0),
                'payment_type' => $paymentText,
                'cash' => $cashAmount,
                'card' => $cardAmount,
                'mfc' => $mfcAmount,
                'total_paid' => $rowTotal,
            ];
        });
    }

    private function foodExportRows(Carbon $startDate, Carbon $endDate)
    {
        return OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->select(
                'order_details.product_id',
                'order_details.product_name',
                DB::raw('SUM(order_details.quantity) as total_qty'),
                DB::raw('COUNT(DISTINCT order_details.order_id) as orders_count'),
                DB::raw('SUM(order_details.subtotal) as total_sales'),
                DB::raw('SUM(order_details.product_discount_amount) as product_discount'),
                DB::raw('SUM(order_details.subtotal - order_details.product_discount_amount) as net_sales')
            )
            ->groupBy('order_details.product_id', 'order_details.product_name')
            ->orderByDesc('total_qty')
            ->get();
    }

    private function reportFileName(string $report, string $extension): string
    {
        $name = match ($report) {
            'payment_type_sales' => 'payment-type-wise-sales',
            'food_sales' => 'food-wise-sales',
            'complimentary_orders' => 'complimentary-order-report',
            default => 'sales-order-report',
        };

        return $name . '-' . now()->format('Y-m-d-His') . '.' . $extension;
    }

   private function exportViewData(Request $request): array
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $report = $request->get('report', 'sales_order');
        if (!in_array($report, ['sales_order', 'payment_type_sales', 'food_sales', 'complimentary_orders'], true)) {
            $report = 'sales_order';
        }

        $periodTotalSale = 0;
        $periodTotalDiscount = 0;
        $periodTotalProductDiscount = 0;
        $periodTotalOrder = 0;

        if ($report === 'payment_type_sales') {
            $dataRows = $this->paymentExportRows($startDate, $endDate, $paymentMethod);
        } elseif ($report === 'food_sales') {
            $dataRows = $this->foodExportRows($startDate, $endDate);
        } elseif ($report === 'complimentary_orders') {
            $complimentaryQuery = Order::with(['customer', 'table', 'orderDetails'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $this->applyComplimentaryOrderFilter($complimentaryQuery);
            $dataRows = $complimentaryQuery->orderByDesc('id')->get();

            $periodTotalSale = $dataRows->sum('grand_total');
            $periodTotalOrder = $dataRows->count();
        } else {
            // Updated for exact blade table matching
            $dataRows = Order::with(['customer', 'table', 'orderDetails'])
                ->where('status', 'Completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('id', 'desc')
                ->get();

            $periodTotalSale = $dataRows->sum('grand_total');
            $periodTotalDiscount = $dataRows->sum('discount_amount');
            $periodTotalProductDiscount = $dataRows->sum('product_discount_amount');
            $periodTotalOrder = $dataRows->count();
        }

        return compact('report', 'dataRows', 'startDate', 'endDate', 'periodTotalSale', 'periodTotalDiscount', 'periodTotalProductDiscount', 'periodTotalOrder') + [
            'restaurant' => RestaurantSetting::first(),
        ];
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeRequestedReportExport($request);
        // 1. বড় HTML স্ট্রিং পার্স করার জন্য লিমিটগুলো বাড়িয়ে দিন
        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        // 2. এরপর আপনার আগের কোডগুলো থাকবে
        $viewData = $this->exportViewData($request);
        $html = view('admin.reports.pdf_export', $viewData)->render();
        $fileName = $this->reportFileName($viewData['report'], 'pdf');
        $tempDir = storage_path('app/mpdf-temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => in_array($viewData['report'], ['payment_type_sales', 'sales_order', 'complimentary_orders'], true) ? 'L' : 'P',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetTitle($fileName);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeRequestedReportExport($request);
        $viewData = $this->exportViewData($request);
        $html = view('admin.reports.pdf_export', $viewData)->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->reportFileName($viewData['report'], 'xls') . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportCsv(Request $request)
    {
        return $this->exportExcel($request);
    }

}
