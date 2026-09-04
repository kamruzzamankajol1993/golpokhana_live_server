<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderKot;
use App\Models\PosSession;
use App\Models\RestaurantSetting;
use App\Models\User;
use App\Models\DeliveryPartner;
use App\Exports\ArrayReportExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:report-sales-order-view', ['only' => ['salesOrder']]);
        $this->middleware('permission:report-delivery-view', ['only' => ['deliveryReport', 'deliveryReportPdf', 'deliveryReportExcel']]);
        $this->middleware('permission:report-kot-view', ['only' => ['kotReport', 'kotReportPdf', 'kotReportExcel']]);
        $this->middleware('permission:report-pos-session-view', ['only' => ['posSessionReport', 'posSessionReportPdf', 'posSessionReportExcel']]);
        $this->middleware('permission:report-due-view', ['only' => ['dueReport', 'dueReportPdf', 'dueReportExcel']]);
        $this->middleware('permission:report-complimentary-orders-view', ['only' => ['complimentaryOrders']]);
        $this->middleware('permission:report-payment-type-sales-view', ['only' => ['paymentTypeSales']]);
        $this->middleware('permission:report-food-sales-view', ['only' => ['foodSales']]);
        $this->middleware('permission:report-waiter-daily-orders-view', ['only' => ['waiterDailyOrders', 'waiterDailyOrdersPdf', 'waiterDailyOrdersExcel']]);
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
            'delivery' => 'report-delivery-view',
            'kots' => 'report-kot-view',
            'pos_sessions' => 'report-pos-session-view',
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

        if ($filterType === 'all') {
            // Keep harmless current-year date values for shared view fields; reports that
            // support All can intentionally skip applying a date range to their query.
            $startDate = Carbon::create($year, 1, 1)->startOfYear()->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfYear()->endOfDay();
        } elseif ($filterType === 'date' && $request->start_date && $request->end_date) {
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


    /** Delivery Report partner selector sourced from delivery_partners table. */
    private function resolveDeliveryReportPartner(Request $request): array
    {
        $deliveryPartners = DeliveryPartner::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $requested = trim((string) $request->get('delivery_partner', 'all'));
        $selectedDeliveryPartner = null;
        $selectedDeliveryPartnerId = 'all';

        if ($requested !== '' && strtolower($requested) !== 'all' && ctype_digit($requested)) {
            $selectedDeliveryPartner = $deliveryPartners->firstWhere('id', (int) $requested);
            if ($selectedDeliveryPartner) {
                $selectedDeliveryPartnerId = (string) $selectedDeliveryPartner->id;
            }
        }

        return compact('deliveryPartners', 'selectedDeliveryPartner', 'selectedDeliveryPartnerId');
    }

    /** Apply stable ID filtering and also support legacy rows that stored partner ID/name in delivery_partner. */
    private function applyDeliveryReportPartnerFilter($query, ?DeliveryPartner $partner)
    {
        if (!$partner) {
            return $query;
        }

        $partnerId = (string) $partner->id;
        $partnerName = strtolower(trim((string) $partner->name));

        return $query->where(function ($partnerQuery) use ($partner, $partnerId, $partnerName) {
            $partnerQuery->where('delivery_partner_id', $partner->id)
                ->orWhere('delivery_partner', $partnerId)
                ->orWhereRaw('LOWER(TRIM(delivery_partner)) = ?', [$partnerName]);
        });
    }

    /** Common query used by Delivery screen, PDF and Excel. */
    private function deliveryReportQuery(Carbon $startDate, Carbon $endDate, ?DeliveryPartner $partner = null)
    {
        $query = Order::with(['customer', 'table', 'waiter', 'user', 'deliveryPartner'])
            ->whereIn('order_type', ['Delivery', 'delivery'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        return $this->applyDeliveryReportPartnerFilter($query, $partner);
    }

    private function deliveryPartnerLabelForOrder($order): string
    {
        if ($order->deliveryPartner) {
            return (string) $order->deliveryPartner->name;
        }

        $raw = trim((string) ($order->delivery_partner ?? ''));
        if ($raw !== '' && ctype_digit($raw)) {
            $name = DeliveryPartner::query()->whereKey((int) $raw)->value('name');
            if ($name) return (string) $name;
        }

        $legacy = [
            'inhouse' => 'In-house Delivery',
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];
        return $legacy[strtolower($raw)] ?? ($raw !== '' ? $raw : 'N/A');
    }

    /** Delivery Report — show only Delivery order types. */
    public function deliveryReport(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);
        $partnerFilter = $this->resolveDeliveryReportPartner($request);
        extract($partnerFilter);

        $baseQuery = $this->deliveryReportQuery($startDate, $endDate, $selectedDeliveryPartner);

        $totalOrders = (clone $baseQuery)->count();
        $completedOrders = (clone $baseQuery)->where('status', 'Completed')->count();
        $totalValue = (float) (clone $baseQuery)->sum('grand_total');
        $totalDue = (float) (clone $baseQuery)->sum('due');
        $selectedDeliveryPartnerLabel = $selectedDeliveryPartner?->name ?? 'ALL';

        $orders = (clone $baseQuery)
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->appends($request->query());

        $deliveryPartnerNameMap = $deliveryPartners->pluck('name', 'id');

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.delivery_table_rows', compact('orders', 'deliveryPartnerNameMap'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $orders])->render(),
                'summary' => [
                    'orders' => $totalOrders,
                    'completed' => $completedOrders,
                    'value' => '৳' . number_format($totalValue, 0),
                    'due' => '৳' . number_format($totalDue, 0),
                    'partner_label' => $selectedDeliveryPartnerLabel,
                ],
            ]);
        }

        return view('admin.reports.delivery_report', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'totalOrders', 'completedOrders', 'totalValue', 'totalDue', 'orders',
            'deliveryPartners', 'selectedDeliveryPartner', 'selectedDeliveryPartnerId', 'selectedDeliveryPartnerLabel',
            'deliveryPartnerNameMap'
        ));
    }

    /**
     * Due Report — all outstanding dues by default.
     * Selecting a delivery partner narrows the list to that partner only;
     * the default All option intentionally includes dine-in/takeaway/non-partner dues too.
     */
    public function dueReport(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $allowedPartners = ['inhouse', 'dine_in', 'takeaway', 'foodpanda', 'foodi', 'pathao_food'];
        $deliveryPartner = strtolower(trim((string) $request->input('delivery_partner', '')));
        if (!in_array($deliveryPartner, $allowedPartners, true)) {
            $deliveryPartner = '';
        }

        $baseQuery = Order::with(['customer', 'table', 'waiter', 'user'])
            ->where('due', '>', 0);

        if ($filterType !== 'all') {
            $baseQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($deliveryPartner === 'inhouse') {
            $baseQuery->whereIn('order_type', ['Delivery', 'delivery'])
                ->where(function ($partnerQuery) {
                    $partnerQuery->where('delivery_partner', 'inhouse')
                        ->orWhereNull('delivery_partner')
                        ->orWhere('delivery_partner', '');
                });
        } elseif ($deliveryPartner === 'dine_in') {
            $baseQuery->whereIn('order_type', ['Dine-In', 'dine-in', 'dine_in', 'Dine In', 'dine in', 'DineIn', 'dinein']);
        } elseif ($deliveryPartner === 'takeaway') {
            $baseQuery->whereIn('order_type', ['Takeaway', 'takeaway', 'Take Away', 'take away', 'take_away', 'Take-Away', 'take-away']);
        } elseif ($deliveryPartner !== '') {
            $baseQuery->whereIn('order_type', ['Delivery', 'delivery'])
                ->where('delivery_partner', $deliveryPartner);
        }

        $totalOrders = (clone $baseQuery)->count();
        $totalGrand = (float) (clone $baseQuery)->sum('grand_total');
        $totalPaid = (float) (clone $baseQuery)->sum('total_paid_amount');
        $totalDue = (float) (clone $baseQuery)->sum('due');

        $orders = (clone $baseQuery)
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        $deliveryPartnerOptions = [
            'inhouse' => 'In-house Delivery',
            'dine_in' => 'Dine-In',
            'takeaway' => 'Takeaway',
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.due_table_rows', compact('orders'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $orders])->render(),
                'summary' => [
                    'orders' => $totalOrders,
                    'grand' => '৳' . number_format($totalGrand, 0),
                    'paid' => '৳' . number_format($totalPaid, 0),
                    'due' => '৳' . number_format($totalDue, 0),
                ],
            ]);
        }

        return view('admin.reports.due_report', compact(
            'filterType', 'year', 'month', 'startDate', 'endDate', 'yearOptions',
            'deliveryPartner', 'deliveryPartnerOptions', 'totalOrders', 'totalGrand',
            'totalPaid', 'totalDue', 'orders'
        ));
    }

    /** Open the filtered Due Report as an inline PDF in a new browser tab. */
    public function dueReportPdf(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);

        $allowedPartners = ['inhouse', 'dine_in', 'takeaway', 'foodpanda', 'foodi', 'pathao_food'];
        $deliveryPartner = strtolower(trim((string) $request->input('delivery_partner', '')));
        if (!in_array($deliveryPartner, $allowedPartners, true)) {
            $deliveryPartner = '';
        }

        $query = Order::with(['customer', 'table', 'waiter', 'user'])
            ->where('due', '>', 0);

        if ($filterType !== 'all') {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($deliveryPartner === 'inhouse') {
            $query->whereIn('order_type', ['Delivery', 'delivery'])
                ->where(function ($partnerQuery) {
                    $partnerQuery->where('delivery_partner', 'inhouse')
                        ->orWhereNull('delivery_partner')
                        ->orWhere('delivery_partner', '');
                });
        } elseif ($deliveryPartner === 'dine_in') {
            $query->whereIn('order_type', ['Dine-In', 'dine-in', 'dine_in', 'Dine In', 'dine in', 'DineIn', 'dinein']);
        } elseif ($deliveryPartner === 'takeaway') {
            $query->whereIn('order_type', ['Takeaway', 'takeaway', 'Take Away', 'take away', 'take_away', 'Take-Away', 'take-away']);
        } elseif ($deliveryPartner !== '') {
            $query->whereIn('order_type', ['Delivery', 'delivery'])
                ->where('delivery_partner', $deliveryPartner);
        }

        $orders = $query->orderByDesc('id')->get();
        $totalOrders = $orders->count();
        $totalGrand = (float) $orders->sum('grand_total');
        $totalPaid = (float) $orders->sum('total_paid_amount');
        $totalDue = (float) $orders->sum('due');
        $restaurant = RestaurantSetting::first();

        $deliveryPartnerOptions = [
            'inhouse' => 'In-house Delivery',
            'dine_in' => 'Dine-In',
            'takeaway' => 'Takeaway',
            'foodpanda' => 'Foodpanda',
            'foodi' => 'Foodi',
            'pathao_food' => 'Pathao Food',
        ];
        $deliveryPartnerLabel = $deliveryPartner === ''
            ? 'All (including non-partner dues)'
            : ($deliveryPartnerOptions[$deliveryPartner] ?? $deliveryPartner);
        $periodLabel = $filterType === 'all'
            ? 'All Dates'
            : $startDate->format('d M Y') . ' to ' . $endDate->format('d M Y');

        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        $html = view('admin.reports.due_pdf', compact(
            'orders', 'periodLabel', 'deliveryPartnerLabel', 'totalOrders',
            'totalGrand', 'totalPaid', 'totalDue', 'restaurant'
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

        $fileName = 'Due_Report_' . ($filterType === 'all'
            ? 'All_Dates'
            : $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d')) . '.pdf';
        $mpdf->SetTitle($fileName);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }



    public function dueReportExcel(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);
        $allowedPartners = ['inhouse', 'dine_in', 'takeaway', 'foodpanda', 'foodi', 'pathao_food'];
        $deliveryPartner = strtolower(trim((string) $request->input('delivery_partner', '')));
        if (!in_array($deliveryPartner, $allowedPartners, true)) $deliveryPartner = '';

        $query = Order::with(['customer', 'table', 'waiter', 'user'])->where('due', '>', 0);
        if ($filterType !== 'all') $query->whereBetween('created_at', [$startDate, $endDate]);
        if ($deliveryPartner === 'inhouse') {
            $query->whereIn('order_type', ['Delivery', 'delivery'])->where(function ($q) {
                $q->where('delivery_partner', 'inhouse')->orWhereNull('delivery_partner')->orWhere('delivery_partner', '');
            });
        } elseif ($deliveryPartner === 'dine_in') {
            $query->whereIn('order_type', ['Dine-In', 'dine-in', 'dine_in', 'Dine In', 'dine in', 'DineIn', 'dinein']);
        } elseif ($deliveryPartner === 'takeaway') {
            $query->whereIn('order_type', ['Takeaway', 'takeaway', 'Take Away', 'take away', 'take_away', 'Take-Away', 'take-away']);
        } elseif ($deliveryPartner !== '') {
            $query->whereIn('order_type', ['Delivery', 'delivery'])->where('delivery_partner', $deliveryPartner);
        }

        $orders = $query->orderByDesc('id')->get();
        $headings = ['Order #', 'Date & Time', 'Customer', 'Order Type', 'Delivery Partner', 'Grand Total', 'Paid', 'Due', 'Payment', 'Status'];
        $rows = $orders->map(function ($order) {
            return [
                '#' . $order->order_number,
                optional($order->created_at)->format('d M Y h:i A'),
                optional($order->customer)->name ?? 'Walk-in',
                $order->order_type ?? 'N/A',
                strtolower((string) $order->order_type) === 'delivery' ? $this->deliveryPartnerLabelForOrder($order) : 'N/A',
                (float) ($order->grand_total ?? 0),
                (float) ($order->total_paid_amount ?? 0),
                (float) ($order->due ?? 0),
                $this->displayPaymentText($order),
                $order->status ?? 'N/A',
            ];
        })->all();
        return Excel::download(new ArrayReportExport($headings, $rows, 'Due Report'), 'due-report-' . now()->format('Y-m-d-His') . '.xlsx');
    }


    /** Open the filtered Delivery Report as an inline PDF in a new browser tab. */
    public function deliveryReportPdf(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);
        $partnerFilter = $this->resolveDeliveryReportPartner($request);
        extract($partnerFilter);

        $orders = $this->deliveryReportQuery($startDate, $endDate, $selectedDeliveryPartner)
            ->orderByDesc('id')
            ->get();

        $totalOrders = $orders->count();
        $completedOrders = $orders->where('status', 'Completed')->count();
        $totalValue = (float) $orders->sum('grand_total');
        $totalDue = (float) $orders->sum('due');
        $restaurant = RestaurantSetting::first();
        $selectedDeliveryPartnerLabel = $selectedDeliveryPartner?->name ?? 'ALL';
        $deliveryPartnerNameMap = $deliveryPartners->pluck('name', 'id');

        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        $html = view('admin.reports.delivery_pdf', compact(
            'orders', 'startDate', 'endDate', 'totalOrders', 'completedOrders',
            'totalValue', 'totalDue', 'restaurant', 'selectedDeliveryPartnerLabel', 'deliveryPartnerNameMap'
        ))->render();

        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);

        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'L',
            'margin_left' => 7, 'margin_right' => 7, 'margin_top' => 8, 'margin_bottom' => 8,
            'tempDir' => $tempDir, 'autoScriptToLang' => true, 'autoLangToFont' => true,
        ]);

        $partnerSlug = $selectedDeliveryPartner ? Str::slug($selectedDeliveryPartner->name, '_') : 'ALL';
        $fileName = 'Delivery_Report_' . ($selectedDeliveryPartner ? ('Partner-' . $selectedDeliveryPartner->id . '_' . $partnerSlug) : 'ALL')
            . '_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.pdf';
        $mpdf->SetTitle($fileName);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }



    public function deliveryReportExcel(Request $request)
    {
        $filters = $this->resolveReportFilters($request);
        extract($filters);
        $partnerFilter = $this->resolveDeliveryReportPartner($request);
        extract($partnerFilter);

        $orders = $this->deliveryReportQuery($startDate, $endDate, $selectedDeliveryPartner)
            ->orderByDesc('id')->get();

        $headings = ['Order #', 'Date & Time', 'Delivery Partner', 'Customer', 'Phone', 'Subtotal', 'VAT', 'Discount', 'Grand Total', 'Due', 'Payment', 'Status'];
        $rows = $orders->map(function ($order) {
            $discount = max(0, (float) ($order->product_discount_amount ?? 0)) + max(0, (float) ($order->discount_amount ?? 0));
            return [
                '#' . $order->order_number,
                optional($order->created_at)->format('d M Y h:i A'),
                $this->deliveryPartnerLabelForOrder($order),
                optional($order->customer)->name ?? 'Walk-in Customer',
                optional($order->customer)->phone ?? optional($order->customer)->mobile ?? 'N/A',
                (float) ($order->subtotal ?? 0),
                (float) ($order->vat_tax ?? 0),
                $discount,
                (float) ($order->grand_total ?? 0),
                max(0, (float) ($order->due ?? 0)),
                $this->displayPaymentText($order),
                $order->status ?? 'N/A',
            ];
        })->all();

        $partnerSlug = $selectedDeliveryPartner ? Str::slug($selectedDeliveryPartner->name, '_') : 'ALL';
        $fileName = 'Delivery_Report_' . ($selectedDeliveryPartner ? ('Partner-' . $selectedDeliveryPartner->id . '_' . $partnerSlug) : 'ALL')
            . '_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.xlsx';

        return Excel::download(new ArrayReportExport($headings, $rows, 'Delivery Report'), $fileName);
    }

    private function displayPaymentText($order): string
    {
        return $order instanceof Order
            ? $order->reportPaymentText(2, true)
            : (string) ($order->payment_type ?? 'N/A');
    }

    private function simpleReportPdfResponse(string $title, array $headings, array $rows, array $meta, string $fileName, string $format = 'A4', string $orientation = 'L')
    {
        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        @set_time_limit(300);

        $restaurant = RestaurantSetting::first();
        $html = view('admin.reports.simple_table_pdf', [
            'title' => $title,
            'subtitle' => $restaurant->name ?? $restaurant->restaurant_name ?? 'Restaurant',
            'headings' => $headings,
            'rows' => $rows,
            'meta' => $meta,
        ])->render();

        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $format, 'orientation' => $orientation,
            'margin_left' => 7, 'margin_right' => 7, 'margin_top' => 8, 'margin_bottom' => 8,
            'tempDir' => $tempDir, 'autoScriptToLang' => true, 'autoLangToFont' => true,
        ]);
        $mpdf->SetTitle($fileName);
        $mpdf->SetFooter('Generated: ' . now()->format('d M Y, h:i A') . '||Page {PAGENO} of {nbpg}');
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



    private function waiterDailyExportData(Request $request): array
    {
        $restaurant = RestaurantSetting::first();
        $businessDate = $this->defaultBusinessDate($restaurant);
        if ($request->filled('business_date')) {
            try { $businessDate = $this->parseReportDate($request->business_date)->startOfDay(); } catch (\Throwable $e) {}
        }
        [$windowStart, $windowEnd] = $this->resolveBusinessWindow($businessDate, $restaurant);

        $waiters = User::query()
            ->whereHas('roles', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['waiter']))
            ->select('id', 'name', 'user_id', 'first_name', 'last_name')
            ->orderBy('name')->orderBy('id')->get();
        $waiterIds = $waiters->pluck('id')->map(fn ($id) => (int) $id)->values();
        $selectedUserId = $request->filled('user_id') ? (int) $request->user_id : null;
        if ($selectedUserId && !$waiterIds->contains($selectedUserId)) $selectedUserId = null;

        $orders = Order::with(['user', 'table'])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereIn('user_id', $waiterIds->all())
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        $headings = ['Order #', 'Waiter User', 'Order Time', 'Table', 'Order Type', 'Status', 'Honored', 'Product Discount', 'Grand Total', 'Payment'];
        $rows = $orders->map(function ($order) {
            $userName = optional($order->user)->name ?: trim((optional($order->user)->first_name ?? '') . ' ' . (optional($order->user)->last_name ?? ''));
            return [
                $order->order_number,
                $userName ?: ('User #' . $order->user_id),
                optional($order->created_at)->format('d/m/Y h:i A'),
                optional($order->table)->table_number ?: 'N/A',
                $order->order_type ?: 'N/A',
                $order->status ?: 'N/A',
                (float) ($order->discount_amount ?? 0),
                (float) ($order->product_discount_amount ?? 0),
                (float) ($order->grand_total ?? 0),
                $this->displayPaymentText($order),
            ];
        })->all();
        $waiterName = $selectedUserId ? (optional($waiters->firstWhere('id', $selectedUserId))->name ?? ('User #' . $selectedUserId)) : 'All Waiters';
        $meta = [
            'Business Date' => $businessDate->format('d M Y'),
            'Business Window' => $windowStart->format('d M Y, h:i A') . ' - ' . $windowEnd->format('d M Y, h:i A'),
            'Waiter' => $waiterName,
            'Orders' => (string) $orders->count(),
            'Completed Sales' => number_format((float) $orders->filter(fn ($o) => strtolower((string) $o->status) === 'completed')->sum('grand_total'), 2),
        ];
        return [$headings, $rows, $meta];
    }

    public function waiterDailyOrdersPdf(Request $request)
    {
        [$headings, $rows, $meta] = $this->waiterDailyExportData($request);
        return $this->simpleReportPdfResponse('Waiter Daily Order Report', $headings, $rows, $meta, 'waiter-daily-orders-' . now()->format('Y-m-d-His') . '.pdf', 'A3', 'L');
    }

    public function waiterDailyOrdersExcel(Request $request)
    {
        [$headings, $rows] = $this->waiterDailyExportData($request);
        return Excel::download(new ArrayReportExport($headings, $rows, 'Waiter Daily Orders'), 'waiter-daily-orders-' . now()->format('Y-m-d-His') . '.xlsx');
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
        $cardProviderAmounts = [];
        $mfsProviderAmounts = [];

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
            if ($card > 0 && (!$paymentMethod || $paymentMethod === 'Card')) {
                $cardOrders++;
                $provider = trim((string) ($order->card_type ?? '')) ?: 'Unspecified';
                $cardProviderAmounts[$provider] = ($cardProviderAmounts[$provider] ?? 0) + $card;
            }
            if ($mfc > 0 && (!$paymentMethod || $paymentMethod === 'Mobile Banking')) {
                $mfcOrders++;
                $provider = trim((string) ($order->mfs_provider ?? '')) ?: 'Unspecified';
                $mfsProviderAmounts[$provider] = ($mfsProviderAmounts[$provider] ?? 0) + $mfc;
            }
        }

        arsort($cardProviderAmounts);
        arsort($mfsProviderAmounts);
        $totalCollected = $cashAmount + $cardAmount + $mfcAmount;

        $paymentRows = [];
        if (!$paymentMethod || $paymentMethod == 'Cash') {
            $paymentRows[] = ['label' => 'Cash', 'icon' => 'bi-cash-coin', 'amount' => $cashAmount, 'orders_count' => $cashOrders, 'percentage' => $totalCollected > 0 ? ($cashAmount / $totalCollected) * 100 : 0];
        }
        if (!$paymentMethod || $paymentMethod == 'Card') {
            $paymentRows[] = ['label' => 'Bank / Card', 'icon' => 'bi-credit-card', 'amount' => $cardAmount, 'orders_count' => $cardOrders, 'percentage' => $totalCollected > 0 ? ($cardAmount / $totalCollected) * 100 : 0, 'providers' => $cardProviderAmounts];
        }
        if (!$paymentMethod || $paymentMethod == 'Mobile Banking') {
            $paymentRows[] = ['label' => 'Mobile Banking / MFC', 'icon' => 'bi-phone', 'amount' => $mfcAmount, 'orders_count' => $mfcOrders, 'percentage' => $totalCollected > 0 ? ($mfcAmount / $totalCollected) * 100 : 0, 'providers' => $mfsProviderAmounts];
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
                $paymentText = $order->report_card_label;
                $rowTotal = $cardAmount;
            } elseif ($paymentMethod === 'Mobile Banking') {
                $paymentText = $order->report_mfs_label;
                $rowTotal = $mfcAmount;
            } else {
                $paymentParts = [];
                if ($cashAmount > 0) $paymentParts[] = 'Cash';
                if ($cardAmount > 0) $paymentParts[] = $order->report_card_label;
                if ($mfcAmount > 0) $paymentParts[] = $order->report_mfs_label;

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
                'card_provider' => trim((string) ($order->card_type ?? '')),
                'mfs_provider' => trim((string) ($order->mfs_provider ?? '')),
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

    /** KOT Report — complete historical list, including delivered/completed KOTs. */
    public function kotReport(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $kotQuery = OrderKot::with(['order.table', 'order.waiter', 'orderDetails']);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $kotQuery->where(function ($query) use ($like) {
                $query->where('id', 'like', $like)
                    ->orWhere('kot_number', 'like', $like)
                    ->orWhere('kitchen_status', 'like', $like)
                    ->orWhere('created_at', 'like', $like)
                    ->orWhereHas('order', function ($orderQuery) use ($like) {
                        $orderQuery->where(function ($orderSearch) use ($like) {
                            $orderSearch->where('order_number', 'like', $like)
                                ->orWhere('order_type', 'like', $like)
                                ->orWhere('status', 'like', $like)
                                ->orWhereHas('table', function ($tableQuery) use ($like) {
                                    $tableQuery->where('table_number', 'like', $like);
                                })
                                ->orWhereHas('waiter', function ($waiterQuery) use ($like) {
                                    $waiterQuery->where('name', 'like', $like);
                                });
                        });
                    });
            });
        }

        $kots = $kotQuery
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.kot_report_rows', compact('kots'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $kots])->render(),
            ]);
        }

        return view('admin.reports.kot_report', compact('kots'));
    }

    /** POS Session Report — complete historical session list. */
    public function posSessionReport(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sessionQuery = PosSession::with('user');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sessionQuery->where(function ($query) use ($like) {
                $query->where('id', 'like', $like)
                    ->orWhere('weekday', 'like', $like)
                    ->orWhere('start_time', 'like', $like)
                    ->orWhere('end_time', 'like', $like)
                    ->orWhere('duration', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('sales_total', 'like', $like)
                    ->orWhere('grand_total', 'like', $like)
                    ->orWhereHas('user', function ($userQuery) use ($like) {
                        $userQuery->where('name', 'like', $like);
                    });
            });
        }

        $sessions = $sessionQuery
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.reports.partials.pos_session_report_rows', compact('sessions'))->render(),
                'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $sessions])->render(),
            ]);
        }

        return view('admin.reports.pos_session_report', compact('sessions'));
    }



    private function kotExportData(): array
    {
        $kots = OrderKot::with(['order.table', 'order.waiter', 'orderDetails'])->orderByDesc('id')->get();
        $headings = ['#', 'KOT', 'Order', 'Type / Table', 'Waiter', 'Items', 'Created', 'KOT Status', 'Order Status'];
        $rows = $kots->values()->map(function ($kot, $index) {
            $order = $kot->order;
            $type = strtolower((string) optional($order)->order_type);
            $location = in_array($type, ['dine-in', 'dine_in'], true)
                ? 'Table ' . (optional(optional($order)->table)->table_number ?? 'N/A')
                : ucfirst(str_replace('_', ' ', (string) optional($order)->order_type));
            $itemQty = $kot->orderDetails->where('is_unavailable', 0)->sum('quantity');
            return [
                $index + 1, $kot->kot_number, '#' . (optional($order)->order_number ?? 'N/A'),
                $location ?: 'N/A', optional(optional($order)->waiter)->name ?? 'Unassigned', (int) $itemQty,
                $kot->created_at ? $kot->created_at->format('d M Y - h:i A') : 'N/A',
                $kot->kitchen_status ?? 'N/A', optional($order)->status ?? 'N/A',
            ];
        })->all();
        return [$headings, $rows, ['Records' => (string) count($rows)]];
    }

    public function kotReportPdf(Request $request)
    {
        [$headings, $rows, $meta] = $this->kotExportData();
        return $this->simpleReportPdfResponse('KOT Report', $headings, $rows, $meta, 'kot-report-' . now()->format('Y-m-d-His') . '.pdf', 'A3', 'L');
    }

    public function kotReportExcel(Request $request)
    {
        [$headings, $rows] = $this->kotExportData();
        return Excel::download(new ArrayReportExport($headings, $rows, 'KOT Report'), 'kot-report-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    private function sessionProviderAmountText($session, string $method, float $amount): string
    {
        if ($amount <= 0) {
            return '0';
        }

        $start = $session->start_time ? Carbon::parse($session->start_time) : null;
        $end = $session->end_time ? Carbon::parse($session->end_time) : now();
        if (!$start) {
            return number_format($amount, 2);
        }

        $column = $method === 'Card' ? 'card_type' : 'mfs_provider';
        $amountColumn = $method === 'Card' ? 'paid_in_card' : 'paid_in_mfc';
        $paymentType = $method === 'Card' ? 'Card' : 'Mobile Banking';

        $names = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($query) use ($amountColumn, $paymentType) {
                $query->where($amountColumn, '>', 0)
                    ->orWhere('payment_type', $paymentType);
            })
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->pluck($column)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        return number_format($amount, 2) . ($names->isNotEmpty() ? ' (' . $names->implode(', ') . ')' : '');
    }

    private function posSessionExportData(): array
    {
        $sessions = PosSession::with('user')->orderByDesc('id')->get();
        $headings = ['ID', 'Employee', 'Day', 'Start Time', 'End Time', 'Duration', 'Sales', 'Service Charge', 'VAT', 'Grand Total', 'Cash', 'Bank / Card', 'MFS', 'Status'];
        $rows = $sessions->map(function ($session) {
            $income = is_array($session->incomes_summary) ? $session->incomes_summary : [];
            return [
                $session->id,
                optional($session->user)->name ?? 'N/A',
                $session->weekday ?? ($session->start_time ? Carbon::parse($session->start_time)->format('l') : 'N/A'),
                $session->start_time ? Carbon::parse($session->start_time)->format('d M Y, h:i A') : 'N/A',
                $session->end_time ? Carbon::parse($session->end_time)->format('d M Y, h:i A') : 'Running',
                $session->duration ?? 'Running',
                (float) ($session->sales_total ?? 0),
                (float) ($session->service_charge ?? 0),
                (float) ($session->vat_total ?? 0),
                (float) ($session->grand_total ?? 0),
                (float) ($income['Cash'] ?? 0),
                $this->sessionProviderAmountText($session, 'Card', (float) ($income['Card'] ?? 0)),
                $this->sessionProviderAmountText($session, 'MFS', (float) ($income['MFC'] ?? 0)),
                $session->status ?? 'N/A',
            ];
        })->all();
        return [$headings, $rows, ['Records' => (string) count($rows)]];
    }

    public function posSessionReportPdf(Request $request)
    {
        [$headings, $rows, $meta] = $this->posSessionExportData();
        return $this->simpleReportPdfResponse('POS Session Report', $headings, $rows, $meta, 'pos-session-report-' . now()->format('Y-m-d-His') . '.pdf', 'A3', 'L');
    }

    public function posSessionReportExcel(Request $request)
    {
        [$headings, $rows] = $this->posSessionExportData();
        return Excel::download(new ArrayReportExport($headings, $rows, 'POS Sessions'), 'pos-session-report-' . now()->format('Y-m-d-His') . '.xlsx');
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
