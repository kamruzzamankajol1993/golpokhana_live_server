<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PosSession;
use Carbon\Carbon;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    // লগইনের পর রোল চেক করে POS সেশন অটোমেটিক ম্যানেজ করা হবে
    protected function authenticated(Request $request, $user)
    {
        // ওয়েটার ছাড়া অন্য সব ইউজারের জন্য লগইনের সময় নতুন POS সেশন শুরু হবে
        if (!$user->hasRole('waiter')) {
            // আগের কোনো Open সেশন থাকলে আগে অটোমেটিক Closed করে দেওয়া হবে
            $openSessions = PosSession::where('user_id', $user->id)
                ->where('status', 'Open')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($openSessions as $session) {
                $this->closePosSession($session);
            }

            // তারপর নতুন সেশন শুরু হবে
            PosSession::create([
                'user_id' => $user->id,
                'weekday' => Carbon::now()->format('l'),
                'start_time' => Carbon::now(),
                'status' => 'Open',
            ]);

            return redirect()->route('home');
        }

        // ওয়েটার হলে সেশন শুরু হবে না, সরাসরি POS-এ যাবে
        return redirect()->route('pos.index');
    }

    private function closePosSession(PosSession $session): void
    {
        $startTime = Carbon::parse($session->start_time);
        $endTime = Carbon::now();

        $orders = Order::where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->where('status', 'Completed')
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
