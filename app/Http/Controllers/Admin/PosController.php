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
use App\Models\PosSession; // ফাইলের উপরে এটি যুক্ত করতে ভুলবেন না
use App\Models\PosSetting;
use App\Models\RestaurantSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
class PosController extends Controller
{
  public function index()
{
    $posOrderWindow = $this->getPosOrderWindowStatus();
    $isPosOrderTimeOpen = $posOrderWindow['is_open'];
    $posOpeningTime = $posOrderWindow['opening_time'];
    $posClosingTime = $posOrderWindow['closing_time'];
    $posClosedMessage = $this->getPosClosedMessage($posOpeningTime);

    // বন্ধ সময় POS-এর order interface load হবে না।
    if (!$isPosOrderTimeOpen) {
        return view('admin.pos.index', compact(
            'isPosOrderTimeOpen',
            'posOpeningTime',
            'posClosingTime',
            'posClosedMessage'
        ));
    }

    $posSetting = DB::table('pos_settings')->first();
    $categories = FoodCategory::whereNull('parent_category_id')->where('status', 1)->orderBy('sort_order', 'asc')->get();
    $tables = Table::with('zone')->get();
    $waiters = Waiter::where('status', 1)->get();
    $customers = Customer::orderBy('name', 'asc')->get();

    $isManagerRole = $this->userHasRoleCaseInsensitive(auth()->user(), 'manager');
    $randomHalfOrderButtonVisible = $isManagerRole
        && Schema::hasTable('pos_settings')
        && Schema::hasColumn('pos_settings', 'random_half_order_button_visible')
        && (bool) ($posSetting->random_half_order_button_visible ?? true);

    $availCount = $tables->filter(function($table) { return strtolower($table->initial_status) === 'available'; })->count();
    $occCount = $tables->filter(function($table) { return strtolower($table->initial_status) === 'occupied'; })->count();
    $resCount = $tables->filter(function($table) { return strtolower($table->initial_status) === 'reserved'; })->count();

    $activeSession = PosSession::where('user_id', auth()->id())->where('status', 'Open')->first();

    $activeTakeawayDeliveryOrders = Order::with(['customer', 'waiter', 'orderDetails'])
        ->whereIn('order_type', ['Takeaway', 'Delivery', 'takeaway', 'delivery', 'Take Away'])
        ->whereIn('status', ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'])
        ->orderBy('id', 'desc')
        ->get();

    $requirePreviousSessionClose = false;

    if ($activeSession) {
        if ($activeSession->start_time->format('Y-m-d') !== Carbon::now()->format('Y-m-d')) {
            $requirePreviousSessionClose = true;
        }
    }

    // সেশন হিস্ট্রি টেবিলের জন্য সব সেশন ডেটা নিয়ে আসা হলো
    $sessions = PosSession::with('user')->orderBy('id', 'desc')->get();

    return view('admin.pos.index', compact(
        'categories',
        'tables',
        'waiters',
        'customers',
        'posSetting',
        'availCount',
        'occCount',
        'resCount',
        'activeSession',
        'requirePreviousSessionClose',
        'sessions',
        'activeTakeawayDeliveryOrders',
        'isManagerRole',
        'randomHalfOrderButtonVisible',
        'isPosOrderTimeOpen',
        'posOpeningTime',
        'posClosingTime',
        'posClosedMessage'
    ));
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

public function startSession(Request $request)
    {
        PosSession::create([
            'user_id' => auth()->id(),
            'weekday' => Carbon::now()->format('l'), // Monday, Tuesday ইত্যাদি
            'start_time' => Carbon::now(),
            'status' => 'Open'
        ]);

        return response()->json(['status' => 'success', 'message' => 'Work period started successfully!']);
    }

   public function endSession(Request $request)
    {
        $session = PosSession::where('id', $request->session_id)
                             ->where('status', 'Open')
                             ->firstOrFail();

        $endTime = Carbon::now();

        // শুধু কমপ্লিট হওয়া অর্ডারগুলোর হিসাব বের করা হবে
        $orders = Order::where('created_at', '>=', $session->start_time)
                       ->where('created_at', '<=', $endTime)
                       ->where('status', 'Completed')
                       ->get();

        $sales_total = $orders->sum('subtotal');
        $service_charge = $orders->sum('service_charge');
        $vat_total = $orders->sum('vat_tax');
        $grand_total = $orders->sum('grand_total');

        // ==========================================
        // পেমেন্ট মেথড অনুযায়ী Cash, Card এবং MFC হিসাব
        // ==========================================
        $cash = 0; $card = 0; $mfc = 0;

        foreach($orders as $order) {
            if ($order->payment_type == 'Split') {
                $cash += $order->paid_in_cash;
                $card += $order->paid_in_card;
                $mfc += $order->paid_in_mfc;
            } else {
                if ($order->payment_type == 'Cash') $cash += $order->total_paid_amount;
                if ($order->payment_type == 'Card') $card += $order->total_paid_amount;
                // মোডালে ভ্যালু "Mobile Banking" দেওয়া আছে, কিন্তু আমরা MFC হিসেবে কাউন্ট করছি
                if ($order->payment_type == 'Mobile Banking') $mfc += $order->total_paid_amount;
            }
        }

        // JSON এ সেভ করার জন্য Array তৈরি (একদম আপনার রিকোয়ারমেন্ট অনুযায়ী)
        $incomes = [
            'Cash' => $cash,
            'Card' => $card,
            'MFC'  => $mfc
        ];

        // ডিউরেশন ক্যালকুলেশন
        $durationDiff = $endTime->diffAsCarbonInterval($session->start_time);
        $duration = $durationDiff->cascade()->forHumans(['short' => true]); // যেমন: 10h 49m

        $session->update([
            'end_time' => $endTime,
            'duration' => $duration,
            'status' => 'Closed',
            'sales_total' => $sales_total,
            'service_charge' => $service_charge,
            'vat_total' => $vat_total,
            'grand_total' => $grand_total,
            'incomes_summary' => $incomes // এখানে JSON আপডেট হলো
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Work period ended successfully!',
            'session_id' => $session->id // প্রিন্টের জন্য
        ]);
    }

public function printSessionReport($id)
    {
        $session = PosSession::with('user')->findOrFail($id);
        $restaurant = \App\Models\RestaurantSetting::first();
        $taxSetting = DB::table('tax_settings')->first();
        return view('admin.pos.session_report', compact('session', 'restaurant', 'taxSetting'));
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

                // নতুন এডিট করা সময় সীমার ভেতরের Completed অর্ডারগুলো নেওয়া হচ্ছে
                $orders = Order::where('created_at', '>=', $startTime)
                               ->where('created_at', '<=', $endTime)
                               ->where('status', 'Completed')
                               ->get();

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
            $session->update([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'weekday' => $startTime->format('l'),
                'status' => $request->status,
                'duration' => $duration,
                'sales_total' => $request->status == 'Closed' ? $sales_total : 0,
                'service_charge' => $request->status == 'Closed' ? $service_charge : 0,
                'vat_total' => $request->status == 'Closed' ? $vat_total : 0,
                'grand_total' => $request->status == 'Closed' ? $grand_total : 0,
                'incomes_summary' => $request->status == 'Closed' ? $incomes : null
            ]);

            // Session History edit থেকে Open session Closed করলে অটোমেটিক নতুন session শুরু হবে
            $shouldStartNewSession = $previousStatus == 'Open'
                && $request->status == 'Closed'
                && $endTime;

            if ($shouldStartNewSession) {
                $hasOpenSession = PosSession::where('user_id', $sessionUserId)
                    ->where('status', 'Open')
                    ->exists();

                if (!$hasOpenSession) {
                    PosSession::create([
                        'user_id' => $sessionUserId,
                        'weekday' => Carbon::now()->format('l'),
                        'start_time' => Carbon::now(),
                        'status' => 'Open'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $shouldStartNewSession
                    ? 'Session closed, report recalculated, and a new session started successfully!'
                    : 'Session updated and report recalculated successfully!'
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

    public function addToCart(Request $request)
{
    if (!$this->getPosOrderWindowStatus()['is_open']) {
        return $this->posOrderClosedResponse();
    }

    $cartKey = $this->getCartKey($request);
    $cart = Session::get($cartKey, []);

    $food = FoodItem::findOrFail($request->food_id);

    $isComplimentary = $request->boolean('is_complimentary');
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

        return view('admin.pos.partials.cart_items', compact('cart', 'subtotal', 'vat_rate', 'service_charge_rate'))->render();
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

public function placeOrder(Request $request)
    {
        if (!$this->getPosOrderWindowStatus()['is_open']) {
            return $this->posOrderClosedResponse();
        }

        $cartKey = $this->getCartKey($request);
        $cart = Session::get($cartKey, []);

        if (count($cart) == 0) {
            return response()->json(['status' => 'error', 'message' => 'Cart is empty!']);
        }

        // New Order modal থেকে Complimentary Order select করলে cart-এর সব item free হবে।
        // Offcanvas-এর Add Complimentary আগের মতো individual complimentary item হিসেবেই থাকবে।
        $isComplimentaryOrder = $request->boolean('is_complimentary_order');
        if ($isComplimentaryOrder) {
            $cart = $this->makeCartComplimentary($cart);
            Session::put($cartKey, $cart);
        }

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
                        'due' => $grand_total,
                        'status' => $newStatus,
                        'notes' => $request->order_notes ?? $order->notes,
                        'preparation_time' => $request->preparation_time ?? 20
                    ];

                    if (Schema::hasColumn('orders', 'is_complimentary_order')) {
                        $orderUpdateData['is_complimentary_order'] = $isComplimentaryOrder ? 1 : 0;
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
                        'due' => $grand_total,
                        'status' => $newStatus // ওয়েটার করলে Waiter_Hold, ফ্রন্ট ডেস্ক করলে Pending হবে
                    ];

                    // Add More Food থেকে শুধু complimentary item add হলে পুরো পুরনো order complimentary করা হবে না।
                    if (Schema::hasColumn('orders', 'is_complimentary_order') && $isComplimentaryOrder) {
                        $orderUpdateData['is_complimentary_order'] = 1;
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
                    'due' => $grand_total,
                    'status' => $newStatus,
                    'notes' => $request->order_notes,
                    'order_time' => now(),
                    'preparation_time' => $request->preparation_time ?? 20
                ];

                if (Schema::hasColumn('orders', 'is_complimentary_order')) {
                    $orderCreateData['is_complimentary_order'] = $isComplimentaryOrder ? 1 : 0;
                }

                $order = Order::create($orderCreateData);

                if ($requestOrderType == 'dine_in') {
                    Table::where('id', $request->table_id)->update(['initial_status' => 'Occupied']);
                }
            }

            // নতুন KOT জেনারেট করা (Global serial: KOT-1, KOT-2, KOT-3...)
            $kotNumber = $this->generateGlobalKotNumber();
            $kot = OrderKot::create([
                'order_id' => $order->id,
                'kot_number' => $kotNumber,
                'kitchen_status' => $isWaiter ? 'Hold' : 'Pending' // ওয়েটার হলে হোল্ড হবে
            ]);

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
        $order = Order::with(['kots.orderDetails', 'orderDetails', 'waiter', 'customer', 'table'])
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

        return view('admin.pos.partials.offcanvas_order', compact('order', 'kitchenBusy', 'finalPaymentDependsOnKitchenStatus', 'availableSwapTables'))->render();
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

    public function getPosOrder($order_id)
    {
        $order = Order::with(['kots.orderDetails', 'orderDetails', 'waiter', 'customer', 'table'])
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
                    'subtotal' => $order->subtotal
                ]
            ]);
        }

        $finalPaymentDependsOnKitchenStatus = (bool) (DB::table('pos_settings')->value('final_payment_depends_on_kitchen_status') ?? 0);

        $kitchenBusy = $finalPaymentDependsOnKitchenStatus
            ? $order->kots()->whereIn('kitchen_status', ['Pending', 'Cooking', 'Hold'])->exists()
            : false;

        return view('admin.pos.partials.offcanvas_order', compact('order', 'kitchenBusy', 'finalPaymentDependsOnKitchenStatus'))->render();
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

    public function completePayment(Request $request)
    {
        if (!$request->filled('order_id')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment is allowed only from an active order offcanvas. Please send the order to kitchen first.'
            ]);
        }

        $selectedPaymentMethod = $request->input('payment_method');
        if (in_array($selectedPaymentMethod, ['Card', 'Mobile Banking'], true)
            && trim((string) $request->input('transaction_id')) === '') {
            $referenceLabel = $selectedPaymentMethod === 'Card'
                ? 'Card Reference Number'
                : 'MFS Reference Number';

            return response()->json([
                'status' => 'error',
                'message' => $referenceLabel . ' is required.'
            ], 422);
        }

        if ($selectedPaymentMethod === 'Split') {
            $splitCardAmount = max(0, (float) $request->input('paid_in_card', 0));
            $splitMfsAmount = max(0, (float) $request->input('paid_in_mfc', 0));

            if ($splitCardAmount > 0 && trim((string) $request->input('split_card_reference')) === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Card Reference Number is required when a Card amount is entered.'
                ], 422);
            }

            if ($splitMfsAmount > 0 && trim((string) $request->input('split_mfs_reference')) === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'MFS Reference Number is required when an MFS amount is entered.'
                ], 422);
            }
        }

        if (mb_strlen((string) $request->input('remark', '')) > 1000) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referred by Whom may not be greater than 1000 characters.'
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

            // বিল ক্যালকুলেশনে সার্ভিস চার্জ চেক (শুধু Dine-In হলে সার্ভিস চার্জ কাটবে)
            $service_charge_rate = (strtolower($order->order_type) == 'dine-in' || strtolower($order->order_type) == 'dine_in') ? ($taxSetting->service_charge ?? 0) : 0;

            $discount_value = $request->discount_value ?? 0;
            $discount_type = $request->discount_type ?? 'fixed';

            // ভ্যাট, সার্ভিস চার্জ এবং ডিসকাউন্ট ক্যালকুলেশন (রাউন্ড ফিগার সহ)
            $service_charge = round(($subtotal * $service_charge_rate) / 100);
            $tax = round((($subtotal + $service_charge) * $vat_rate) / 100);
            $discount_amount = round(($discount_type == 'percentage') ? ($subtotal * $discount_value) / 100 : $discount_value);
            $grand_total = round(($subtotal + $tax + $service_charge) - $discount_amount);

            // ===============================================
            // পেমেন্ট স্প্লিট এবং Due ক্যালকুলেশন
            // ===============================================
            $paymentMethod = $request->payment_method;

            // Total Paid = শুধু bill payment. Tips/Given/Change আলাদা থাকবে।
            // Split হলে Total Paid input ব্যবহার হবে না; Cash + Card + Mobile Banking sum হবে।
            if ($paymentMethod == 'Split') {
                $cash = max(0, (float) ($request->paid_in_cash ?? 0));
                $card = max(0, (float) ($request->paid_in_card ?? 0));
                $mfc  = max(0, (float) ($request->paid_in_mfc ?? 0));
                $totalPaid = round($cash + $card + $mfc, 2);
            } else {
                $totalPaid = max(0, (float) ($request->total_paid_amount ?? 0));
                $cash = ($paymentMethod == 'Cash') ? $totalPaid : 0;
                $card = ($paymentMethod == 'Card') ? $totalPaid : 0;
                $mfc  = ($paymentMethod == 'Mobile Banking') ? $totalPaid : 0;
            }

            $tipsAmount = max(0, round((float) ($request->tips_amount ?? 0), 2));
            $givenMoney = max(0, round((float) ($request->given_money ?? 0), 2));
            $maxAllowedTips = max(0, round($givenMoney - $totalPaid, 2));

            // Given Money দিয়ে bill payment + tips—দুটিই cover করতে হবে।
            if ($tipsAmount > $maxAllowedTips) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Given Money is insufficient. Total Paid + Tips cannot exceed Given Money. Maximum allowed Tips: ৳' . number_format($maxAllowedTips, 2, '.', '') . '.'
                ]);
            }

            $changeAmount = max(0, round($givenMoney - $totalPaid - $tipsAmount, 2));

            // Due হিসাব করা হচ্ছে — tips/given money due কমাবে না, শুধু bill paid amount কমাবে।
            $due = max(0, round($grand_total - $totalPaid, 2));

            // ===============================================
            // মডেলের মাস অ্যাসাইনমেন্ট রেসট্রিকশন এড়াতে সরাসরি প্রপার্টি সেট করে সেভ করা
            // ===============================================
            $order->discount_type     = $discount_type;
            $order->discount_amount   = $discount_amount;
            $order->vat_tax           = $tax;
            $order->service_charge    = $service_charge;
            $order->grand_total       = $grand_total;
            $order->payment_type      = $paymentMethod;
            $order->transaction_id    = in_array($paymentMethod, ['Card', 'Mobile Banking'], true) ? trim((string) $request->transaction_id) : null;
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
            $order->total_paid_amount = $totalPaid;
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
        $cartKey = $this->getCartKey($request);
        $cart = Session::get($cartKey, []);

        if(isset($cart[$request->cart_id])) {
            $cart[$request->cart_id]['note'] = $request->note;
            Session::put($cartKey, $cart);
        }
        return response()->json(['status' => 'success']);
    }

    public function removeFromCart(Request $request)
    {
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


    public function removeOrderedItem(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_detail_id' => 'required|exists:order_details,id',
            'qty' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $order = Order::lockForUpdate()->findOrFail($request->order_id);
            $detail = OrderDetail::where('order_id', $order->id)
                ->lockForUpdate()
                ->findOrFail($request->order_detail_id);

            if (!empty($detail->is_unavailable)) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Unavailable item cannot be deleted again.']);
            }

            $currentQty = max(1, (int) $detail->quantity);
            $deleteQty = min((int) $request->qty, $currentQty);
            $deleteReason = trim((string) ($request->reason ?? ''));
            $unitTotal = $currentQty > 0 ? ((float) $detail->subtotal / $currentQty) : 0;
            $removeAmount = round($unitTotal * $deleteQty, 2);
            $addons = json_decode($detail->addons ?? '[]', true);
            if (!is_array($addons)) $addons = [];
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
                    : ($deleteQty >= $currentQty ? 'Ordered item fully deleted from offcanvas' : 'Ordered item quantity deleted from offcanvas'),
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

            $newSubtotal = max(0, round((float) $order->subtotal - $removeAmount, 2));
            $taxSetting = DB::table('tax_settings')->first();
            $vatRate = $taxSetting->vat_rate ?? 0;
            $serviceRate = (strtolower($order->order_type) == 'dine-in' || strtolower($order->order_type) == 'dine_in')
                ? ($taxSetting->service_charge ?? 0)
                : 0;

            $serviceCharge = round(($newSubtotal * $serviceRate) / 100);
            $vatTax = round((($newSubtotal + $serviceCharge) * $vatRate) / 100);
            // orders.discount_amount-এ calculated discount amount থাকে, তাই item delete হলেও একই amount cap করে রাখা হলো
            $discountAmount = min(round((float) ($order->discount_amount ?? 0)), round($newSubtotal + $serviceCharge + $vatTax));
            $grandTotal = max(0, round(($newSubtotal + $serviceCharge + $vatTax) - $discountAmount));
            $totalPaid = (float) ($order->total_paid_amount ?? 0);

            $order->subtotal = $newSubtotal;
            $order->service_charge = $serviceCharge;
            $order->vat_tax = $vatTax;
            $order->grand_total = $grandTotal;
            $order->due = max(0, $grandTotal - $totalPaid);
            $order->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Item quantity deleted successfully.'
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
        $order = Order::with(['orderDetails', 'customer', 'waiter', 'user'])->findOrFail($id);
        $restaurant = \App\Models\RestaurantSetting::first();

        // Final payment values are now stored separately:
        // total_paid_amount = bill payment, tips_amount = tips, given_money = received money, change_amount = return amount.
        $order->invoice_paid_amount = (float) ($order->total_paid_amount ?? 0);
        $order->invoice_paid_in_cash = (float) ($order->paid_in_cash ?? 0);
        $order->invoice_paid_in_card = (float) ($order->paid_in_card ?? 0);
        $order->invoice_paid_in_mfc = (float) ($order->paid_in_mfc ?? 0);

        return view('admin.pos.invoice', compact('order', 'restaurant'));
    }

    public function printPreInvoice(Request $request, $id)
    {
        $order = Order::with(['orderDetails', 'customer', 'waiter', 'user'])->findOrFail($id);
        $restaurant = \App\Models\RestaurantSetting::first();
        $taxSetting = DB::table('tax_settings')->first();
        $invoiceSetting = \App\Models\InvoiceSetting::first();

        // মোডালে দেওয়া লাইভ ডিসকাউন্টটি রিসিভ করে ক্যালকুলেশন করা হচ্ছে
        // (যাতে সেভ না করলেও প্রি-ইনভয়েসে ডিসকাউন্ট দেখায়)
        $subtotal = $order->subtotal;
        $disc_type = $request->disc_type ?? 'fixed';
        $disc_val = $request->disc_val ?? 0;

        $discount_amount = ($disc_type == 'percentage') ? ($subtotal * $disc_val) / 100 : $disc_val;
        $vat_rate = $taxSetting->vat_rate ?? 0;
        $service_rate = $taxSetting->service_charge ?? 0;

        $service = round(($subtotal * $service_rate) / 100);
$tax = round((($subtotal + $service) * $vat_rate) / 100);
$discount_amount = round(($disc_type == 'percentage') ? ($subtotal * $disc_val) / 100 : $disc_val);
$grand_total = round(($subtotal + $tax + $service) - $discount_amount);

        // শুধু ভিউয়ের জন্য সাময়িকভাবে ডাটাগুলো ওভাররাইড করা হলো (ডাটাবেজে সেভ হবে না)
        $order->discount_type = $disc_type;
        $order->discount_amount = $discount_amount;
        $order->vat_tax = $tax;
        $order->service_charge = $service;
        $order->grand_total = $grand_total;

        return view('admin.pos.pre_invoice', compact('order', 'restaurant', 'invoiceSetting'));
    }

}
