<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PosSession;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Services\PosSessionManagerResolver;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Login no longer starts or replaces a POS session automatically.
     * A user starts the work period explicitly from the POS header.
     */
    protected function authenticated(Request $request, $user)
    {
        $employee = $user->employee;
        if ($employee && (!$employee->can_login || $employee->employment_status !== 'active')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Login access for this employee is disabled or the employee is not active.',
            ]);
        }

        // The POS work period is Manager-owned and shared. Any user's browser
        // activity participates in the same Manager session timeout window.
        $sessionManagerId = app(PosSessionManagerResolver::class)->resolveId($user);
        if ($sessionManagerId) {
            $this->closeTimedOutPosSessionsForUser($sessionManagerId);
        }

        // A running shared Manager session is accepted automatically after login;
        // do not ask each user/browser to Continue Previous or Start New.
        $request->session()->forget('force_pos_unfinished_prompt');

        if ($user->hasRole('waiter')) {
            return redirect()->route('pos.index');
        }

        return redirect()->route('home');
    }

    private function closeTimedOutPosSessionsForUser(int $userId): void
    {
        $now = Carbon::now('Asia/Dhaka');
        $lifetimeMinutes = max(1, (int) config('session.lifetime', 180));

        $openSessions = PosSession::where('user_id', $userId)
            ->where('status', 'Open')
            ->orderBy('id')
            ->get();

        foreach ($openSessions as $session) {
            $lastActivity = null;

            if (Schema::hasColumn('pos_sessions', 'last_activity_at') && $session->last_activity_at) {
                $lastActivity = Carbon::parse($session->last_activity_at, 'Asia/Dhaka');
            } elseif ($session->updated_at) {
                $lastActivity = Carbon::parse($session->updated_at, 'Asia/Dhaka');
            } else {
                $lastActivity = Carbon::parse($session->start_time, 'Asia/Dhaka');
            }

            $expiresAt = $lastActivity->copy()->addMinutes($lifetimeMinutes);
            if ($now->greaterThanOrEqualTo($expiresAt)) {
                // The close action happens at login, while the report window ends at
                // the timeout point so hours after browser-close are not counted.
                $this->closePosSession($session, $expiresAt);
            }
        }
    }

    /**
     * Explicit logout closes the user's open POS work period(s).
     * Closing a browser/tab is intentionally NOT treated as logout because that
     * browser event is not reliable. In that case the open session can be resumed.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            // Logout closes the POS work period. Only occupied tables block logout;
            // unpaid/due bills do not block logout.
            $blockers = $this->getPosLogoutBlockers();
            if ($blockers['blocked']) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 'logout_blocked_unsettled_pos',
                        'message' => $blockers['message'],
                        'occupied_table_count' => $blockers['occupied_table_count'],
                        'pending_takeaway_delivery_count' => $blockers['pending_takeaway_delivery_count'],
                        'pending_takeaway_delivery_order_ids' => $blockers['pending_takeaway_delivery_order_ids'],
                        'unpaid_bill_count' => $blockers['unpaid_bill_count'],
                    ], 409);
                }

                return back()->with('error', $blockers['message']);
            }

            // Do not close the shared Manager work period on user logout.
            // It stays available to the Manager and other POS users until someone
            // explicitly presses End Session or the inactivity timeout expires.
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Prevent explicit logout only while the restaurant still has occupied tables.
     * Unpaid/due bills are intentionally ignored. This remains server-side so a direct
     * POST to /logout cannot bypass the occupied-table requirement.
     */
    private function getPosLogoutBlockers(): array
    {
        $activeStatuses = ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'];

        // Existing rule: any occupied dine-in table blocks logout.
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

        // New rule: today's Pending Takeaway/Delivery orders must be completed
        // or cancelled before logout. Older orders and non-Pending statuses do not block.
        $today = Carbon::now('Asia/Dhaka')->toDateString();
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
            $messages[] = 'Please clear all occupied tables before logout.';
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

            $messages[] = 'Please complete or cancel today\'s ' . $pendingText . ' before logout.';
        }

        return [
            'blocked' => $occupiedTables->isNotEmpty() || $pendingTakeawayDeliveryOrders->isNotEmpty(),
            'occupied_table_count' => $occupiedTables->count(),
            'pending_takeaway_delivery_count' => $pendingTakeawayDeliveryOrders->count(),
            'pending_takeaway_delivery_order_ids' => $pendingTakeawayDeliveryOrders
                ->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            // Kept for response compatibility with the existing frontend.
            'unpaid_bill_count' => 0,
            'message' => empty($messages) ? '' : 'Logout blocked. ' . implode(' ', $messages),
        ];
    }

    private function closePosSession(PosSession $session, ?Carbon $endTime = null): void
    {
        $startTime = Carbon::parse($session->start_time, 'Asia/Dhaka');
        $endTime = ($endTime ?: Carbon::now('Asia/Dhaka'))->copy();

        if ($endTime->lt($startTime)) {
            $endTime = $startTime->copy();
        }

        // Session reports use every non-cancelled order created inside the
        // session start/end window; completion is not required.
        $orders = Order::whereBetween('created_at', [$startTime, $endTime])
            ->whereNotIn('status', ['Cancelled', 'cancelled'])
            ->get();

        $salesTotal = $orders->sum('subtotal');
        $serviceCharge = $orders->sum('service_charge');
        $vatTotal = $orders->sum('vat_tax');
        $grandTotal = $orders->sum('grand_total');

        $cash = 0;
        $card = 0;
        $mfc = 0;

        foreach ($orders as $order) {
            if ($order->payment_type == 'Split') {
                $cash += $order->paid_in_cash;
                $card += $order->paid_in_card;
                $mfc += $order->paid_in_mfc;
            } else {
                if ($order->payment_type == 'Cash') {
                    $cash += $order->total_paid_amount;
                }
                if ($order->payment_type == 'Card') {
                    $card += $order->total_paid_amount;
                }
                if ($order->payment_type == 'Mobile Banking') {
                    $mfc += $order->total_paid_amount;
                }
            }
        }

        $durationDiff = $endTime->diffAsCarbonInterval($startTime);
        $duration = $durationDiff->cascade()->forHumans(['short' => true]);

        $session->update([
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
        ]);
    }
}
