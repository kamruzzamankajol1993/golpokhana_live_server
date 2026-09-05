<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FoodItem;
use App\Models\FoodCategory;
use App\Models\Table;
use App\Models\Waiter;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderKot;
use App\Models\OrderDetail;
use App\Models\PointHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\PosSession;
use App\Models\DeliveryPartner; // ফাইলের উপরে এটি যুক্ত করতে ভুলবেন না
use App\Models\TableBooking;
use App\Models\PosSetting;
use App\Models\RestaurantSetting;
use App\Services\PosSessionManagerResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Exports\ArrayReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
class PosController extends Controller
{
public function index(\Illuminate\Http\Request $request)
{
    $user = auth()->user();
    $sessionManagerId = $user
        ? app(PosSessionManagerResolver::class)->resolveId($user)
        : null;

    if ($sessionManagerId) {
        $this->closeTimedOutOpenSessionsForUser($sessionManagerId);
    }

    // POS work periods are shared under the Manager account. Any POS user sees
    // the same running Manager-owned session instead of getting a user-wise session.
    $activeSession = $sessionManagerId
        ? PosSession::where('user_id', $sessionManagerId)
            ->where('status', 'Open')
            ->orderByDesc('id')
            ->first()
        : null;

    // A shared Manager session is immediately active in every browser/user login.
    // There is no Continue Previous / Start New prompt per individual user anymore.
    $forceUnfinishedSessionPrompt = false;
    session()->forget('force_pos_unfinished_prompt');

    $posOrderWindow = $this->getPosOrderWindowStatus();
    $isPosOrderTimeOpen = $posOrderWindow['is_open'];
    $posOpeningTime = $posOrderWindow['opening_time'];
    $posClosingTime = $posOrderWindow['closing_time'];
    $posClosedMessage = $this->getPosClosedMessage($posOpeningTime);
    $posSessionLifetimeMinutes = max(1, (int) config('session.lifetime', 180));

    if (!$isPosOrderTimeOpen) {
        return view('admin.pos.index', compact(
            'isPosOrderTimeOpen',
            'posOpeningTime',
            'posClosingTime',
            'posClosedMessage',
            'activeSession',
            'forceUnfinishedSessionPrompt',
            'posSessionLifetimeMinutes'
        ));
    }

    $posSetting = DB::table('pos_settings')->first();
    $categories = FoodCategory::whereNull('parent_category_id')->where('status', 1)->orderBy('sort_order', 'asc')->get();
    $tables = Table::with('zone')->get();

    // Reservation is a live POS state. A future booking must not reserve the table early.
    $bdNow = Carbon::now('Asia/Dhaka');
    $today = $bdNow->toDateString();
    $currentTime = $bdNow->format('H:i:s');

    $currentBookingsByTable = TableBooking::with('customer')
        ->whereIn('status', ['upcoming', 'confirmed'])
        ->whereDate('booking_date', $today)
        ->where(function ($query) use ($currentTime) {
            $query->whereNull('booking_start_time')
                ->orWhereTime('booking_start_time', '<=', $currentTime);
        })
        ->where(function ($query) use ($currentTime) {
            $query->whereNull('booking_end_time')
                ->orWhereTime('booking_end_time', '>=', $currentTime);
        })
        ->orderBy('booking_start_time')
        ->get()
        ->groupBy('table_id')
        ->map(fn ($bookings) => $bookings->first());

    foreach ($tables as $table) {
        if (strtolower((string) $table->initial_status) === 'occupied') {
            continue;
        }

        if ($currentBookingsByTable->has($table->id)) {
            $booking = $currentBookingsByTable->get($table->id);
            $table->initial_status = 'reserved';
            $table->reserved_customer_id = $booking->customer_id;
            $table->reserved_booking_id = $booking->id;
        } else {
            if (strtolower((string) $table->initial_status) === 'reserved') {
                $table->initial_status = 'available';
            }
            $table->reserved_customer_id = null;
            $table->reserved_booking_id = null;
        }
    }

    $waiters = Waiter::where('status', 1)->get();
    $customers = Customer::orderBy('name', 'asc')->get();
    $deliveryPartners = DeliveryPartner::where('status',1)->orderBy('name')->get();
    $tableId = request()->get('table_id');
    $selectedTableId = $tableId;
    $selectedTable = $tableId ? Table::with('zone')->find($tableId) : null;
    $selectedBookingId = $request->get('table_booking_id');

    $isManagerRole = $this->userHasRoleCaseInsensitive(auth()->user(), 'manager');
    $randomHalfOrderButtonVisible = $isManagerRole
        && Schema::hasTable('pos_settings')
        && Schema::hasColumn('pos_settings', 'random_half_order_button_visible')
        && (bool) ($posSetting->random_half_order_button_visible ?? true);

    $availCount = $tables->filter(function($table) { return strtolower($table->initial_status) === 'available'; })->count();
    $occCount = $tables->filter(function($table) { return strtolower($table->initial_status) === 'occupied'; })->count();
    $resCount = $tables->filter(function($table) { return strtolower($table->initial_status) === 'reserved'; })->count();

    $activeTakeawayDeliveryOrders = Order::with(['customer', 'waiter', 'orderDetails', 'deliveryPartner'])
        ->whereIn('order_type', ['Takeaway', 'Delivery', 'takeaway', 'delivery', 'Take Away'])
        ->whereIn('status', ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'])
        ->orderBy('id', 'desc')
        ->get();

    // Work periods may cross calendar/business days and remain active until explicitly ended.
    $requirePreviousSessionClose = false;

    // Operational session dropdown follows the restaurant business day, not calendar date.
    $businessDayWindow = $this->getPosBusinessDayWindow();
    $sessions = PosSession::with('user')
        ->whereBetween('start_time', [$businessDayWindow['start'], $businessDayWindow['end']])
        ->orderBy('id', 'desc')
        ->get();

    $sessions->transform(function ($session) {
        $session->report_grand_total = (float) $this->reportableOrdersForSessionWindow(
            Carbon::parse($session->start_time),
            Carbon::parse($session->end_time ?: now())
        )->sum('grand_total');
        return $session;
    });

    return view('admin.pos.index', compact(
        'categories',
        'tables',
        'waiters',
        'customers',
        'deliveryPartners',
        'posSetting',
        'availCount',
        'occCount',
        'resCount',
        'activeSession',
        'forceUnfinishedSessionPrompt',
        'requirePreviousSessionClose',
        'sessions',
        'activeTakeawayDeliveryOrders',
        'isManagerRole',
        'randomHalfOrderButtonVisible',
        'isPosOrderTimeOpen',
        'posOpeningTime',
        'posClosingTime',
        'posClosedMessage',
        'selectedTableId',
        'selectedTable',
        'selectedBookingId',
        'posSessionLifetimeMinutes'
    ));
}

    /**
     * Standalone POS session history page with AJAX pagination.
     * This mirrors the session list previously shown inside the POS header modal.
     */
public function sessionList(Request $request)
{
    $businessDayWindow = $this->getPosBusinessDayWindow();
    $search = trim((string) $request->query('search', ''));

    $sessionsQuery = PosSession::with('user')
        ->whereBetween('start_time', [$businessDayWindow['start'], $businessDayWindow['end']]);

    if ($search !== '') {
        $like = '%' . $search . '%';
        $sessionsQuery->where(function ($query) use ($like) {
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

    $sessions = $sessionsQuery
        ->orderByDesc('id')
        ->paginate(15)
        ->appends($request->query());

    $sessions->getCollection()->transform(function ($session) {
        $session->report_grand_total = (float) $this->reportableOrdersForSessionWindow(
            Carbon::parse($session->start_time),
            Carbon::parse($session->end_time ?: now())
        )->sum('grand_total');
        return $session;
    });

    if ($request->ajax()) {
        return response()->json([
            'html' => view('admin.pos.sessions.partials.rows', compact('sessions'))->render(),
            'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $sessions])->render(),
        ]);
    }

    return view('admin.pos.sessions.index', compact('sessions'));
}

    /**
     * Active/running KOT list. Completed/cancelled orders disappear automatically.
     */
public function kotList(Request $request)
{
    $businessDayWindow = $this->getPosBusinessDayWindow();
    $search = trim((string) $request->query('search', ''));

    $kotQuery = OrderKot::with([
            'order.table',
            'order.waiter',
            'orderDetails',
        ])
        ->whereBetween('created_at', [$businessDayWindow['start'], $businessDayWindow['end']])
        ->where('kitchen_status', '!=', 'Hold')
        ->whereHas('order', function ($query) {
            $query->whereNotIn('status', [
                'Completed', 'completed',
                'Cancelled', 'cancelled',
                'Delivered', 'delivered',
            ]);
        });

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
        ->paginate(15)
        ->appends($request->query());

    if ($request->ajax()) {
        return response()->json([
            'html' => view('admin.pos.kots.partials.rows', compact('kots'))->render(),
            'pagination' => view('admin.reports.partials.custom_pagination', ['paginator' => $kots])->render(),
        ]);
    }

    return view('admin.pos.kots.index', compact('kots'));
}



    /** Export the same current business-day KOT rows shown in POS > KOT List. */
    public function kotListPdf(Request $request)
    {
        [$businessDayWindow, $kots] = $this->currentPosKotsForExport();
        [$headings, $rows] = $this->buildPosKotExportRows($kots);
        return $this->posListPdfResponse(
            'POS KOT List', $headings, $rows,
            $this->businessDayExportLabel($businessDayWindow),
            'pos-kot-list-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function kotListExcel(Request $request)
    {
        [, $kots] = $this->currentPosKotsForExport();
        [$headings, $rows] = $this->buildPosKotExportRows($kots);
        return Excel::download(new ArrayReportExport($headings, $rows, 'POS KOT List'), 'pos-kot-list-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    /** Export the same current business-day session rows shown in POS > Session List. */
    public function sessionListPdf(Request $request)
    {
        [$businessDayWindow, $sessions] = $this->currentPosSessionsForExport();
        [$headings, $rows] = $this->buildPosSessionExportRows($sessions);
        return $this->posListPdfResponse(
            'POS Session List', $headings, $rows,
            $this->businessDayExportLabel($businessDayWindow),
            'pos-session-list-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function sessionListExcel(Request $request)
    {
        [, $sessions] = $this->currentPosSessionsForExport();
        [$headings, $rows] = $this->buildPosSessionExportRows($sessions);
        return Excel::download(new ArrayReportExport($headings, $rows, 'POS Sessions'), 'pos-session-list-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    private function currentPosKotsForExport(): array
    {
        $businessDayWindow = $this->getPosBusinessDayWindow();
        $kots = OrderKot::with(['order.table', 'order.waiter', 'orderDetails'])
            ->whereBetween('created_at', [$businessDayWindow['start'], $businessDayWindow['end']])
            ->where('kitchen_status', '!=', 'Hold')
            ->whereHas('order', function ($query) {
                $query->whereNotIn('status', ['Completed', 'completed', 'Cancelled', 'cancelled', 'Delivered', 'delivered']);
            })
            ->orderByDesc('id')->get();
        return [$businessDayWindow, $kots];
    }

    private function currentPosSessionsForExport(): array
    {
        $businessDayWindow = $this->getPosBusinessDayWindow();
        $sessions = PosSession::with('user')
            ->whereBetween('start_time', [$businessDayWindow['start'], $businessDayWindow['end']])
            ->orderByDesc('id')->get();
        $sessions->transform(function ($session) {
            $session->report_grand_total = (float) $this->reportableOrdersForSessionWindow(
                Carbon::parse($session->start_time),
                Carbon::parse($session->end_time ?: now())
            )->sum('grand_total');
            return $session;
        });
        return [$businessDayWindow, $sessions];
    }

    private function buildPosKotExportRows($kots): array
    {
        $headings = ['ID', 'KOT', 'Order', 'Type / Table', 'Waiter', 'Items', 'Created', 'KOT Status', 'Order Status'];
        $rows = $kots->map(function ($kot) {
            $order = $kot->order;
            $type = strtolower((string) optional($order)->order_type);
            $location = in_array($type, ['dine-in', 'dine_in'], true)
                ? 'Table ' . (optional(optional($order)->table)->table_number ?? 'N/A')
                : ucfirst(str_replace('_', ' ', (string) optional($order)->order_type));
            $activeQty = $kot->orderDetails->filter(fn ($item) => (int) ($item->is_unavailable ?? 0) !== 1)->sum('quantity');
            return [
                $kot->id, $kot->kot_number ?? 'N/A', '#' . (optional($order)->order_number ?? 'N/A'),
                $location ?: 'N/A', optional(optional($order)->waiter)->name ?? 'Unassigned', (int) $activeQty,
                $kot->created_at ? $kot->created_at->format('d M Y, h:i A') : 'N/A',
                $kot->kitchen_status ?? 'N/A', optional($order)->status ?? 'N/A',
            ];
        })->values()->all();
        return [$headings, $rows];
    }

    private function buildPosSessionExportRows($sessions): array
    {
        $headings = ['ID', 'Employee', 'Day', 'Start Time', 'End Time', 'Duration', 'Grand Total', 'Status'];
        $rows = $sessions->map(function ($session) {
            $start = Carbon::parse($session->start_time);
            return [
                $session->id, optional($session->user)->name ?? 'N/A', $session->weekday ?? $start->format('l'),
                $session->start_time ? Carbon::parse($session->start_time)->format('d M Y, h:i A') : 'N/A',
                $session->end_time ? Carbon::parse($session->end_time)->format('d M Y, h:i A') : 'Running',
                $session->duration ?? 'Running', (float) ($session->report_grand_total ?? 0), $session->status ?? 'N/A',
            ];
        })->values()->all();
        return [$headings, $rows];
    }

    private function businessDayExportLabel(array $businessDayWindow): string
    {
        return 'Business day: ' . $businessDayWindow['start']->format('d M Y, h:i A') . ' - ' . $businessDayWindow['end']->format('d M Y, h:i A');
    }

    private function posListPdfResponse(string $title, array $headings, array $rows, string $subtitle, string $fileName)
    {
        @ini_set('pcre.backtrack_limit', '10000000');
        @ini_set('memory_limit', '512M');
        $tempDir = storage_path('app/mpdf');
        if (!is_dir($tempDir)) @mkdir($tempDir, 0775, true);
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'L',
            'margin_left' => 8, 'margin_right' => 8, 'margin_top' => 10, 'margin_bottom' => 10,
            'tempDir' => $tempDir,
        ]);
        $mpdf->SetTitle($title);
        $mpdf->SetFooter('Generated: ' . now()->format('d M Y, h:i A') . '||Page {PAGENO} of {nbpg}');
        $mpdf->WriteHTML(view('admin.pos.list_export_pdf', compact('title', 'subtitle', 'headings', 'rows'))->render());
        while (ob_get_level() > 0) ob_end_clean();
        return response($mpdf->Output($fileName, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Restaurant Settings-এর opening/closing time অনুযায়ী POS order নেওয়া যাবে কি না।
     * Overnight window-এর ক্ষেত্রে (যেমন 12:01 PM থেকে 6:00 AM),
     * opening-এর পর অথবা closing পর্যন্ত সময় open ধরা হবে।
     */
    private function getPosOrderWindowStatus(): array
    {
        $restaurantSetting = RestaurantSetting::first();

        $openingTime = $this->normalizePosTimeToMinute(
            $restaurantSetting ? $restaurantSetting->opening_time : null,
            '12:01'
        );
        $closingTime = $this->normalizePosTimeToMinute(
            $restaurantSetting ? $restaurantSetting->closing_time : null,
            '06:00'
        );
        $currentTime = Carbon::now()->format('H:i');

        if ($openingTime === $closingTime) {
            $isOpen = true;
        } elseif ($openingTime > $closingTime) {
            // Overnight business window, e.g. 12:01 PM to next day 6:00 AM.
            $isOpen = $currentTime >= $openingTime || $currentTime <= $closingTime;
        } else {
            // Same-day business window.
            $isOpen = $currentTime >= $openingTime && $currentTime <= $closingTime;
        }

        return [
            'is_open' => $isOpen,
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
            'current_time' => $currentTime,
        ];
    }

    private function getPosBusinessDayWindow(?Carbon $moment = null): array
    {
        $restaurantSetting = RestaurantSetting::first();
        $opening = $this->normalizePosTimeToMinute($restaurantSetting ? $restaurantSetting->opening_time : null, '12:01');
        $closing = $this->normalizePosTimeToMinute($restaurantSetting ? $restaurantSetting->closing_time : null, '06:00');
    
        $now = ($moment ?: Carbon::now('Asia/Dhaka'))->copy()->setTimezone('Asia/Dhaka');
        $today = $now->copy()->startOfDay();
        $time = $now->format('H:i');
    
        $buildWindow = static function (Carbon $businessDate) use ($opening, $closing): array {
            $start = $businessDate->copy()->startOfDay()->setTimeFromTimeString($opening . ':00');
            $end = $businessDate->copy()->startOfDay()->setTimeFromTimeString($closing . ':00');
            if ($closing <= $opening) {
                $end->addDay();
            }
            return [
                'business_date' => $businessDate->copy()->startOfDay(),
                'start' => $start,
                'end' => $end,
                'opening_time' => $opening,
                'closing_time' => $closing,
            ];
        };
    
        if ($opening === $closing) {
            return $buildWindow($time >= $opening ? $today : $today->copy()->subDay());
        }
    
        if ($opening < $closing) {
            return $buildWindow($time < $opening ? $today->copy()->subDay() : $today);
        }
    
        if ($time >= $opening) {
            return $buildWindow($today);
        }
    
        return $buildWindow($today->copy()->subDay());
    }

    private function normalizePosTimeToMinute($time, string $fallback): string
    {
        if (empty($time)) {
            return $fallback;
        }

        try {
            return Carbon::parse((string) $time)->format('H:i');
        } catch (\Throwable $exception) {
            return $fallback;
        }
    }

    private function getPosClosedMessage(?string $openingTime = null): string
    {
        if (empty($openingTime)) {
            $openingTime = $this->getPosOrderWindowStatus()['opening_time'];
        }

        try {
            $formattedOpeningTime = Carbon::createFromFormat('H:i', $openingTime)->format('h:i A');
        } catch (\Throwable $exception) {
            $formattedOpeningTime = '12:01 PM';
        }

        return 'The restaurant is currently closed. Orders will be accepted from ' . $formattedOpeningTime . '.';
    }

    private function posOrderClosedResponse()
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getPosClosedMessage()
        ], 423);
    }

public function activateRandomHalfOrderList(Request $request)
    {
        if (!$this->userHasRoleCaseInsensitive($request->user(), 'manager')) {
            abort(403, 'Only a Manager can activate the Random Order List from POS.');
        }

        if (!Schema::hasTable('pos_settings')
            || !Schema::hasColumn('pos_settings', 'order_list_random_half_enabled')
            || !Schema::hasColumn('pos_settings', 'random_half_order_button_visible')
            || !Schema::hasColumn('pos_settings', 'random_order_hide_percentage')) {
            return redirect()
                ->route('pos.index')
                ->with('error', 'Please run the latest database migration first.');
        }

        return DB::transaction(function () {
            $posSetting = PosSetting::query()->lockForUpdate()->first();

            if (!$posSetting) {
                $posSetting = new PosSetting();
                $posSetting->random_order_hide_percentage = 50;
                $posSetting->random_half_order_button_visible = true;
            }

            if (!(bool) $posSetting->random_half_order_button_visible) {
                return redirect()->route('pos.index');
            }

            // The percentage is controlled only from Super Admin settings.
            // Manager's Go button applies that saved value directly without any popup/input.
            $posSetting->random_order_hide_percentage = max(
                1,
                min(100, (int) ($posSetting->random_order_hide_percentage ?? 50))
            );
            $posSetting->order_list_random_half_enabled = true;
            $posSetting->random_half_order_button_visible = false;
            $posSetting->save();

            return redirect()->route('pos.index');
        });
    }

    private function userHasRoleCaseInsensitive($user, string $roleName): bool
    {
        if (!$user) {
            return false;
        }

        return $user->getRoleNames()->contains(function ($assignedRole) use ($roleName) {
            return strcasecmp($assignedRole, $roleName) === 0;
        });
    }

private function reportableOrdersForSessionWindow(Carbon $start, Carbon $end)
{
    return Order::query()
        ->whereNotIn('status', ['Cancelled', 'cancelled'])
        ->whereBetween('created_at', [$start, $end]);
}

private function closePosSessionAt(PosSession $session, Carbon $endTime, ?int $endedByUserId = null): PosSession
{
    $startTime = Carbon::parse($session->start_time, 'Asia/Dhaka');
    $endTime = $endTime->copy()->setTimezone('Asia/Dhaka');

    if ($endTime->lt($startTime)) {
        $endTime = $startTime->copy();
    }

    $orders = $this->reportableOrdersForSessionWindow($startTime, $endTime)->get();

    $salesTotal = $orders->sum('subtotal');
    $serviceCharge = $orders->sum('service_charge');
    $vatTotal = $orders->sum('vat_tax');
    $grandTotal = $orders->sum('grand_total');

    $cash = 0;
    $card = 0;
    $mfc = 0;

    foreach ($orders as $order) {
        if ($order->payment_type === 'Split') {
            $cash += $order->paid_in_cash;
            $card += $order->paid_in_card;
            $mfc += $order->paid_in_mfc;
        } else {
            if ($order->payment_type === 'Cash') {
                $cash += $order->total_paid_amount;
            }
            if ($order->payment_type === 'Card') {
                $card += $order->total_paid_amount;
            }
            if ($order->payment_type === 'Mobile Banking') {
                $mfc += $order->total_paid_amount;
            }
        }
    }

    $duration = $endTime
        ->diffAsCarbonInterval($startTime)
        ->cascade()
        ->forHumans(['short' => true]);

    $updatePayload = [
        'end_time' => $endTime,
        'duration' => $duration,
        'status' => 'Closed',
        'sales_total' => $salesTotal,
        'service_charge' => $serviceCharge,
        'vat_total' => $vatTotal,
        'grand_total' => $grandTotal,
        'incomes_summary' => [
            'Cash' => $cash,
            'Card' => $card,
            'MFC' => $mfc,
        ],
    ];

    if ($endedByUserId && Schema::hasColumn('pos_sessions', 'ended_by_user_id')) {
        $updatePayload['ended_by_user_id'] = $endedByUserId;
    }

    $session->update($updatePayload);

    return $session->fresh();
}

private function closeTimedOutOpenSessionsForUser(int $userId): void
{
    // A POS work period now stays Open until an explicit End Session/manual close.
    // Login lifetime, browser inactivity, tab close and elapsed time must never close it.
    return;
}

private function touchOpenPosSessionActivity(int $userId): void
{
    if (!Schema::hasColumn('pos_sessions', 'last_activity_at')) {
        return;
    }

    DB::table('pos_sessions')
        ->where('user_id', $userId)
        ->where('status', 'Open')
        ->update(['last_activity_at' => Carbon::now('Asia/Dhaka')]);
}

public function touchSessionActivity(Request $request)
{
    $user = auth()->user();
    $sessionManagerId = $user
        ? app(PosSessionManagerResolver::class)->resolveId($user)
        : null;

    if ($sessionManagerId) {
        $this->closeTimedOutOpenSessionsForUser($sessionManagerId);
        $this->touchOpenPosSessionActivity($sessionManagerId);
    }

    return response()->json(['status' => 'success']);
}

/**
 * Read-only shared work-period status for POS browser synchronization.
 * This intentionally does not update last_activity_at; the existing heartbeat
 * remains responsible for activity while this endpoint only reports state.
 */
public function sessionStatus(Request $request)
{
    $user = auth()->user();
    $sessionManagerId = $user
        ? app(PosSessionManagerResolver::class)->resolveId($user)
        : null;

    if (!$sessionManagerId) {
        return response()->json([
            'status' => 'success',
            'active' => false,
            'session_id' => null,
            'start_time' => null,
        ]);
    }

    $this->closeTimedOutOpenSessionsForUser($sessionManagerId);

    $activeSession = PosSession::where('user_id', $sessionManagerId)
        ->where('status', 'Open')
        ->orderByDesc('id')
        ->first();

    return response()->json([
        'status' => 'success',
        'active' => (bool) $activeSession,
        'session_id' => $activeSession?->id,
        'start_time' => $activeSession && $activeSession->start_time
            ? $activeSession->start_time->format('Y-m-d H:i:s')
            : null,
    ]);
}

private function getPosSessionCloseBlockers(PosSession $session, ?Carbon $checkTime = null): array
{
    $activeStatuses = ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'];

    // Existing rule: ending/replacing/edit-closing a POS session is blocked while
    // any dine-in table is occupied. Unpaid/due bills are intentionally ignored.
    $activeOrderTableIds = Order::query()
        ->whereNotNull('table_id')
        ->whereIn('status', $activeStatuses)
        ->pluck('table_id');

    $persistedOccupiedTableIds = Table::query()
        ->whereRaw('LOWER(TRIM(initial_status)) = ?', ['occupied'])
        ->pluck('id');

    $occupiedTableIds = $activeOrderTableIds
        ->merge($persistedOccupiedTableIds)
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values();

    $occupiedTables = $occupiedTableIds->isEmpty()
        ? collect()
        : Table::query()
            ->whereIn('id', $occupiedTableIds->all())
            ->orderBy('table_number')
            ->get(['id', 'table_number']);

    // New rule: today's Pending Takeaway/Delivery orders also block Session End,
    // Session Edit -> Closed, and replacing an unfinished session with a new one.
    $today = ($checkTime ?: Carbon::now('Asia/Dhaka'))
        ->copy()
        ->timezone('Asia/Dhaka')
        ->toDateString();

    $pendingTakeawayDeliveryOrders = Order::query()
        ->whereDate('order_time', $today)
        ->whereRaw('LOWER(TRIM(status)) = ?', ['pending'])
        ->whereRaw(
            "LOWER(REPLACE(REPLACE(REPLACE(TRIM(order_type), '-', ''), ' ', ''), '_', '')) IN (?, ?)",
            ['takeaway', 'delivery']
        )
        ->orderBy('id')
        ->get(['id', 'order_number', 'order_type']);

    $messages = [];

    if ($occupiedTables->isNotEmpty()) {
        $tableNames = $occupiedTables
            ->pluck('table_number')
            ->filter(fn ($number) => trim((string) $number) !== '')
            ->take(6)
            ->map(fn ($number) => 'Table ' . $number)
            ->implode(', ');

        $occupiedText = $occupiedTables->count() . ' occupied table' . ($occupiedTables->count() === 1 ? '' : 's');
        if ($tableNames !== '') {
            $occupiedText .= ' (' . $tableNames . ($occupiedTables->count() > 6 ? ', ...' : '') . ')';
        }

        $messages[] = 'Please clear ' . $occupiedText . ' first.';
    }

    if ($pendingTakeawayDeliveryOrders->isNotEmpty()) {
        $orderLabels = $pendingTakeawayDeliveryOrders
            ->take(6)
            ->map(function ($order) {
                $number = trim((string) ($order->order_number ?? ''));
                return $number !== '' ? '#' . $number : 'Order #' . $order->id;
            })
            ->implode(', ');

        $pendingText = $pendingTakeawayDeliveryOrders->count()
            . ' pending Takeaway/Delivery order'
            . ($pendingTakeawayDeliveryOrders->count() === 1 ? '' : 's');

        if ($orderLabels !== '') {
            $pendingText .= ' (' . $orderLabels
                . ($pendingTakeawayDeliveryOrders->count() > 6 ? ', ...' : '') . ')';
        }

        $messages[] = 'Please complete or cancel today\'s ' . $pendingText . ' first.';
    }

    return [
        'blocked' => $occupiedTables->isNotEmpty() || $pendingTakeawayDeliveryOrders->isNotEmpty(),
        'occupied_table_count' => $occupiedTables->count(),
        'occupied_table_ids' => $occupiedTables->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        'pending_takeaway_delivery_count' => $pendingTakeawayDeliveryOrders->count(),
        'pending_takeaway_delivery_order_ids' => $pendingTakeawayDeliveryOrders
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        // Kept for response compatibility with the existing frontend.
        'unpaid_bill_count' => 0,
        'unpaid_order_ids' => [],
        'message' => empty($messages) ? '' : 'Session cannot be ended. ' . implode(' ', $messages),
    ];
}

private function sessionCloseBlockedResponse(array $blockers)
{
    return response()->json([
        'status' => 'error',
        'code' => 'session_close_blocked',
        'message' => $blockers['message'],
        'occupied_table_count' => $blockers['occupied_table_count'],
        'occupied_table_ids' => $blockers['occupied_table_ids'],
        'pending_takeaway_delivery_count' => $blockers['pending_takeaway_delivery_count'],
        'pending_takeaway_delivery_order_ids' => $blockers['pending_takeaway_delivery_order_ids'],
        'unpaid_bill_count' => $blockers['unpaid_bill_count'],
        'unpaid_order_ids' => $blockers['unpaid_order_ids'],
    ], 409);
}

public function startSession(Request $request)
{
    $actor = auth()->user();
    $actorId = (int) $actor->id;
    $manager = app(PosSessionManagerResolver::class)->resolve($actor);

    if (!$manager) {
        return response()->json([
            'status' => 'error',
            'code' => 'pos_manager_not_found',
            'message' => 'Manager user not found. Please assign the Manager/manager role to one user first.',
        ], 422);
    }

    $managerId = (int) $manager->id;
    $this->closeTimedOutOpenSessionsForUser($managerId);
    $action = strtolower((string) $request->input('action', 'start'));

    return DB::transaction(function () use ($actorId, $managerId, $action) {
        // Lock the Manager row, not the logged-in user. This guarantees that two
        // different users/browsers cannot create two shared work periods at once.
        DB::table('users')->where('id', $managerId)->lockForUpdate()->first();

        $activeSession = PosSession::where('user_id', $managerId)
            ->where('status', 'Open')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        // Once a shared Manager work period has started, even another Start/New request
        // must reuse it. Only the explicit End Session/manual close path can close it.
        if ($activeSession) {
            $this->touchOpenPosSessionActivity($managerId);
            session()->forget('force_pos_unfinished_prompt');

            return response()->json([
                'status' => 'success',
                'message' => 'Manager work period is already running.',
                'session_id' => $activeSession->id,
                'start_time' => optional($activeSession->start_time)->format('Y-m-d H:i:s'),
                'already_active' => true,
            ]);
        }

        $now = Carbon::now('Asia/Dhaka');
        $payload = [
            'user_id' => $managerId,
            'weekday' => $now->format('l'),
            'start_time' => $now,
            'status' => 'Open',
        ];

        if (Schema::hasColumn('pos_sessions', 'started_by_user_id')) {
            $payload['started_by_user_id'] = $actorId;
        }
        if (Schema::hasColumn('pos_sessions', 'ended_by_user_id')) {
            $payload['ended_by_user_id'] = null;
        }
        if (Schema::hasColumn('pos_sessions', 'last_activity_at')) {
            $payload['last_activity_at'] = $now;
        }

        $session = PosSession::create($payload);
        session()->forget('force_pos_unfinished_prompt');

        return response()->json([
            'status' => 'success',
            'message' => $action === 'new'
                ? 'Previous work period closed and a new Manager work period started.'
                : 'Manager work period started successfully!',
            'session_id' => $session->id,
            'start_time' => $now->format('Y-m-d H:i:s'),
            'already_active' => false,
        ]);
    });
}

public function endSession(Request $request)
{
    $actor = auth()->user();
    $actorId = (int) $actor->id;
    $managerId = app(PosSessionManagerResolver::class)->resolveId($actor);

    if (!$managerId) {
        return response()->json([
            'status' => 'error',
            'code' => 'pos_manager_not_found',
            'message' => 'Manager user not found.',
        ], 422);
    }

    $session = PosSession::where('id', $request->session_id)
        ->where('user_id', $managerId)
        ->where('status', 'Open')
        ->firstOrFail();

    $blockers = $this->getPosSessionCloseBlockers($session);
    if ($blockers['blocked']) {
        return $this->sessionCloseBlockedResponse($blockers);
    }

    $session = $this->closePosSessionAt($session, Carbon::now('Asia/Dhaka'), $actorId);
    session()->forget('force_pos_unfinished_prompt');

    return response()->json([
        'status' => 'success',
        'message' => 'Work period ended successfully!',
        'session_id' => $session->id,
    ]);
}

public function printSessionReport($id)
{
    $session = PosSession::with('user')->findOrFail($id);
    $restaurant = \App\Models\RestaurantSetting::first();
    $taxSetting = DB::table('tax_settings')->first();

    $sessionStart = Carbon::parse($session->start_time);
    $reportEnd = Carbon::parse($session->end_time ?: now());

    // Only the order creation time decides whether an order belongs to this session.
    // Completed is NOT required; Pending/Cooking/Ready orders are included too.
    $orders = $this->reportableOrdersForSessionWindow($sessionStart, $reportEnd)
        ->with(['orderDetails.foodItem.addons'])
        ->get();

    $productDiscount = $orders->sum(function ($order) {
        return (float) ($order->product_discount_amount ?? 0);
    });
    $honored = $orders->sum(function ($order) {
        return (float) ($order->discount_amount ?? 0);
    });

    // Session closing report keeps outlet sales and delivery-partner sales as
    // separate components so the printed Total Sales can include both without
    // double-counting partner orders.
    $outletOrders = $orders->filter(function ($order) {
        return empty($order->delivery_partner_id);
    });
    $deliveryPartnerOrders = $orders->filter(function ($order) {
        return !empty($order->delivery_partner_id);
    });

    $salesSummary = [
        'sales_total' => (float) $orders->sum('subtotal'),
        'outlet_sales' => (float) $outletOrders->sum('subtotal'),
        'delivery_partner_sales' => (float) $deliveryPartnerOrders->sum('subtotal'),
        'product_discount' => (float) $productDiscount,
        'honored' => (float) $honored,
        'discount_total' => (float) ($productDiscount + $honored),
        'service_charge' => (float) $orders->sum('service_charge'),
        'vat_total' => (float) $orders->sum('vat_tax'),
        // Explicitly combine outlet + delivery-partner final totals. This keeps
        // per-order rounding intact and guarantees partner sales are included.
        'grand_total' => (float) ($outletOrders->sum('grand_total') + $deliveryPartnerOrders->sum('grand_total')),
    ];

    $reportIncomes = ['Cash' => 0, 'Card' => 0, 'MFC' => 0];
    $cardProviderIncome = [];
    $mfsProviderIncome = [];
    $departmentIncome = ['dine_in' => 0, 'delivery' => 0, 'takeaway' => 0];
    $closingExtraSummary = [
        'complimentary' => 0,
        'due' => 0,
    ];

    $deliveryPartnerDue = [];
    $usedDeliveryPartnerIds = $deliveryPartnerOrders
        ->pluck('delivery_partner_id')
        ->filter()
        ->unique()
        ->values();

    $deliveryPartnerQuery = DeliveryPartner::query();
    if ($usedDeliveryPartnerIds->isNotEmpty()) {
        $deliveryPartnerQuery->where(function ($query) use ($usedDeliveryPartnerIds) {
            $query->where('status', 1)
                ->orWhereIn('id', $usedDeliveryPartnerIds->all());
        });
    } else {
        $deliveryPartnerQuery->where('status', 1);
    }

    $deliveryPartners = $deliveryPartnerQuery->orderBy('name')->get();
    foreach ($deliveryPartners as $partner) {
        $deliveryPartnerDue[$partner->id] = [
            'name' => $partner->name,
            'due' => 0,
        ];
    }

    $deliveryPartnerIncome = [];
    foreach ($deliveryPartners as $partner) {
        $deliveryPartnerIncome[$partner->id] = [
            'name' => $partner->name,
            'amount' => 0,
        ];
    }

    foreach ($orders as $order) {
        $orderDue = max(0, (float) ($order->due ?? 0));

        if (!empty($order->delivery_partner_id) && isset($deliveryPartnerDue[$order->delivery_partner_id])) {
            $deliveryPartnerDue[$order->delivery_partner_id]['due'] += $orderDue;
        } else {
            // Keep outlet/customer due separate from delivery-partner due so
            // the Total Due row does not count partner due twice.
            $closingExtraSummary['due'] += $orderDue;
        }

        if (!empty($order->delivery_partner_id) && isset($deliveryPartnerIncome[$order->delivery_partner_id])) {
            // Sales section is a component breakdown. Use subtotal here so
            // Service Charge, VAT and discounts can be shown separately below.
            $deliveryPartnerIncome[$order->delivery_partner_id]['amount'] += (float) ($order->subtotal ?? 0);
        }

        foreach ($order->orderDetails as $detail) {
            if (!empty($detail->is_unavailable)) {
                continue;
            }

            $isComplimentary = !empty($order->is_complimentary_order)
                || !empty($detail->is_complimentary)
                || ((float) ($detail->price ?? 0) <= 0 && (float) ($detail->subtotal ?? 0) <= 0);

            if (!$isComplimentary) {
                continue;
            }

            $food = $detail->foodItem;
            $foodPrice = $food ? (float) ($food->discount_price ?? $food->base_price ?? 0) : 0;
            $addonTotal = 0;
            $savedAddons = json_decode($detail->addons ?? '[]', true);

            if (is_array($savedAddons)) {
                $currentAddons = $food ? $food->addons->keyBy('id') : collect();
                foreach ($savedAddons as $addon) {
                    if (!is_array($addon)) {
                        continue;
                    }

                    $addonId = (int) ($addon['id'] ?? 0);
                    if ($addonId > 0 && $currentAddons->has($addonId)) {
                        $addonTotal += (float) ($currentAddons->get($addonId)->price ?? 0);
                    } else {
                        $addonTotal += max(0, (float) ($addon['price'] ?? 0));
                    }
                }
            }

            $quantity = max(1, (int) ($detail->quantity ?? 1));
            $closingExtraSummary['complimentary'] += ($foodPrice + $addonTotal) * $quantity;
        }

        // total_paid_amount may include booking advance. Method-wise fields are the money paid now.
        $cardContribution = 0;
        $mfsContribution = 0;
        if ($order->payment_type === 'Split') {
            $cashContribution = (float) ($order->paid_in_cash ?? 0);
            $cardContribution = (float) ($order->paid_in_card ?? 0);
            $mfsContribution = (float) ($order->paid_in_mfc ?? 0);
            $reportIncomes['Cash'] += $cashContribution;
            $reportIncomes['Card'] += $cardContribution;
            $reportIncomes['MFC'] += $mfsContribution;
        } else {
            $advance = max(0, (float) ($order->booking_advance ?? 0));
            $fallbackPaid = max(0, (float) ($order->total_paid_amount ?? 0) - $advance);

            if ($order->payment_type === 'Cash') {
                $reportIncomes['Cash'] += (float) ($order->paid_in_cash ?? 0) > 0
                    ? (float) $order->paid_in_cash
                    : $fallbackPaid;
            } elseif ($order->payment_type === 'Card') {
                $cardContribution = (float) ($order->paid_in_card ?? 0) > 0
                    ? (float) $order->paid_in_card
                    : $fallbackPaid;
                $reportIncomes['Card'] += $cardContribution;
            } elseif ($order->payment_type === 'Mobile Banking') {
                $mfsContribution = (float) ($order->paid_in_mfc ?? 0) > 0
                    ? (float) $order->paid_in_mfc
                    : $fallbackPaid;
                $reportIncomes['MFC'] += $mfsContribution;
            }
        }

        if ($cardContribution > 0) {
            $provider = trim((string) ($order->card_type ?? '')) ?: 'Unspecified';
            $cardProviderIncome[$provider] = ($cardProviderIncome[$provider] ?? 0) + $cardContribution;
        }
        if ($mfsContribution > 0) {
            $provider = trim((string) ($order->mfs_provider ?? '')) ?: 'Unspecified';
            $mfsProviderIncome[$provider] = ($mfsProviderIncome[$provider] ?? 0) + $mfsContribution;
        }

        $departmentKey = $this->normalizePosOrderType($order->order_type ?? 'dine_in');
        if (array_key_exists($departmentKey, $departmentIncome)) {
            $departmentIncome[$departmentKey] += (float) ($order->grand_total ?? 0);
        }
    }

    // Booking time (booking_date + booking_start_time), not booking record creation time,
    // decides which session owns the customer advance section.
    $bookingCandidates = TableBooking::where('advance_amount', '>', 0)
        ->whereBetween('booking_date', [$sessionStart->toDateString(), $reportEnd->toDateString()])
        ->get();

    $sessionBookings = $bookingCandidates->filter(function ($booking) use ($sessionStart, $reportEnd) {
        try {
            $date = $booking->booking_date instanceof \Carbon\CarbonInterface
                ? $booking->booking_date->format('Y-m-d')
                : Carbon::parse($booking->booking_date)->format('Y-m-d');

            $timeValue = $booking->booking_start_time ?: $booking->booking_time ?: '00:00:00';
            $time = $timeValue instanceof \Carbon\CarbonInterface
                ? $timeValue->format('H:i:s')
                : Carbon::parse((string) $timeValue)->format('H:i:s');

            $bookingAt = Carbon::parse($date . ' ' . $time);
            return $bookingAt->between($sessionStart, $reportEnd, true);
        } catch (\Throwable $exception) {
            return false;
        }
    });

    // Booking advance keeps its original payment method in the session income summary.
    foreach ($sessionBookings as $booking) {
        $advance = max(0, (float) ($booking->advance_amount ?? 0));
        $method = strtolower(trim((string) ($booking->advance_payment_method ?? '')));
        if ($method === 'cash') {
            $reportIncomes['Cash'] += $advance;
        } elseif ($method === 'card') {
            $reportIncomes['Card'] += $advance;
            $cardProviderIncome['Unspecified'] = ($cardProviderIncome['Unspecified'] ?? 0) + $advance;
        } elseif (in_array($method, ['mfs', 'mobile banking', 'mobile_banking'], true)) {
            $reportIncomes['MFC'] += $advance;
            $mfsProviderIncome['Unspecified'] = ($mfsProviderIncome['Unspecified'] ?? 0) + $advance;
        }
    }

    // Do NOT reduce table_bookings.advance_amount. Only Session Report's outstanding
    // Customer Advance is reduced when that booking is consumed by a completed POS order.
    $bookingIds = $sessionBookings->pluck('id');
    $consumed = $bookingIds->isEmpty() ? collect() : Order::query()
        ->whereIn('table_booking_id', $bookingIds)
        ->whereIn('status', ['Completed', 'completed'])
        ->where('booking_advance', '>', 0)
        ->selectRaw('table_booking_id, SUM(booking_advance) as used_advance')
        ->groupBy('table_booking_id')
        ->pluck('used_advance', 'table_booking_id');

    $customerAdvance = (float) $sessionBookings->sum(function ($booking) use ($consumed) {
        return max(0, (float) $booking->advance_amount - (float) ($consumed[$booking->id] ?? 0));
    });

    arsort($cardProviderIncome);
    arsort($mfsProviderIncome);

    return view('admin.pos.session_report', compact(
        'session',
        'restaurant',
        'taxSetting',
        'salesSummary',
        'reportIncomes',
        'cardProviderIncome',
        'mfsProviderIncome',
        'departmentIncome',
        'closingExtraSummary',
        'customerAdvance',
        'deliveryPartnerDue',
        'deliveryPartnerIncome'
    ));
}

    public function updateSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required',
            'start_time' => 'required',
            'status' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $session = PosSession::lockForUpdate()->findOrFail($request->session_id);
            $previousStatus = $session->status;
            $sessionUserId = $session->user_id;

            // Session Edit protection: an Open session cannot be changed to Closed
            // while any POS table is occupied. This server-side guard applies to every
            // edit UI that posts to pos.session.update, so it cannot be bypassed from
            // the POS inline editor, Session List modal, or Work Period Sessions modal.
            $previousStatusNormalized = strtolower(trim((string) $previousStatus));
            $requestedStatusNormalized = strtolower(trim((string) $request->status));
            $isClosingFromSessionEdit = $previousStatusNormalized !== 'closed'
                && $requestedStatusNormalized === 'closed';

            if ($isClosingFromSessionEdit) {
                $blockers = $this->getPosSessionCloseBlockers($session);
                if ($blockers['blocked']) {
                    DB::rollBack();
                    return $this->sessionCloseBlockedResponse($blockers);
                }
            }

            $startTime = Carbon::parse($request->start_time);
            $endTime = $request->end_time ? Carbon::parse($request->end_time) : null;
            $duration = null;

            // ডিফল্ট ভ্যালু সেট করা হচ্ছে
            $sales_total = 0;
            $service_charge = 0;
            $vat_total = 0;
            $grand_total = 0;
            $incomes = ['Cash' => 0, 'Card' => 0, 'MFC' => 0];

            // যদি সেশন Closed থাকে এবং এন্ড টাইম দেওয়া হয়, তবে নতুন সময় অনুযায়ী হিসাব রি-ক্যালকুলেট হবে
            if ($endTime && $request->status == 'Closed') {
                // ডিউরেশন ক্যালকুলেশন
                $durationDiff = $endTime->diffAsCarbonInterval($startTime);
                $duration = $durationDiff->cascade()->forHumans(['short' => true]);

                // নতুন এডিট করা সময় সীমার ভেতরের সব non-cancelled order নেওয়া হচ্ছে।
                // Completed হওয়া বাধ্যতামূলক নয়।
                $orders = $this->reportableOrdersForSessionWindow($startTime, $endTime)->get();

                $sales_total = $orders->sum('subtotal');
                $service_charge = $orders->sum('service_charge');
                $vat_total = $orders->sum('vat_tax');
                $grand_total = $orders->sum('grand_total');

                // নতুন করে পেমেন্ট মেথড সামারি তৈরি করা হচ্ছে
                $cash = 0; $card = 0; $mfc = 0;
                foreach($orders as $order) {
                    if ($order->payment_type == 'Split') {
                        $cash += $order->paid_in_cash;
                        $card += $order->paid_in_card;
                        $mfc += $order->paid_in_mfc;
                    } else {
                        if ($order->payment_type == 'Cash') $cash += $order->total_paid_amount;
                        if ($order->payment_type == 'Card') $card += $order->total_paid_amount;
                        if ($order->payment_type == 'Mobile Banking') $mfc += $order->total_paid_amount;
                    }
                }

                $incomes = [
                    'Cash' => $cash,
                    'Card' => $card,
                    'MFC'  => $mfc
                ];
            }

            // ডাটাবেজে আপডেট
            $updatePayload = [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'weekday' => $startTime->format('l'),
                'status' => $request->status,
                'duration' => $duration,
                'sales_total' => $request->status == 'Closed' ? $sales_total : 0,
                'service_charge' => $request->status == 'Closed' ? $service_charge : 0,
                'vat_total' => $request->status == 'Closed' ? $vat_total : 0,
                'grand_total' => $request->status == 'Closed' ? $grand_total : 0,
                'incomes_summary' => $request->status == 'Closed' ? $incomes : null,
            ];

            if (Schema::hasColumn('pos_sessions', 'ended_by_user_id')) {
                $updatePayload['ended_by_user_id'] = $request->status == 'Closed'
                    ? (int) auth()->id()
                    : null;
            }

            $session->update($updatePayload);

            // Closing/editing a session must never auto-start another one.
            // The next work period is started explicitly from the POS Start Session button.
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Session updated and report recalculated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    public function getFoods(Request $request)
    {
        $posSetting = DB::table('pos_settings')->first();
        $limit = $posSetting ? ($posSetting->items_per_page ?? 12) : 12;

        $query = FoodItem::with('addons')->where('is_available', 1);

        if ($request->category_id) {
            $query->where('food_category_id', $request->category_id)->orWhere('sub_category_id', $request->category_id);
        }
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $foods = $query->get();
        return view('admin.pos.partials.food_grid', compact('foods'))->render();
    }

    public function getAddons($id)
    {
        $food = FoodItem::with('addons')->findOrFail($id);
        return response()->json(['status' => 'success', 'food' => $food]);
    }

    // ====================================================
    // টেবিল অনুযায়ী আলাদা কার্ট তৈরি করার হেল্পার মেথড
    // ====================================================
   private function normalizePosOrderType($orderType): string
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', trim((string) $orderType)));

        if (in_array($normalized, ['dine_in', 'dinein'], true)) {
            return 'dine_in';
        }

        if (in_array($normalized, ['takeaway', 'take_away'], true)) {
            return 'takeaway';
        }

        if ($normalized === 'delivery') {
            return 'delivery';
        }

        return $normalized ?: 'dine_in';
    }

   private function getCartKey(Request $request)
    {
        $orderType = $this->normalizePosOrderType($request->order_type ?? 'dine_in');

        // Existing Takeaway/Delivery order থেকে Add More/Complimentary করলে order-wise cart রাখতে হবে,
        // না হলে একাধিক non-table order এক কার্টে mix হয়ে যাবে।
        if (in_array($orderType, ['takeaway', 'delivery'], true)) {
            if ($request->filled('order_id')) {
                return 'pos_cart_order_' . $request->order_id;
            }

            return $orderType === 'delivery' ? 'pos_cart_delivery' : 'pos_cart_takeaway';
        }

        return 'pos_cart_table_' . $request->table_id;
    }

    private function getExistingOrderCartKey(Order $order): string
    {
        $orderType = $this->normalizePosOrderType($order->order_type ?? 'dine_in');

        if (in_array($orderType, ['takeaway', 'delivery'], true)) {
            return 'pos_cart_order_' . $order->id;
        }

        return 'pos_cart_table_' . $order->table_id;
    }

    // ====================================================
    // পুরো অর্ডার Complimentary হলে cart-এর সব item/addon price 0 করা হবে
    // Existing offcanvas complimentary flow আলাদা থাকবে, এই helper শুধু new order complimentary flag পেলে কাজ করবে।
    // ====================================================
    private function makeCartComplimentary(array $cart): array
    {
        foreach ($cart as &$item) {
            $item['price'] = 0;
            $item['addon_total'] = 0;
            $item['is_complimentary'] = true;

            if (!empty($item['addons']) && is_array($item['addons'])) {
                foreach ($item['addons'] as &$addon) {
                    $addon['price'] = 0;
                }
                unset($addon);
            }
        }
        unset($item);

        return $cart;
    }

    /**
     * Build a stable key for customer-facing order rows.
     * Add More Food keeps separate KOT detail rows for kitchen history, while
     * identical food configurations are merged into one POS/invoice row.
     */
    private function orderDetailDisplayMergeKey(OrderDetail $detail): string
    {
        $addons = json_decode($detail->addons ?? '[]', true);
        if (!is_array($addons)) {
            $addons = [];
        }

        $addons = array_values(array_map(function ($addon) {
            return [
                'id' => (int) ($addon['id'] ?? 0),
                'name' => (string) ($addon['name'] ?? ''),
                'price' => round((float) ($addon['price'] ?? 0), 4),
            ];
        }, array_filter($addons, 'is_array')));

        usort($addons, function ($a, $b) {
            return [$a['id'], $a['name'], $a['price']] <=> [$b['id'], $b['name'], $b['price']];
        });

        $isComplimentary = !empty($detail->is_complimentary)
            || ((float) ($detail->price ?? 0) <= 0 && (float) ($detail->subtotal ?? 0) <= 0);

        return sha1(json_encode([
            'product_id' => (int) ($detail->product_id ?? 0),
            'product_name' => (string) ($detail->product_name ?? ''),
            'price' => round((float) ($detail->price ?? 0), 4),
            'addons' => $addons,
            'note' => trim((string) ($detail->food_note ?? '')),
            'complimentary_note' => trim((string) ($detail->complimentary_note ?? '')),
            'complimentary' => $isComplimentary ? 1 : 0,
            'unavailable' => !empty($detail->is_unavailable) ? 1 : 0,
            'product_discount_type' => (string) ($detail->product_discount_type ?? ''),
            'product_discount_value' => round((float) ($detail->product_discount_value ?? 0), 4),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Merge identical order detail rows for POS/offcanvas and invoice display only.
     * The original per-KOT rows remain untouched so every kitchen KOT stays accurate.
     */
    private function mergeOrderDetailsForDisplay($details)
    {
        $groups = [];

        foreach (collect($details)->sortBy('id') as $detail) {
            $key = $this->orderDetailDisplayMergeKey($detail);

            if (!isset($groups[$key])) {
                $groups[$key] = (object) [
                    'id' => (int) $detail->id,
                    'detail_ids' => [(int) $detail->id],
                    'order_kot_id' => $detail->order_kot_id ? (int) $detail->order_kot_id : null,
                    'display_kot_id' => $detail->order_kot_id ? (int) $detail->order_kot_id : null,
                    'product_id' => (int) ($detail->product_id ?? 0),
                    'product_name' => (string) ($detail->product_name ?? ''),
                    'quantity' => (int) ($detail->quantity ?? 0),
                    'price' => (float) ($detail->price ?? 0),
                    'subtotal' => (float) ($detail->subtotal ?? 0),
                    'addons' => $detail->addons ?? '[]',
                    'food_note' => $detail->food_note ?? null,
                    'complimentary_note' => $detail->complimentary_note ?? null,
                    'is_complimentary' => !empty($detail->is_complimentary) ? 1 : 0,
                    'is_unavailable' => !empty($detail->is_unavailable) ? 1 : 0,
                    'product_discount_type' => $detail->product_discount_type ?? null,
                    'product_discount_value' => (float) ($detail->product_discount_value ?? 0),
                    'product_discount_amount' => (float) ($detail->product_discount_amount ?? 0),
                ];
                continue;
            }

            $row = $groups[$key];
            $row->id = (int) $detail->id;
            $row->detail_ids[] = (int) $detail->id;
            $row->display_kot_id = $detail->order_kot_id ? (int) $detail->order_kot_id : $row->display_kot_id;
            $row->quantity += (int) ($detail->quantity ?? 0);
            $row->subtotal += (float) ($detail->subtotal ?? 0);
            $row->product_discount_amount += (float) ($detail->product_discount_amount ?? 0);
        }

        return collect(array_values($groups));
    }

    private function requestedOrderDetailIds(Request $request): array
    {
        $ids = collect(explode(',', (string) $request->input('order_detail_ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0);

        if ($request->filled('order_detail_id')) {
            $ids->push((int) $request->input('order_detail_id'));
        }

        return $ids->unique()->values()->all();
    }

    public function addToCart(Request $request)
{
    if (!$this->getPosOrderWindowStatus()['is_open']) {
        return $this->posOrderClosedResponse();
    }

    $cartKey = $this->getCartKey($request);
    $cart = Session::get($cartKey, []);

    $food = FoodItem::findOrFail($request->food_id);

    $isComplimentary = $request->boolean('is_complimentary');
    if ($isComplimentary && ($passwordError = $this->validatePosActionPassword($request->input('action_password')))) {
        return $passwordError;
    }

    $complimentaryNote = $isComplimentary
        ? trim((string) $request->input('complimentary_note', ''))
        : '';
    $price = $isComplimentary ? 0 : ($food->discount_price ?? $food->base_price);
    $addonTotal = 0;
    $addons = [];

    if ($request->addons) {
        foreach ($request->addons as $addonId) {
            $addon = \App\Models\FoodAddon::find($addonId);
            if ($addon) {
                $addons[] = [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => $isComplimentary ? 0 : $addon->price
                ];
                $addonTotal += $isComplimentary ? 0 : $addon->price;
            }
        }
    }

    // Same addon combination detect করার জন্য sort করা হলো
    usort($addons, function ($a, $b) {
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    });

    $newQty = (int) ($request->qty ?? 1);
    $existingCartId = null;

    foreach ($cart as $cartId => $item) {
        $itemAddons = $item['addons'] ?? [];

        usort($itemAddons, function ($a, $b) {
            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
        });

        if (
            (int) $item['food_id'] === (int) $food->id &&
            (bool) ($item['is_complimentary'] ?? false) === $isComplimentary &&
            trim((string) ($item['complimentary_note'] ?? '')) === $complimentaryNote &&
            json_encode($itemAddons) === json_encode($addons)
        ) {
            $existingCartId = $cartId;
            break;
        }
    }

    if ($existingCartId !== null) {
        // Product আগে থেকেই cart-এ থাকলে শুধু quantity বাড়বে; position change হবে না
        $cart[$existingCartId]['qty'] += $newQty;
    } else {
        // নতুন product হলে নতুন line add হবে
        $cartId = uniqid();

        $cart[$cartId] = [
            'food_id' => $food->id,
            'name' => $food->name,
            'qty' => $newQty,
            'price' => $price,
            'addon_total' => $addonTotal,
            'addons' => $addons,
            'is_complimentary' => $isComplimentary,
            'complimentary_note' => $isComplimentary && $complimentaryNote !== '' ? $complimentaryNote : null,
            'note' => ''
        ];
    }

    Session::put($cartKey, $cart);

    return response()->json(['status' => 'success']);
}

    public function getCart(Request $request)
    {
        $cartKey = $this->getCartKey($request);
        $cart = Session::get($cartKey, []);

        $subtotal = 0;
        foreach($cart as $item) {
            $subtotal += ($item['price'] + $item['addon_total']) * $item['qty'];
        }

        // Latest added item list-এর শুরুতে দেখানোর জন্য render করার আগে reverse করা হলো
        $cart = array_reverse($cart, true);

        $taxSetting = DB::table('tax_settings')->first();
        $vat_rate = $taxSetting ? $taxSetting->vat_rate : 0;

        // নতুন লজিক: শুধু Dine-In হলে সার্ভিস চার্জ পাবে, Takeaway/Delivery তে 0 হবে
        $service_charge_rate = ($this->normalizePosOrderType($request->order_type ?? 'dine_in') === 'dine_in') ? ($taxSetting->service_charge ?? 0) : 0;

        $complimentaryNoteRequired = (bool) (PosSetting::first()?->complimentary_note_required ?? false);

        return view('admin.pos.partials.cart_items', compact(
            'cart',
            'subtotal',
            'vat_rate',
            'service_charge_rate',
            'complimentaryNoteRequired'
        ))->render();
    }

    /**
     * Generate global KOT serial number.
     * KOT number will continue across all orders: KOT-1, KOT-2, KOT-3...
     */
    private function generateGlobalKotNumber()
    {
        $lastKotNumber = OrderKot::where('kot_number', 'like', 'KOT-%')
            ->selectRaw("MAX(CAST(REPLACE(kot_number, 'KOT-', '') AS UNSIGNED)) as max_number")
            ->value('max_number');

        return 'KOT-' . (((int) $lastKotNumber) + 1);
    }

    /**
     * Resolve the advance already collected for a dine-in table booking.
     * The booking amount remains on the booking record for audit/reporting;
     * the order stores the applied amount so it is deducted only from the bill due.
     */
private function resolveTableBookingForOrder(Request $request, $tableId, $customerId): ?TableBooking
{
    if (!$tableId || !Schema::hasColumn('table_bookings', 'advance_amount')) {
        return null;
    }

    $bookingId = (int) $request->input('table_booking_id', 0);
    $bdNow = Carbon::now('Asia/Dhaka');
    $currentTime = $bdNow->format('H:i:s');

    // Booking association is automatic from the selected table and is valid only
    // while the reservation window is active. No visible Table Booking selector is needed.
    $query = TableBooking::where('table_id', $tableId)
        ->whereIn('status', ['upcoming', 'confirmed'])
        ->whereDate('booking_date', $bdNow->toDateString())
        ->where(function ($time) use ($currentTime) {
            $time->whereNull('booking_start_time')
                ->orWhereTime('booking_start_time', '<=', $currentTime);
        })
        ->where(function ($time) use ($currentTime) {
            $time->whereNull('booking_end_time')
                ->orWhereTime('booking_end_time', '>=', $currentTime);
        });

    // A Go-to-POS booking id is only an extra safety constraint; the active time window is authoritative.
    if ($bookingId > 0) {
        $query->where('id', $bookingId);
    }

    $booking = $query->orderBy('booking_start_time')->first();
    if (!$booking) {
        return null;
    }

    // Never apply the same booking advance to another live/completed non-cancelled order.
    if (Schema::hasColumn('orders', 'table_booking_id') && Schema::hasColumn('orders', 'booking_advance')) {
        $usedBookingQuery = Order::where('table_booking_id', $booking->id)
            ->where('booking_advance', '>', 0)
            ->whereNotIn('status', ['Cancelled', 'cancelled']);

        if ($request->filled('order_id')) {
            $usedBookingQuery->where('id', '!=', (int) $request->input('order_id'));
        }

        if ($usedBookingQuery->exists()) {
            return null;
        }
    }

    return $booking;
}

public function placeOrder(Request $request)
    {
        if (!$this->getPosOrderWindowStatus()['is_open']) {
            return $this->posOrderClosedResponse();
        }

        $actor = auth()->user();
        $sessionManagerId = app(PosSessionManagerResolver::class)->resolveId($actor);

        if ($sessionManagerId) {
            $this->closeTimedOutOpenSessionsForUser($sessionManagerId);
        }

        $activeSession = $sessionManagerId
            ? PosSession::where('user_id', $sessionManagerId)
                ->where('status', 'Open')
                ->exists()
            : false;

        if (!$activeSession) {
            return response()->json([
                'status' => 'error',
                'code' => 'pos_session_required',
                'message' => 'Please start the POS session before creating an order.',
            ], 409);
        }

        $cartKey = $this->getCartKey($request);
        $cart = Session::get($cartKey, []);

        if (count($cart) == 0) {
            return response()->json(['status' => 'error', 'message' => 'Cart is empty!']);
        }

        $requestOrderTypeForValidation = $this->normalizePosOrderType($request->order_type ?? 'dine_in');
        $posPreference = PosSetting::first();
        if ($requestOrderTypeForValidation === 'dine_in' && (bool) ($posPreference->dine_in_waiter_required ?? false)) {
            $effectiveWaiterId = (int) ($request->waiter_id ?: 0);

            // Add More / existing-order submissions may omit waiter_id; keep the already assigned waiter valid.
            if ($effectiveWaiterId <= 0 && $request->filled('order_id')) {
                $effectiveWaiterId = (int) (Order::whereKey($request->order_id)->value('waiter_id') ?: 0);
            }

            if ($effectiveWaiterId <= 0 || !Waiter::whereKey($effectiveWaiterId)->where('status', 1)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please assign a waiter before starting a Dine-In order.'
                ], 422);
            }
        }

        // New Order modal থেকে Complimentary Order select করলে cart-এর সব item free হবে।
        // Offcanvas-এর Add Complimentary আগের মতো individual complimentary item হিসেবেই থাকবে।
        $isComplimentaryOrder = $request->boolean('is_complimentary_order');
        if ($isComplimentaryOrder) {
            if ($passwordError = $this->validatePosActionPassword($request->input('action_password'))) {
                return $passwordError;
            }

            $cart = $this->makeCartComplimentary($cart);
        }

        // Complimentary notes for cart-added food are product-wise. The visible cart
        // food Note is mirrored to complimentary_note so it is stored on order_details.
        $complimentaryNoteRequired = (bool) ($posPreference->complimentary_note_required ?? false);
        foreach ($cart as $cartId => $item) {
            if (empty($item['is_complimentary'])) {
                continue;
            }

            $complimentaryNote = trim((string) ($item['note'] ?? ''));
            if ($complimentaryNote === '') {
                // Backward-compatible fallback for carts created by an older page.
                $complimentaryNote = trim((string) ($item['complimentary_note'] ?? ''));
            }

            if ($complimentaryNoteRequired && $complimentaryNote === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please enter a note for every complimentary food before sending to kitchen.'
                ], 422);
            }

            $cart[$cartId]['complimentary_note'] = $complimentaryNote !== '' ? $complimentaryNote : null;
        }
        Session::put($cartKey, $cart);

        // Track whether this submission is adding food to an already-running order.
        // KOTs created from Add More are allowed to delete their items without the POS action password.
        $isAddMoreFlow = false;

        DB::beginTransaction();
        try {
            // ইউজারের রোল অনুযায়ী স্ট্যাটাস নির্ধারণ
            $isWaiter = auth()->user()->hasRole('waiter');
            $newStatus = $isWaiter ? 'Waiter_Hold' : 'Pending';

            // ১. কার্টে থাকা আইটেমের টোটাল হিসাব করা
            $current_cart_subtotal = 0;
            foreach ($cart as $item) {
                $current_cart_subtotal += ($item['price'] + $item['addon_total']) * $item['qty'];
            }

            $taxSetting = DB::table('tax_settings')->first();
            $vat_rate = $taxSetting->vat_rate ?? 0;
            $service_charge_rate = ($this->normalizePosOrderType($request->order_type ?? 'dine_in') === 'dine_in') ? ($taxSetting->service_charge ?? 0) : 0;
            $discount_value = $request->discount_value ?? 0;
            $discount_type = $request->discount_type ?? 'fixed';

            $order_type_val = 'Dine-In';
            $requestOrderType = $this->normalizePosOrderType($request->order_type ?? 'dine_in');
            if($requestOrderType == 'takeaway') $order_type_val = 'Takeaway';
            if($requestOrderType == 'delivery') $order_type_val = 'Delivery';

            $deliveryPartner = $requestOrderType === 'delivery' ? (int) ($request->delivery_partner ?: 0) : null;

            if ($requestOrderType === 'delivery' && !DeliveryPartner::where('id',$deliveryPartner)->exists()) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please select a valid delivery partner.'
                ], 422);
            }

            if ($request->filled('order_id')) {
                $order = Order::findOrFail($request->order_id);

                // চেক করা হচ্ছে অর্ডারে আগে থেকেই কোনো একটিভ (Pending/Cooking/Ready) KOT আছে কি না
                $hasActiveKots = OrderKot::where('order_id', $order->id)->where('kitchen_status', '!=', 'Hold')->exists();

                if (!$hasActiveKots) {
                    // ==========================================
                    // অবস্থা ১: সম্পূর্ণ ফ্রেশ অর্ডার (শুধু Hold/QR KOT আছে)
                    // ==========================================
                    $service_charge = round(($current_cart_subtotal * $service_charge_rate) / 100);
                    $tax = round((($current_cart_subtotal + $service_charge) * $vat_rate) / 100);
                    $discount_amount = round(($discount_type == 'percentage') ? ($current_cart_subtotal * $discount_value) / 100 : $discount_value);
                    $grand_total = round(($current_cart_subtotal + $tax + $service_charge) - $discount_amount);

                    $customerId = $order->customer_id;
                    if ($request->is_walk_in == '0') {
                        if ($request->customer_id) {
                            $customerId = $request->customer_id;
                        } else if ($request->customer_name) {
                            $newCustomer = Customer::create([
                                'name' => $request->customer_name,
                                'phone' => $request->customer_phone
                            ]);
                            $customerId = $newCustomer->id;
                        }
                    } elseif ($request->has('is_walk_in')) {
                        $customerId = null;
                    }

                    $booking = $requestOrderType === 'dine_in'
                        ? $this->resolveTableBookingForOrder($request, $order->table_id ?: $request->table_id, $customerId)
                        : null;
                    $bookingAdvance = Schema::hasColumn('orders', 'booking_advance')
                        ? max(0, (float) ($booking->advance_amount ?? $order->booking_advance ?? 0))
                        : 0;

                    $orderUpdateData = [
                        'customer_id' => $customerId,
                        'waiter_id' => $request->waiter_id ?: $order->waiter_id,
                        'user_id' => $order->user_id ?: (auth()->id() ?? 1),
                        'order_type' => $order_type_val,
                        'subtotal' => $current_cart_subtotal,
                        'discount_amount' => $discount_amount,
                        'discount_type' => $discount_type,
                        'vat_tax' => $tax,
                        'service_charge' => $service_charge,
                        'grand_total' => $grand_total,
                        'due' => max(0, $grand_total - $bookingAdvance),
                        'status' => $newStatus,
                        'notes' => $request->order_notes ?? $order->notes,
                        'preparation_time' => $request->preparation_time ?? 20
                    ];

                    if (Schema::hasColumn('orders', 'booking_advance')) {
                        $orderUpdateData['booking_advance'] = $bookingAdvance;
                    }
                    if (Schema::hasColumn('orders', 'table_booking_id')) {
                        $orderUpdateData['table_booking_id'] = $booking->id ?? $order->table_booking_id ?? null;
                    }
                    if (Schema::hasColumn('orders', 'is_complimentary_order')) {
                        $orderUpdateData['is_complimentary_order'] = $isComplimentaryOrder ? 1 : 0;
                    }
                    if (Schema::hasColumn('orders', 'delivery_partner')) {
                        $orderUpdateData['delivery_partner'] = $deliveryPartner;
                    }
                    if (Schema::hasColumn('orders', 'delivery_partner_id')) {
                        $orderUpdateData['delivery_partner_id'] = $deliveryPartner;
                    }

                    $order->update($orderUpdateData);

                    // ডুপ্লিকেট রোধে আগের Hold আইটেম মুছে ফেলা হলো
                    OrderDetail::where('order_id', $order->id)->delete();
                    OrderKot::where('order_id', $order->id)->delete();

                    if ($order->table_id) {
                        Table::where('id', $order->table_id)->update(['initial_status' => 'Occupied']);
                    }

                } else {
                    // ==========================================
                    // অবস্থা ২: Add More Food (আগে থেকেই কিচেনে রান্না চলছে, ওয়েটার নতুন খাবার যোগ করেছে)
                    // ==========================================
                    $isAddMoreFlow = true;

                    // ১. ফ্রন্ট ডেস্ক যখন কার্ট এপ্রুভ করবে, তখন আগের তৈরি হওয়া ডামি 'Hold' KOT ডিলিট করে সাবটোটাল মাইনাস করতে হবে (যাতে ডাবল বিল না হয়)
                    $holdKots = OrderKot::where('order_id', $order->id)->where('kitchen_status', 'Hold')->get();
                    if ($holdKots->count() > 0) {
                        $holdSubtotal = 0;
                        foreach ($holdKots as $hk) {
                            $hk_details = OrderDetail::where('order_kot_id', $hk->id)->get();
                            foreach($hk_details as $hkd) {
                                $holdSubtotal += $hkd->subtotal;
                            }
                            OrderDetail::where('order_kot_id', $hk->id)->delete();
                            $hk->delete();
                        }
                        $order->subtotal -= $holdSubtotal;
                    }

                    // ২. নতুন কার্টের অ্যামাউন্ট অর্ডারের সাথে যোগ করা
                    $total_subtotal = $order->subtotal + $current_cart_subtotal;
                    $service_charge = round(($total_subtotal * $service_charge_rate) / 100);
                    $tax = round((($total_subtotal + $service_charge) * $vat_rate) / 100);
                    $discount_amount = round(($discount_type == 'percentage') ? ($total_subtotal * $discount_value) / 100 : $discount_value);
                    $grand_total = round(($total_subtotal + $tax + $service_charge) - $discount_amount);

                    $orderUpdateData = [
                        'subtotal' => $total_subtotal,
                        'discount_amount' => $discount_amount,
                        'discount_type' => $discount_type,
                        'vat_tax' => $tax,
                        'service_charge' => $service_charge,
                        'grand_total' => $grand_total,
                        'preparation_time' => $request->preparation_time ?? 20,
                        'due' => max(0, $grand_total - (Schema::hasColumn('orders', 'booking_advance') ? (float) ($order->booking_advance ?? 0) : 0)),
                        'status' => $newStatus // ওয়েটার করলে Waiter_Hold, ফ্রন্ট ডেস্ক করলে Pending হবে
                    ];

                    // Add More Food থেকে শুধু complimentary item add হলে পুরো পুরনো order complimentary করা হবে না।
                    if (Schema::hasColumn('orders', 'is_complimentary_order') && $isComplimentaryOrder) {
                        $orderUpdateData['is_complimentary_order'] = 1;
                    }
                    if (Schema::hasColumn('orders', 'delivery_partner') && $requestOrderType === 'delivery') {
                        $orderUpdateData['delivery_partner'] = $deliveryPartner;
                    }
                    if (Schema::hasColumn('orders', 'delivery_partner_id') && $requestOrderType === 'delivery') {
                        $orderUpdateData['delivery_partner_id'] = $deliveryPartner;
                    }

                    $order->update($orderUpdateData);
                }

            } else {
                // ==========================================
                // অবস্থা ৩: একদম নতুন অর্ডার
                // ==========================================
                $service_charge = round(($current_cart_subtotal * $service_charge_rate) / 100);
                $tax = round((($current_cart_subtotal + $service_charge) * $vat_rate) / 100);
                $discount_amount = round(($discount_type == 'percentage') ? ($current_cart_subtotal * $discount_value) / 100 : $discount_value);
                $grand_total = round(($current_cart_subtotal + $tax + $service_charge) - $discount_amount);

                $customerId = null;
                if ($request->is_walk_in == '0') {
                    if ($request->customer_id) {
                        $customerId = $request->customer_id;
                    } else if ($request->customer_name) {
                        $newCustomer = Customer::create(['name' => $request->customer_name, 'phone' => $request->customer_phone]);
                        $customerId = $newCustomer->id;
                    }
                }

                $booking = $requestOrderType === 'dine_in'
                    ? $this->resolveTableBookingForOrder($request, $request->table_id, $customerId)
                    : null;
                $bookingAdvance = Schema::hasColumn('orders', 'booking_advance')
                    ? max(0, (float) ($booking->advance_amount ?? 0))
                    : 0;

                $orderCreateData = [
                    'customer_id' => $customerId,
                    'table_id' => in_array($requestOrderType, ['takeaway', 'delivery'], true) ? null : $request->table_id,
                    'waiter_id' => $request->waiter_id,
                    'user_id' => auth()->id() ?? 1,
                    'order_type' => $order_type_val,
                    'subtotal' => $current_cart_subtotal,
                    'discount_amount' => $discount_amount,
                    'discount_type' => $discount_type,
                    'vat_tax' => $tax,
                    'service_charge' => $service_charge,
                    'grand_total' => $grand_total,
                    'due' => max(0, $grand_total - $bookingAdvance),
                    'status' => $newStatus,
                    'notes' => $request->order_notes,
                    'order_time' => now(),
                    'preparation_time' => $request->preparation_time ?? 20
                ];

                if (Schema::hasColumn('orders', 'booking_advance')) {
                    $orderCreateData['booking_advance'] = $bookingAdvance;
                }
                if (Schema::hasColumn('orders', 'table_booking_id')) {
                    $orderCreateData['table_booking_id'] = $booking->id ?? null;
                }
                if (Schema::hasColumn('orders', 'is_complimentary_order')) {
                    $orderCreateData['is_complimentary_order'] = $isComplimentaryOrder ? 1 : 0;
                }
                if (Schema::hasColumn('orders', 'delivery_partner')) {
                    $orderCreateData['delivery_partner'] = $deliveryPartner;
                }
                if (Schema::hasColumn('orders', 'delivery_partner_id')) {
                    $orderCreateData['delivery_partner_id'] = $deliveryPartner;
                }

                $order = Order::create($orderCreateData);

                if ($requestOrderType == 'dine_in') {
                    Table::where('id', $request->table_id)->update(['initial_status' => 'Occupied']);
                }
            }

            // নতুন KOT জেনারেট করা (Global serial: KOT-1, KOT-2, KOT-3...)
            $kotNumber = $this->generateGlobalKotNumber();
            $kotData = [
                'order_id' => $order->id,
                'kot_number' => $kotNumber,
                'kitchen_status' => $isWaiter ? 'Hold' : 'Pending' // ওয়েটার হলে হোল্ড হবে
            ];
            if (Schema::hasColumn('order_kots', 'is_add_more')) {
                $kotData['is_add_more'] = $isAddMoreFlow ? 1 : 0;
            }
            $kot = OrderKot::create($kotData);

            // কার্টের আইটেমগুলো নতুন KOT-তে সেভ করা
            foreach ($cart as $item) {
                $detailData = [
                    'order_id' => $order->id,
                    'order_kot_id' => $kot->id,
                    'product_id' => $item['food_id'],
                    'product_name' => $item['name'],
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => ($item['price'] + $item['addon_total']) * $item['qty'],
                    'addons' => json_encode($item['addons']),
                    'food_note' => $item['note'] ?? null
                ];

                if (Schema::hasColumn('order_details', 'is_complimentary')) {
                    $detailData['is_complimentary'] = !empty($item['is_complimentary']) ? 1 : 0;
                }
                if (Schema::hasColumn('order_details', 'complimentary_note')) {
                    $detailData['complimentary_note'] = !empty($item['is_complimentary'])
                        ? (trim((string) ($item['complimentary_note'] ?? '')) ?: null)
                        : null;
                }

                OrderDetail::create($detailData);
            }

            Session::forget($cartKey);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $isWaiter ? 'Order Sent to Front Desk!' : 'Food Added to Order! (' . $kotNumber . ')',
                'kot_id' => $kot->id,
                'redirect_url' => $isWaiter ? route('pos.index') : route('kitchen.print_kot', ['id' => $kot->id, 'source' => 'pos'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }


    public function getTableOrder($table_id)
    {
        // Cooking এবং Ready স্টেটাস যুক্ত করা হলো যাতে সব ধরনের রানিং অর্ডার পাওয়া যায়
        $order = Order::with(['kots.orderDetails', 'orderDetails', 'waiter', 'customer', 'table', 'tableBooking', 'deliveryPartner'])
                      ->where('table_id', $table_id)
                      ->whereIn('status', ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'])
                      ->first();

        if(!$order) return response()->json(['status' => 'error', 'message' => 'No active order found.']);

        $isWaiter = auth()->user()->hasRole('waiter');

        // চেক করা হচ্ছে অর্ডারে কোনো Hold KOT আছে কি না (অর্থাৎ ওয়েটার নতুন কিছু অ্যাড করেছে কি না)
        $holdKots = $order->kots->where('kitchen_status', 'Hold');

        // ফ্রন্ট ডেস্ক যদি ক্লিক করে এবং কোনো Hold KOT থাকে, তবে সরাসরি কার্টে লোড হবে (অফক্যানভাস নয়)
        if ($holdKots->count() > 0 && !$isWaiter) {
            $cart = [];

            // শুধুমাত্র Hold হওয়া আইটেমগুলো কার্টে নিয়ে আসা হচ্ছে
            foreach ($holdKots as $kot) {
                foreach ($kot->orderDetails as $detail) {
                    if (!empty($detail->is_unavailable)) continue;

                    $addons = json_decode($detail->addons ?? '[]', true);
                    if (!is_array($addons)) $addons = [];

                    $addonTotal = 0;
                    foreach ($addons as $addon) {
                        $addonTotal += (float) ($addon['price'] ?? 0);
                    }

                    $qty = (int) ($detail->quantity ?: 1);
                    $price = (float) ($detail->price ?? 0);

                    if ($price <= 0 && $qty > 0) {
                        $price = round(((float) $detail->subtotal / $qty) - $addonTotal, 2);
                    }

                    if ($price < 0) {
                        $price = 0;
                    }

                    $cart['wh_' . $detail->id] = [
                        'food_id' => $detail->product_id,
                        'name' => $detail->product_name,
                        'qty' => $qty,
                        'price' => $price,
                        'addon_total' => $addonTotal,
                        'addons' => $addons,
                        'is_complimentary' => !empty($detail->is_complimentary),
                        'note' => $detail->food_note ?? ''
                    ];
                }
            }

            Session::put($this->getExistingOrderCartKey($order), $cart);

            return response()->json([
                'status' => 'load_cart',
                'order_data' => [
                    'order_id' => $order->id,
                    'order_type' => 'dine_in',
                    'table_id' => $order->table_id,
                    'table_number' => $order->table->table_number ?? ('Table ' . $order->table_id),
                    'waiter_id' => $order->waiter_id,
                    'waiter_name' => $order->waiter->name ?? '',
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer->name ?? 'Walk-in Customer',
                    'customer_phone' => $order->customer->phone ?? '',
                    'is_walk_in' => $order->customer_id ? 0 : 1,
                    'notes' => $order->notes ?? '',
                    'delivery_partner' => $order->resolved_delivery_partner_id ?? null,
                    'delivery_partner_name' => $order->delivery_partner_display_name,
                    'subtotal' => $order->subtotal
                ]
            ]);
        }

        // যদি কোনো Hold KOT না থাকে (সব কিচেনে চলে গেছে), বা ওয়েটার নিজে ক্লিক করে, তবে আগের মতোই অফক্যানভাস খুলবে
        $finalPaymentDependsOnKitchenStatus = (bool) (DB::table('pos_settings')->value('final_payment_depends_on_kitchen_status') ?? 0);

        // Setting YES হলে Ready না হওয়া পর্যন্ত Payment disabled থাকবে।
        $kitchenBusy = $finalPaymentDependsOnKitchenStatus
            ? $order->kots()->whereIn('kitchen_status', ['Pending', 'Cooking', 'Hold'])->exists()
            : false;

        $activeStatuses = ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'];
        $availableSwapTables = Table::with('zone')
            ->where('id', '!=', $order->table_id)
            ->whereRaw('LOWER(TRIM(initial_status)) = ?', ['available'])
            ->whereNotIn('id', Order::select('table_id')
                ->whereNotNull('table_id')
                ->whereIn('status', $activeStatuses)
                ->where('id', '!=', $order->id)
            )
            ->orderBy('table_number', 'asc')
            ->get();

        $customers = $order->customer_id
            ? collect()
            : Customer::orderBy('name', 'asc')->get(['id', 'name', 'phone']);
        $waiters = Waiter::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $deliveryPartners = DeliveryPartner::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $mergedOrderItems = $this->mergeOrderDetailsForDisplay($order->orderDetails);
        $mergedOrderItemsByKot = $mergedOrderItems->groupBy('display_kot_id');

        return view('admin.pos.partials.offcanvas_order', compact('order', 'kitchenBusy', 'finalPaymentDependsOnKitchenStatus', 'availableSwapTables', 'customers', 'waiters', 'deliveryPartners', 'mergedOrderItems', 'mergedOrderItemsByKot'))->render();
    }

public function tableReservationStatuses()
{
    $bdNow = Carbon::now('Asia/Dhaka');
    $today = $bdNow->toDateString();
    $currentTime = $bdNow->format('H:i:s');

    $activeTableIds = Order::query()
        ->whereNotNull('table_id')
        ->whereIn('status', ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'])
        ->pluck('table_id')
        ->map(fn ($id) => (int) $id)
        ->flip();

    $bookingsByTable = TableBooking::with('customer')
        ->whereIn('status', ['upcoming', 'confirmed'])
        ->whereDate('booking_date', $today)
        ->where(function ($query) use ($currentTime) {
            $query->whereNull('booking_start_time')
                ->orWhereTime('booking_start_time', '<=', $currentTime);
        })
        ->where(function ($query) use ($currentTime) {
            $query->whereNull('booking_end_time')
                ->orWhereTime('booking_end_time', '>=', $currentTime);
        })
        ->orderBy('booking_start_time')
        ->get()
        ->groupBy('table_id')
        ->map(fn ($bookings) => $bookings->first());

    $states = Table::query()->get(['id'])->map(function ($table) use ($activeTableIds, $bookingsByTable) {
        $tableId = (int) $table->id;

        if ($activeTableIds->has($tableId)) {
            return ['table_id' => $tableId, 'status' => 'occupied', 'booking_id' => null, 'customer_id' => null];
        }

        $booking = $bookingsByTable->get($tableId);
        if ($booking) {
            return [
                'table_id' => $tableId,
                'status' => 'reserved',
                'booking_id' => (int) $booking->id,
                'customer_id' => $booking->customer_id ? (int) $booking->customer_id : null,
            ];
        }

        return ['table_id' => $tableId, 'status' => 'available', 'booking_id' => null, 'customer_id' => null];
    })->values();

    return response()->json([
        'status' => 'success',
        'server_time' => $bdNow->toIso8601String(),
        'tables' => $states,
    ]);
}

        public function swapTable(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'new_table_id' => 'required|integer|exists:tables,id',
        ]);

        DB::beginTransaction();

        try {
            $activeStatuses = ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'];

            $order = Order::where('id', $request->order_id)
                ->whereIn('status', $activeStatuses)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Active order not found or already completed.'
                ], 404);
            }

            $orderType = $this->normalizePosOrderType($order->order_type ?? 'dine_in');
            if ($orderType !== 'dine_in') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Table swap is allowed only for Dine-In orders.'
                ], 422);
            }

            $oldTableId = $order->table_id;
            $newTableId = (int) $request->new_table_id;

            if (!$oldTableId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'This order has no assigned table.'
                ], 422);
            }

            if ((int) $oldTableId === $newTableId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please select a different table.'
                ], 422);
            }

            $oldTable = Table::where('id', $oldTableId)->lockForUpdate()->first();
            $newTable = Table::where('id', $newTableId)->lockForUpdate()->first();

            if (!$newTable) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected table was not found.'
                ], 404);
            }

            $newTableStatus = strtolower(trim((string) $newTable->initial_status));
            if ($newTableStatus !== 'available') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected table is not available for swap.'
                ], 422);
            }

            $targetHasActiveOrder = Order::where('table_id', $newTableId)
                ->where('id', '!=', $order->id)
                ->whereIn('status', $activeStatuses)
                ->exists();

            if ($targetHasActiveOrder) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selected table already has an active order.'
                ], 422);
            }

            $order->table_id = $newTableId;
            $order->save();

            if (Schema::hasColumn('order_kots', 'table_id')) {
                OrderKot::where('order_id', $order->id)->update(['table_id' => $newTableId]);
            }

            $newTable->initial_status = 'Occupied';
            $newTable->save();
            $newTable->loadMissing('zone');

            if ($oldTable) {
                $oldTableStillHasActiveOrder = Order::where('table_id', $oldTable->id)
                    ->where('id', '!=', $order->id)
                    ->whereIn('status', $activeStatuses)
                    ->exists();

                if (!$oldTableStillHasActiveOrder) {
                    $oldTable->initial_status = 'Available';
                    $oldTable->save();
                }
            }

            $oldCartKey = 'pos_cart_table_' . $oldTableId;
            $newCartKey = 'pos_cart_table_' . $newTableId;

            if ($oldCartKey !== $newCartKey && Session::has($oldCartKey)) {
                $oldCart = Session::get($oldCartKey, []);
                $newCart = Session::get($newCartKey, []);

                if (!is_array($oldCart)) $oldCart = [];
                if (!is_array($newCart)) $newCart = [];

                Session::put($newCartKey, array_replace($newCart, $oldCart));
                Session::forget($oldCartKey);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Table swapped successfully.',
                'order_id' => $order->id,
                'old_table_id' => (int) $oldTableId,
                'old_table_number' => $oldTable->table_number ?? null,
                'new_table_id' => $newTable->id,
                'new_table_number' => $newTable->table_number,
                'new_table_meta' => ($newTable->zone->name ?? 'Main') . ' · ' . ($newTable->seating_capacity ?? 0) . ' seats',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Table swap failed! ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateActiveOrderMeta(Request $request)
    {
        if (!$this->getPosOrderWindowStatus()['is_open']) {
            return $this->posOrderClosedResponse();
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'update_type' => 'required|in:customer,delivery_partner,waiter',
            'customer_mode' => 'nullable|in:existing,new,walk_in',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'delivery_partner' => 'nullable|integer|exists:delivery_partners,id',
            'waiter_id' => 'nullable|integer|exists:waiters,id',
        ]);

        if ($request->update_type === 'customer') {
            $customerMode = $request->input('customer_mode', 'existing');

            if ($customerMode === 'new') {
                $request->validate([
                    'customer_name' => 'required|string|max:255',
                    'customer_phone' => 'required|string|max:20',
                ]);
            } elseif ($customerMode === 'existing') {
                $request->validate([
                    'customer_id' => 'required|integer|exists:customers,id',
                ]);
            }
        } elseif ($request->update_type === 'delivery_partner') {
            $request->validate([
                'delivery_partner' => 'required|integer|exists:delivery_partners,id',
            ]);
        } else {
            $request->validate([
                'waiter_id' => 'required|integer|exists:waiters,id',
            ]);
        }

        DB::beginTransaction();

        try {
            $activeStatuses = ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'];
            $order = Order::where('id', $request->order_id)
                ->whereIn('status', $activeStatuses)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Active order not found or payment is already completed.'
                ], 404);
            }

            // payment_type has a database default of "Cash", even before any payment is made.
            // Treat the order as payment-started only when an actual paid amount exists.
            $hasPayment = (float) ($order->total_paid_amount ?? 0) > 0
                || (float) ($order->paid_in_cash ?? 0) > 0
                || (float) ($order->paid_in_card ?? 0) > 0
                || (float) ($order->paid_in_mfc ?? 0) > 0;

            if ($hasPayment) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer, waiter or delivery partner cannot be changed after payment has started.'
                ], 422);
            }

            if ($request->update_type === 'customer') {
                $customerMode = $request->input('customer_mode', 'existing');

                if ($customerMode === 'walk_in') {
                    $order->customer_id = null;
                    $order->save();

                    DB::commit();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Order changed back to Walk-in Customer successfully.',
                        'customer' => null,
                    ]);
                }

                if ($order->customer_id) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This order already has a customer. Change it back to Walk-in first if you want to assign another customer.'
                    ], 422);
                }

                if ($customerMode === 'new') {
                    $customer = Customer::create([
                        'name' => trim((string) $request->customer_name),
                        'phone' => trim((string) $request->customer_phone),
                        'email' => $request->filled('customer_email') ? trim((string) $request->customer_email) : null,
                    ]);
                } else {
                    $customer = Customer::findOrFail($request->customer_id);
                }

                $order->customer_id = $customer->id;
                $order->save();

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Customer added to the order successfully.',
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                    ],
                ]);
            }

            if ($request->update_type === 'waiter') {
                $waiter = Waiter::where('id', $request->waiter_id)
                    ->where('status', 1)
                    ->first();

                if (!$waiter) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please select an active waiter.'
                    ], 422);
                }

                $order->waiter_id = $waiter->id;
                $order->save();

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Waiter updated successfully.',
                    'waiter' => ['id' => $waiter->id, 'name' => $waiter->name],
                ]);
            }

            if ($this->normalizePosOrderType($order->order_type ?? '') !== 'delivery') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Delivery partner can only be changed for Delivery orders.'
                ], 422);
            }

            $deliveryPartner = DeliveryPartner::whereKey((int) $request->delivery_partner)
                ->where('status', 1)
                ->first();

            if (!$deliveryPartner) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please select an active delivery partner.'
                ], 422);
            }

            if (Schema::hasColumn('orders', 'delivery_partner')) {
                $order->delivery_partner = $deliveryPartner->id;
            }
            if (Schema::hasColumn('orders', 'delivery_partner_id')) {
                $order->delivery_partner_id = $deliveryPartner->id;
            }
            $order->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery partner updated successfully.',
                'delivery_partner' => $deliveryPartner->id,
                'delivery_partner_name' => $deliveryPartner->name,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Order details update failed! ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPosOrder($order_id)
    {
        $order = Order::with(['kots.orderDetails', 'orderDetails', 'waiter', 'customer', 'table', 'deliveryPartner'])
            ->where('id', $order_id)
            ->whereIn('status', ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'])
            ->first();

        if(!$order) return response()->json(['status' => 'error', 'message' => 'No active order found.']);

        $orderType = $this->normalizePosOrderType($order->order_type ?? 'dine_in');
        if (!in_array($orderType, ['takeaway', 'delivery'], true)) {
            return response()->json(['status' => 'error', 'message' => 'This order should be opened from table view.']);
        }

        $isWaiter = auth()->user()->hasRole('waiter');
        $holdKots = $order->kots->where('kitchen_status', 'Hold');

        // Front Desk যদি Waiter Hold item approve করে, order-wise cart-এ load হবে।
        if ($holdKots->count() > 0 && !$isWaiter) {
            $cart = [];

            foreach ($holdKots as $kot) {
                foreach ($kot->orderDetails as $detail) {
                    if (!empty($detail->is_unavailable)) continue;

                    $addons = json_decode($detail->addons ?? '[]', true);
                    if (!is_array($addons)) $addons = [];

                    $addonTotal = 0;
                    foreach ($addons as $addon) {
                        $addonTotal += (float) ($addon['price'] ?? 0);
                    }

                    $qty = (int) ($detail->quantity ?: 1);
                    $price = (float) ($detail->price ?? 0);

                    if ($price <= 0 && $qty > 0) {
                        $price = round(((float) $detail->subtotal / $qty) - $addonTotal, 2);
                    }

                    if ($price < 0) {
                        $price = 0;
                    }

                    $cart['wh_' . $detail->id] = [
                        'food_id' => $detail->product_id,
                        'name' => $detail->product_name,
                        'qty' => $qty,
                        'price' => $price,
                        'addon_total' => $addonTotal,
                        'addons' => $addons,
                        'is_complimentary' => !empty($detail->is_complimentary),
                        'note' => $detail->food_note ?? ''
                    ];
                }
            }

            Session::put($this->getExistingOrderCartKey($order), $cart);

            return response()->json([
                'status' => 'load_cart',
                'order_data' => [
                    'order_id' => $order->id,
                    'order_type' => $orderType,
                    'table_id' => null,
                    'table_number' => $orderType === 'delivery' ? 'Delivery' : 'Takeaway',
                    'waiter_id' => $order->waiter_id,
                    'waiter_name' => $order->waiter->name ?? '',
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer->name ?? 'Walk-in Customer',
                    'customer_phone' => $order->customer->phone ?? '',
                    'is_walk_in' => $order->customer_id ? 0 : 1,
                    'notes' => $order->notes ?? '',
                    'delivery_partner' => $order->resolved_delivery_partner_id ?? null,
                    'delivery_partner_name' => $order->delivery_partner_display_name,
                    'subtotal' => $order->subtotal
                ]
            ]);
        }

        $finalPaymentDependsOnKitchenStatus = (bool) (DB::table('pos_settings')->value('final_payment_depends_on_kitchen_status') ?? 0);

        $kitchenBusy = $finalPaymentDependsOnKitchenStatus
            ? $order->kots()->whereIn('kitchen_status', ['Pending', 'Cooking', 'Hold'])->exists()
            : false;

        $customers = $order->customer_id
            ? collect()
            : Customer::orderBy('name', 'asc')->get(['id', 'name', 'phone']);
        $waiters = Waiter::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $deliveryPartners = DeliveryPartner::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $mergedOrderItems = $this->mergeOrderDetailsForDisplay($order->orderDetails);
        $mergedOrderItemsByKot = $mergedOrderItems->groupBy('display_kot_id');

        return view('admin.pos.partials.offcanvas_order', compact('order', 'kitchenBusy', 'finalPaymentDependsOnKitchenStatus', 'customers', 'waiters', 'deliveryPartners', 'mergedOrderItems', 'mergedOrderItemsByKot'))->render();
    }

    public function holdWebOrder(Request $request)
{
    if (!$this->getPosOrderWindowStatus()['is_open']) {
        return $this->posOrderClosedResponse();
    }

    $request->validate([
        'id' => 'required|exists:orders,id',
        'waiter_id' => 'required|exists:waiters,id',
        'preparation_time' => 'nullable|integer|min:1',
    ]);

    DB::beginTransaction();

    try {
        $order = Order::with(['orderDetails', 'table', 'waiter', 'customer'])
            ->lockForUpdate()
            ->findOrFail($request->id);

        $normalizedStatus = str_replace(['-', ' '], '_', strtolower(trim($order->status ?? '')));

        if (!in_array($normalizedStatus, ['qr_pending', 'qr'])) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'This web order is not available for hold.'
            ]);
        }

        if (!$order->table_id) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Table information is missing for this web order.'
            ]);
        }

        $customerType = $request->customer_type ?? 'walk_in';
        $customerId = $order->customer_id;

        if ($customerType === 'existing') {
            if (!$request->customer_id) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Please select an existing customer.'
                ]);
            }

            $customerId = $request->customer_id;
        } elseif ($customerType === 'new') {
            if (!$request->customer_name) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Please enter customer name.'
                ]);
            }

            $newCustomer = Customer::create([
                'name' => $request->customer_name,
                'phone' => $request->customer_phone
            ]);

            $customerId = $newCustomer->id;
        } else {
            $customerId = null;
        }

        $cart = [];

        foreach ($order->orderDetails as $detail) {
            if (!empty($detail->is_unavailable)) {
                continue;
            }

            $addons = json_decode($detail->addons ?? '[]', true);

            if (!is_array($addons)) {
                $addons = [];
            }

            $addonTotal = 0;

            foreach ($addons as $addon) {
                $addonTotal += (float) ($addon['price'] ?? 0);
            }

            $qty = (int) ($detail->quantity ?: 1);
            $price = (float) ($detail->price ?? 0);

            if ($price <= 0 && $qty > 0) {
                $price = round(((float) $detail->subtotal / $qty) - $addonTotal, 2);
            }

            if ($price < 0) {
                $price = 0;
            }

            $cart['qr_' . $detail->id] = [
                'food_id' => $detail->product_id,
                'name' => $detail->product_name,
                'qty' => $qty,
                'price' => $price,
                'addon_total' => $addonTotal,
                'addons' => $addons,
                'note' => $detail->food_note ?? ''
            ];
        }

        if (count($cart) == 0) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'This web order has no available items to hold.'
            ]);
        }

        $cartKey = 'pos_cart_table_' . $order->table_id;
        Session::put($cartKey, $cart);

        $order->customer_id = $customerId;
        $order->waiter_id = $request->waiter_id;
        $order->preparation_time = $request->preparation_time ?? 20;
        $order->user_id = $order->user_id ?: (auth()->id() ?? 1);
        $order->status = 'QR_Hold';
        $order->save();

        Table::where('id', $order->table_id)->update([
            'initial_status' => 'Occupied'
        ]);

        $order->load(['table', 'waiter', 'customer']);

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Web order moved to POS cart. You can edit it before sending to kitchen.',
            'order_id' => $order->id,
            'table_id' => $order->table_id,
            'table_number' => $order->table->table_number ?? ('Table ' . $order->table_id),
            'waiter_id' => $order->waiter_id,
            'waiter_name' => $order->waiter->name ?? '',
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer->name ?? 'Walk-in Customer',
            'customer_phone' => $order->customer->phone ?? '',
            'is_walk_in' => $order->customer_id ? 0 : 1,
            'notes' => $order->notes ?? ''
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

    public function completePendingTakeawayDeliveryPayments(Request $request)
    {
        DB::beginTransaction();

        try {
            $orders = Order::whereIn('order_type', ['Takeaway', 'Delivery', 'takeaway', 'delivery', 'Take Away'])
                ->whereIn('status', ['Pending', 'pending'])
                ->lockForUpdate()
                ->get();

            if ($orders->count() === 0) {
                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'No pending Takeaway / Delivery order found.',
                    'completed_count' => 0,
                    'completed_ids' => []
                ]);
            }

            $completedIds = [];

            foreach ($orders as $order) {
                $grandTotal = max(0, round((float) ($order->grand_total ?? 0), 2));

                $order->payment_type = 'Cash';
                $order->transaction_id = null;
                $order->status = 'Completed';
                $order->due = 0;
                $order->total_paid_amount = $grandTotal;
                $order->paid_in_cash = $grandTotal;
                $order->paid_in_card = 0;
                $order->paid_in_mfc = 0;

                if (Schema::hasColumn('orders', 'tips_amount')) {
                    $order->tips_amount = 0;
                }
                if (Schema::hasColumn('orders', 'given_money')) {
                    $order->given_money = $grandTotal;
                }
                if (Schema::hasColumn('orders', 'change_amount')) {
                    $order->change_amount = 0;
                }

                if (Schema::hasColumn('orders', 'kitchen_to_payment_minutes')) {
                    $firstKitchenSentAt = OrderKot::where('order_id', $order->id)
                        ->where('kitchen_status', '!=', 'Hold')
                        ->orderBy('created_at', 'asc')
                        ->value('created_at');

                    $order->kitchen_to_payment_minutes = $firstKitchenSentAt
                        ? Carbon::parse($firstKitchenSentAt)->diffInMinutes(now())
                        : null;
                }

                $order->save();

                OrderKot::where('order_id', $order->id)
                    ->where('kitchen_status', '!=', 'Delivered')
                    ->update(['kitchen_status' => 'Delivered']);

                $completedIds[] = $order->id;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => count($completedIds) . ' pending Takeaway / Delivery order payment completed successfully.',
                'completed_count' => count($completedIds),
                'completed_ids' => $completedIds
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Pending Takeaway / Delivery payment completion failed! ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate product-wise discounts for the current order lines.
     * The existing whole-order discount remains independent and unchanged.
     */
    private function calculateProductWiseDiscounts(Order $order, array $requestedDiscounts, bool $persist = false): array
    {
        $details = $order->relationLoaded('orderDetails')
            ? $order->orderDetails
            : $order->orderDetails()->get();

        $total = 0.0;
        $breakdown = [];

        foreach ($details as $detail) {
            if (!empty($detail->is_unavailable)) {
                continue;
            }

            $config = $requestedDiscounts[(string) $detail->id]
                ?? $requestedDiscounts[$detail->id]
                ?? [];

            $type = ($config['type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
            $value = max(0, (float) ($config['value'] ?? 0));
            $lineSubtotal = max(0, (float) ($detail->subtotal ?? 0));

            if ($type === 'percentage') {
                $value = min($value, 100);
                $amount = round(($lineSubtotal * $value) / 100);
            } else {
                $amount = min($value, $lineSubtotal);
            }

            $amount = max(0, round($amount));
            $total += $amount;

            $detail->product_discount_type = $amount > 0 ? $type : null;
            $detail->product_discount_value = $amount > 0 ? $value : 0;
            $detail->product_discount_amount = $amount;

            if ($persist) {
                $detail->save();
            }

            $breakdown[$detail->id] = [
                'type' => $detail->product_discount_type,
                'value' => (float) $detail->product_discount_value,
                'amount' => (float) $detail->product_discount_amount,
            ];
        }

        return [
            'total' => round($total),
            'items' => $breakdown,
        ];
    }

    private function requestHasProductWiseDiscount(array $requestedDiscounts): bool
    {
        foreach ($requestedDiscounts as $config) {
            if (is_array($config) && max(0, (float) ($config['value'] ?? 0)) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the exact billing snapshot used by a pre-invoice.
     * When $persist is true, the discount/tax totals are saved so reopening POS
     * and the later final invoice keep the pre-invoice calculation.
     */
    private function buildPreInvoiceSnapshot(Order $order, array $input, bool $persist = false): array
    {
        $order->loadMissing(['orderDetails', 'customer', 'waiter', 'table', 'deliveryPartner']);

        $taxSetting = DB::table('tax_settings')->first();
        $subtotal = (float) ($order->subtotal ?? 0);
        $existingSnapshot = is_array($order->pre_invoice_snapshot ?? null) ? $order->pre_invoice_snapshot : [];
        $defaultDiscType = $existingSnapshot['discount_type'] ?? ($order->discount_type ?? 'fixed');
        $discType = (($input['disc_type'] ?? $input['discount_type'] ?? $defaultDiscType) === 'percentage') ? 'percentage' : 'fixed';

        $storedDiscValue = 0.0;
        if (Schema::hasColumn('orders', 'discount_value')) {
            $storedDiscValue = max(0, (float) ($order->discount_value ?? 0));
        }
        if ($storedDiscValue <= 0 && isset($existingSnapshot['discount_value'])) {
            $storedDiscValue = max(0, (float) $existingSnapshot['discount_value']);
        }
        if ($storedDiscValue <= 0 && $discType === 'percentage' && $subtotal > 0 && (float) ($order->discount_amount ?? 0) > 0) {
            $storedDiscValue = round(((float) $order->discount_amount / $subtotal) * 100, 2);
        }

        if (array_key_exists('disc_val', $input)) {
            $discValue = max(0, (float) $input['disc_val']);
        } elseif (array_key_exists('discount_value', $input)) {
            $discValue = max(0, (float) $input['discount_value']);
        } else {
            $discValue = $discType === 'percentage' ? $storedDiscValue : max(0, (float) ($order->discount_amount ?? 0));
        }

        $requestedProductDiscounts = $input['product_discounts'] ?? null;
        if (!is_array($requestedProductDiscounts)) {
            $requestedProductDiscounts = [];
            foreach ($order->orderDetails as $detail) {
                if ((float) ($detail->product_discount_value ?? 0) > 0) {
                    $requestedProductDiscounts[$detail->id] = [
                        'type' => ($detail->product_discount_type ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed',
                        'value' => (float) $detail->product_discount_value,
                    ];
                }
            }
        }

        $productDiscountResult = $this->calculateProductWiseDiscounts($order, $requestedProductDiscounts, $persist);
        $productDiscountAmount = (float) ($productDiscountResult['total'] ?? 0);

        $vatRate = (float) ($taxSetting->vat_rate ?? 0);
        $normalizedOrderType = strtolower(str_replace([' ', '-'], '_', (string) ($order->order_type ?? '')));
        $serviceRate = in_array($normalizedOrderType, ['dine_in', 'dinein'], true)
            ? (float) ($taxSetting->service_charge ?? 0)
            : 0;

        $serviceCharge = round(($subtotal * $serviceRate) / 100);
        $vatTax = round((($subtotal + $serviceCharge) * $vatRate) / 100);
        $discountAmount = round($discType === 'percentage'
            ? ($subtotal * min($discValue, 100)) / 100
            : min($discValue, $subtotal));
        $grandTotal = max(0, round(($subtotal + $vatTax + $serviceCharge) - $discountAmount - $productDiscountAmount));

        // Pre-invoice remark is part of the saved billing snapshot so it can be
        // restored when the Final Payment modal is opened later.
        $remark = trim((string) ($input['remark'] ?? ($existingSnapshot['remark'] ?? '')));

        $snapshotItems = $order->orderDetails
            ->filter(fn ($detail) => empty($detail->is_unavailable))
            ->map(function ($detail) {
                return [
                    'order_detail_id' => (int) $detail->id,
                    'product_id' => $detail->product_id ? (int) $detail->product_id : null,
                    'product_name' => (string) ($detail->product_name ?? ''),
                    'quantity' => (int) ($detail->quantity ?? 0),
                    'price' => (float) ($detail->price ?? 0),
                    'subtotal' => (float) ($detail->subtotal ?? 0),
                    'addons' => json_decode($detail->addons ?? '[]', true) ?: [],
                    'food_note' => $detail->food_note,
                    'complimentary_note' => $detail->complimentary_note,
                    'is_complimentary' => !empty($detail->is_complimentary),
                    'product_discount_type' => $detail->product_discount_type,
                    'product_discount_value' => (float) ($detail->product_discount_value ?? 0),
                    'product_discount_amount' => (float) ($detail->product_discount_amount ?? 0),
                ];
            })
            ->values()
            ->all();

        $snapshot = [
            'version' => 1,
            'printed_at' => now()->toIso8601String(),
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'table' => optional($order->table)->table_number,
            'customer' => optional($order->customer)->name ?: 'Walk-in Customer',
            'waiter' => optional($order->waiter)->name,
            'delivery_partner' => $order->delivery_partner_display_name,
            'items' => $snapshotItems,
            'subtotal' => $subtotal,
            'discount_type' => $discType,
            'discount_value' => $discValue,
            'discount_amount' => $discountAmount,
            'product_discounts' => $productDiscountResult['items'] ?? [],
            'product_discount_amount' => $productDiscountAmount,
            'service_charge_rate' => $serviceRate,
            'service_charge' => $serviceCharge,
            'vat_rate' => $vatRate,
            'vat_tax' => $vatTax,
            'grand_total' => $grandTotal,
            'remark' => $remark,
        ];

        if ($persist) {
            $order->discount_type = $discType;
            $order->discount_amount = $discountAmount;
            if (Schema::hasColumn('orders', 'discount_value')) {
                $order->discount_value = $discType === 'percentage' ? $discValue : 0;
            }
            $order->product_discount_amount = $productDiscountAmount;
            $order->service_charge = $serviceCharge;
            $order->vat_tax = $vatTax;
            $order->grand_total = $grandTotal;

            if (Schema::hasColumn('orders', 'pre_invoice_snapshot')) {
                $order->pre_invoice_snapshot = $snapshot;
            }
            if (Schema::hasColumn('orders', 'pre_invoice_printed_at')) {
                $order->pre_invoice_printed_at = now();
            }

            $order->save();
        }

        return $snapshot;
    }

    public function savePreInvoiceSnapshot(Request $request, $id)
    {
        $request->validate([
            'disc_type' => 'nullable|in:fixed,percentage',
            'disc_val' => 'nullable|numeric|min:0',
            'product_discounts' => 'nullable|array',
            'remark' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::with('orderDetails')->lockForUpdate()->findOrFail($id);
            $snapshot = $this->buildPreInvoiceSnapshot($order, $request->all(), true);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'snapshot' => $snapshot,
                'preview_url' => route('pos.pre_invoice', ['id' => $order->id]),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Pre-invoice could not be saved. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function completePayment(Request $request)
    {
        if (!$request->filled('order_id')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment is allowed only from an active order offcanvas. Please send the order to kitchen first.'
            ]);
        }

        $selectedPaymentMethod = $request->input('payment_method');
        $allowedCardTypes = ['Visa', 'Mastercard', 'American Express', 'UnionPay', 'JCB', 'Nexus', 'Diners Club', 'GPay', 'Other'];
        $allowedMfsProviders = ['Rocket', 'bKash', 'MYCash', 'Islami Bank mCash', 'tap', 'FirstCash', 'Upay', 'OK Wallet', 'RUPALICASH', 'TeleCash', 'Islamic Wallet', 'Meghna Pay', 'Nagad', 'LENDEN', 'Other'];

        if ($selectedPaymentMethod === 'Card'
            && !in_array(trim((string) $request->input('card_type')), $allowedCardTypes, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select a valid card type.'
            ], 422);
        }

        if ($selectedPaymentMethod === 'Mobile Banking'
            && !in_array(trim((string) $request->input('mfs_provider')), $allowedMfsProviders, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select a valid MFS service.'
            ], 422);
        }

        if (in_array($selectedPaymentMethod, ['Card', 'Mobile Banking'], true)
            && trim((string) $request->input('transaction_id')) === '') {
            $referenceLabel = $selectedPaymentMethod === 'Card'
                ? 'Bank / Card Reference Number'
                : 'MFS Reference Number';

            return response()->json([
                'status' => 'error',
                'message' => $referenceLabel . ' is required.'
            ], 422);
        }

        if ($selectedPaymentMethod === 'Split') {
            $splitCardAmount = max(0, (float) $request->input('paid_in_card', 0));
            $splitMfsAmount = max(0, (float) $request->input('paid_in_mfc', 0));

            if ($splitCardAmount > 0
                && !in_array(trim((string) $request->input('split_card_type')), $allowedCardTypes, true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please select a valid card type for the split card amount.'
                ], 422);
            }

            if ($splitMfsAmount > 0
                && !in_array(trim((string) $request->input('split_mfs_provider')), $allowedMfsProviders, true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please select a valid MFS service for the split MFS amount.'
                ], 422);
            }

            if ($splitCardAmount > 0 && trim((string) $request->input('split_card_reference')) === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bank / Card Reference Number is required when a Bank / Card amount is entered.'
                ], 422);
            }

            if ($splitMfsAmount > 0 && trim((string) $request->input('split_mfs_reference')) === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'MFS Reference Number is required when an MFS amount is entered.'
                ], 422);
            }
        }

        $requestedProductDiscounts = $request->input('product_discounts', []);
        if (!is_array($requestedProductDiscounts)) {
            $requestedProductDiscounts = [];
        }

        $discountValueForRemark = max(0, (float) $request->input('discount_value', 0));
        $hasProductWiseDiscount = $this->requestHasProductWiseDiscount($requestedProductDiscounts);
        if (($discountValueForRemark > 0 || $hasProductWiseDiscount) && trim((string) $request->input('remark', '')) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Remark is required when a discount is applied.'
            ], 422);
        }

        if (mb_strlen((string) $request->input('remark', '')) > 1000) {
            return response()->json([
                'status' => 'error',
                'message' => 'Remark may not be greater than 1000 characters.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $taxSetting = DB::table('tax_settings')->first();
            $vat_rate = $taxSetting->vat_rate ?? 0;
            $isComplimentaryOrder = $request->boolean('is_complimentary_order');

            $order = Order::findOrFail($request->order_id);

            $finalPaymentDependsOnKitchenStatus = (bool) (DB::table('pos_settings')->value('final_payment_depends_on_kitchen_status') ?? 0);

            if ($request->filled('order_id') && $finalPaymentDependsOnKitchenStatus) {
                $hasKitchenPending = OrderKot::where('order_id', $order->id)
                    ->whereIn('kitchen_status', ['Pending', 'Cooking', 'Hold'])
                    ->exists();

                if ($hasKitchenPending) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Kitchen is busy. Payment is allowed only after all KOT items are Ready.'
                    ]);
                }
            }

            $subtotal = $order->subtotal;
            $productDiscountResult = $this->calculateProductWiseDiscounts($order, $requestedProductDiscounts, true);
            $product_discount_amount = (float) $productDiscountResult['total'];

            // বিল ক্যালকুলেশনে সার্ভিস চার্জ চেক (শুধু Dine-In হলে সার্ভিস চার্জ কাটবে)
            $service_charge_rate = (strtolower($order->order_type) == 'dine-in' || strtolower($order->order_type) == 'dine_in') ? ($taxSetting->service_charge ?? 0) : 0;

            $discount_value = $request->discount_value ?? 0;
            $discount_type = $request->discount_type ?? 'fixed';

            // ভ্যাট, সার্ভিস চার্জ এবং ডিসকাউন্ট ক্যালকুলেশন (রাউন্ড ফিগার সহ)
            $service_charge = round(($subtotal * $service_charge_rate) / 100);
            $tax = round((($subtotal + $service_charge) * $vat_rate) / 100);
            // Whole-order discount calculation is intentionally unchanged.
            $discount_amount = round(($discount_type == 'percentage') ? ($subtotal * $discount_value) / 100 : $discount_value);
            $grand_total = max(0, round(($subtotal + $tax + $service_charge) - $discount_amount - $product_discount_amount));

            // ===============================================
            // পেমেন্ট স্প্লিট এবং Due ক্যালকুলেশন
            // ===============================================
            $paymentMethod = $request->payment_method;

            // The linked Table Booking remains the authoritative source of advance amount.
            // We copy it to the order for invoice/report audit but never reduce table_bookings.advance_amount.
            $bookingAdvanceSource = null;
            if (Schema::hasColumn('orders', 'table_booking_id') && $order->table_booking_id) {
                $linkedBooking = TableBooking::find($order->table_booking_id);
                $bookingAdvanceSource = $linkedBooking?->advance_amount;
            }

            $advanceAmount = Schema::hasColumn('orders', 'booking_advance')
                ? min((float) $grand_total, max(0, round((float) ($bookingAdvanceSource ?? $order->booking_advance ?? 0), 2)))
                : 0;

            if (Schema::hasColumn('orders', 'booking_advance')) {
                $order->booking_advance = $advanceAmount;
            }

            $remainingPayable = max(0, round((float) $grand_total - $advanceAmount, 2));

            // Total Paid is the combined paid amount including reservation advance.
            // Cash/Card/MFS fields represent only the money received now at final POS payment.
            if ($paymentMethod === 'Split') {
                $cash = max(0, (float) ($request->paid_in_cash ?? 0));
                $card = max(0, (float) ($request->paid_in_card ?? 0));
                $mfc  = max(0, (float) ($request->paid_in_mfc ?? 0));

                $splitTotal = max(0, round($cash + $card + $mfc, 2));
                if ($splitTotal > $remainingPayable && $splitTotal > 0) {
                    $ratio = $remainingPayable / $splitTotal;
                    $cash = round($cash * $ratio, 2);
                    $card = round($card * $ratio, 2);
                    $mfc = max(0, round($remainingPayable - $cash - $card, 2));
                }

                $currentPayment = max(0, round($cash + $card + $mfc, 2));
                $totalPaid = min((float) $grand_total, round($advanceAmount + $currentPayment, 2));
            } else {
                $requestedTotalPaid = max(0, round((float) ($request->total_paid_amount ?? 0), 2));
                $totalPaid = min((float) $grand_total, max($advanceAmount, $requestedTotalPaid));
                $currentPayment = max(0, min($remainingPayable, round($totalPaid - $advanceAmount, 2)));

                $cash = $paymentMethod === 'Cash' ? $currentPayment : 0;
                $card = $paymentMethod === 'Card' ? $currentPayment : 0;
                $mfc  = $paymentMethod === 'Mobile Banking' ? $currentPayment : 0;
            }

            $tipsAmount = max(0, round((float) ($request->tips_amount ?? 0), 2));
            $givenMoney = max(0, round((float) ($request->given_money ?? 0), 2));
            $requiredGivenMoney = round($currentPayment + $tipsAmount, 2);
            $saveAsDueOrder = (int) $request->input('save_as_due_order', 0) === 1;

            if ($givenMoney + 0.001 < $requiredGivenMoney) {
                if (!$saveAsDueOrder) {
                    DB::rollBack();

                    return response()->json([
                        'status' => 'error',
                        'message' => 'You entered less money than the payment amount. Please correct the Given Money amount or save it as a Due Order.'
                    ], 422);
                }

                $tipsAmount = min($tipsAmount, $givenMoney);
                $receivedForBill = max(0, round($givenMoney - $tipsAmount, 2));
                $currentPayment = min($currentPayment, $receivedForBill, $remainingPayable);

                if ($paymentMethod === 'Cash') {
                    $cash = $currentPayment;
                    $card = 0;
                    $mfc = 0;
                } elseif ($paymentMethod === 'Card') {
                    $cash = 0;
                    $card = $currentPayment;
                    $mfc = 0;
                } elseif ($paymentMethod === 'Mobile Banking') {
                    $cash = 0;
                    $card = 0;
                    $mfc = $currentPayment;
                } elseif ($paymentMethod === 'Split') {
                    $originalSplitTotal = max(0, round($cash + $card + $mfc, 2));
                    if ($originalSplitTotal > 0 && $currentPayment > 0) {
                        $cash = round($currentPayment * ($cash / $originalSplitTotal), 2);
                        $card = round($currentPayment * ($card / $originalSplitTotal), 2);
                        $mfc = max(0, round($currentPayment - $cash - $card, 2));
                    } else {
                        $cash = 0;
                        $card = 0;
                        $mfc = 0;
                    }
                }

                $totalPaid = min((float) $grand_total, round($advanceAmount + $currentPayment, 2));
            }

            // Advance is excluded from Given Money/Change because it was collected at booking time.
            $changeAmount = max(0, round($givenMoney - $currentPayment - $tipsAmount, 2));

            // Total Paid already contains advance, therefore it is subtracted exactly once.
            $due = max(0, round($grand_total - $totalPaid, 2));

            // ===============================================
            // মডেলের মাস অ্যাসাইনমেন্ট রেসট্রিকশন এড়াতে সরাসরি প্রপার্টি সেট করে সেভ করা
            // ===============================================
            $order->discount_type     = $discount_type;
            $order->discount_amount   = $discount_amount;
            if (Schema::hasColumn('orders', 'discount_value')) {
                $order->discount_value = $discount_type === 'percentage' ? max(0, (float) $discount_value) : 0;
            }
            $order->product_discount_amount = $product_discount_amount;
            $order->vat_tax           = $tax;
            $order->service_charge    = $service_charge;
            $order->grand_total       = $grand_total;
            $order->payment_type      = $paymentMethod;
            $order->transaction_id    = in_array($paymentMethod, ['Card', 'Mobile Banking'], true) ? trim((string) $request->transaction_id) : null;
            if (Schema::hasColumn('orders', 'card_type')) {
                $order->card_type = $paymentMethod === 'Card'
                    ? trim((string) $request->input('card_type'))
                    : ($paymentMethod === 'Split' && $card > 0 ? trim((string) $request->input('split_card_type')) : null);
            }
            if (Schema::hasColumn('orders', 'mfs_provider')) {
                $order->mfs_provider = $paymentMethod === 'Mobile Banking'
                    ? trim((string) $request->input('mfs_provider'))
                    : ($paymentMethod === 'Split' && $mfc > 0 ? trim((string) $request->input('split_mfs_provider')) : null);
            }
            if (Schema::hasColumn('orders', 'payment_remark')) {
                $remark = trim((string) $request->input('remark', ''));
                $order->payment_remark = $remark !== '' ? $remark : null;
            }
            if (Schema::hasColumn('orders', 'split_card_reference')) {
                $order->split_card_reference = $paymentMethod === 'Split' && $card > 0
                    ? trim((string) $request->input('split_card_reference'))
                    : null;
            }
            if (Schema::hasColumn('orders', 'split_mfs_reference')) {
                $order->split_mfs_reference = $paymentMethod === 'Split' && $mfc > 0
                    ? trim((string) $request->input('split_mfs_reference'))
                    : null;
            }
            $order->status            = 'Completed'; // স্ট্যাটাস ১০০% আপডেট হবে
            $order->due               = $due;
            $order->total_paid_amount = min((float) $grand_total, $totalPaid);
            if (Schema::hasColumn('orders', 'tips_amount')) {
                $order->tips_amount = $tipsAmount;
            }
            if (Schema::hasColumn('orders', 'given_money')) {
                $order->given_money = $givenMoney;
            }
            if (Schema::hasColumn('orders', 'change_amount')) {
                $order->change_amount = $changeAmount;
            }
            $order->paid_in_cash      = $cash;
            $order->paid_in_card      = $card;
            $order->paid_in_mfc       = $mfc;
            if (Schema::hasColumn('orders', 'is_complimentary_order') && $isComplimentaryOrder) {
                $order->is_complimentary_order = 1;
            }

            // POS থেকে Kitchen-এ পাঠানো সময় থেকে Final Payment পর্যন্ত সময় মিনিটে সেভ হবে।
            if (Schema::hasColumn('orders', 'kitchen_to_payment_minutes')) {
                $firstKitchenSentAt = OrderKot::where('order_id', $order->id)
                    ->where('kitchen_status', '!=', 'Hold')
                    ->orderBy('created_at', 'asc')
                    ->value('created_at');

                $order->kitchen_to_payment_minutes = $firstKitchenSentAt
                    ? Carbon::parse($firstKitchenSentAt)->diffInMinutes(now())
                    : null;
            }

            $order->save();

            // Payment completes the reservation lifecycle so the table becomes Available immediately,
            // while the original booking advance amount/payment details remain untouched for audit.
            if (Schema::hasColumn('orders', 'table_booking_id') && $order->table_booking_id) {
                TableBooking::where('id', $order->table_booking_id)->update(['status' => 'completed']);
            }

            // Final payment হলে kitchen dashboard থেকে সরানোর জন্য সব KOT Delivered করা হবে।
            OrderKot::where('order_id', $order->id)
                ->where('kitchen_status', '!=', 'Delivered')
                ->update(['kitchen_status' => 'Delivered']);


            if ($order->table_id) {
                Table::where('id', $order->table_id)->update(['initial_status' => 'Available']);
            }

            DB::commit();
            return response()->json([
                'status'       => 'success',
                'redirect_url' => url('/pos/invoice/'.$order->id)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Payment failed! '.$e->getMessage()]);
        }
    }

    private function logDeletedCartOrOrderItem(array $data)
    {
        // Migration run না থাকলেও POS delete flow যেন crash না করে
        if (!Schema::hasTable('pos_deleted_item_histories')) {
            return;
        }

        DB::table('pos_deleted_item_histories')->insert([
            'order_id' => $data['order_id'] ?? null,
            'order_detail_id' => $data['order_detail_id'] ?? null,
            'order_kot_id' => $data['order_kot_id'] ?? null,
            'food_id' => $data['food_id'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'unit_price' => $data['unit_price'] ?? 0,
            'addon_total' => $data['addon_total'] ?? 0,
            'deleted_quantity' => $data['deleted_quantity'] ?? 0,
            'previous_quantity' => $data['previous_quantity'] ?? 0,
            'remaining_quantity' => $data['remaining_quantity'] ?? 0,
            'subtotal_removed' => $data['subtotal_removed'] ?? 0,
            'source' => $data['source'] ?? 'cart',
            'cart_key' => $data['cart_key'] ?? null,
            'cart_item_key' => $data['cart_item_key'] ?? null,
            'order_type' => $data['order_type'] ?? null,
            'table_id' => $data['table_id'] ?? null,
            'addons' => isset($data['addons']) ? json_encode($data['addons']) : null,
            'note' => $data['note'] ?? null,
            'deleted_by' => auth()->id(),
            'reason' => $data['reason'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateCart(Request $request)
    {
        $cartKey = $this->getCartKey($request);
        $cart = Session::get($cartKey, []);

        if(isset($cart[$request->cart_id])) {
            $item = $cart[$request->cart_id];
            $previousQty = (int) ($item['qty'] ?? 0);
            $unitTotal = (float) (($item['price'] ?? 0) + ($item['addon_total'] ?? 0));

            if($request->action == 'plus') {
                $cart[$request->cart_id]['qty'] += 1;
            } elseif($request->action == 'minus') {
                if($cart[$request->cart_id]['qty'] > 1) {
                    $cart[$request->cart_id]['qty'] -= 1;
                    $this->logDeletedCartOrOrderItem([
                        'source' => 'cart',
                        'cart_key' => $cartKey,
                        'cart_item_key' => $request->cart_id,
                        'food_id' => $item['food_id'] ?? null,
                        'product_name' => $item['name'] ?? null,
                        'unit_price' => $item['price'] ?? 0,
                        'addon_total' => $item['addon_total'] ?? 0,
                        'deleted_quantity' => 1,
                        'previous_quantity' => $previousQty,
                        'remaining_quantity' => $previousQty - 1,
                        'subtotal_removed' => $unitTotal,
                        'order_type' => $request->order_type,
                        'table_id' => $request->table_id,
                        'addons' => $item['addons'] ?? [],
                        'note' => $item['note'] ?? null,
                        'reason' => 'Quantity decreased from cart',
                    ]);
                } else {
                    $this->logDeletedCartOrOrderItem([
                        'source' => 'cart',
                        'cart_key' => $cartKey,
                        'cart_item_key' => $request->cart_id,
                        'food_id' => $item['food_id'] ?? null,
                        'product_name' => $item['name'] ?? null,
                        'unit_price' => $item['price'] ?? 0,
                        'addon_total' => $item['addon_total'] ?? 0,
                        'deleted_quantity' => $previousQty,
                        'previous_quantity' => $previousQty,
                        'remaining_quantity' => 0,
                        'subtotal_removed' => $unitTotal * $previousQty,
                        'order_type' => $request->order_type,
                        'table_id' => $request->table_id,
                        'addons' => $item['addons'] ?? [],
                        'note' => $item['note'] ?? null,
                        'reason' => 'Cart item removed by minus button',
                    ]);
                    unset($cart[$request->cart_id]);
                }
            } elseif($request->action == 'set') {
                $qty = (int) $request->qty;
                if($qty > 0) {
                    if ($qty < $previousQty) {
                        $deletedQty = $previousQty - $qty;
                        $this->logDeletedCartOrOrderItem([
                            'source' => 'cart',
                            'cart_key' => $cartKey,
                            'cart_item_key' => $request->cart_id,
                            'food_id' => $item['food_id'] ?? null,
                            'product_name' => $item['name'] ?? null,
                            'unit_price' => $item['price'] ?? 0,
                            'addon_total' => $item['addon_total'] ?? 0,
                            'deleted_quantity' => $deletedQty,
                            'previous_quantity' => $previousQty,
                            'remaining_quantity' => $qty,
                            'subtotal_removed' => $unitTotal * $deletedQty,
                            'order_type' => $request->order_type,
                            'table_id' => $request->table_id,
                            'addons' => $item['addons'] ?? [],
                            'note' => $item['note'] ?? null,
                            'reason' => 'Cart quantity manually reduced',
                        ]);
                    }
                    $cart[$request->cart_id]['qty'] = $qty;
                } else {
                    $this->logDeletedCartOrOrderItem([
                        'source' => 'cart',
                        'cart_key' => $cartKey,
                        'cart_item_key' => $request->cart_id,
                        'food_id' => $item['food_id'] ?? null,
                        'product_name' => $item['name'] ?? null,
                        'unit_price' => $item['price'] ?? 0,
                        'addon_total' => $item['addon_total'] ?? 0,
                        'deleted_quantity' => $previousQty,
                        'previous_quantity' => $previousQty,
                        'remaining_quantity' => 0,
                        'subtotal_removed' => $unitTotal * $previousQty,
                        'order_type' => $request->order_type,
                        'table_id' => $request->table_id,
                        'addons' => $item['addons'] ?? [],
                        'note' => $item['note'] ?? null,
                        'reason' => 'Cart item removed by quantity input',
                    ]);
                    unset($cart[$request->cart_id]);
                }
            }
            Session::put($cartKey, $cart);
        }
        return response()->json(['status' => 'success']);
    }

    public function updateNote(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|string',
            'note' => 'nullable|string|max:1000',
        ]);

        $cartKey = $this->getCartKey($request);
        $cart = Session::get($cartKey, []);

        if (isset($cart[$request->cart_id])) {
            $note = trim((string) $request->input('note', ''));
            $cart[$request->cart_id]['note'] = $note;

            // In Complimentary Mode the normal cart food Note is the complimentary
            // authorization Note for this exact product/order-detail line.
            if (!empty($cart[$request->cart_id]['is_complimentary'])) {
                $cart[$request->cart_id]['complimentary_note'] = $note !== '' ? $note : null;
            }

            Session::put($cartKey, $cart);
        }

        return response()->json(['status' => 'success']);
    }

    public function removeFromCart(Request $request)
    {
        // Cart items are not yet finalized/saved order items, so deleting them does not
        // require the shared POS action password. Saved order-item deletion still does.
        $request->validate([
            'cart_id' => 'required',
        ]);

        $cartKey = $this->getCartKey($request);
        $cart = Session::get($cartKey, []);

        if(isset($cart[$request->cart_id])) {
            $item = $cart[$request->cart_id];
            $qty = (int) ($item['qty'] ?? 0);
            $unitTotal = (float) (($item['price'] ?? 0) + ($item['addon_total'] ?? 0));

            $this->logDeletedCartOrOrderItem([
                'source' => 'cart',
                'cart_key' => $cartKey,
                'cart_item_key' => $request->cart_id,
                'food_id' => $item['food_id'] ?? null,
                'product_name' => $item['name'] ?? null,
                'unit_price' => $item['price'] ?? 0,
                'addon_total' => $item['addon_total'] ?? 0,
                'deleted_quantity' => $qty,
                'previous_quantity' => $qty,
                'remaining_quantity' => 0,
                'subtotal_removed' => $unitTotal * $qty,
                'order_type' => $request->order_type,
                'table_id' => $request->table_id,
                'addons' => $item['addons'] ?? [],
                'note' => $item['note'] ?? null,
                'reason' => 'Cart item removed by trash button',
            ]);

            unset($cart[$request->cart_id]);
            Session::put($cartKey, $cart);
        }
        return response()->json(['status' => 'success']);
    }


    /**
     * Validate the shared POS action password used by destructive/complimentary actions.
     * Returns a JSON error response when invalid, otherwise null.
     */
    private function validatePosActionPassword($providedPassword)
    {
        $savedPassword = (string) (optional(RestaurantSetting::first())->pos_action_password ?? '');
        $providedPassword = (string) ($providedPassword ?? '');

        if ($savedPassword === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'POS Action Password is not configured. Please set it from Settings first.'
            ], 422);
        }

        if ($providedPassword === '' || !hash_equals($savedPassword, $providedPassword)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wrong POS Action Password.'
            ], 422);
        }

        return null;
    }

    /**
     * Toggle an already ordered POS food line between normal and complimentary.
     * Existing "Add Complimentary" cart flow stays unchanged; this only affects
     * a saved order_detail that is already visible in the active-order offcanvas.
     */
    public function verifyPosActionPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if ($passwordError = $this->validatePosActionPassword($request->input('password'))) {
            return $passwordError;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Password verified.'
        ]);
    }

    public function makeOrderedItemComplimentary(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_detail_id' => 'nullable|integer|exists:order_details,id',
            'order_detail_ids' => 'nullable|string',
            'is_complimentary' => 'nullable|boolean',
            'action_password' => 'required|string',
            'complimentary_note' => 'nullable|string|max:1000',
        ]);

        $detailIds = $this->requestedOrderDetailIds($request);
        if (empty($detailIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No order item was selected.'
            ], 422);
        }

        if ($passwordError = $this->validatePosActionPassword($request->input('action_password'))) {
            return $passwordError;
        }

        // Backward compatible: old callers without this field still make the item complimentary.
        $makeComplimentary = $request->has('is_complimentary')
            ? $request->boolean('is_complimentary')
            : true;

        DB::beginTransaction();

        try {
            $activeStatuses = ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'];

            $order = Order::where('id', $request->order_id)
                ->whereIn('status', $activeStatuses)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only an active POS order can be changed from this screen.'
                ], 422);
            }

            $complimentaryNote = trim((string) $request->input('complimentary_note', ''));
            $complimentaryNoteRequired = (bool) (PosSetting::first()?->complimentary_note_required ?? false);
            if ($makeComplimentary && $complimentaryNoteRequired && $complimentaryNote === '') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'A note is required to make this food complimentary.'
                ], 422);
            }

            $details = OrderDetail::where('order_id', $order->id)
                ->whereIn('id', $detailIds)
                ->lockForUpdate()
                ->get()
                ->sortBy('id')
                ->values();

            if ($details->count() !== count($detailIds)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more order item rows could not be found.'
                ], 422);
            }

            if ($details->contains(fn ($detail) => !empty($detail->is_unavailable))) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unavailable food cannot be changed.'
                ], 422);
            }

            // Only rows that are identical in the merged POS view may be changed together.
            $mergeKey = $this->orderDetailDisplayMergeKey($details->first());
            if ($details->contains(fn ($detail) => $this->orderDetailDisplayMergeKey($detail) !== $mergeKey)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected merged item is no longer identical. Please refresh the POS and try again.'
                ], 422);
            }

            $alreadyComplimentary = $details->every(function ($detail) {
                return !empty($detail->is_complimentary)
                    || ((float) ($detail->price ?? 0) <= 0 && (float) ($detail->subtotal ?? 0) <= 0);
            });

            $restoreFood = null;
            $restoreAddons = collect();
            $normalFoodPrice = 0;

            if (!$makeComplimentary && $alreadyComplimentary) {
                $restoreFood = FoodItem::with('addons')->find($details->first()->product_id);
                if (!$restoreFood) {
                    throw new \RuntimeException('Normal price could not be restored because the food item no longer exists.');
                }

                $normalFoodPrice = (float) ($restoreFood->discount_price ?? $restoreFood->base_price ?? 0);
                $restoreAddons = $restoreFood->addons->keyBy('id');
            }

            foreach ($details as $detail) {
                if ($makeComplimentary) {
                    $addons = json_decode($detail->addons ?? '[]', true);
                    if (!is_array($addons)) {
                        $addons = [];
                    }

                    foreach ($addons as &$addon) {
                        if (is_array($addon)) {
                            $addon['price'] = 0;
                        }
                    }
                    unset($addon);

                    $detail->price = 0;
                    $detail->subtotal = 0;
                    $detail->addons = json_encode($addons);
                    $detail->product_discount_type = null;
                    $detail->product_discount_value = 0;
                    $detail->product_discount_amount = 0;

                    if (Schema::hasColumn('order_details', 'is_complimentary')) {
                        $detail->is_complimentary = 1;
                    }
                    if (Schema::hasColumn('order_details', 'complimentary_note')) {
                        $detail->complimentary_note = $complimentaryNote !== '' ? $complimentaryNote : null;
                    }

                    $detail->save();
                    continue;
                }

                if ($alreadyComplimentary) {
                    $addons = json_decode($detail->addons ?? '[]', true);
                    if (!is_array($addons)) {
                        $addons = [];
                    }

                    $addonTotal = 0;
                    foreach ($addons as &$addon) {
                        if (!is_array($addon)) {
                            continue;
                        }

                        $addonId = (int) ($addon['id'] ?? 0);
                        if ($addonId > 0 && $restoreAddons->has($addonId)) {
                            $currentAddon = $restoreAddons->get($addonId);
                            $addon['name'] = $currentAddon->name ?? ($addon['name'] ?? 'Addon');
                            $addon['price'] = (float) ($currentAddon->price ?? 0);
                        } else {
                            $addon['price'] = max(0, (float) ($addon['price'] ?? 0));
                        }

                        $addonTotal += (float) ($addon['price'] ?? 0);
                    }
                    unset($addon);

                    $quantity = max(1, (int) ($detail->quantity ?? 1));
                    $detail->price = $normalFoodPrice;
                    $detail->subtotal = round(($normalFoodPrice + $addonTotal) * $quantity, 2);
                    $detail->addons = json_encode($addons);
                    $detail->product_discount_type = null;
                    $detail->product_discount_value = 0;
                    $detail->product_discount_amount = 0;

                    if (Schema::hasColumn('order_details', 'is_complimentary')) {
                        $detail->is_complimentary = 0;
                    }
                    if (Schema::hasColumn('order_details', 'complimentary_note')) {
                        $detail->complimentary_note = null;
                    }

                    $detail->save();
                }
            }

            // A whole complimentary order stops being whole-complimentary as soon as this merged item returns to normal.
            if (!$makeComplimentary && $alreadyComplimentary
                && Schema::hasColumn('orders', 'is_complimentary_order')
                && !empty($order->is_complimentary_order)) {
                $order->is_complimentary_order = 0;
            }

            $remainingDetails = OrderDetail::where('order_id', $order->id)
                ->where('is_unavailable', 0)
                ->get();

            $newSubtotal = 0;
            $productDiscountTotal = 0;

            foreach ($remainingDetails as $remainingDetail) {
                $lineSubtotal = max(0, (float) ($remainingDetail->subtotal ?? 0));
                $lineIsComplimentary = !empty($remainingDetail->is_complimentary)
                    || ((float) ($remainingDetail->price ?? 0) <= 0 && $lineSubtotal <= 0);

                if ($lineIsComplimentary) {
                    $remainingDetail->product_discount_type = null;
                    $remainingDetail->product_discount_value = 0;
                    $remainingDetail->product_discount_amount = 0;
                    $remainingDetail->save();
                    continue;
                }

                $newSubtotal += $lineSubtotal;

                $type = ($remainingDetail->product_discount_type ?? 'fixed') === 'percentage'
                    ? 'percentage'
                    : 'fixed';
                $value = max(0, (float) ($remainingDetail->product_discount_value ?? 0));

                if ($type === 'percentage') {
                    $value = min($value, 100);
                    $lineProductDiscount = round(($lineSubtotal * $value) / 100);
                } else {
                    $lineProductDiscount = min($value, $lineSubtotal);
                }

                $lineProductDiscount = max(0, round($lineProductDiscount));
                $remainingDetail->product_discount_type = $lineProductDiscount > 0 ? $type : null;
                $remainingDetail->product_discount_value = $lineProductDiscount > 0 ? $value : 0;
                $remainingDetail->product_discount_amount = $lineProductDiscount;
                $remainingDetail->save();

                $productDiscountTotal += $lineProductDiscount;
            }

            $newSubtotal = max(0, round($newSubtotal, 2));
            $taxSetting = DB::table('tax_settings')->first();
            $vatRate = (float) ($taxSetting->vat_rate ?? 0);
            $normalizedOrderType = $this->normalizePosOrderType($order->order_type ?? 'dine_in');
            $serviceRate = $normalizedOrderType === 'dine_in'
                ? (float) ($taxSetting->service_charge ?? 0)
                : 0;

            $serviceCharge = round(($newSubtotal * $serviceRate) / 100);
            $vatTax = round((($newSubtotal + $serviceCharge) * $vatRate) / 100);
            $discountAmount = min(
                max(0, round((float) ($order->discount_amount ?? 0))),
                round($newSubtotal + $serviceCharge + $vatTax)
            );
            $productDiscountTotal = min(max(0, round($productDiscountTotal)), round($newSubtotal));
            $grandTotal = max(0, round(
                ($newSubtotal + $serviceCharge + $vatTax) - $discountAmount - $productDiscountTotal
            ));
            $totalPaid = max(0, (float) ($order->total_paid_amount ?? 0));

            $order->subtotal = $newSubtotal;
            $order->service_charge = $serviceCharge;
            $order->vat_tax = $vatTax;
            $order->discount_amount = $discountAmount;
            $order->product_discount_amount = $productDiscountTotal;
            $order->grand_total = $grandTotal;
            $order->due = max(0, round($grandTotal - $totalPaid, 2));
            $order->save();

            DB::commit();

            if ($makeComplimentary) {
                $message = $alreadyComplimentary
                    ? 'Food is already complimentary.'
                    : 'Food converted to complimentary successfully.';
            } else {
                $message = $alreadyComplimentary
                    ? 'Food returned to normal successfully.'
                    : 'Food is already normal.';
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'is_complimentary' => $makeComplimentary ? 1 : 0,
                'order_id' => $order->id,
                'order_detail_id' => $details->last()->id,
                'order_detail_ids' => $details->pluck('id')->values()->all(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Food status update failed! ' . $e->getMessage()
            ], 500);
        }
    }


    public function removeOrderedItem(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_detail_id' => 'nullable|integer|exists:order_details,id',
            'order_detail_ids' => 'nullable|string',
            'qty' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:1000',
            'action_password' => 'nullable|string',
        ]);

        $detailIds = $this->requestedOrderDetailIds($request);
        if (empty($detailIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No order item was selected.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $order = Order::lockForUpdate()->findOrFail($request->order_id);
            $details = OrderDetail::where('order_id', $order->id)
                ->whereIn('id', $detailIds)
                ->lockForUpdate()
                ->get()
                ->sortByDesc('id')
                ->values();

            if ($details->count() !== count($detailIds)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more order item rows could not be found.'
                ], 422);
            }

            // Anything already sent to the kitchen is a saved order item and must stay
            // password protected. Add More food is password-free only while it is still
            // unsent in the POS cart; after Send to Kitchen it follows this same rule.
            if ($passwordError = $this->validatePosActionPassword($request->input('action_password'))) {
                DB::rollBack();
                return $passwordError;
            }

            if ($details->contains(fn ($detail) => !empty($detail->is_unavailable))) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Unavailable item cannot be deleted again.']);
            }

            // Prevent a stale/tampered merged row from deleting unrelated order items together.
            $mergeKey = $this->orderDetailDisplayMergeKey($details->first());
            if ($details->contains(fn ($detail) => $this->orderDetailDisplayMergeKey($detail) !== $mergeKey)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'The selected merged item is no longer identical. Please refresh the POS and try again.'
                ], 422);
            }

            $availableQty = $details->sum(fn ($detail) => max(0, (int) ($detail->quantity ?? 0)));
            $remainingDeleteQty = min((int) $request->qty, $availableQty);
            $requestedDeleteQty = $remainingDeleteQty;
            $deleteReason = trim((string) ($request->reason ?? ''));
            $totalRemoveAmount = 0.0;

            foreach ($details as $detail) {
                if ($remainingDeleteQty <= 0) {
                    break;
                }

                $currentQty = max(1, (int) $detail->quantity);
                $deleteQty = min($remainingDeleteQty, $currentQty);
                $unitTotal = $currentQty > 0 ? ((float) $detail->subtotal / $currentQty) : 0;
                $removeAmount = round($unitTotal * $deleteQty, 2);
                $totalRemoveAmount += $removeAmount;
                $addons = json_decode($detail->addons ?? '[]', true);
                if (!is_array($addons)) {
                    $addons = [];
                }

                $addonTotal = 0;
                foreach ($addons as $addon) {
                    $addonTotal += (float) ($addon['price'] ?? 0);
                }

                $this->logDeletedCartOrOrderItem([
                    'source' => 'ordered_item',
                    'order_id' => $order->id,
                    'order_detail_id' => $detail->id,
                    'order_kot_id' => $detail->order_kot_id,
                    'food_id' => $detail->product_id,
                    'product_name' => $detail->product_name,
                    'unit_price' => $detail->price ?? 0,
                    'addon_total' => $addonTotal,
                    'deleted_quantity' => $deleteQty,
                    'previous_quantity' => $currentQty,
                    'remaining_quantity' => max(0, $currentQty - $deleteQty),
                    'subtotal_removed' => $removeAmount,
                    'order_type' => $order->order_type,
                    'table_id' => $order->table_id,
                    'addons' => $addons,
                    'note' => $detail->food_note ?? null,
                    'reason' => $deleteReason !== ''
                        ? $deleteReason
                        : ($deleteQty >= $currentQty ? 'Merged ordered item row fully deleted from offcanvas' : 'Merged ordered item quantity deleted from offcanvas'),
                ]);

                if ($deleteQty >= $currentQty) {
                    $kotId = $detail->order_kot_id;
                    $detail->delete();

                    if ($kotId) {
                        $hasDetails = OrderDetail::where('order_kot_id', $kotId)->exists();
                        if (!$hasDetails) {
                            OrderKot::where('id', $kotId)->delete();
                        }
                    }
                } else {
                    $detail->quantity = $currentQty - $deleteQty;
                    $detail->subtotal = max(0, round((float) $detail->subtotal - $removeAmount, 2));
                    $detail->save();
                }

                $remainingDeleteQty -= $deleteQty;
            }

            $newSubtotal = max(0, round((float) $order->subtotal - $totalRemoveAmount, 2));
            $taxSetting = DB::table('tax_settings')->first();
            $vatRate = $taxSetting->vat_rate ?? 0;
            $serviceRate = (strtolower($order->order_type) == 'dine-in' || strtolower($order->order_type) == 'dine_in')
                ? ($taxSetting->service_charge ?? 0)
                : 0;

            $serviceCharge = round(($newSubtotal * $serviceRate) / 100);
            $vatTax = round((($newSubtotal + $serviceCharge) * $vatRate) / 100);

            // Existing product-wise discount settings are recalculated against the changed line subtotals.
            $productDiscountTotal = 0;
            $remainingDetails = OrderDetail::where('order_id', $order->id)
                ->where('is_unavailable', 0)
                ->get();

            foreach ($remainingDetails as $remainingDetail) {
                $lineSubtotal = max(0, (float) ($remainingDetail->subtotal ?? 0));
                $type = ($remainingDetail->product_discount_type ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
                $value = max(0, (float) ($remainingDetail->product_discount_value ?? 0));

                if ($type === 'percentage') {
                    $value = min($value, 100);
                    $lineProductDiscount = round(($lineSubtotal * $value) / 100);
                } else {
                    $lineProductDiscount = min($value, $lineSubtotal);
                }

                $lineProductDiscount = max(0, round($lineProductDiscount));
                $remainingDetail->product_discount_type = $lineProductDiscount > 0 ? $type : null;
                $remainingDetail->product_discount_value = $lineProductDiscount > 0 ? $value : 0;
                $remainingDetail->product_discount_amount = $lineProductDiscount;
                $remainingDetail->save();
                $productDiscountTotal += $lineProductDiscount;
            }

            $discountAmount = min(round((float) ($order->discount_amount ?? 0)), round($newSubtotal + $serviceCharge + $vatTax));
            $productDiscountTotal = min(max(0, round($productDiscountTotal)), round($newSubtotal));
            $grandTotal = max(0, round(($newSubtotal + $serviceCharge + $vatTax) - $discountAmount - $productDiscountTotal));
            $totalPaid = (float) ($order->total_paid_amount ?? 0);

            $order->subtotal = $newSubtotal;
            $order->service_charge = $serviceCharge;
            $order->vat_tax = $vatTax;
            $order->product_discount_amount = $productDiscountTotal;
            $order->grand_total = $grandTotal;
            $order->due = max(0, $grandTotal - $totalPaid);
            $order->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $requestedDeleteQty . ' item quantity deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }



    // (getTableOrder এবং completePayment মেথড আগের মতোই থাকবে...)
    // public function getTableOrder($table_id)
    // {
    //     $order = Order::with(['kots.orderDetails', 'waiter', 'customer', 'table'])
    //                   ->where('table_id', $table_id)
    //                   ->where('status', 'Pending')
    //                   ->first();

    //     if(!$order) return response()->json(['status' => 'error', 'message' => 'No active order found.']);

    //     $kitchenBusy = $order->kots()->whereIn('kitchen_status', ['Pending', 'Cooking'])->exists();
    //     return view('admin.pos.partials.offcanvas_order', compact('order', 'kitchenBusy'))->render();
    // }




    // ====================================================
    // Invoice & Pre-Invoice Print Methods
    // ====================================================
    public function printInvoice($id)
    {
        $order = Order::with(['orderDetails', 'customer', 'waiter', 'user', 'deliveryPartner'])->findOrFail($id);
        $order->ensureFeedbackToken();
        $restaurant = \App\Models\RestaurantSetting::first();
        $posSetting = \App\Models\PosSetting::first();

        // Final payment values are now stored separately:
        // total_paid_amount = bill payment, tips_amount = tips, given_money = received money, change_amount = return amount.
        $order->invoice_paid_amount = (float) ($order->total_paid_amount ?? 0);
        $order->invoice_paid_in_cash = (float) ($order->paid_in_cash ?? 0);
        $order->invoice_paid_in_card = (float) ($order->paid_in_card ?? 0);
        $order->invoice_paid_in_mfc = (float) ($order->paid_in_mfc ?? 0);

        $mergedOrderItems = $this->mergeOrderDetailsForDisplay($order->orderDetails);

        return view('admin.pos.invoice', compact('order', 'restaurant', 'mergedOrderItems', 'posSetting'));
    }

    public function printPreInvoice(Request $request, $id)
    {
        $order = Order::with(['orderDetails', 'customer', 'waiter', 'user', 'deliveryPartner'])->findOrFail($id);
        $order->ensureFeedbackToken();
        $restaurant = \App\Models\RestaurantSetting::first();
        $invoiceSetting = \App\Models\InvoiceSetting::first();
        $posSetting = \App\Models\PosSetting::first();

        $hasLiveInputs = $request->has('disc_type')
            || $request->has('disc_val')
            || $request->has('product_discounts');

        if ($hasLiveInputs) {
            // Backward-compatible direct URL usage: calculate from the provided query values.
            $snapshot = $this->buildPreInvoiceSnapshot($order, $request->all(), false);
        } else {
            $snapshot = is_array($order->pre_invoice_snapshot ?? null)
                ? $order->pre_invoice_snapshot
                : null;

            if (!$snapshot) {
                $snapshot = [
                    'subtotal' => (float) ($order->subtotal ?? 0),
                    'discount_type' => $order->discount_type ?: 'fixed',
                    'discount_value' => Schema::hasColumn('orders', 'discount_value') ? (float) ($order->discount_value ?? 0) : 0,
                    'discount_amount' => (float) ($order->discount_amount ?? 0),
                    'product_discount_amount' => (float) ($order->product_discount_amount ?? 0),
                    'service_charge' => (float) ($order->service_charge ?? 0),
                    'vat_tax' => (float) ($order->vat_tax ?? 0),
                    'grand_total' => (float) ($order->grand_total ?? 0),
                ];
            }
        }

        // Render the saved pre-invoice numbers exactly as they were calculated.
        $order->discount_type = $snapshot['discount_type'] ?? 'fixed';
        $order->discount_amount = (float) ($snapshot['discount_amount'] ?? 0);
        $order->product_discount_amount = (float) ($snapshot['product_discount_amount'] ?? 0);
        $order->vat_tax = (float) ($snapshot['vat_tax'] ?? 0);
        $order->service_charge = (float) ($snapshot['service_charge'] ?? 0);
        $order->grand_total = (float) ($snapshot['grand_total'] ?? 0);

        $mergedOrderItems = $this->mergeOrderDetailsForDisplay($order->orderDetails);

        return view('admin.pos.pre_invoice', compact(
            'order',
            'restaurant',
            'invoiceSetting',
            'mergedOrderItems',
            'snapshot',
            'posSetting'
        ));
    }

}
