<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfflinePosDataController extends Controller
{
    private array $columnsCache = [];

    public function bootstrap(Request $request): JsonResponse
    {
        // Bootstrap is always a complete snapshot. Incremental/background sync uses /master-data.
        $request->query->remove('last_synced_at');

        $master = app(OfflinePosMasterDataController::class);
        $settings = app(OfflinePosSettingController::class)->settings($request)->getData(true);
        $masterData = $master->masterData($request)->getData(true);

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'mode' => 'full_bootstrap',
            'bootstrap' => [
                'offline_controls' => $settings['offline_controls'] ?? [],
                'restaurant_settings' => $settings['restaurant_settings'] ?? [],
                'pos_settings' => $settings['pos_settings'] ?? [],
                'tax_settings' => $settings['tax_settings'] ?? [],
                'invoice_settings' => $settings['invoice_settings'] ?? [],
                'users' => $masterData['users'] ?? [],
                'floor_zones' => $masterData['floor_zones'] ?? [],
                'zones' => $masterData['floor_zones'] ?? [],
                'tables' => $masterData['tables'] ?? [],
                'waiters' => $masterData['waiters'] ?? [],
                'customers' => $masterData['customers'] ?? [],
                'food_categories' => $masterData['food_categories'] ?? [],
                'food_items' => $masterData['food_items'] ?? [],
                'food_addons' => $masterData['food_addons'] ?? [],
                'delivery_partners' => $masterData['delivery_partners'] ?? [],
                'occasions' => $masterData['occasions'] ?? [],
                'payment_methods' => $this->paymentMethods(),
                'payment_options' => app(OfflinePosOperationController::class)->paymentOptions()->getData(true),
                'table_dashboard' => app(OfflinePosOperationController::class)->dashboard($request)->getData(true),
                'table_bookings' => app(OfflinePosBookingController::class)->index($request)->getData(true)['table_bookings'] ?? [],
                'booking_options' => app(OfflinePosBookingController::class)->options()->getData(true),
                'pos_sessions' => app(OfflinePosSessionController::class)->history($request)->getData(true)['pos_sessions'] ?? [],
                'current_pos_session' => app(OfflinePosSessionController::class)->current($request)->getData(true),
                'pos_status' => app(OfflinePosOperationController::class)->posStatus()->getData(true),
                'active_orders' => $this->activeOrders(),
            ],
        ]);
    }

    public function activeOrdersResponse(): JsonResponse
    {
        $orders = $this->activeOrders();

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'active_orders' => $orders,
            'meta' => ['count' => count($orders)],
        ]);
    }

    private function paymentMethods(): array
    {
        return [
            ['server_id' => 1, 'name' => 'Cash', 'code' => 'cash', 'is_active' => true, 'requires_transaction_id' => false, 'sort_order' => 1],
            ['server_id' => 2, 'name' => 'Bank / Card', 'code' => 'card', 'is_active' => true, 'requires_transaction_id' => true, 'sort_order' => 2],
            ['server_id' => 3, 'name' => 'MFS', 'code' => 'mfs', 'is_active' => true, 'requires_transaction_id' => true, 'sort_order' => 3],
            ['server_id' => 4, 'name' => 'Split', 'code' => 'split', 'is_active' => true, 'requires_transaction_id' => false, 'sort_order' => 4],
        ];
    }

    public function changedOrdersResponse(Request $request): JsonResponse
    {
        $updatedAfter = trim((string) ($request->query('updated_after') ?? $request->query('last_synced_at') ?? ''));
        $orders = $this->ordersSnapshot($updatedAfter !== '' ? $updatedAfter : null, false);

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'requested_updated_after' => $updatedAfter !== '' ? $updatedAfter : null,
            'orders' => $orders,
            'meta' => ['count' => count($orders)],
        ]);
    }

    private function activeOrders(): array
    {
        return $this->ordersSnapshot(null, true);
    }

    /**
     * Return a complete order snapshot including child KOT/detail rows.
     * Active snapshots power the POS screens; timestamp snapshots also include
     * Completed/Cancelled rows so another terminal can clear stale local orders.
     */
    private function ordersSnapshot(?string $updatedAfter, bool $activeOnly): array
    {
        if (!Schema::hasTable('orders')) {
            return [];
        }

        $query = DB::table('orders')->select($this->availableColumns('orders', [
            'id', 'offline_uuid', 'synced_at', 'order_number', 'invoice_number', 'qr_reference',
            'customer_id', 'table_id', 'table_booking_id', 'send_to_kitchen', 'subtotal', 'vat_tax',
            'service_charge', 'discount_amount', 'discount_value', 'discount_type', 'product_discount_amount',
            'reward_point_discount', 'delivery_charge', 'grand_total', 'due', 'total_paid_amount', 'tips_amount',
            'given_money', 'change_amount', 'paid_in_cash', 'paid_in_card', 'paid_in_mfc', 'delivery_address',
            'status', 'order_type', 'number_of_guests', 'is_complimentary_order', 'delivery_partner', 'delivery_partner_id',
            'user_id', 'waiter_id', 'order_time', 'completed_at', 'preparation_time', 'kitchen_to_payment_minutes',
            'payment_type', 'transaction_id', 'payment_remark', 'split_card_reference', 'split_mfs_reference',
            'card_type', 'mfs_provider', 'notes', 'booking_advance', 'pre_invoice_snapshot',
            'pre_invoice_printed_at', 'created_at', 'updated_at',
        ]));

        if ($activeOnly && Schema::hasColumn('orders', 'status')) {
            $query->whereIn('status', ['Pending', 'Processing', 'Waiter_Hold', 'QR_Pending', 'QR_Hold', 'Cooking', 'Ready']);
        }
        if ($updatedAfter && Schema::hasColumn('orders', 'updated_at')) {
            $query->where('updated_at', '>', $updatedAfter);
        }

        $orders = $query
            ->orderBy($updatedAfter ? 'updated_at' : 'id', $updatedAfter ? 'asc' : 'desc')
            ->orderBy('id')
            ->limit($activeOnly ? 500 : 1000)
            ->get();
        if ($orders->isEmpty()) {
            return [];
        }

        $orderIds = $orders->pluck('id')->all();
        $details = $this->rowsGroupedBy('order_details', 'order_id', $orderIds, [
            'id', 'offline_uuid', 'synced_at', 'order_id', 'order_kot_id', 'product_id', 'product_name',
            'addons', 'food_note', 'is_complimentary', 'complimentary_note', 'quantity', 'price', 'subtotal',
            'product_discount_type', 'product_discount_value', 'product_discount_amount', 'is_unavailable',
            'is_completed', 'created_at', 'updated_at',
        ]);
        $kots = $this->rowsGroupedBy('order_kots', 'order_id', $orderIds, [
            'id', 'offline_uuid', 'synced_at', 'order_id', 'kot_number', 'kitchen_status', 'is_add_more',
            'created_at', 'updated_at',
        ]);
        $customers = $this->lookup('customers', [
            'id', 'offline_uuid', 'name', 'phone', 'email', 'dob', 'address', 'points', 'total_orders',
            'created_at', 'updated_at',
        ]);
        $waiters = $this->lookup('waiters', [
            'id', 'user_id', 'hr_employee_id', 'zone_id', 'shift_id', 'employee_id', 'name', 'phone',
            'email', 'image', 'join_date', 'notes', 'status', 'created_at', 'updated_at',
        ]);
        $tables = $this->lookup('tables', [
            'id', 'table_number', 'qr_token', 'seating_capacity', 'zone_id', 'floor_zone_id',
            'initial_status', 'notes', 'created_at', 'updated_at',
        ]);
        $partners = $this->lookup('delivery_partners', [
            'id', 'name', 'status', 'created_at', 'updated_at',
        ]);

        return $orders->map(function ($row) use ($details, $kots, $customers, $waiters, $tables, $partners) {
            $data = $this->row($row);
            $id = $data['server_id'] ?? null;
            $data['customer'] = isset($data['customer_id']) ? ($customers[$data['customer_id']] ?? null) : null;
            $data['waiter'] = isset($data['waiter_id']) ? ($waiters[$data['waiter_id']] ?? null) : null;
            $data['table'] = isset($data['table_id']) ? ($tables[$data['table_id']] ?? null) : null;
            $partnerId = $data['delivery_partner_id'] ?? null;
            if (!$partnerId && isset($data['delivery_partner']) && is_numeric($data['delivery_partner'])) {
                $partnerId = (int) $data['delivery_partner'];
            }
            $data['delivery_partner_data'] = $partnerId ? ($partners[$partnerId] ?? null) : null;
            $data['order_details'] = $details[$id] ?? [];
            $data['kots'] = $kots[$id] ?? [];
            $data['display_status'] = match ((string) ($data['status'] ?? '')) {
                'Cooking' => 'Preparing',
                'Waiter_Hold', 'QR_Hold', 'QR_Pending' => 'Pending',
                default => (string) ($data['status'] ?? ''),
            };
            $data['is_terminal'] = in_array(strtolower((string) ($data['status'] ?? '')), ['completed', 'cancelled', 'canceled'], true);
            return $data;
        })->values()->all();
    }

    private function rowsGroupedBy(string $table, string $groupColumn, array $ids, array $wanted): array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $groupColumn)) {
            return [];
        }

        return DB::table($table)
            ->select($this->availableColumns($table, $wanted))
            ->whereIn($groupColumn, $ids)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->row($row))
            ->groupBy($groupColumn)
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }

    private function lookup(string $table, array $wanted): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->select($this->availableColumns($table, $wanted))
            ->get()
            ->map(fn ($row) => $this->row($row))
            ->keyBy('server_id')
            ->all();
    }

    private function columns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $this->columnsCache[$table] = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        }

        return $this->columnsCache[$table];
    }

    private function availableColumns(string $table, array $wanted): array
    {
        $columns = array_values(array_intersect($wanted, $this->columns($table)));
        return $columns ?: ['id'];
    }

    private function row(object|array $row): array
    {
        $data = (array) $row;
        if (array_key_exists('id', $data)) {
            $data['server_id'] = $data['id'];
        }

        if (isset($data['addons']) && is_string($data['addons'])) {
            $decoded = json_decode($data['addons'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['addons_decoded'] = $decoded;
            }
        }

        return $data;
    }
}
