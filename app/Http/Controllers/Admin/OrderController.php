<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\OrderDetail;
use App\Models\OrderKot;
use App\Models\Table;
use App\Models\PosDeletedItemHistory;
use App\Exports\OrdersExport;
use App\Support\OrderVisibility;
use Maatwebsite\Excel\Facades\Excel;
use Schema;
class OrderController extends Controller
{
    public function index(Request $request)
    {
        // 1. Stats Calculation
        // When Random Half is On, the cards count the same globally visible order set
        // used by the unfiltered Order List. Today's complete orders remain included.
        $today = Carbon::today();
        $statsQuery = OrderVisibility::apply(Order::query());
        $stats = [
            'today_orders' => (clone $statsQuery)->whereDate('orders.created_at', $today)->count(),
            'active_orders' => (clone $statsQuery)->whereIn('orders.status', ['Pending', 'Cooking', 'Processing'])->count(),
            'completed_orders' => (clone $statsQuery)->where('orders.status', 'Completed')->count(),
            'revenue_today' => (clone $statsQuery)
                ->whereDate('orders.created_at', $today)
                ->where('orders.status', 'Completed')
                ->sum('orders.grand_total'),
        ];

        // 2. Query Builder
        // একই filter logic Order List, PDF export এবং Excel export — তিন জায়গায় use হবে।
        $query = $this->buildOrderReportQuery($request);

        // When enabled by a Super Admin, show a deterministic random half of
        // matching historical orders plus every matching order from today.
        // PDF/Excel exports intentionally continue using the complete filtered query.
        $query = OrderVisibility::apply($query, $request->except('page'));

        $orders = $query->orderBy('id', 'desc')->paginate(10)->appends($request->query());

        // AJAX Response for table
        if ($request->ajax()) {
            return view('admin.order.partials._order_table', compact('orders'))->render();
        }

        return view('admin.order.index', compact('orders', 'stats'));
    }

    public function show($id)
    {
        $order = Order::with(['customer', 'table', 'waiter', 'orderDetails', 'user', 'duePayments.user'])->findOrFail($id);
        return view('admin.order.partials._order_details', compact('order'))->render();
    }

    /**
     * Order List/PDF/Excel export এর জন্য common filtered query.
     * এখানে নতুন filter add করলে তিন জায়গায় একইভাবে কাজ করবে।
     */
    /**
     * Order report date filter parser.
     * UI now sends DD-MM-YYYY, but old Y-m-d links are still supported.
     */
    private function parseOrderFilterDate(?string $date): ?Carbon
    {
        $date = trim((string) $date);

        if ($date === '') {
            return null;
        }

        $formats = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d'];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                $errors = Carbon::getLastErrors();

                if (($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))
                    && $parsed
                    && $parsed->format($format) === $date) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                // Try next supported format.
            }
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildOrderReportQuery(Request $request)
    {
        $query = Order::with(['customer', 'table', 'orderDetails']);

        // Search: order number অথবা customer name.
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($c) use ($search) {
                      $c->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter.
        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        // Payment filter — Cash/Card/Mobile Banking/Split সব support করবে।
        $payment = trim((string) $request->input('payment', ''));
        if ($payment !== '') {
            $query->where('payment_type', $payment);
        }

        // Date range filter from the order page JS: date_from/date_to.
        // আগে controller শুধু date_range পড়ত, তাই PDF/Excel export অনেক সময় সব order নিয়ে নিত।
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        if ($dateFrom !== '' || $dateTo !== '') {
            $fromDate = $this->parseOrderFilterDate($dateFrom);
            $toDate = $this->parseOrderFilterDate($dateTo);

            if ($fromDate && $toDate) {
                $from = $fromDate->copy()->startOfDay();
                $to = $toDate->copy()->endOfDay();

                if ($from->gt($to)) {
                    [$from, $to] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
                }

                $query->whereBetween('created_at', [$from, $to]);
            } elseif ($fromDate) {
                $query->where('created_at', '>=', $fromDate->copy()->startOfDay());
            } elseif ($toDate) {
                $query->where('created_at', '<=', $toDate->copy()->endOfDay());
            }
        } elseif ($request->filled('date_range')) {
            // Backward compatibility for old date_range filter.
            if ($request->date_range == 'Today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($request->date_range == 'Yesterday') {
                $query->whereDate('created_at', Carbon::yesterday());
            } elseif ($request->date_range == 'This Week') {
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            } elseif ($request->date_range == 'This Month') {
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
            }
        }

        return $query;
    }

    public function exportPDF(Request $request)
    {
        // Do not send one huge HTML string to mPDF.
        // Also do not split one table across multiple WriteHTML calls.
       // Time out এবং Memory issue যেন না হয় তার জন্য লিমিট বাড়িয়ে দেওয়া হলো
        @ini_set('pcre.backtrack_limit', '10000000');
        @ini_set('memory_limit', '1024M'); // ৫১২ মেগাবাইট থেকে বাড়িয়ে ১০২৪ মেগাবাইট (১ জিবি) করা হলো
        @ini_set('max_execution_time', '300'); // ম্যাক্সিমাম এক্সিকিউশন টাইম ৫ মিনিট করা হলো
        @set_time_limit(300); // স্ক্রিপ্ট টাইমআউট ১২০ সেকেন্ড থেকে বাড়িয়ে ৩০০ সেকেন্ড করা হলো (দরকার হলে 0 দিতে পারেন আনলিমিটেড টাইমের জন্য)

        $restaurant = \App\Models\RestaurantSetting::first();
        $dateFilterLabel = $this->orderReportDateFilterLabel($request);
        $baseQuery = $this->buildOrderReportQuery($request);
        $totalOrders = (clone $baseQuery)->count();

        $mpdfTempDir = storage_path('app/mpdf');
        if (!is_dir($mpdfTempDir)) {
            @mkdir($mpdfTempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => $mpdfTempDir,
        ]);

        $mpdf->SetTitle('Order Report - ' . now()->format('d M, Y'));
        // This puts "Generated" on the Left, leaves the Center blank, and puts "Page" on the Right.
$mpdf->SetFooter('Generated: ' . now()->format('d M Y, h:i A') . '||Page {PAGENO} of {nbpg}');

        $mpdf->WriteHTML(view('admin.order.pdf_report', [
            'mode' => 'styles',
        ])->render(), HTMLParserMode::HEADER_CSS);

        $mpdf->WriteHTML(view('admin.order.pdf_report', [
            'mode' => 'header',
            'restaurant' => $restaurant,
            'dateFilterLabel' => $dateFilterLabel,
            'totalOrders' => $totalOrders,
        ])->render(), HTMLParserMode::HTML_BODY);

        $totals = [
            'subtotal' => 0,
            'revenue' => 0,
            'discount' => 0,
            'product_discount' => 0,
            'service_charge' => 0,
            'tips' => 0,
            'given' => 0,
            'change' => 0,
        ];

        if ($totalOrders <= 0) {
            $mpdf->WriteHTML(view('admin.order.pdf_report', [
                'mode' => 'empty',
            ])->render(), HTMLParserMode::HTML_BODY);
        } else {
            (clone $baseQuery)
                ->orderBy('id', 'desc')
                ->chunk(60, function ($orders) use ($mpdf, &$totals) {
                    foreach ($orders as $order) {
                        $discountAmount = max(0, (float) ($order->discount_amount ?? 0));
                        $productDiscountAmount = max(0, (float) ($order->product_discount_amount ?? 0));
                        $serviceCharge = max(0, (float) ($order->service_charge ?? 0));
                        $tipsAmount = max(0, (float) ($order->tips_amount ?? ((float) ($order->total_paid_amount ?? 0) - (float) ($order->grand_total ?? 0))));
                        $givenMoney = max(0, (float) ($order->given_money ?? 0));
                        $changeAmount = max(0, (float) ($order->change_amount ?? 0));

                        if ($order->status === 'Completed') {
                            $totals['subtotal'] += (float) ($order->subtotal ?? 0);
                            $totals['revenue'] += (float) ($order->grand_total ?? 0);
                        }

                        $totals['discount'] += $discountAmount;
                        $totals['product_discount'] += $productDiscountAmount;
                        $totals['service_charge'] += $serviceCharge;
                        $totals['tips'] += $tipsAmount;
                        $totals['given'] += $givenMoney;
                        $totals['change'] += $changeAmount;
                    }

                    // Important: every chunk is a complete table. No open <table>/<tbody> is carried
                    // between WriteHTML calls. This prevents mPDF "Undefined array key" table-parser errors.
                    $mpdf->WriteHTML(view('admin.order.pdf_report', [
                        'mode' => 'table',
                        'orders' => $orders,
                    ])->render(), HTMLParserMode::HTML_BODY);
                });
        }

        $mpdf->WriteHTML(view('admin.order.pdf_report', [
            'mode' => 'summary',
            'ordersCount' => $totalOrders,
            'totals' => $totals,
        ])->render(), HTMLParserMode::HTML_BODY);

        $fileName = 'Order_Report_' . now()->format('d_M_Y') . '.pdf';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($mpdf->Output($fileName, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    private function orderReportDateFilterLabel(Request $request): string
    {
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $fromDate = $this->parseOrderFilterDate($dateFrom);
        $toDate = $this->parseOrderFilterDate($dateTo);

        if ($fromDate && $toDate) {
            return $fromDate->format('d M Y') . ' - ' . $toDate->format('d M Y');
        }

        if ($fromDate) {
            return 'From ' . $fromDate->format('d M Y');
        }

        if ($toDate) {
            return 'Until ' . $toDate->format('d M Y');
        }

        if ($dateFrom !== '' || $dateTo !== '') {
            return 'Selected Date Range';
        }

        if ($request->filled('date_range')) {
            return (string) $request->date_range;
        }

        return 'All Orders';
    }

    public function exportExcel(Request $request)
    {
        // Excel export-এও PDF এবং Order List-এর same filtered data যাবে।
        $orders = $this->buildOrderReportQuery($request)->orderBy('id', 'desc')->get();
        $fileName = 'Order_Report_' . now()->format('d_M_Y') . '.xlsx';

        return Excel::download(new OrdersExport($orders), $fileName);
    }


    // ==========================================
    // Real-Time Notification Logic
    // ==========================================
  public function checkNotifications()
    {
        // orderDetails সহ ডাটা আনা হচ্ছে যাতে মোডালে আইটেম দেখানো যায়
        $newOrder = \App\Models\Order::with(['table', 'orderDetails'])
            ->where('status', 'QR_Pending')
            ->orderBy('id', 'asc')
            ->first();

        $waiterCall = \Illuminate\Support\Facades\DB::table('waiter_calls')
            ->join('tables', 'waiter_calls.table_id', '=', 'tables.id')
            ->where('waiter_calls.status', 'pending')
            ->select('waiter_calls.*', 'tables.table_number')
            ->orderBy('waiter_calls.id', 'asc')
            ->first();

        return response()->json([
            'status'      => 'success',
            'order'       => $newOrder,
            'waiter_call' => $waiterCall
        ]);
    }

    /**
     * Generate global KOT serial number.
     * KOT number will continue across all orders: KOT-1, KOT-2, KOT-3...
     */
    private function generateGlobalKotNumber()
    {
        $lastKotNumber = \App\Models\OrderKot::where('kot_number', 'like', 'KOT-%')
            ->selectRaw("MAX(CAST(REPLACE(kot_number, 'KOT-', '') AS UNSIGNED)) as max_number")
            ->value('max_number');

        return 'KOT-' . (((int) $lastKotNumber) + 1);
    }

   public function acceptQrOrder(Request $request)
{
    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $order = \App\Models\Order::findOrFail($request->id);

        // ১. কাস্টমার সেটআপ
        $customerId = null;
        if ($request->customer_type == 'existing') {
            $customerId = $request->customer_id;
        } elseif ($request->customer_type == 'new') {
            $newCustomer = \App\Models\Customer::create([
                'name' => $request->customer_name,
                'phone' => $request->customer_phone
            ]);
            $customerId = $newCustomer->id;
        }

        // ২. অর্ডার আপডেট (স্ট্যাটাস, ওয়েটার, কাস্টমার এবং প্রিপারেশন টাইম)
        $order->status = 'Pending';
        $order->customer_id = $customerId;
        $order->waiter_id = $request->waiter_id;
        // প্রিপারেশন টাইম ডাটাবেজে সেভ করা হচ্ছে
        $order->preparation_time = $request->preparation_time ?? 20;
        $order->save();

        // ৩. কিচেন KOT জেনারেট করা (Global serial: KOT-1, KOT-2, KOT-3...)
        $kotNumber = $this->generateGlobalKotNumber();
        $kotId = \Illuminate\Support\Facades\DB::table('order_kots')->insertGetId([
            'order_id' => $order->id,
            'kot_number' => $kotNumber,
            'kitchen_status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ৪. আইটেমগুলোকে KOT এর সাথে লিংক করা
        \Illuminate\Support\Facades\DB::table('order_details')
            ->where('order_id', $order->id)
            ->update(['order_kot_id' => $kotId]);

        // ৫. টেবিল Occupied করা
        if ($order->table_id) {
            \App\Models\Table::where('id', $order->table_id)->update(['initial_status' => 'Occupied']);
        }

        \Illuminate\Support\Facades\DB::commit();
        return response()->json(['status' => 'success']);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

    public function resolveWaiterCall(Request $request)
    {
        \Illuminate\Support\Facades\DB::table('waiter_calls')
            ->where('id', $request->id)
            ->update(['status' => 'resolved']);

        return response()->json(['status' => 'success']);
    }


    /**
     * Order edit page.
     * এখানে নতুন প্রোডাক্ট অ্যাড করার কোনো অপশন নেই।
     * শুধু existing order item quantity এবং payment summary edit করা যাবে।
     */
    public function edit($id)
    {
        $order = Order::with(['customer', 'table', 'waiter', 'orderDetails', 'user'])->findOrFail($id);
        $taxSetting = DB::table('tax_settings')->first();

        $vatRate = (float) ($taxSetting->vat_rate ?? 0);
        $normalizedOrderType = strtolower(str_replace('_', '-', (string) $order->order_type));
        $serviceChargeRate = $normalizedOrderType === 'dine-in'
            ? (float) ($taxSetting->service_charge ?? 0)
            : 0;

        // DB-তে discount_amount calculated amount হিসেবে থাকে।
        // যদি পুরনো order percentage discount দিয়ে করা হয়, edit form-এ percentage value approximate করে দেখানো হবে।
        $discountValue = (float) ($order->discount_amount ?? 0);
        if (($order->discount_type ?? 'fixed') === 'percentage' && (float) ($order->subtotal ?? 0) > 0) {
            $discountValue = round(((float) $order->discount_amount / (float) $order->subtotal) * 100, 2);
        }

        return view('admin.order.edit', compact('order', 'taxSetting', 'vatRate', 'serviceChargeRate', 'discountValue'));
    }

    /**
     * Update order quantity + payment summary.
     * নতুন item add/delete করা হচ্ছে না; শুধু বর্তমান order_details line quantity update হবে।
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.product_discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'items.*.product_discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['Cash', 'Card', 'Mobile Banking', 'Split'])],
            'total_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'tips_amount' => ['nullable', 'numeric', 'min:0'],
            'given_money' => ['nullable', 'numeric', 'min:0'],
            'change_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_in_cash' => ['nullable', 'numeric', 'min:0'],
            'paid_in_card' => ['nullable', 'numeric', 'min:0'],
            'paid_in_mfc' => ['nullable', 'numeric', 'min:0'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        DB::beginTransaction();

        try {
            $order = Order::with('orderDetails')->lockForUpdate()->findOrFail($id);
            $inputItems = $request->input('items', []);

            $newSubtotal = 0;
            $productDiscountTotal = 0;

            foreach ($order->orderDetails as $detail) {
                // শুধু existing detail id গুলোই update হবে, নতুন কোনো item create হবে না।
                if (!isset($inputItems[$detail->id])) {
                    $newSubtotal += (float) ($detail->subtotal ?? 0);
                    $productDiscountTotal += max(0, (float) ($detail->product_discount_amount ?? 0));
                    continue;
                }

                $itemInput = $inputItems[$detail->id];
                $newQty = max(1, (int) ($itemInput['quantity'] ?? $detail->quantity));
                $oldQty = max(1, (int) ($detail->quantity ?? 1));
                $oldLineSubtotal = (float) ($detail->subtotal ?? 0);

                // Existing line subtotal/qty থেকে unit total বের করা হচ্ছে, যাতে addon price preserve থাকে।
                if ($oldLineSubtotal > 0 && $oldQty > 0) {
                    $unitTotal = $oldLineSubtotal / $oldQty;
                } else {
                    $addonTotal = 0;
                    $addons = json_decode($detail->addons ?? '[]', true);
                    if (is_array($addons)) {
                        foreach ($addons as $addon) {
                            $addonTotal += (float) ($addon['price'] ?? 0);
                        }
                    }
                    $unitTotal = (float) ($detail->price ?? 0) + $addonTotal;
                }

                $newLineSubtotal = round($unitTotal * $newQty, 2);

                $productDiscountType = (($itemInput['product_discount_type'] ?? $detail->product_discount_type ?? 'fixed') === 'percentage')
                    ? 'percentage'
                    : 'fixed';
                $productDiscountValue = max(0, (float) ($itemInput['product_discount_value'] ?? $detail->product_discount_value ?? 0));

                if ($productDiscountType === 'percentage') {
                    $productDiscountValue = min($productDiscountValue, 100);
                    $productDiscountAmount = round(($newLineSubtotal * $productDiscountValue) / 100);
                } else {
                    $productDiscountAmount = min($productDiscountValue, $newLineSubtotal);
                }

                $productDiscountAmount = max(0, round($productDiscountAmount));

                $detail->quantity = $newQty;
                $detail->subtotal = $newLineSubtotal;
                $detail->product_discount_type = $productDiscountAmount > 0 ? $productDiscountType : null;
                $detail->product_discount_value = $productDiscountAmount > 0 ? $productDiscountValue : 0;
                $detail->product_discount_amount = $productDiscountAmount;
                $detail->save();

                $newSubtotal += $newLineSubtotal;
                $productDiscountTotal += $productDiscountAmount;
            }

            $taxSetting = DB::table('tax_settings')->first();
            $vatRate = (float) ($taxSetting->vat_rate ?? 0);

            $normalizedOrderType = strtolower(str_replace('_', '-', (string) $order->order_type));
            $serviceChargeRate = $normalizedOrderType === 'dine-in'
                ? (float) ($taxSetting->service_charge ?? 0)
                : 0;

            $serviceCharge = round(($newSubtotal * $serviceChargeRate) / 100);
            $vatTax = round((($newSubtotal + $serviceCharge) * $vatRate) / 100);

            $discountType = $request->discount_type ?? 'fixed';
            $discountValue = (float) ($request->discount_value ?? 0);
            $discountAmount = $discountType === 'percentage'
                ? round(($newSubtotal * $discountValue) / 100)
                : round($discountValue);

            // Other discount এবং product-wise discount আলাদা থাকবে।
            $discountAmount = min(max(0, $discountAmount), round($newSubtotal + $serviceCharge + $vatTax));
            $productDiscountTotal = min(max(0, round($productDiscountTotal)), round($newSubtotal));
            $grandTotal = max(0, round(($newSubtotal + $serviceCharge + $vatTax) - $discountAmount - $productDiscountTotal));

            $paymentMethod = $request->payment_method;
            if ($paymentMethod === 'Split') {
                $cash = max(0, (float) ($request->paid_in_cash ?? 0));
                $card = max(0, (float) ($request->paid_in_card ?? 0));
                $mfc = max(0, (float) ($request->paid_in_mfc ?? 0));
                $totalPaid = round($cash + $card + $mfc, 2);
            } else {
                $totalPaid = max(0, (float) ($request->total_paid_amount ?? 0));
                $cash = $paymentMethod === 'Cash' ? $totalPaid : 0;
                $card = $paymentMethod === 'Card' ? $totalPaid : 0;
                $mfc = $paymentMethod === 'Mobile Banking' ? $totalPaid : 0;
            }

            $tipsAmount = max(0, round((float) ($request->tips_amount ?? 0), 2));
            $givenMoney = max(0, round((float) ($request->given_money ?? 0), 2));
            $changeAmount = max(0, round($givenMoney - $totalPaid - $tipsAmount, 2));
            $due = max(0, round($grandTotal - $totalPaid, 2));

            $order->subtotal = $newSubtotal;
            $order->discount_type = $discountType;
            $order->discount_amount = $discountAmount;
            $order->product_discount_amount = $productDiscountTotal;
            $order->vat_tax = $vatTax;
            $order->service_charge = $serviceCharge;
            $order->grand_total = $grandTotal;
            $order->payment_type = $paymentMethod;
            // Transaction / Reference No will be stored only for Card or Mobile Banking. Other payment types will clear it.
            $order->transaction_id = in_array($paymentMethod, ['Card', 'Mobile Banking'], true) ? $request->transaction_id : null;
            $order->total_paid_amount = $totalPaid;
            $order->paid_in_cash = $cash;
            $order->paid_in_card = $card;
            $order->paid_in_mfc = $mfc;
            $order->due = $due;

            if (Schema::hasColumn('orders', 'tips_amount')) {
                $order->tips_amount = $tipsAmount;
            }
            if (Schema::hasColumn('orders', 'given_money')) {
                $order->given_money = $givenMoney;
            }
            if (Schema::hasColumn('orders', 'change_amount')) {
                $order->change_amount = $changeAmount;
            }

            $order->save();

            DB::commit();

            return redirect()
                ->route('order.edit', $order->id)
                ->with('success', 'Order updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Order update failed! ' . $e->getMessage());
        }
    }


    /**
     * Collect a later payment against an existing due balance and keep an auditable history.
     */
    public function payDue(Request $request, $id)
    {
        abort_unless(auth()->user()?->can('order-edit'), 403);

        $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_type' => ['required', Rule::in(['Cash', 'Card', 'Mobile Banking'])],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($request->payment_type, ['Card', 'Mobile Banking'], true)
            && trim((string) $request->transaction_reference) === '') {
            return back()->withErrors([
                'transaction_reference' => 'Reference number is required for Card or Mobile Banking due payment.',
            ])->withInput();
        }

        DB::beginTransaction();

        try {
            $order = Order::lockForUpdate()->findOrFail($id);
            $dueBefore = max(0, round((float) ($order->due ?? 0), 2));
            $amount = max(0, round((float) $request->amount, 2));

            if ($dueBefore <= 0) {
                DB::rollBack();
                return back()->with('error', 'This order has no due amount remaining.');
            }

            if ($amount > $dueBefore) {
                DB::rollBack();
                return back()->withErrors([
                    'amount' => 'Due payment cannot exceed the remaining due amount of ৳' . number_format($dueBefore, 2) . '.',
                ])->withInput();
            }

            $dueAfter = max(0, round($dueBefore - $amount, 2));
            $paymentType = $request->payment_type;

            \App\Models\OrderDuePayment::create([
                'order_id' => $order->id,
                'amount' => $amount,
                'due_before' => $dueBefore,
                'due_after' => $dueAfter,
                'payment_type' => $paymentType,
                'transaction_reference' => in_array($paymentType, ['Card', 'Mobile Banking'], true)
                    ? trim((string) $request->transaction_reference)
                    : null,
                'remark' => trim((string) $request->remark) !== '' ? trim((string) $request->remark) : null,
                'received_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            $order->total_paid_amount = round((float) ($order->total_paid_amount ?? 0) + $amount, 2);

            if ($paymentType === 'Cash') {
                $order->paid_in_cash = round((float) ($order->paid_in_cash ?? 0) + $amount, 2);
            } elseif ($paymentType === 'Card') {
                $order->paid_in_card = round((float) ($order->paid_in_card ?? 0) + $amount, 2);
            } else {
                $order->paid_in_mfc = round((float) ($order->paid_in_mfc ?? 0) + $amount, 2);
            }

            $activeMethods = collect([
                'Cash' => (float) ($order->paid_in_cash ?? 0),
                'Card' => (float) ($order->paid_in_card ?? 0),
                'Mobile Banking' => (float) ($order->paid_in_mfc ?? 0),
            ])->filter(fn ($value) => $value > 0);

            $order->payment_type = $activeMethods->count() > 1
                ? 'Split'
                : ($activeMethods->keys()->first() ?: $paymentType);
            $order->due = $dueAfter;
            $order->save();

            DB::commit();

            return redirect()
                ->route('order.details', $order->id)
                ->with('success', 'Due payment of ৳' . number_format($amount, 2) . ' received successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Due payment failed! ' . $e->getMessage())->withInput();
        }
    }

    public function details($id)
    {
        $order = Order::with(['customer', 'table', 'waiter', 'orderDetails', 'user', 'review', 'duePayments.user'])->findOrFail($id);

        return view('admin.order.show', compact('order'));
    }


    public function deletedHistory($id)
    {
        $order = Order::with(['customer', 'table'])->findOrFail($id);

        $histories = PosDeletedItemHistory::with('user')
            ->where('order_id', $order->id)
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.order.partials._delete_history', compact('order', 'histories'))->render();
    }

    public function destroy($id)
{
    if (!auth()->user()->can('order-delete')) {
        return response()->json([
            'status' => 'error',
            'message' => 'You do not have permission to delete this order.'
        ], 403);
    }

    \Illuminate\Support\Facades\DB::beginTransaction();

    try {
        $order = Order::with(['orderDetails', 'kots'])->findOrFail($id);

        // যদি dine-in order হয়, table available করে দেওয়া
        if ($order->table_id) {
            \App\Models\Table::where('id', $order->table_id)->update([
                'initial_status' => 'Available'
            ]);
        }

        // Related data delete
        \App\Models\OrderDetail::where('order_id', $order->id)->delete();
        \App\Models\OrderKot::where('order_id', $order->id)->delete();

        // Main order delete
        $order->delete();

        \Illuminate\Support\Facades\DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Order deleted successfully!'
        ]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();

        return response()->json([
            'status' => 'error',
            'message' => 'Order delete failed! ' . $e->getMessage()
        ], 500);
    }
}
}
