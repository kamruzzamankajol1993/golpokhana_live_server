<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Table;
use App\Models\TableBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OfflinePosBookingController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'booking_statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'upcoming', 'label' => 'Upcoming'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'seated', 'label' => 'Seated'],
                ['value' => 'no-show', 'label' => 'No-show'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'advance_payment_methods' => [
                ['value' => 'Cash', 'label' => 'Cash'],
                ['value' => 'Card', 'label' => 'Bank / Card'],
                ['value' => 'MFS', 'label' => 'MFS'],
            ],
        ]);
    }
    public function index(Request $request): JsonResponse
    {
        if (!Schema::hasTable('table_bookings')) {
            return response()->json(['status' => true, 'table_bookings' => [], 'meta' => ['count' => 0]]);
        }

        $query = TableBooking::query()->with(['customer', 'table', 'tables', 'occasion']);

        if ($request->filled('date')) {
            $query->whereDate('booking_date', $request->query('date'));
        }
        if ($request->filled('status')) {
            $query->whereRaw('LOWER(TRIM(status)) = ?', [strtolower(trim((string) $request->query('status')))]);
        }
        if ($request->filled('last_synced_at') && Schema::hasColumn('table_bookings', 'updated_at')) {
            $query->where('updated_at', '>', $request->query('last_synced_at'));
        }

        $rows = $query->orderByDesc('booking_date')->orderBy('booking_start_time')->orderByDesc('id')
            ->limit(1000)->get()->map(fn (TableBooking $booking) => $this->serialize($booking))->values()->all();

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'table_bookings' => $rows,
            'meta' => ['count' => count($rows)],
        ]);
    }

    public function push(Request $request): JsonResponse
    {
        $rows = $request->validate(['table_bookings' => ['required', 'array']])['table_bookings'];
        $results = [];

        foreach ($rows as $payload) {
            if (!is_array($payload)) {
                $results[] = ['status' => 'failed', 'message' => 'Booking payload must be an object.'];
                continue;
            }

            DB::beginTransaction();
            try {
                $booking = $this->upsert($payload);
                DB::commit();
                $results[] = [
                    'local_uuid' => $payload['local_uuid'] ?? $payload['offline_uuid'] ?? null,
                    'server_id' => $booking?->id,
                    'booking_id' => $booking?->booking_id,
                    'status' => $booking ? 'synced' : 'deleted',
                ];
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

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'table_bookings' => $results,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $booking = TableBooking::with('tables')->findOrFail($id);
        $tableIds = $booking->tables->pluck('id')->push($booking->table_id)->filter()->unique()->values()->all();
        $booking->delete();
        foreach ($tableIds as $tableId) $this->releaseTableIfFree((int) $tableId);

        return response()->json(['status' => true, 'server_id' => $id, 'message' => 'Booking deleted.']);
    }

    private function upsert(array $payload): ?TableBooking
    {
        $offlineUuid = trim((string) ($payload['local_uuid'] ?? $payload['offline_uuid'] ?? ''));
        $serverId = $payload['server_id'] ?? null;

        if (!$serverId && $offlineUuid === '') {
            throw new \InvalidArgumentException('Booking local_uuid is required for safe offline synchronization.');
        }

        $booking = $serverId ? TableBooking::find($serverId) : null;
        if (!$booking && $offlineUuid !== '' && Schema::hasColumn('table_bookings', 'offline_uuid')) {
            $booking = TableBooking::where('offline_uuid', $offlineUuid)->first();
        }

        if (!empty($payload['_deleted']) || !empty($payload['deleted'])) {
            if ($booking) {
                $booking->loadMissing('tables');
                $tableIdsToRelease = $booking->tables->pluck('id')
                    ->push($booking->table_id)
                    ->filter()
                    ->unique()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
                $booking->delete();
                foreach ($tableIdsToRelease as $releaseId) {
                    $this->releaseTableIfFree($releaseId);
                }
            }
            return null;
        }

        $tableIds = $payload['table_server_ids'] ?? $payload['table_ids'] ?? null;
        if (!is_array($tableIds)) {
            $singleTableId = $payload['table_server_id'] ?? $payload['table_id'] ?? null;
            $tableIds = $singleTableId ? [$singleTableId] : [];
        }
        $tableIds = array_values(array_unique(array_filter(array_map('intval', $tableIds))));
        if (!$tableIds || Table::whereIn('id', $tableIds)->count() !== count($tableIds)) {
            throw new \InvalidArgumentException('One or more selected booking tables do not exist.');
        }
        $tableId = $tableIds[0]; // backward-compatible primary table

        $customerId = $payload['customer_server_id'] ?? $payload['customer_id'] ?? null;
        $customerLocalUuid = trim((string) ($payload['customer_local_uuid'] ?? $payload['customer_offline_uuid'] ?? ''));
        if (!$customerId && $customerLocalUuid !== '' && Schema::hasColumn('customers', 'offline_uuid')) {
            $customerId = Customer::where('offline_uuid', $customerLocalUuid)->value('id');
        }
        if (!$customerId && !empty($payload['customer']) && is_array($payload['customer'])) {
            $customer = $this->resolveCustomer($payload['customer']);
            $customerId = $customer->id;
        }
        if (!$customerId || !Customer::whereKey($customerId)->exists()) {
            throw new \InvalidArgumentException('A valid customer is required for a booking.');
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'upcoming')));
        $status = match ($status) {
            'noshow', 'no_show', 'no show' => 'no-show',
            default => $status,
        };
        $allowedStatuses = ['pending', 'upcoming', 'confirmed', 'seated', 'no-show', 'completed', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Invalid table booking status.');
        }

        $start = $payload['booking_start_time'] ?? $payload['booking_time'] ?? null;
        $end = $payload['booking_end_time'] ?? null;
        if (!$start || empty($payload['booking_date'])) {
            throw new \InvalidArgumentException('Booking date and start time are required.');
        }

        $advancePaymentMethod = $this->normalizeAdvancePaymentMethod($payload['advance_payment_method'] ?? null);
        $advancePaymentReference = trim((string) ($payload['advance_payment_reference'] ?? ''));
        if (in_array($advancePaymentMethod, ['Card', 'MFS'], true) && $advancePaymentReference === '') {
            throw new \InvalidArgumentException('Reference number is required for Bank / Card or MFS booking advance.');
        }

        $booking ??= new TableBooking();
        $oldTableIds = [];
        if ($booking->exists) {
            $booking->loadMissing('tables');
            $oldTableIds = $booking->tables->pluck('id')->push($booking->table_id)->filter()->unique()->map(fn ($id) => (int) $id)->values()->all();
        }
        $data = [
            'offline_uuid' => $offlineUuid !== '' ? $offlineUuid : ($booking->offline_uuid ?? null),
            'customer_id' => $customerId,
            'table_id' => $tableId,
            'is_new_customer' => $payload['is_new_customer'] ?? 0,
            'number_of_guests' => max(1, (int) ($payload['number_of_guests'] ?? $payload['guests'] ?? 1)),
            'booking_date' => $payload['booking_date'],
            'booking_time' => $start,
            'booking_start_time' => $start,
            'booking_end_time' => $end,
            'occasion_id' => $payload['occasion_server_id'] ?? $payload['occasion_id'] ?? null,
            'special_request' => $payload['special_request'] ?? null,
            'advance_amount' => max(0, (float) ($payload['advance_amount'] ?? 0)),
            'advance_payment_method' => $advancePaymentMethod,
            'advance_payment_reference' => $advancePaymentReference !== '' ? $advancePaymentReference : null,
            'status' => $status,
        ];

        $booking->fill($this->filterColumns('table_bookings', $data));
        $booking->save();
        if (Schema::hasColumn('table_bookings', 'booking_id') && empty($booking->booking_id)) {
            $booking->booking_id = '#BK-' . (1000 + $booking->id);
            $booking->save();
        }

        if (Schema::hasTable('table_booking_tables')) {
            $booking->tables()->sync($tableIds);
        }

        foreach (array_diff($oldTableIds, $tableIds) as $oldTableId) {
            $this->releaseTableIfFree((int) $oldTableId);
        }
        if (in_array($status, ['completed', 'cancelled', 'no-show'], true)) {
            foreach ($tableIds as $releaseId) $this->releaseTableIfFree((int) $releaseId);
        }

        return $booking->fresh(['customer', 'table', 'tables', 'occasion']);
    }

    private function resolveCustomer(array $payload): Customer
    {
        $offlineUuid = trim((string) ($payload['local_uuid'] ?? $payload['offline_uuid'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $customer = !empty($payload['server_id']) ? Customer::find($payload['server_id']) : null;
        if (!$customer && $offlineUuid !== '' && Schema::hasColumn('customers', 'offline_uuid')) {
            $customer = Customer::where('offline_uuid', $offlineUuid)->first();
        }
        if (!$customer && $phone !== '') {
            $customer = Customer::where('phone', $phone)->first();
        }
        $customer ??= new Customer();
        $customer->fill($this->filterColumns('customers', [
            'offline_uuid' => $offlineUuid !== '' ? $offlineUuid : ($customer->offline_uuid ?? null),
            'name' => $payload['name'] ?? ($customer->name ?? 'Walk-in Customer'),
            'phone' => $phone !== '' ? $phone : ($customer->phone ?? null),
            'email' => $payload['email'] ?? ($customer->email ?? null),
            'address' => $payload['address'] ?? ($customer->address ?? null),
        ]));
        $customer->save();
        return $customer;
    }

    private function normalizeAdvancePaymentMethod($value): ?string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') return null;

        return match ($raw) {
            'cash' => 'Cash',
            'card', 'bank/card', 'bank / card', 'bank card' => 'Card',
            'mfs', 'mobile banking', 'mobile_banking', 'bkash', 'nagad', 'rocket' => 'MFS',
            default => throw new \InvalidArgumentException('Unsupported booking advance payment method. Use Cash, Card or MFS.'),
        };
    }

    private function releaseTableIfFree(?int $tableId): void
    {
        if (!$tableId || !Schema::hasTable('tables') || !Schema::hasColumn('tables', 'initial_status')) {
            return;
        }
        $hasActiveOrder = DB::table('orders')->where('table_id', $tableId)
            ->whereIn('status', ['Pending', 'Waiter_Hold', 'QR_Pending', 'QR_Hold', 'Cooking', 'Ready'])->exists();
        if (!$hasActiveOrder) {
            Table::whereKey($tableId)->update(['initial_status' => 'Available']);
        }
    }

    private function serialize(TableBooking $booking): array
    {
        $data = $booking->toArray();
        $data['server_id'] = $booking->id;
        $data['local_uuid'] = $booking->offline_uuid ?? null;
        $tableIds = $booking->relationLoaded('tables') ? $booking->tables->pluck('id') : collect();
        $tableIds = $tableIds->push($booking->table_id)->filter()->unique()->map(fn ($id) => (int) $id)->values()->all();
        $data['table_server_ids'] = $tableIds;
        return $data;
    }

    private function filterColumns(string $table, array $data): array
    {
        if (!Schema::hasTable($table)) return [];
        $columns = Schema::getColumnListing($table);
        return array_filter($data, fn ($value, $key) => in_array($key, $columns, true), ARRAY_FILTER_USE_BOTH);
    }
}
