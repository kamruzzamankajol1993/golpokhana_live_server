<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderKot;
use App\Models\RestaurantSetting;
use App\Models\Table;
use App\Models\TableBooking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfflinePosOperationController extends Controller
{
    public function posStatus(): JsonResponse
    {
        $settings = RestaurantSetting::first();
        $opening = $this->normalizeTime($settings?->opening_time, '12:01');
        $closing = $this->normalizeTime($settings?->closing_time, '06:00');
        $current = Carbon::now('Asia/Dhaka')->format('H:i');

        $isOpen = $opening === $closing
            ? true
            : ($opening > $closing
                ? ($current >= $opening || $current <= $closing)
                : ($current >= $opening && $current <= $closing));

        return response()->json([
            'status' => true,
            'is_open' => $isOpen,
            'opening_time' => $opening,
            'closing_time' => $closing,
            'current_time' => $current,
            'server_time' => now()->toDateTimeString(),
        ]);
    }

    public function paymentOptions(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'payment_methods' => [
                ['value' => 'Cash', 'label' => 'Cash'],
                ['value' => 'Card', 'label' => 'Bank / Card'],
                ['value' => 'Mobile Banking', 'label' => 'MFS'],
                ['value' => 'Split', 'label' => 'Split'],
            ],
            'card_types' => ['Visa', 'Mastercard', 'American Express', 'UnionPay', 'JCB', 'Nexus', 'Diners Club', 'GPay', 'Bangla QR Card', 'Other'],
            'mfs_providers' => ['Rocket', 'bKash', 'MYCash', 'Islami Bank mCash', 'tap', 'FirstCash', 'Upay', 'OK Wallet', 'RUPALICASH', 'TeleCash', 'Islamic Wallet', 'Meghna Pay', 'Nagad', 'Bangla QR', 'LENDEN', 'Other'],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $tables = $this->tableStates();
        $takeawayDelivery = $this->takeawayDeliveryRows();
        $bookings = $this->todayBookings();

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'tables' => $tables,
            'takeaway_delivery_orders' => $takeawayDelivery,
            'today_bookings' => $bookings,
            'counts' => [
                'tables' => count($tables),
                'available' => count(array_filter($tables, fn ($row) => ($row['display_status'] ?? '') === 'Available')),
                'occupied' => count(array_filter($tables, fn ($row) => ($row['display_status'] ?? '') === 'Occupied')),
                'waiter_qr' => count(array_filter($tables, fn ($row) => in_array($row['display_status'] ?? '', ['Waiter Order', 'QR Order'], true))),
                'takeaway_delivery' => count($takeawayDelivery),
                'bookings' => count($bookings),
            ],
        ]);
    }

    public function tableStatesResponse(): JsonResponse
    {
        return response()->json(['status' => true, 'server_time' => now()->toDateTimeString(), 'tables' => $this->tableStates()]);
    }

    public function takeawayDelivery(): JsonResponse
    {
        $rows = $this->takeawayDeliveryRows();
        return response()->json(['status' => true, 'server_time' => now()->toDateTimeString(), 'orders' => $rows, 'meta' => ['count' => count($rows)]]);
    }

    public function orderByTable(int $tableId): JsonResponse
    {
        $order = Order::query()->with($this->orderRelations())
            ->where('table_id', $tableId)
            ->whereIn('status', ['Pending', 'Waiter_Hold', 'QR_Pending', 'QR_Hold', 'Cooking', 'Ready'])
            ->latest('id')->first();

        if (!$order) {
            return response()->json(['status' => false, 'message' => 'No active order found.', 'order' => null], 404);
        }

        return response()->json(['status' => true, 'order' => $this->serializeOrder($order)]);
    }

    public function order(int $id): JsonResponse
    {
        $order = Order::with($this->orderRelations())->findOrFail($id);
        return response()->json(['status' => true, 'order' => $this->serializeOrder($order)]);
    }

    public function printData(int $id): JsonResponse
    {
        $order = Order::with($this->orderRelations())->findOrFail($id);
        $settings = app(OfflinePosSettingController::class)->settings(request())->getData(true);

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'order' => $this->serializeOrder($order),
            'restaurant_settings' => $settings['restaurant_settings'] ?? [],
            'tax_settings' => $settings['tax_settings'] ?? [],
            'invoice_settings' => $settings['invoice_settings'] ?? [],
            'pos_settings' => $settings['pos_settings'] ?? [],
        ]);
    }

    public function kotPrintData(int $id): JsonResponse
    {
        $kot = OrderKot::with(['order.customer', 'order.table', 'order.waiter', 'order.deliveryPartner', 'orderDetails'])->findOrFail($id);
        $data = $kot->toArray();
        $data['server_id'] = $kot->id;
        $data['local_uuid'] = $kot->offline_uuid ?? null;
        $isRunningOrder = OrderKot::where('order_id', $kot->order_id)
            ->where('id', '<', $kot->id)
            ->where('kitchen_status', '!=', 'Hold')
            ->exists();

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'kot' => $data,
            'is_running_order' => $isRunningOrder,
            'powered_by_system_name' => RestaurantSetting::query()->value('name'),
        ]);
    }

    public function mergedKotPrintData(int $orderId): JsonResponse
    {
        $order = Order::with($this->orderRelations())->findOrFail($orderId);
        $merged = [];
        foreach ($order->orderDetails as $detail) {
            if (!empty($detail->is_unavailable)) continue;
            $addons = is_string($detail->addons) ? (json_decode($detail->addons, true) ?: []) : ($detail->addons ?? []);
            $key = (string) ($detail->product_id ?? $detail->product_name) . '|' . md5(json_encode($addons)) . '|' . trim((string) ($detail->food_note ?? ''));
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'product_id' => $detail->product_id,
                    'product_name' => $detail->product_name,
                    'quantity' => 0,
                    'addons' => $addons,
                    'food_note' => $detail->food_note,
                    'is_complimentary' => (bool) ($detail->is_complimentary ?? false),
                ];
            }
            $merged[$key]['quantity'] += (int) ($detail->quantity ?? 0);
        }

        $sortedKots = $order->kots->sortBy('id')->values();
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'order' => $this->serializeOrder($order),
            'merged_items' => array_values($merged),
            'kot_numbers' => $sortedKots->pluck('kot_number')->filter()->values()->all(),
            'last_kot_at' => optional($sortedKots->last())->created_at ?: $order->updated_at ?: $order->created_at,
            'powered_by_system_name' => RestaurantSetting::query()->value('name'),
        ]);
    }

    public function verifyActionPassword(Request $request): JsonResponse
    {
        $provided = (string) $request->validate(['password' => ['required', 'string']])['password'];
        $saved = (string) (optional(RestaurantSetting::first())->pos_action_password ?? '');
        if ($saved === '') {
            return response()->json(['status' => false, 'message' => 'POS Action Password is not configured.'], 422);
        }
        if (!hash_equals($saved, $provided)) {
            return response()->json(['status' => false, 'message' => 'Wrong POS Action Password.'], 422);
        }
        return response()->json(['status' => true, 'message' => 'Password verified.']);
    }

    private function tableStates(): array
    {
        if (!Schema::hasTable('tables')) return [];
        $activeStatuses = ['Pending', 'Waiter_Hold', 'QR_Pending', 'QR_Hold', 'Cooking', 'Ready'];
        $activeOrders = Order::query()->whereNotNull('table_id')->whereIn('status', $activeStatuses)->latest('id')->get()
            ->groupBy('table_id')->map(fn ($rows) => $rows->first());

        $now = Carbon::now('Asia/Dhaka');
        $bookings = collect();
        if (Schema::hasTable('table_bookings')) {
            $bookingRows = TableBooking::with(['customer', 'tables'])->whereIn('status', ['pending', 'upcoming', 'confirmed', 'seated'])
                ->whereDate('booking_date', $now->toDateString())
                ->where(function ($q) use ($now) {
                    $time = $now->format('H:i:s');
                    $q->whereNull('booking_start_time')->orWhereTime('booking_start_time', '<=', $time);
                })
                ->where(function ($q) use ($now) {
                    $time = $now->format('H:i:s');
                    $q->whereNull('booking_end_time')->orWhereTime('booking_end_time', '>=', $time);
                })->orderBy('booking_start_time')->get();

            foreach ($bookingRows as $booking) {
                $ids = $booking->tables->pluck('id')->push($booking->table_id)->filter()->unique();
                foreach ($ids as $id) {
                    if (!$bookings->has((int) $id)) $bookings->put((int) $id, $booking);
                }
            }
        }

        $zoneTable = Schema::hasTable('floor_zones') ? 'floor_zones' : (Schema::hasTable('zones') ? 'zones' : null);
        $zoneNames = $zoneTable ? DB::table($zoneTable)->pluck('name', 'id') : collect();

        return Table::query()->orderBy('table_number')->get()->map(function ($table) use ($activeOrders, $bookings, $zoneNames) {
            $order = $activeOrders->get($table->id) ?: $activeOrders->get((string) $table->id);
            $booking = $bookings->get($table->id) ?: $bookings->get((string) $table->id);
            $display = 'Available';
            if ($order) {
                $orderStatus = strtolower(trim((string) $order->status));
                $display = str_contains($orderStatus, 'waiter') ? 'Waiter Order' : (str_contains($orderStatus, 'qr') ? 'QR Order' : 'Occupied');
            } elseif ($booking) {
                $display = 'Reserved';
            } elseif (strtolower(trim((string) ($table->initial_status ?? ''))) === 'occupied') {
                $display = 'Occupied';
            }
            $zoneId = $table->floor_zone_id ?? $table->zone_id ?? null;
            return [
                'server_id' => $table->id,
                'table_number' => $table->table_number,
                'seating_capacity' => $table->seating_capacity,
                'zone_server_id' => $zoneId,
                'zone_name' => $zoneId ? ($zoneNames[$zoneId] ?? null) : null,
                'initial_status' => $table->initial_status,
                'display_status' => $display,
                'active_order_id' => $order?->id,
                'active_order_number' => $order?->order_number,
                'bill_printed' => $order && Schema::hasColumn('orders', 'pre_invoice_printed_at') ? !empty($order->pre_invoice_printed_at) : false,
                'booking' => $booking ? [
                    'server_id' => $booking->id,
                    'booking_id' => $booking->booking_id,
                    'customer_id' => $booking->customer_id,
                    'customer_name' => $booking->customer?->name,
                    'start_time' => $booking->booking_start_time,
                    'end_time' => $booking->booking_end_time,
                ] : null,
            ];
        })->values()->all();
    }

    private function takeawayDeliveryRows(): array
    {
        if (!Schema::hasTable('orders')) return [];
        return Order::with(['customer', 'deliveryPartner', 'waiter', 'orderDetails', 'kots'])
            ->whereIn('status', ['Pending', 'Waiter_Hold', 'Cooking', 'Ready', 'Completed'])
            ->where(function ($q) {
                $q->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(TRIM(order_type), '-', ''), ' ', ''), '_', '')) IN (?, ?)", ['takeaway', 'delivery']);
            })->latest('id')->limit(500)->get()->map(fn ($order) => $this->serializeOrder($order))->values()->all();
    }

    private function todayBookings(): array
    {
        if (!Schema::hasTable('table_bookings')) return [];
        return TableBooking::with(['customer', 'table', 'tables', 'occasion'])->whereDate('booking_date', Carbon::now('Asia/Dhaka')->toDateString())
            ->orderBy('booking_start_time')->get()->map(function ($booking) {
                $data = $booking->toArray();
                $data['server_id'] = $booking->id;
                $data['local_uuid'] = $booking->offline_uuid ?? null;
                $data['table_server_ids'] = $booking->tables->pluck('id')->push($booking->table_id)->filter()->unique()->map(fn ($id) => (int) $id)->values()->all();
                return $data;
            })->values()->all();
    }

    private function normalizeTime($value, string $fallback): string
    {
        if (empty($value)) return $fallback;
        try {
            return Carbon::parse((string) $value)->format('H:i');
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    private function orderRelations(): array
    {
        return ['customer', 'table', 'waiter', 'deliveryPartner', 'tableBooking', 'kots.orderDetails', 'orderDetails'];
    }

    private function serializeOrder(Order $order): array
    {
        $data = $order->toArray();
        $data['server_id'] = $order->id;
        $data['local_uuid'] = $order->offline_uuid ?? null;
        $data['display_status'] = match ((string) ($order->status ?? '')) {
            'Cooking' => 'Preparing',
            'Waiter_Hold', 'QR_Hold', 'QR_Pending' => 'Pending',
            default => (string) ($order->status ?? ''),
        };
        $data['delivery_partner_name'] = $order->delivery_partner_display_name;
        if (isset($data['order_details'])) {
            foreach ($data['order_details'] as &$detail) {
                if (isset($detail['id'])) $detail['server_id'] = $detail['id'];
                $detail['local_uuid'] = $detail['offline_uuid'] ?? null;
                if (isset($detail['addons']) && is_string($detail['addons'])) $detail['addons_decoded'] = json_decode($detail['addons'], true) ?: [];
            }
            unset($detail);
        }
        if (isset($data['kots'])) {
            foreach ($data['kots'] as &$kot) {
                if (isset($kot['id'])) $kot['server_id'] = $kot['id'];
                $kot['local_uuid'] = $kot['offline_uuid'] ?? null;
            }
            unset($kot);
        }
        return $data;
    }
}
