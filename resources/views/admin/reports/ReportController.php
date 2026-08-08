<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RestaurantSetting;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class ReportController extends Controller
{
    /**
     * Common filter resolver for all report pages.
     * Default: current year data will be active.
     */
    private function resolveReportFilters(Request $request): array
    {
        $currentYear = Carbon::now()->year;
        $filterType = $request->filter_type ?: 'year';
        $year = (int) ($request->year ?: $currentYear);
        $month = (int) ($request->month ?: Carbon::now()->month);

        if ($filterType === 'date' && $request->start_date && $request->end_date) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
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

    /** Apply payment type filter using the saved POS payment columns. */
    private function applyPaymentCollectionFilter($query, ?string $paymentMethod)
    {
        if (!$paymentMethod) {
            return $query;
        }

        return match ($paymentMethod) {
            'Cash' => $query->where('paid_in_cash', '>', 0),
            'Card' => $query->where('paid_in_card', '>', 0),
            'Mobile Banking' => $query->where('paid_in_mfc', '>', 0),
            'Split' => $query->where('payment_type', 'Split'),
            default => $query->where('payment_type', $paymentMethod),
        };
    }

    /** Create paginator from an in-memory collection. */
    private function paginateCollection($items, Request $request, int $perPage = 15): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage() ?: 1;
        $items = collect($items)->values();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /** Sales & Order period summary rows. */
    private function buildSalesOrderPeriodRows(string $filterType, int $year, Carbon $startDate, Carbon $endDate, Request $request): array
    {
        if ($filterType === 'year') {
            $sales = Order::select(DB::raw('MONTH(created_at) as period_key'), DB::raw('SUM(grand_total) as total_sale'))
                ->where('status', 'Completed')
                ->whereYear('created_at', $year)
                ->groupBy('period_key')
                ->pluck('total_sale', 'period_key');

            $orders = Order::select(DB::raw('MONTH(created_at) as period_key'), DB::raw('COUNT(*) as total_order'))
                ->whereYear('created_at', $year)
                ->groupBy('period_key')
                ->pluck('total_order', 'period_key');

            $rows = collect(range(1, 12))->map(function ($monthNo) use ($year, $sales, $orders) {
                return [
                    'period' => Carbon::create($year, $monthNo, 1)->format('M Y'),
                    'total_sale' => (float) ($sales[$monthNo] ?? 0),
                    'total_order' => (int) ($orders[$monthNo] ?? 0),
                ];
            });
        } else {
            $sales = Order::select(DB::raw('DATE(created_at) as period_key'), DB::raw('SUM(grand_total) as total_sale'))
                ->where('status', 'Completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('period_key')
                ->pluck('total_sale', 'period_key');

            $orders = Order::select(DB::raw('DATE(created_at) as period_key'), DB::raw('COUNT(*) as total_order'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('period_key')
                ->pluck('total_order', 'period_key');

            $rows = collect();
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $key = $date->format('Y-m-d');
                $rows->push([
                    'period' => $date->format('j/m/Y'),
                    'total_sale' => (float) ($sales[$key] ?? 0),
                    'total_order' => (int) ($orders[$key] ?? 0),
                ]);
            }
        }

        $totalSale = (float) $rows->sum('total_sale');
        $totalOrder = (int) $rows->sum('total_order');

        return [
            'periodRows' => $this->paginateCollection($rows, $request, 15),
            'allPeriodRows' => $rows->values(),
            'periodTotalSale' => $totalSale,
            'periodTotalOrder' => $totalOrder,
        ];
    }

    /** Sales & Order Report. */
    public function salesOrder(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $completedOrdersQuery = Order::where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRevenue = (clone $completedOrdersQuery)->sum('grand_total');
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $avgOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;
        $uniqueCustomers = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        $periodSummary = $this->buildSalesOrderPeriodRows($filterType, $year, $startDate, $endDate, $request);
        extract($periodSummary);

        return view('admin.reports.sales_order', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'totalRevenue', 'totalOrders', 'avgOrderValue', 'uniqueCustomers',
            'periodRows', 'periodTotalSale', 'periodTotalOrder'
        ));
    }

    public function index(Request $request)
    {
        return $this->salesOrder($request);
    }

    /** Payment Type Wise Sales Report. */
    public function paymentTypeSales(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $ordersQuery = Order::where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->applyPaymentCollectionFilter($ordersQuery, $paymentMethod);
        $orders = $ordersQuery->get();

        $cashAmount = (float) $orders->sum('paid_in_cash');
        $cardAmount = (float) $orders->sum('paid_in_card');
        $mfcAmount = (float) $orders->sum('paid_in_mfc');
        $totalCollected = $cashAmount + $cardAmount + $mfcAmount;

        $paymentRows = collect([
            ['label' => 'Cash', 'icon' => 'bi-cash-coin', 'amount' => $cashAmount, 'orders_count' => $orders->filter(fn ($order) => (float) $order->paid_in_cash > 0)->count(), 'percentage' => $totalCollected > 0 ? ($cashAmount / $totalCollected) * 100 : 0],
            ['label' => 'Card', 'icon' => 'bi-credit-card', 'amount' => $cardAmount, 'orders_count' => $orders->filter(fn ($order) => (float) $order->paid_in_card > 0)->count(), 'percentage' => $totalCollected > 0 ? ($cardAmount / $totalCollected) * 100 : 0],
            ['label' => 'Mobile Banking / MFC', 'icon' => 'bi-phone', 'amount' => $mfcAmount, 'orders_count' => $orders->filter(fn ($order) => (float) $order->paid_in_mfc > 0)->count(), 'percentage' => $totalCollected > 0 ? ($mfcAmount / $totalCollected) * 100 : 0],
        ]);

        $paymentOrdersQuery = Order::with(['customer', 'table'])
            ->where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('paid_in_cash', '>', 0)
                    ->orWhere('paid_in_card', '>', 0)
                    ->orWhere('paid_in_mfc', '>', 0);
            });
        $this->applyPaymentCollectionFilter($paymentOrdersQuery, $paymentMethod);

        $paymentOrders = $paymentOrdersQuery->orderBy('id', 'desc')
            ->paginate(15)
            ->appends($request->query());

        return view('admin.reports.payment_type_sales', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'paymentMethod', 'yearOptions',
            'paymentRows', 'totalCollected', 'paymentOrders'
        ));
    }

    /** Food Wise Sales Report. */
    public function foodSales(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $foodRows = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->select('order_details.product_id', 'order_details.product_name', DB::raw('SUM(order_details.quantity) as total_qty'), DB::raw('COUNT(DISTINCT order_details.order_id) as orders_count'), DB::raw('SUM(order_details.subtotal) as total_sales'))
            ->groupBy('order_details.product_id', 'order_details.product_name')
            ->orderByDesc('total_qty')
            ->paginate(20)
            ->appends($request->query());

        $totalFoodQty = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->sum('order_details.quantity');

        $totalFoodSales = OrderDetail::query()
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->sum('order_details.subtotal');

        return view('admin.reports.food_sales', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'foodRows', 'totalFoodQty', 'totalFoodSales'
        ));
    }

    public function exportPdf(Request $request)
    {
        $report = $request->report ?: 'sales_order';
        $filters = $this->resolveReportFilters($request);
        extract($filters);
        $restaurant = RestaurantSetting::first();
        $periodTotalSale = 0;
        $periodTotalOrder = 0;

        if ($report === 'payment_type_sales') {
            $ordersQuery = Order::where('status', 'Completed')->whereBetween('created_at', [$startDate, $endDate]);
            $this->applyPaymentCollectionFilter($ordersQuery, $paymentMethod);
            $orders = $ordersQuery->get();
            $cashAmount = (float) $orders->sum('paid_in_cash');
            $cardAmount = (float) $orders->sum('paid_in_card');
            $mfcAmount = (float) $orders->sum('paid_in_mfc');
            $totalCollected = $cashAmount + $cardAmount + $mfcAmount;
            $dataRows = collect([
                ['label' => 'Cash', 'orders_count' => $orders->filter(fn ($o) => (float) $o->paid_in_cash > 0)->count(), 'amount' => $cashAmount, 'percentage' => $totalCollected > 0 ? ($cashAmount / $totalCollected) * 100 : 0],
                ['label' => 'Card', 'orders_count' => $orders->filter(fn ($o) => (float) $o->paid_in_card > 0)->count(), 'amount' => $cardAmount, 'percentage' => $totalCollected > 0 ? ($cardAmount / $totalCollected) * 100 : 0],
                ['label' => 'Mobile Banking / MFC', 'orders_count' => $orders->filter(fn ($o) => (float) $o->paid_in_mfc > 0)->count(), 'amount' => $mfcAmount, 'percentage' => $totalCollected > 0 ? ($mfcAmount / $totalCollected) * 100 : 0],
            ]);
        } elseif ($report === 'food_sales') {
            $dataRows = OrderDetail::query()
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.status', 'Completed')
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select('order_details.product_name', DB::raw('SUM(order_details.quantity) as total_qty'), DB::raw('COUNT(DISTINCT order_details.order_id) as orders_count'), DB::raw('SUM(order_details.subtotal) as total_sales'))
                ->groupBy('order_details.product_name')
                ->orderByDesc('total_qty')
                ->get();
        } else {
            $summary = $this->buildSalesOrderPeriodRows($filterType, $year, $startDate, $endDate, $request);
            $dataRows = $summary['allPeriodRows'];
            $periodTotalSale = $summary['periodTotalSale'];
            $periodTotalOrder = $summary['periodTotalOrder'];
        }

        $html = view('admin.reports.pdf_export', compact('restaurant', 'startDate', 'endDate', 'report', 'dataRows', 'periodTotalSale', 'periodTotalOrder'))->render();
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'L', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 16, 'margin_bottom' => 16, 'margin_header' => 9, 'margin_footer' => 9]);
        $mpdf->SetTitle('Report - ' . now()->format('d M, Y'));
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('Report_' . now()->format('Y_m_d') . '.pdf', 'S'), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Report_' . now()->format('Y_m_d') . '.pdf"']);
    }

    public function exportCsv(Request $request)
    {
        $report = $request->report ?: 'sales_order';
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $fileName = 'Report_' . $report . '_' . now()->format('Y_m_d') . '.csv';
        $headers = ['Content-type' => 'text/csv', 'Content-Disposition' => "attachment; filename=$fileName", 'Pragma' => 'no-cache', 'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0', 'Expires' => '0'];

        $callback = function () use ($report, $startDate, $endDate, $paymentMethod, $filterType, $year, $request) {
            $file = fopen('php://output', 'w');

            if ($report === 'payment_type_sales') {
                fputcsv($file, ['Payment Type', 'Order Count', 'Amount', 'Percentage']);
                $ordersQuery = Order::where('status', 'Completed')->whereBetween('created_at', [$startDate, $endDate]);
                $this->applyPaymentCollectionFilter($ordersQuery, $paymentMethod);
                $orders = $ordersQuery->get();
                $cashAmount = (float) $orders->sum('paid_in_cash');
                $cardAmount = (float) $orders->sum('paid_in_card');
                $mfcAmount = (float) $orders->sum('paid_in_mfc');
                $total = $cashAmount + $cardAmount + $mfcAmount;
                $rows = [['Cash', $orders->filter(fn ($o) => (float) $o->paid_in_cash > 0)->count(), $cashAmount], ['Card', $orders->filter(fn ($o) => (float) $o->paid_in_card > 0)->count(), $cardAmount], ['Mobile Banking / MFC', $orders->filter(fn ($o) => (float) $o->paid_in_mfc > 0)->count(), $mfcAmount]];
                foreach ($rows as $row) {
                    fputcsv($file, [$row[0], $row[1], $row[2], $total > 0 ? round(($row[2] / $total) * 100, 2) . '%' : '0%']);
                }
            } elseif ($report === 'food_sales') {
                fputcsv($file, ['Food Item', 'Total Qty', 'Order Count', 'Total Sales']);
                $rows = OrderDetail::query()
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->where('orders.status', 'Completed')
                    ->whereBetween('orders.created_at', [$startDate, $endDate])
                    ->select('order_details.product_name', DB::raw('SUM(order_details.quantity) as total_qty'), DB::raw('COUNT(DISTINCT order_details.order_id) as orders_count'), DB::raw('SUM(order_details.subtotal) as total_sales'))
                    ->groupBy('order_details.product_name')
                    ->orderByDesc('total_qty')
                    ->get();
                foreach ($rows as $row) {
                    fputcsv($file, [$row->product_name, $row->total_qty, $row->orders_count, $row->total_sales]);
                }
            } else {
                fputcsv($file, ['Period', 'Total Sale', 'Total Order']);
                $summary = $this->buildSalesOrderPeriodRows($filterType, $year, $startDate, $endDate, $request);
                foreach ($summary['allPeriodRows'] as $row) {
                    fputcsv($file, [$row['period'], $row['total_sale'], $row['total_order']]);
                }
                fputcsv($file, ['Total', $summary['periodTotalSale'], $summary['periodTotalOrder']]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
