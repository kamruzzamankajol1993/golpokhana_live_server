<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosSession;
use App\Models\Table;
use App\Models\User;
use App\Services\PosSessionManagerResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OfflinePosSessionController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $manager = $this->resolveManager($request->query('actor_user_id'));
        $session = $manager ? PosSession::where('user_id', $manager->id)->where('status', 'Open')->latest('id')->first() : null;

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'active' => (bool) $session,
            'pos_session' => $session ? $this->serialize($session) : null,
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actor_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $manager = $this->resolveManager($validated['actor_user_id']);
        if (!$manager) {
            return response()->json(['status' => false, 'code' => 'pos_manager_not_found', 'message' => 'Manager user not found.'], 422);
        }

        $session = PosSession::where('user_id', $manager->id)
            ->where('status', 'Open')
            ->latest('id')
            ->first();

        if (!$session) {
            return response()->json([
                'status' => true,
                'active' => false,
                'message' => 'No active POS session found.',
                'pos_session' => null,
            ]);
        }

        if (Schema::hasColumn('pos_sessions', 'last_activity_at')) {
            $session->forceFill(['last_activity_at' => Carbon::now('Asia/Dhaka')])->save();
            $session->refresh();
        }

        return response()->json([
            'status' => true,
            'active' => true,
            'message' => 'POS session activity updated.',
            'pos_session' => $this->serialize($session),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        if (!Schema::hasTable('pos_sessions')) {
            return response()->json(['status' => true, 'pos_sessions' => [], 'meta' => ['count' => 0]]);
        }

        $query = PosSession::query()->with(['user', 'startedBy', 'endedBy']);
        if ($request->filled('last_synced_at') && Schema::hasColumn('pos_sessions', 'updated_at')) {
            $query->where('updated_at', '>', $request->query('last_synced_at'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('start_time', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('start_time', '<=', $request->query('to'));
        }

        $rows = $query->latest('id')->limit(500)->get()->map(fn (PosSession $session) => $this->serialize($session))->values()->all();
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'pos_sessions' => $rows,
            'meta' => ['count' => count($rows)],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actor_user_id' => ['required', 'integer', 'exists:users,id'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'local_uuid' => ['nullable', 'string', 'max:100'],
        ]);

        $actor = User::findOrFail($validated['actor_user_id']);
        $manager = app(PosSessionManagerResolver::class)->resolve($actor);
        if (!$manager) {
            return response()->json(['status' => false, 'code' => 'pos_manager_not_found', 'message' => 'Manager user not found.'], 422);
        }

        return DB::transaction(function () use ($actor, $manager, $validated) {
            DB::table('users')->where('id', $manager->id)->lockForUpdate()->first();
            $active = PosSession::where('user_id', $manager->id)->where('status', 'Open')->latest('id')->lockForUpdate()->first();
            if ($active) {
                return response()->json([
                    'status' => true,
                    'already_active' => true,
                    'message' => 'Manager work period is already running.',
                    'pos_session' => $this->serialize($active),
                ]);
            }

            $now = Carbon::now('Asia/Dhaka');
            $payload = [
                'user_id' => $manager->id,
                'weekday' => $now->format('l'),
                'start_time' => $now,
                'status' => 'Open',
                'opening_balance' => round((float) ($validated['opening_balance'] ?? 0), 2),
            ];
            if (Schema::hasColumn('pos_sessions', 'offline_uuid') && !empty($validated['local_uuid'])) $payload['offline_uuid'] = $validated['local_uuid'];
            if (Schema::hasColumn('pos_sessions', 'started_by_user_id')) $payload['started_by_user_id'] = $actor->id;
            if (Schema::hasColumn('pos_sessions', 'last_activity_at')) $payload['last_activity_at'] = $now;

            $session = PosSession::create($this->filterColumns('pos_sessions', $payload));
            return response()->json([
                'status' => true,
                'already_active' => false,
                'message' => 'Manager work period started successfully.',
                'pos_session' => $this->serialize($session),
            ]);
        });
    }

    public function end(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'actor_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $actor = User::findOrFail($validated['actor_user_id']);
        $manager = app(PosSessionManagerResolver::class)->resolve($actor);
        if (!$manager) {
            return response()->json(['status' => false, 'code' => 'pos_manager_not_found', 'message' => 'Manager user not found.'], 422);
        }

        $session = PosSession::whereKey($validated['session_id'])->where('user_id', $manager->id)->where('status', 'Open')->firstOrFail();
        $blockers = $this->closeBlockers();
        if ($blockers['blocked']) {
            return response()->json(array_merge([
                'status' => false,
                'code' => 'session_close_blocked',
                'message' => $blockers['message'],
            ], $blockers), 409);
        }

        $session = $this->closeSession($session, Carbon::now('Asia/Dhaka'), (int) $actor->id);
        return response()->json(['status' => true, 'message' => 'Work period ended successfully.', 'pos_session' => $this->serialize($session)]);
    }

    public function push(Request $request): JsonResponse
    {
        $rows = $request->validate(['pos_sessions' => ['required', 'array']])['pos_sessions'];
        $results = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $results[] = ['status' => 'failed', 'message' => 'Session payload must be an object.'];
                continue;
            }
            try {
                $results[] = DB::transaction(fn () => $this->upsertOfflineSession($row));
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

        return response()->json(['status' => true, 'server_time' => now()->toDateTimeString(), 'pos_sessions' => $results]);
    }

    public function reportData(int $id): JsonResponse
    {
        $session = PosSession::with(['user', 'startedBy', 'endedBy'])->findOrFail($id);
        $start = Carbon::parse($session->start_time);
        $end = Carbon::parse($session->end_time ?: now());
        $orders = Order::query()->whereNotIn('status', ['Cancelled', 'cancelled'])->whereBetween('created_at', [$start, $end])->orderBy('id')->get();

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'pos_session' => $this->serialize($session),
            'orders' => $orders,
            'summary' => $this->orderSummary($orders),
        ]);
    }

    private function upsertOfflineSession(array $row): array
    {
        $offlineUuid = trim((string) ($row['local_uuid'] ?? $row['offline_uuid'] ?? ''));
        $serverId = $row['server_id'] ?? null;
        if (!$serverId && $offlineUuid === '') {
            throw new \InvalidArgumentException('POS session local_uuid is required for safe synchronization.');
        }

        $actorId = (int) ($row['actor_user_id'] ?? $row['started_by_user_id'] ?? $row['user_id'] ?? 0);
        $actor = $actorId ? User::find($actorId) : null;
        $manager = app(PosSessionManagerResolver::class)->resolve($actor);
        if (!$manager) throw new \InvalidArgumentException('Manager user not found.');

        $session = $serverId ? PosSession::find($serverId) : null;
        if (!$session && $offlineUuid !== '' && Schema::hasColumn('pos_sessions', 'offline_uuid')) {
            $session = PosSession::where('offline_uuid', $offlineUuid)->first();
        }
        if (!$session && strtolower((string) ($row['status'] ?? 'open')) === 'open') {
            $session = PosSession::where('user_id', $manager->id)->where('status', 'Open')->latest('id')->first();
        }

        $session ??= new PosSession();
        $start = Carbon::parse($row['start_time'] ?? now(), 'Asia/Dhaka');
        $status = strcasecmp((string) ($row['status'] ?? 'Open'), 'Closed') === 0 ? 'Closed' : 'Open';
        $payload = [
            'offline_uuid' => $offlineUuid !== '' ? $offlineUuid : ($session->offline_uuid ?? null),
            'user_id' => $manager->id,
            'started_by_user_id' => $row['started_by_user_id'] ?? $actor?->id,
            'weekday' => $row['weekday'] ?? $start->format('l'),
            'start_time' => $start,
            'last_activity_at' => $row['last_activity_at'] ?? $start,
            'status' => 'Open',
            'opening_balance' => max(0, (float) ($row['opening_balance'] ?? 0)),
        ];
        $session->fill($this->filterColumns('pos_sessions', $payload));
        $session->save();

        if ($status === 'Closed') {
            $end = Carbon::parse($row['end_time'] ?? now(), 'Asia/Dhaka');
            $session = $this->closeSession($session, $end, (int) ($row['ended_by_user_id'] ?? $actor?->id ?? 0));
        }

        return [
            'local_uuid' => $offlineUuid ?: null,
            'server_id' => $session->id,
            'status' => 'synced',
            'session_status' => $session->status,
        ];
    }

    private function closeSession(PosSession $session, Carbon $endTime, ?int $endedByUserId = null): PosSession
    {
        $start = Carbon::parse($session->start_time, 'Asia/Dhaka');
        $end = $endTime->copy()->setTimezone('Asia/Dhaka');
        if ($end->lt($start)) $end = $start->copy();
        $orders = Order::query()->whereNotIn('status', ['Cancelled', 'cancelled'])->whereBetween('created_at', [$start, $end])->get();
        $summary = $this->orderSummary($orders);
        $duration = $end->diffAsCarbonInterval($start)->cascade()->forHumans(['short' => true]);

        $payload = [
            'end_time' => $end,
            'duration' => $duration,
            'status' => 'Closed',
            'sales_total' => $summary['sales_total'],
            'service_charge' => $summary['service_charge'],
            'vat_total' => $summary['vat_total'],
            'grand_total' => $summary['grand_total'],
            'incomes_summary' => $summary['incomes_summary'],
        ];
        if ($endedByUserId && Schema::hasColumn('pos_sessions', 'ended_by_user_id')) $payload['ended_by_user_id'] = $endedByUserId;
        $session->update($this->filterColumns('pos_sessions', $payload));
        return $session->fresh();
    }

    private function orderSummary($orders): array
    {
        $cash = $card = $mfc = 0.0;
        foreach ($orders as $order) {
            if ((string) $order->payment_type === 'Split') {
                $cash += (float) ($order->paid_in_cash ?? 0);
                $card += (float) ($order->paid_in_card ?? 0);
                $mfc += (float) ($order->paid_in_mfc ?? 0);
            } elseif ((string) $order->payment_type === 'Cash') {
                $cash += (float) ($order->total_paid_amount ?? 0);
            } elseif ((string) $order->payment_type === 'Card') {
                $card += (float) ($order->total_paid_amount ?? 0);
            } elseif ((string) $order->payment_type === 'Mobile Banking') {
                $mfc += (float) ($order->total_paid_amount ?? 0);
            }
        }
        return [
            'sales_total' => (float) $orders->sum('subtotal'),
            'service_charge' => (float) $orders->sum('service_charge'),
            'vat_total' => (float) $orders->sum('vat_tax'),
            'grand_total' => (float) $orders->sum('grand_total'),
            'incomes_summary' => ['Cash' => $cash, 'Card' => $card, 'MFC' => $mfc],
        ];
    }

    private function closeBlockers(): array
    {
        $activeStatuses = ['Pending', 'Waiter_Hold', 'Cooking', 'Ready'];
        $activeOrderTableIds = Order::query()->whereNotNull('table_id')->whereIn('status', $activeStatuses)->pluck('table_id');
        $persisted = Table::query()->whereRaw('LOWER(TRIM(initial_status)) = ?', ['occupied'])->pluck('id');
        $ids = $activeOrderTableIds->merge($persisted)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $tables = $ids->isEmpty() ? collect() : Table::whereIn('id', $ids)->orderBy('table_number')->get(['id', 'table_number']);

        $today = Carbon::now('Asia/Dhaka')->toDateString();
        $takeaways = Order::query()->whereDate('order_time', $today)->whereRaw('LOWER(TRIM(status)) = ?', ['pending'])
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(TRIM(order_type), '-', ''), ' ', ''), '_', '')) IN (?, ?)", ['takeaway', 'delivery'])
            ->get(['id', 'order_number', 'order_type']);

        $messages = [];
        if ($tables->isNotEmpty()) $messages[] = 'Please clear ' . $tables->count() . ' occupied table(s) first.';
        if ($takeaways->isNotEmpty()) $messages[] = 'Please complete or cancel today\'s ' . $takeaways->count() . ' pending Takeaway/Delivery order(s) first.';

        return [
            'blocked' => $tables->isNotEmpty() || $takeaways->isNotEmpty(),
            'occupied_table_count' => $tables->count(),
            'occupied_table_ids' => $tables->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'pending_takeaway_delivery_count' => $takeaways->count(),
            'pending_takeaway_delivery_order_ids' => $takeaways->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'message' => empty($messages) ? '' : 'Session cannot be ended. ' . implode(' ', $messages),
        ];
    }

    private function resolveManager($actorId): ?User
    {
        $actor = $actorId ? User::find((int) $actorId) : null;
        return app(PosSessionManagerResolver::class)->resolve($actor);
    }

    private function serialize(PosSession $session): array
    {
        $data = $session->toArray();
        $data['server_id'] = $session->id;
        $data['local_uuid'] = $session->offline_uuid ?? null;
        $isOpen = strcasecmp((string) $session->status, 'Open') === 0;
        $data['display_status'] = $isOpen ? 'Active' : 'Closed';
        if ($isOpen && $session->start_time) {
            try {
                $liveOrders = Order::query()
                    ->whereNotIn('status', ['Cancelled', 'cancelled'])
                    ->whereBetween('created_at', [Carbon::parse($session->start_time), Carbon::now('Asia/Dhaka')])
                    ->get();
                $liveSummary = $this->orderSummary($liveOrders);
                $data['live_summary'] = $liveSummary;
                $data['display_grand_total'] = $liveSummary['grand_total'];
            } catch (\Throwable $e) {
                $data['live_summary'] = null;
                $data['display_grand_total'] = (float) ($session->grand_total ?? 0);
            }
        } else {
            $data['live_summary'] = null;
            $data['display_grand_total'] = (float) ($session->grand_total ?? 0);
        }
        try {
            $start = Carbon::parse($session->start_time);
            $end = $session->end_time ? Carbon::parse($session->end_time) : Carbon::now('Asia/Dhaka');
            $seconds = max(0, $start->diffInSeconds($end));
            $data['duration_seconds'] = $seconds;
            $data['display_duration'] = $session->end_time
                ? ($session->duration ?: sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60)))
                : 'Ongoing';
        } catch (\Throwable $e) {
            $data['duration_seconds'] = null;
            $data['display_duration'] = $session->end_time ? ($session->duration ?: null) : 'Ongoing';
        }
        return $data;
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        return array_filter($data, fn ($value, $key) => in_array($key, $columns, true), ARRAY_FILTER_USE_BOTH);
    }
}
