<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderKot;
use App\Models\Table;
use App\Models\Waiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OfflinePosSyncController extends Controller
{
    public function pull(Request $request): JsonResponse
    {
        // First implementation: return the current full bootstrap payload.
        // It is safe for the offline app and can later be optimized to only return changed rows.
        $response = app(OfflinePosDataController::class)->bootstrap();
        $payload = $response->getData(true);
        $payload['mode'] = 'full_bootstrap_fallback';
        $payload['requested_last_synced_at'] = $request->query('last_synced_at');
        return response()->json($payload);
    }

    public function pushCustomers(Request $request): JsonResponse
    {
        $customers = $request->input('customers', []);
        $results = [];

        foreach ($customers as $row) {
            if (!is_array($row)) continue;
            $offlineUuid = $row['local_uuid'] ?? $row['offline_uuid'] ?? null;

            $customer = null;
            if (!empty($row['server_id'])) {
                $customer = Customer::find($row['server_id']);
            }
            if (!$customer && $offlineUuid && Schema::hasColumn('customers', 'offline_uuid')) {
                $customer = Customer::where('offline_uuid', $offlineUuid)->first();
            }
            if (!$customer && !empty($row['phone'])) {
                $customer = Customer::where('phone', $row['phone'])->first();
            }
            if (!$customer) {
                $customer = new Customer();
            }

            $customer->fill($this->filterColumns('customers', [
                'offline_uuid' => $offlineUuid,
                'name' => $row['name'] ?? 'Walk-in Customer',
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'dob' => $row['dob'] ?? null,
                'address' => $row['address'] ?? null,
                'points' => $row['points'] ?? 0,
                'total_orders' => $row['total_orders'] ?? 0,
            ]));
            $customer->save();

            $results[] = [
                'local_uuid' => $offlineUuid,
                'server_id' => $customer->id,
                'status' => 'synced',
            ];
        }

        return response()->json(['status' => true, 'customers' => $results, 'server_time' => now()->toDateTimeString()]);
    }

    public function pushOrders(Request $request): JsonResponse
    {
        $orders = $request->input('orders', []);
        $results = [];

        foreach ($orders as $payload) {
            if (!is_array($payload)) continue;

            DB::beginTransaction();
            try {
                $result = $this->upsertOrder($payload);
                DB::commit();
                $results[] = $result;
            } catch (Throwable $e) {
                DB::rollBack();
                $results[] = [
                    'local_uuid' => $payload['local_uuid'] ?? null,
                    'server_id' => $payload['server_id'] ?? null,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['status' => true, 'orders' => $results, 'server_time' => now()->toDateTimeString()]);
    }

    private function upsertOrder(array $payload): array
    {
        $offlineUuid = $payload['local_uuid'] ?? $payload['offline_uuid'] ?? null;

        $order = null;
        if (!empty($payload['server_id'])) {
            $order = Order::find($payload['server_id']);
        }
        if (!$order && $offlineUuid && Schema::hasColumn('orders', 'offline_uuid')) {
            $order = Order::where('offline_uuid', $offlineUuid)->first();
        }
        if (!$order) {
            $order = new Order();
        }

        $customerId = $payload['customer_server_id'] ?? $payload['customer_id'] ?? null;
        if (!$customerId && !empty($payload['customer'])) {
            $customerResult = $this->pushSingleCustomer($payload['customer']);
            $customerId = $customerResult?->id;
        }

        $tableId = $payload['table_server_id'] ?? $payload['table_id'] ?? null;
        $waiterId = $payload['waiter_server_id'] ?? $payload['waiter_id'] ?? null;

        $orderData = [
            'offline_uuid' => $offlineUuid,
            'order_number' => $payload['order_number'] ?? null,
            'customer_id' => $customerId,
            'table_id' => $tableId,
            'send_to_kitchen' => $payload['send_to_kitchen'] ?? 1,
            'subtotal' => $payload['subtotal'] ?? 0,
            'vat_tax' => $payload['vat_tax'] ?? 0,
            'service_charge' => $payload['service_charge'] ?? 0,
            'discount_amount' => $payload['discount_amount'] ?? 0,
            'discount_type' => $payload['discount_type'] ?? null,
            'reward_point_discount' => $payload['reward_point_discount'] ?? 0,
            'delivery_charge' => $payload['delivery_charge'] ?? 0,
            'grand_total' => $payload['grand_total'] ?? 0,
            'due' => $payload['due'] ?? 0,
            'total_paid_amount' => $payload['total_paid_amount'] ?? 0,
            'tips_amount' => $payload['tips_amount'] ?? 0,
            'given_money' => $payload['given_money'] ?? 0,
            'change_amount' => $payload['change_amount'] ?? 0,
            'paid_in_cash' => $payload['paid_in_cash'] ?? 0,
            'paid_in_card' => $payload['paid_in_card'] ?? 0,
            'paid_in_mfc' => $payload['paid_in_mfc'] ?? 0,
            'delivery_address' => $payload['delivery_address'] ?? null,
            'status' => $payload['status'] ?? 'Pending',
            'order_type' => $payload['order_type'] ?? 'Dine-In',
            'user_id' => $payload['user_server_id'] ?? $payload['user_id'] ?? null,
            'waiter_id' => $waiterId,
            'order_time' => $payload['order_time'] ?? $payload['created_at'] ?? now(),
            'preparation_time' => $payload['preparation_time'] ?? null,
            'kitchen_to_payment_minutes' => $payload['kitchen_to_payment_minutes'] ?? null,
            'payment_type' => $payload['payment_type'] ?? 'Cash',
            'transaction_id' => $payload['transaction_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'is_complimentary_order' => $payload['is_complimentary_order'] ?? 0,
        ];

        $order->fill($this->filterColumns('orders', $orderData));
        $order->save();

        if ($order->table_id) {
            $status = strtolower((string) $order->status) === 'completed' ? 'Available' : 'Occupied';
            Table::where('id', $order->table_id)->update(['initial_status' => $status]);
        }

        $kotMap = [];
        foreach (($payload['kots'] ?? []) as $kotPayload) {
            $kot = $this->upsertKot($order, $kotPayload);
            $kotMap[$kotPayload['local_uuid'] ?? $kotPayload['id'] ?? $kot->id] = $kot->id;
        }

        foreach (($payload['order_details'] ?? $payload['details'] ?? []) as $detailPayload) {
            $kotLocal = $detailPayload['order_kot_local_uuid'] ?? null;
            $kotId = $detailPayload['order_kot_server_id'] ?? ($kotLocal && isset($kotMap[$kotLocal]) ? $kotMap[$kotLocal] : null);
            $this->upsertDetail($order, $detailPayload, $kotId);
        }

        return [
            'local_uuid' => $offlineUuid,
            'server_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => 'synced',
        ];
    }

    private function upsertKot(Order $order, array $payload): OrderKot
    {
        $offlineUuid = $payload['local_uuid'] ?? $payload['offline_uuid'] ?? null;
        $kot = null;
        if (!empty($payload['server_id'])) $kot = OrderKot::find($payload['server_id']);
        if (!$kot && $offlineUuid && Schema::hasColumn('order_kots', 'offline_uuid')) {
            $kot = OrderKot::where('offline_uuid', $offlineUuid)->first();
        }
        if (!$kot) $kot = new OrderKot();

        $kot->fill($this->filterColumns('order_kots', [
            'offline_uuid' => $offlineUuid,
            'order_id' => $order->id,
            'kot_number' => $payload['kot_number'] ?? ('KOT-' . now()->timestamp),
            'kitchen_status' => $payload['kitchen_status'] ?? 'Pending',
        ]));
        $kot->save();
        return $kot;
    }

    private function upsertDetail(Order $order, array $payload, ?int $kotId): OrderDetail
    {
        $offlineUuid = $payload['local_uuid'] ?? $payload['offline_uuid'] ?? null;
        $detail = null;
        if (!empty($payload['server_id'])) $detail = OrderDetail::find($payload['server_id']);
        if (!$detail && $offlineUuid && Schema::hasColumn('order_details', 'offline_uuid')) {
            $detail = OrderDetail::where('offline_uuid', $offlineUuid)->first();
        }
        if (!$detail) $detail = new OrderDetail();

        $detail->fill($this->filterColumns('order_details', [
            'offline_uuid' => $offlineUuid,
            'order_id' => $order->id,
            'order_kot_id' => $kotId,
            'product_id' => $payload['product_server_id'] ?? $payload['product_id'] ?? null,
            'product_name' => $payload['product_name'] ?? 'Food Item',
            'food_note' => $payload['food_note'] ?? null,
            'is_complimentary' => $payload['is_complimentary'] ?? 0,
            'addons' => is_array($payload['addons'] ?? null) ? json_encode($payload['addons']) : ($payload['addons'] ?? null),
            'quantity' => $payload['quantity'] ?? 1,
            'price' => $payload['price'] ?? 0,
            'subtotal' => $payload['subtotal'] ?? 0,
            'is_completed' => $payload['is_completed'] ?? 0,
            'is_unavailable' => $payload['is_unavailable'] ?? 0,
        ]));
        $detail->save();
        return $detail;
    }

    private function pushSingleCustomer(array $row): ?Customer
    {
        $offlineUuid = $row['local_uuid'] ?? $row['offline_uuid'] ?? null;
        $customer = null;
        if (!empty($row['server_id'])) $customer = Customer::find($row['server_id']);
        if (!$customer && $offlineUuid && Schema::hasColumn('customers', 'offline_uuid')) $customer = Customer::where('offline_uuid', $offlineUuid)->first();
        if (!$customer && !empty($row['phone'])) $customer = Customer::where('phone', $row['phone'])->first();
        if (!$customer) $customer = new Customer();
        $customer->fill($this->filterColumns('customers', [
            'offline_uuid' => $offlineUuid,
            'name' => $row['name'] ?? 'Walk-in Customer',
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'address' => $row['address'] ?? null,
        ]));
        $customer->save();
        return $customer;
    }

    private function filterColumns(string $table, array $data): array
    {
        if (!Schema::hasTable($table)) return [];
        return collect($data)->filter(fn ($v, $k) => Schema::hasColumn($table, $k))->all();
    }
}
