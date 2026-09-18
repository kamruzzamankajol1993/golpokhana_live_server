<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderKot;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OfflinePosSyncController extends Controller
{
    private array $columnsCache = [];

    public function pull(Request $request): JsonResponse
    {
        $lastSyncedAt = trim((string) $request->query('last_synced_at', ''));

        if ($lastSyncedAt === '') {
            return app(OfflinePosDataController::class)->bootstrap($request);
        }

        $master = app(OfflinePosMasterDataController::class)->masterData($request)->getData(true);
        $settings = app(OfflinePosSettingController::class)->settings($request)->getData(true);
        $dataController = app(OfflinePosDataController::class);
        $activeOrders = $dataController->activeOrdersResponse()->getData(true);
        $orderChanges = $dataController->changedOrdersResponse($request)->getData(true);
        $kitchenChanges = app(OfflinePosKitchenController::class)->sync($request)->getData(true);

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'mode' => 'incremental',
            'requested_last_synced_at' => $lastSyncedAt,
            'offline_controls' => $settings['offline_controls'] ?? [],
            'restaurant_settings' => $settings['restaurant_settings'] ?? [],
            'pos_settings' => $settings['pos_settings'] ?? [],
            'tax_settings' => $settings['tax_settings'] ?? [],
            'invoice_settings' => $settings['invoice_settings'] ?? [],
            'master_data' => [
                'users' => $master['users'] ?? [],
                'floor_zones' => $master['floor_zones'] ?? [],
                'zones' => $master['floor_zones'] ?? [],
                'tables' => $master['tables'] ?? [],
                'waiters' => $master['waiters'] ?? [],
                'customers' => $master['customers'] ?? [],
                'food_categories' => $master['food_categories'] ?? [],
                'food_items' => $master['food_items'] ?? [],
                'food_addons' => $master['food_addons'] ?? [],
                'delivery_partners' => $master['delivery_partners'] ?? [],
                'occasions' => $master['occasions'] ?? [],
            ],
            'active_orders' => $activeOrders['active_orders'] ?? [],
            'order_changes' => $orderChanges['orders'] ?? [],
            'kitchen_changes' => $kitchenChanges['data'] ?? [],
            'table_bookings' => app(OfflinePosBookingController::class)->index($request)->getData(true)['table_bookings'] ?? [],
            'booking_options' => app(OfflinePosBookingController::class)->options()->getData(true),
            'pos_sessions' => app(OfflinePosSessionController::class)->history($request)->getData(true)['pos_sessions'] ?? [],
            'table_dashboard' => app(OfflinePosOperationController::class)->dashboard($request)->getData(true),
            'payment_options' => app(OfflinePosOperationController::class)->paymentOptions()->getData(true),
        ]);
    }

    public function pushCustomers(Request $request): JsonResponse
    {
        $request->validate(['customers' => ['required', 'array']]);
        $results = $this->syncCustomers($request->input('customers', []));

        return $this->syncResponse('customers', $results);
    }

    public function pushOrders(Request $request): JsonResponse
    {
        $request->validate(['orders' => ['required', 'array']]);
        $results = $this->syncOrders($request->input('orders', []));

        return $this->syncResponse('orders', $results);
    }

    public function pushAll(Request $request): JsonResponse
    {
        $request->validate([
            'customers' => ['nullable', 'array'],
            'orders' => ['nullable', 'array'],
            'table_bookings' => ['nullable', 'array'],
            'pos_sessions' => ['nullable', 'array'],
        ]);

        // Dependency order: customers -> bookings/sessions -> orders.
        // This lets an offline order resolve a newly-created customer/booking and mirrors the POS session prerequisite.
        $customers = $request->input('customers', []);
        $bookings = $request->input('table_bookings', []);
        $sessions = $request->input('pos_sessions', []);
        $orders = $request->input('orders', []);

        $customerResults = $this->syncCustomers($customers);
        $bookingResults = [];
        if (!empty($bookings)) {
            $bookingResponse = app(OfflinePosBookingController::class)
                ->push(new Request(['table_bookings' => $bookings]))
                ->getData(true);
            $bookingResults = $bookingResponse['table_bookings'] ?? [];
        }

        $sessionResults = [];
        if (!empty($sessions)) {
            $sessionResponse = app(OfflinePosSessionController::class)
                ->push(new Request(['pos_sessions' => $sessions]))
                ->getData(true);
            $sessionResults = $sessionResponse['pos_sessions'] ?? [];
        }

        $orderResults = $this->syncOrders($orders);

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'customers' => $customerResults,
            'orders' => $orderResults,
            'table_bookings' => $bookingResults,
            'pos_sessions' => $sessionResults,
            'summary' => [
                'customers_synced' => $this->countStatus($customerResults, 'synced'),
                'customers_failed' => $this->countStatus($customerResults, 'failed'),
                'orders_synced' => $this->countStatus($orderResults, 'synced'),
                'orders_failed' => $this->countStatus($orderResults, 'failed'),
                'table_bookings_synced' => $this->countStatus($bookingResults, 'synced'),
                'table_bookings_failed' => $this->countStatus($bookingResults, 'failed'),
                'pos_sessions_synced' => $this->countStatus($sessionResults, 'synced'),
                'pos_sessions_failed' => $this->countStatus($sessionResults, 'failed'),
            ],
        ]);
    }

    private function syncCustomers(array $rows): array
    {
        $results = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $results[] = ['status' => 'failed', 'message' => 'Customer payload must be an object.'];
                continue;
            }

            try {
                $customer = $this->upsertCustomer($row);
                $results[] = [
                    'local_uuid' => $row['local_uuid'] ?? $row['offline_uuid'] ?? null,
                    'server_id' => $customer->id,
                    'status' => 'synced',
                ];
            } catch (Throwable $e) {
                report($e);
                $results[] = [
                    'local_uuid' => $row['local_uuid'] ?? $row['offline_uuid'] ?? null,
                    'server_id' => $row['server_id'] ?? null,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function syncOrders(array $rows): array
    {
        $results = [];

        foreach ($rows as $payload) {
            if (!is_array($payload)) {
                $results[] = ['status' => 'failed', 'message' => 'Order payload must be an object.'];
                continue;
            }

            DB::beginTransaction();
            try {
                $results[] = $this->upsertOrder($payload);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                report($e);
                $results[] = [
                    'local_uuid' => $payload['local_uuid'] ?? $payload['offline_uuid'] ?? null,
                    'server_id' => $payload['server_id'] ?? null,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function upsertOrder(array $payload): array
    {
        $offlineUuid = trim((string) ($payload['local_uuid'] ?? $payload['offline_uuid'] ?? ''));
        $serverId = $payload['server_id'] ?? null;

        if ($offlineUuid === '' && !$serverId) {
            throw new \InvalidArgumentException('Order local_uuid is required for safe retry/idempotent synchronization.');
        }

        $order = $serverId ? Order::find($serverId) : null;
        if (!$order && $offlineUuid !== '' && $this->hasColumn('orders', 'offline_uuid')) {
            $order = Order::where('offline_uuid', $offlineUuid)->first();
        }
        $order ??= new Order();
        $oldTableId = $order->exists ? $order->table_id : null;

        $customerId = $payload['customer_server_id'] ?? $payload['customer_id'] ?? null;
        $customerLocalUuid = trim((string) (
            $payload['customer_local_uuid']
            ?? $payload['customer_offline_uuid']
            ?? ($payload['customer']['local_uuid'] ?? null)
            ?? ($payload['customer']['offline_uuid'] ?? null)
            ?? ''
        ));

        if (!$customerId && $customerLocalUuid !== '' && $this->hasColumn('customers', 'offline_uuid')) {
            $customerId = Customer::where('offline_uuid', $customerLocalUuid)->value('id');
        }

        if (!$customerId && !empty($payload['customer']) && is_array($payload['customer'])) {
            $customerId = $this->upsertCustomer($payload['customer'])->id;
        }

        $tableId = $payload['table_server_id'] ?? $payload['table_id'] ?? null;
        $waiterId = $payload['waiter_server_id'] ?? $payload['waiter_id'] ?? null;
        $deliveryPartnerId = $payload['delivery_partner_server_id'] ?? $payload['delivery_partner_id'] ?? null;
        $tableBookingId = $payload['table_booking_server_id'] ?? $payload['table_booking_id'] ?? null;
        $tableBookingLocalUuid = trim((string) ($payload['table_booking_local_uuid'] ?? $payload['table_booking_offline_uuid'] ?? ''));
        if (!$tableBookingId && $tableBookingLocalUuid !== '' && Schema::hasTable('table_bookings') && Schema::hasColumn('table_bookings', 'offline_uuid')) {
            $tableBookingId = DB::table('table_bookings')->where('offline_uuid', $tableBookingLocalUuid)->value('id');
        }

        if ($deliveryPartnerId && Schema::hasTable('delivery_partners')) {
            $exists = DB::table('delivery_partners')->where('id', $deliveryPartnerId)->exists();
            if (!$exists) {
                throw new \InvalidArgumentException('Selected delivery partner does not exist on the main server.');
            }
        }

        $this->assertTableAssignmentIsAvailable($order, $tableId ? (int) $tableId : null, $tableBookingId ? (int) $tableBookingId : null, $payload);

        $status = (string) ($payload['status'] ?? 'Pending');
        $completedAt = $payload['completed_at'] ?? null;
        if (!$completedAt && strtolower($status) === 'completed') {
            $completedAt = now();
        }

        $orderData = [
            'offline_uuid' => $offlineUuid !== '' ? $offlineUuid : null,
            'customer_id' => $customerId,
            'table_id' => $tableId,
            'table_booking_id' => $tableBookingId,
            'send_to_kitchen' => $payload['send_to_kitchen'] ?? 1,
            'subtotal' => $payload['subtotal'] ?? 0,
            'vat_tax' => $payload['vat_tax'] ?? 0,
            'service_charge' => $payload['service_charge'] ?? 0,
            'discount_amount' => $payload['discount_amount'] ?? 0,
            'discount_value' => $payload['discount_value'] ?? 0,
            'discount_type' => $payload['discount_type'] ?? null,
            'product_discount_amount' => $payload['product_discount_amount'] ?? 0,
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
            'status' => $status,
            'order_type' => $payload['order_type'] ?? 'Dine-In',
            'number_of_guests' => isset($payload['number_of_guests']) || isset($payload['guest_count'])
                ? max(1, (int) ($payload['number_of_guests'] ?? $payload['guest_count']))
                : null,
            'delivery_partner' => $deliveryPartnerId ?? ($payload['delivery_partner'] ?? null),
            'delivery_partner_id' => $deliveryPartnerId,
            'is_complimentary_order' => $payload['is_complimentary_order'] ?? 0,
            'user_id' => $payload['user_server_id'] ?? $payload['user_id'] ?? null,
            'waiter_id' => $waiterId,
            'order_time' => $payload['order_time'] ?? $payload['created_at'] ?? now(),
            'completed_at' => $completedAt,
            'preparation_time' => $payload['preparation_time'] ?? null,
            'kitchen_to_payment_minutes' => $payload['kitchen_to_payment_minutes'] ?? null,
            'payment_type' => $payload['payment_type'] ?? 'Cash',
            'transaction_id' => $payload['transaction_id'] ?? null,
            'payment_remark' => $payload['payment_remark'] ?? $payload['remarks'] ?? null,
            'split_card_reference' => $payload['split_card_reference'] ?? null,
            'split_mfs_reference' => $payload['split_mfs_reference'] ?? null,
            'card_type' => $payload['card_type'] ?? $payload['split_card_type'] ?? null,
            'mfs_provider' => $payload['mfs_provider'] ?? $payload['split_mfs_provider'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'booking_advance' => $payload['booking_advance'] ?? 0,
            'pre_invoice_snapshot' => $payload['pre_invoice_snapshot'] ?? null,
            'pre_invoice_printed_at' => $payload['pre_invoice_printed_at'] ?? null,
            // Preserve the original offline business timestamp. updated_at remains
            // server-controlled so timestamp-based background pull still detects this sync.
            'created_at' => $payload['created_at'] ?? $payload['order_time'] ?? ($order->created_at ?? now()),
        ];

        $order->fill($this->filterColumns('orders', $orderData));
        $order->save();

        if ($order->table_id && Schema::hasTable('tables') && Schema::hasColumn('tables', 'initial_status')) {
            $finished = in_array(strtolower((string) $order->status), ['completed', 'cancelled', 'canceled'], true);
            Table::whereKey($order->table_id)->update(['initial_status' => $finished ? 'Available' : 'Occupied']);
        }

        // Swapping a synced order to another table must also free the previous table when no active order remains there.
        if ($oldTableId && (int) $oldTableId !== (int) $order->table_id && Schema::hasTable('tables') && Schema::hasColumn('tables', 'initial_status')) {
            $oldTableStillBusy = Order::where('id', '!=', $order->id)->where('table_id', $oldTableId)
                ->whereIn('status', ['Pending', 'Waiter_Hold', 'QR_Pending', 'QR_Hold', 'Cooking', 'Ready'])->exists();
            if (!$oldTableStillBusy) {
                Table::whereKey($oldTableId)->update(['initial_status' => 'Available']);
            }
        }

        // Apply explicit tombstones from add-more/remove-item offline actions.
        $this->deleteOrderChildren($order, $payload);

        $kotMap = [];
        $kotResults = [];
        foreach (($payload['kots'] ?? []) as $kotPayload) {
            if (!is_array($kotPayload)) {
                continue;
            }
            $kot = $this->upsertKot($order, $kotPayload);
            $key = $kotPayload['local_uuid'] ?? $kotPayload['offline_uuid'] ?? $kotPayload['id'] ?? $kot->id;
            $kotMap[(string) $key] = $kot->id;
            $kotResults[] = ['local_uuid' => $kotPayload['local_uuid'] ?? $kotPayload['offline_uuid'] ?? null, 'server_id' => $kot->id, 'kot_number' => $kot->kot_number, 'kitchen_status' => $kot->kitchen_status];
        }

        $detailResults = [];
        foreach (($payload['order_details'] ?? $payload['details'] ?? []) as $detailPayload) {
            if (!is_array($detailPayload)) {
                continue;
            }
            $kotLocal = $detailPayload['order_kot_local_uuid'] ?? null;
            $kotId = $detailPayload['order_kot_server_id']
                ?? ($kotLocal !== null && isset($kotMap[(string) $kotLocal]) ? $kotMap[(string) $kotLocal] : null);
            $detail = $this->upsertDetail($order, $detailPayload, $kotId);
            $detailResults[] = ['local_uuid' => $detailPayload['local_uuid'] ?? $detailPayload['offline_uuid'] ?? null, 'server_id' => $detail->id, 'order_kot_id' => $detail->order_kot_id];
        }

        if (strtolower((string) $order->status) === 'completed') {
            $this->assertCompletedPaymentRules($order, $payload);
            OrderKot::where('order_id', $order->id)->where('kitchen_status', '!=', 'Delivered')->update(['kitchen_status' => 'Delivered']);
            if ($order->table_id && Schema::hasTable('tables') && Schema::hasColumn('tables', 'initial_status')) {
                Table::whereKey($order->table_id)->update(['initial_status' => 'Available']);
            }
            if ($this->hasColumn('orders', 'table_booking_id') && $order->table_booking_id && Schema::hasTable('table_bookings')) {
                DB::table('table_bookings')->where('id', $order->table_booking_id)->update(['status' => 'completed']);
            }
        }

        return [
            'local_uuid' => $offlineUuid !== '' ? $offlineUuid : null,
            'server_id' => $order->id,
            'order_number' => $order->order_number,
            'kots' => $kotResults,
            'order_details' => $detailResults,
            'status' => 'synced',
        ];
    }

    private function deleteOrderChildren(Order $order, array $payload): void
    {
        $detailServerIds = array_values(array_filter(array_map('intval', (array) ($payload['deleted_detail_server_ids'] ?? []))));
        if ($detailServerIds) {
            OrderDetail::where('order_id', $order->id)->whereIn('id', $detailServerIds)->delete();
        }

        $detailLocalUuids = array_values(array_filter(array_map('strval', (array) ($payload['deleted_detail_local_uuids'] ?? []))));
        if ($detailLocalUuids && $this->hasColumn('order_details', 'offline_uuid')) {
            OrderDetail::where('order_id', $order->id)->whereIn('offline_uuid', $detailLocalUuids)->delete();
        }

        $kotIds = array_values(array_filter(array_map('intval', (array) ($payload['deleted_kot_server_ids'] ?? []))));
        $kotLocalUuids = array_values(array_filter(array_map('strval', (array) ($payload['deleted_kot_local_uuids'] ?? []))));
        if (!$kotIds && !$kotLocalUuids) {
            return;
        }
        $kots = OrderKot::where('order_id', $order->id)
            ->where(function ($query) use ($kotIds, $kotLocalUuids) {
                if ($kotIds) $query->whereIn('id', $kotIds);
                if ($kotLocalUuids && $this->hasColumn('order_kots', 'offline_uuid')) {
                    $kotIds ? $query->orWhereIn('offline_uuid', $kotLocalUuids) : $query->whereIn('offline_uuid', $kotLocalUuids);
                }
            })->get();
        foreach ($kots as $kot) {
            OrderDetail::where('order_id', $order->id)->where('order_kot_id', $kot->id)->delete();
            $kot->delete();
        }
    }

    private function upsertKot(Order $order, array $payload): OrderKot
    {
        $offlineUuid = trim((string) ($payload['local_uuid'] ?? $payload['offline_uuid'] ?? ''));
        $serverId = $payload['server_id'] ?? null;

        if ($offlineUuid === '' && !$serverId) {
            throw new \InvalidArgumentException('KOT local_uuid is required for safe retry/idempotent synchronization.');
        }

        $kot = $serverId ? OrderKot::find($serverId) : null;
        if ($kot && (int) $kot->order_id !== (int) $order->id) {
            throw new \InvalidArgumentException('KOT does not belong to the target order.');
        }

        if (!$kot && $offlineUuid !== '' && $this->hasColumn('order_kots', 'offline_uuid')) {
            $kot = OrderKot::where('offline_uuid', $offlineUuid)->first();
            if ($kot && (int) $kot->order_id !== (int) $order->id) {
                throw new \InvalidArgumentException('KOT local UUID belongs to a different order.');
            }
        }
        $isNewKot = !$kot;
        $kot ??= new OrderKot();
        $kotNumber = $isNewKot ? $this->generateGlobalKotNumber() : ($kot->kot_number ?: ($payload['kot_number'] ?? $this->generateGlobalKotNumber()));

        $kot->fill($this->filterColumns('order_kots', [
            'offline_uuid' => $offlineUuid !== '' ? $offlineUuid : ($kot->offline_uuid ?? null),
            'order_id' => $order->id,
            'kot_number' => $kotNumber,
            'kitchen_status' => $payload['kitchen_status'] ?? ($kot->kitchen_status ?? 'Pending'),
            'is_add_more' => $payload['is_add_more'] ?? ($kot->is_add_more ?? 0),
            'created_at' => $payload['created_at'] ?? ($kot->created_at ?? $order->order_time ?? now()),
        ]));
        $kot->save();

        return $kot;
    }

    private function upsertDetail(Order $order, array $payload, ?int $kotId): OrderDetail
    {
        $offlineUuid = trim((string) ($payload['local_uuid'] ?? $payload['offline_uuid'] ?? ''));
        $serverId = $payload['server_id'] ?? null;

        if ($offlineUuid === '' && !$serverId) {
            throw new \InvalidArgumentException('Order detail local_uuid is required for safe retry/idempotent synchronization.');
        }

        $detail = $serverId ? OrderDetail::find($serverId) : null;
        if ($detail && (int) $detail->order_id !== (int) $order->id) {
            throw new \InvalidArgumentException('Order detail does not belong to the target order.');
        }

        if (!$detail && $offlineUuid !== '' && $this->hasColumn('order_details', 'offline_uuid')) {
            $detail = OrderDetail::where('offline_uuid', $offlineUuid)->first();
            if ($detail && (int) $detail->order_id !== (int) $order->id) {
                throw new \InvalidArgumentException('Order detail local UUID belongs to a different order.');
            }
        }
        $detail ??= new OrderDetail();

        $addons = $payload['addons'] ?? null;
        if (is_array($addons)) {
            $addons = json_encode($addons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $detail->fill($this->filterColumns('order_details', [
            'offline_uuid' => $offlineUuid !== '' ? $offlineUuid : null,
            'order_id' => $order->id,
            'order_kot_id' => $kotId,
            'product_id' => $payload['product_server_id'] ?? $payload['product_id'] ?? null,
            'product_name' => $payload['product_name'] ?? 'Food Item',
            'food_note' => $payload['food_note'] ?? $payload['note'] ?? null,
            'is_complimentary' => $payload['is_complimentary'] ?? 0,
            'complimentary_note' => $payload['complimentary_note'] ?? null,
            'addons' => $addons,
            'quantity' => $payload['quantity'] ?? 1,
            'price' => $payload['price'] ?? 0,
            'subtotal' => $payload['subtotal'] ?? 0,
            'product_discount_type' => $payload['product_discount_type'] ?? null,
            'product_discount_value' => $payload['product_discount_value'] ?? 0,
            'product_discount_amount' => $payload['product_discount_amount'] ?? 0,
            'is_completed' => $payload['is_completed'] ?? 0,
            'is_unavailable' => $payload['is_unavailable'] ?? 0,
            'created_at' => $payload['created_at'] ?? ($detail->created_at ?? $order->order_time ?? now()),
        ]));
        $detail->save();

        return $detail;
    }

    private function assertCompletedPaymentRules(Order $order, array $payload): void
    {
        $paymentMethod = trim((string) ($order->payment_type ?? 'Cash'));
        $allowedCardTypes = ['Visa', 'Mastercard', 'American Express', 'UnionPay', 'JCB', 'Nexus', 'Diners Club', 'GPay', 'Bangla QR Card', 'Other'];
        $allowedMfsProviders = ['Rocket', 'bKash', 'MYCash', 'Islami Bank mCash', 'tap', 'FirstCash', 'Upay', 'OK Wallet', 'RUPALICASH', 'TeleCash', 'Islamic Wallet', 'Meghna Pay', 'Nagad', 'Bangla QR', 'LENDEN', 'Other'];

        if ($paymentMethod === 'Card') {
            if (!in_array(trim((string) ($order->card_type ?? '')), $allowedCardTypes, true)) {
                throw new \InvalidArgumentException('Please select a valid card type.');
            }
            if (trim((string) ($order->transaction_id ?? '')) === '') {
                throw new \InvalidArgumentException('Bank / Card Reference Number is required.');
            }
        }

        if ($paymentMethod === 'Mobile Banking') {
            if (!in_array(trim((string) ($order->mfs_provider ?? '')), $allowedMfsProviders, true)) {
                throw new \InvalidArgumentException('Please select a valid MFS service.');
            }
            if (trim((string) ($order->transaction_id ?? '')) === '') {
                throw new \InvalidArgumentException('MFS Reference Number is required.');
            }
        }

        if ($paymentMethod === 'Split') {
            $cardAmount = max(0, (float) ($order->paid_in_card ?? 0));
            $mfsAmount = max(0, (float) ($order->paid_in_mfc ?? 0));
            if ($cardAmount > 0) {
                if (!in_array(trim((string) ($order->card_type ?? '')), $allowedCardTypes, true)) {
                    throw new \InvalidArgumentException('Please select a valid card type for the split card amount.');
                }
                if (trim((string) ($order->split_card_reference ?? '')) === '') {
                    throw new \InvalidArgumentException('Bank / Card Reference Number is required when a Bank / Card amount is entered.');
                }
            }
            if ($mfsAmount > 0) {
                if (!in_array(trim((string) ($order->mfs_provider ?? '')), $allowedMfsProviders, true)) {
                    throw new \InvalidArgumentException('Please select a valid MFS service for the split MFS amount.');
                }
                if (trim((string) ($order->split_mfs_reference ?? '')) === '') {
                    throw new \InvalidArgumentException('MFS Reference Number is required when an MFS amount is entered.');
                }
            }
        }

        $hasWholeDiscount = max(0, (float) ($order->discount_value ?? 0)) > 0 || max(0, (float) ($order->discount_amount ?? 0)) > 0;
        $hasItemDiscount = OrderDetail::where('order_id', $order->id)->where('product_discount_amount', '>', 0)->exists();
        if (($hasWholeDiscount || $hasItemDiscount) && trim((string) ($order->payment_remark ?? '')) === '') {
            throw new \InvalidArgumentException('Remark is required when a discount is applied.');
        }

        $dependsOnKitchen = Schema::hasTable('pos_settings')
            && Schema::hasColumn('pos_settings', 'final_payment_depends_on_kitchen_status')
            && (bool) (DB::table('pos_settings')->value('final_payment_depends_on_kitchen_status') ?? false);

        if ($dependsOnKitchen) {
            $busyKitchen = OrderKot::where('order_id', $order->id)
                ->whereIn('kitchen_status', ['Pending', 'Cooking', 'Hold'])
                ->exists();
            if ($busyKitchen) {
                throw new \InvalidArgumentException('Final payment is blocked until all kitchen items are Ready.');
            }
        }
    }

    private function assertTableAssignmentIsAvailable(Order $order, ?int $tableId, ?int $tableBookingId, array $payload): void
    {
        if (!$tableId) {
            return;
        }

        if (!Schema::hasTable('tables') || !Table::whereKey($tableId)->exists()) {
            throw new \InvalidArgumentException('Selected table does not exist on the main server.');
        }

        $orderType = strtolower((string) ($payload['order_type'] ?? $order->order_type ?? 'Dine-In'));
        $normalizedOrderType = preg_replace('/[^a-z]/', '', $orderType);
        if ($normalizedOrderType !== 'dinein') {
            throw new \InvalidArgumentException('A table can only be assigned to a Dine-In order.');
        }

        $sameTable = $order->exists && (int) ($order->table_id ?? 0) === $tableId;
        if ($sameTable) {
            return;
        }

        $activeStatuses = ['Pending', 'Waiter_Hold', 'QR_Pending', 'QR_Hold', 'Cooking', 'Ready'];
        $conflictingOrder = Order::query()
            ->where('table_id', $tableId)
            ->when($order->exists, fn ($query) => $query->where('id', '!=', $order->id))
            ->whereIn('status', $activeStatuses)
            ->exists();

        if ($conflictingOrder) {
            throw new \InvalidArgumentException('Selected table already has an active order. Pull the latest table state and retry.');
        }

        if (!Schema::hasTable('table_bookings')) {
            return;
        }

        $now = Carbon::now('Asia/Dhaka');
        $time = $now->format('H:i:s');
        $bookingQuery = DB::table('table_bookings')
            ->whereIn(DB::raw('LOWER(status)'), ['pending', 'upcoming', 'confirmed', 'seated'])
            ->whereDate('booking_date', $now->toDateString())
            ->where(function ($query) use ($time) {
                if (Schema::hasColumn('table_bookings', 'booking_start_time')) {
                    $query->whereNull('booking_start_time')->orWhereTime('booking_start_time', '<=', $time);
                }
            })
            ->where(function ($query) use ($time) {
                if (Schema::hasColumn('table_bookings', 'booking_end_time')) {
                    $query->whereNull('booking_end_time')->orWhereTime('booking_end_time', '>=', $time);
                }
            })
            ->where(function ($query) use ($tableId) {
                $query->where('table_id', $tableId);
                if (Schema::hasTable('table_booking_tables')) {
                    $query->orWhereExists(function ($subQuery) use ($tableId) {
                        $subQuery->select(DB::raw(1))
                            ->from('table_booking_tables')
                            ->whereColumn('table_booking_tables.table_booking_id', 'table_bookings.id')
                            ->where('table_booking_tables.table_id', $tableId);
                    });
                }
            });

        if ($tableBookingId) {
            $bookingQuery->where('id', '!=', $tableBookingId);
        }

        if ($bookingQuery->exists()) {
            throw new \InvalidArgumentException('Selected table is currently reserved by another booking. Pull the latest table state and retry.');
        }
    }

    private function generateGlobalKotNumber(): string
    {
        $max = OrderKot::where('kot_number', 'like', 'KOT-%')
            ->selectRaw("MAX(CAST(REPLACE(kot_number, 'KOT-', '') AS UNSIGNED)) as max_number")
            ->value('max_number');

        return 'KOT-' . (((int) $max) + 1);
    }

    private function upsertCustomer(array $row): Customer
    {
        $offlineUuid = trim((string) ($row['local_uuid'] ?? $row['offline_uuid'] ?? ''));
        $serverId = $row['server_id'] ?? null;
        $phone = trim((string) ($row['phone'] ?? ''));

        if (!$serverId && $offlineUuid === '' && $phone === '') {
            throw new \InvalidArgumentException('Customer needs local_uuid, server_id, or phone for safe synchronization.');
        }

        $customer = $serverId ? Customer::find($serverId) : null;
        if (!$customer && $offlineUuid !== '' && $this->hasColumn('customers', 'offline_uuid')) {
            $customer = Customer::where('offline_uuid', $offlineUuid)->first();
        }
        if (!$customer && $phone !== '' && $this->hasColumn('customers', 'phone')) {
            $customer = Customer::where('phone', $phone)->first();
        }

        $isNewCustomer = !$customer;
        if ($isNewCustomer && $phone === '') {
            throw new \InvalidArgumentException('Phone is required when creating a new customer on the main server.');
        }

        $customer ??= new Customer();

        $customerData = [
            'offline_uuid' => $offlineUuid !== '' ? $offlineUuid : ($customer->offline_uuid ?? null),
            'name' => $row['name'] ?? ($customer->name ?? 'Walk-in Customer'),
            'phone' => $phone !== '' ? $phone : null,
            'email' => array_key_exists('email', $row) ? $row['email'] : ($customer->email ?? null),
            'dob' => array_key_exists('dob', $row) ? $row['dob'] : ($customer->dob ?? null),
            'address' => array_key_exists('address', $row) ? $row['address'] : ($customer->address ?? null),
        ];

        if ($isNewCustomer) {
            $customerData['points'] = 0;
            $customerData['total_orders'] = 0;
            if (!empty($row['created_at'])) {
                $customerData['created_at'] = $row['created_at'];
            }
        }

        if ($phone === '' && !$isNewCustomer) {
            unset($customerData['phone']);
        }

        $customer->fill($this->filterColumns('customers', $customerData));
        $customer->save();

        return $customer;
    }

    private function syncResponse(string $key, array $results): JsonResponse
    {
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            $key => $results,
            'summary' => [
                'synced' => $this->countStatus($results, 'synced'),
                'failed' => $this->countStatus($results, 'failed'),
            ],
        ]);
    }

    private function countStatus(array $results, string $status): int
    {
        return count(array_filter($results, fn (array $row) => ($row['status'] ?? null) === $status));
    }

    private function columns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $this->columnsCache[$table] = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        }
        return $this->columnsCache[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = $this->columns($table);
        if (!$columns) {
            return [];
        }

        return array_filter(
            $data,
            fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
