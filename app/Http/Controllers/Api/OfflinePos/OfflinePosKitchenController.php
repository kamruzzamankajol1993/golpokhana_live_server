<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderKot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfflinePosKitchenController extends Controller
{
    /** Main RMS kitchen board uses only Pending, Cooking and Ready as active states. */
    public function board(Request $request): JsonResponse
    {
        $allKots = OrderKot::with([
            'order.table', 'order.waiter', 'order.customer', 'order.deliveryPartner',
            'orderDetails.foodItem.category', 'orderDetails.foodItem.subCategory',
        ])->whereIn('kitchen_status', ['Pending', 'Cooking', 'Ready'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $displayKots = $allKots;
        if ($request->filled('status')) {
            $status = $this->normalizeStatus((string) $request->query('status'));
            if (in_array($status, ['Pending', 'Cooking', 'Ready'], true)) {
                $displayKots = $allKots->where('kitchen_status', $status)->values();
            }
        }

        $summary = $this->buildKitchenSummary($allKots);

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'kitchen_statuses' => $this->statusDefinitions(),
            'counts' => [
                'pending' => $allKots->where('kitchen_status', 'Pending')->count(),
                'cooking' => $allKots->where('kitchen_status', 'Cooking')->count(),
                'ready' => $allKots->where('kitchen_status', 'Ready')->count(),
                'all' => $allKots->count(),
            ],
            // Same summary logic as the main RMS KitchenController. The offline
            // kitchen design uses this for the left-side category/food counters.
            'category_summary' => $summary['category_summary'],
            'food_summary' => $summary['food_summary'],
            'summary_count' => $summary['summary_count'],
            'data' => $displayKots->map(fn (OrderKot $kot) => $this->serializeKot($kot))->values()->all(),
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json(['status' => true, 'kitchen_statuses' => $this->statusDefinitions()]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kot_id' => ['nullable', 'integer'],
            'kot_local_uuid' => ['nullable', 'string', 'max:100'],
            'local_uuid' => ['nullable', 'string', 'max:100'],
            'kitchen_status' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $status = $this->normalizeStatus((string) ($data['kitchen_status'] ?? $data['status'] ?? ''));
        if (!in_array($status, ['Pending', 'Cooking', 'Ready', 'Delivered'], true)) {
            return response()->json(['status' => false, 'message' => 'Invalid kitchen status.'], 422);
        }

        $kot = $this->resolveKot($data['kot_id'] ?? null, $data['kot_local_uuid'] ?? $data['local_uuid'] ?? null);
        if (!$kot) return response()->json(['status' => false, 'message' => 'KOT not found.'], 404);

        $kot->kitchen_status = $status;
        $kot->save();

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'data' => $this->serializeKot($kot->fresh(['order.table', 'order.waiter', 'order.customer', 'order.deliveryPartner', 'orderDetails'])),
        ]);
    }

    /**
     * Background pull. Timestamp based sync is required because a status update changes
     * an existing KOT and therefore cannot be discovered reliably by after_id alone.
     */
    public function sync(Request $request): JsonResponse
    {
        $query = OrderKot::with(['order.table', 'order.waiter', 'order.customer', 'order.deliveryPartner', 'orderDetails'])
            ->orderBy('updated_at')->orderBy('id');

        $updatedAfter = trim((string) ($request->query('updated_after') ?? $request->query('last_synced_at') ?? ''));
        if ($updatedAfter !== '' && Schema::hasColumn('order_kots', 'updated_at')) {
            $query->where('updated_at', '>', $updatedAfter);
        } elseif ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->query('after_id'));
        }

        $rows = $query->limit(500)->get();
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'requested_updated_after' => $updatedAfter !== '' ? $updatedAfter : null,
            'data' => $rows->map(fn (OrderKot $kot) => $this->serializeKot($kot))->values()->all(),
        ]);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.kot_id' => ['nullable', 'integer'],
            'items.*.kot_local_uuid' => ['nullable', 'string', 'max:100'],
            'items.*.local_uuid' => ['nullable', 'string', 'max:100'],
            'items.*.kitchen_status' => ['nullable', 'string'],
            'items.*.status' => ['nullable', 'string'],
        ])['items'];

        $results = [];
        foreach ($items as $item) {
            $status = $this->normalizeStatus((string) ($item['kitchen_status'] ?? $item['status'] ?? ''));
            $localUuid = $item['kot_local_uuid'] ?? $item['local_uuid'] ?? null;
            $kot = $this->resolveKot($item['kot_id'] ?? null, $localUuid);
            if (!$kot || !in_array($status, ['Pending', 'Cooking', 'Ready', 'Delivered'], true)) {
                $results[] = ['kot_id' => $item['kot_id'] ?? null, 'local_uuid' => $localUuid, 'status' => 'failed'];
                continue;
            }
            $kot->kitchen_status = $status;
            $kot->save();
            $results[] = ['kot_id' => $kot->id, 'local_uuid' => $kot->offline_uuid ?? $localUuid, 'kitchen_status' => $status, 'status' => 'synced'];
        }

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'items' => $results,
        ]);
    }

    /** Main RMS kitchen can mark a saved item unavailable; expose the same operation for sync parity. */
    public function markItemUnavailable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'detail_id' => ['nullable', 'integer'],
            'detail_local_uuid' => ['nullable', 'string', 'max:100'],
        ]);
        $detail = !empty($data['detail_id']) ? OrderDetail::find($data['detail_id']) : null;
        if (!$detail && !empty($data['detail_local_uuid']) && Schema::hasColumn('order_details', 'offline_uuid')) {
            $detail = OrderDetail::where('offline_uuid', $data['detail_local_uuid'])->first();
        }
        if (!$detail) return response()->json(['status' => false, 'message' => 'Order item not found.'], 404);

        DB::transaction(function () use ($detail) {
            $detail->is_unavailable = 1;
            $detail->product_discount_type = null;
            $detail->product_discount_value = 0;
            $detail->product_discount_amount = 0;
            $detail->save();

            $order = Order::findOrFail($detail->order_id);
            $available = OrderDetail::where('order_id', $order->id)->where('is_unavailable', 0);
            $subtotal = (float) $available->sum('subtotal');
            $productDiscount = (float) OrderDetail::where('order_id', $order->id)->where('is_unavailable', 0)->sum('product_discount_amount');
            $taxSetting = Schema::hasTable('tax_settings') ? DB::table('tax_settings')->first() : null;
            $vatRate = (float) ($taxSetting->vat_rate ?? 0);
            $serviceRate = in_array(strtolower((string) $order->order_type), ['dine-in', 'dine_in'], true) ? (float) ($taxSetting->service_charge ?? 0) : 0;
            $serviceCharge = round(($subtotal * $serviceRate) / 100);
            $tax = round((($subtotal + $serviceCharge) * $vatRate) / 100);
            $grand = max(0, round(($subtotal + $tax + $serviceCharge) - (float) $order->discount_amount - $productDiscount));
            $order->update([
                'subtotal' => $subtotal,
                'service_charge' => $serviceCharge,
                'vat_tax' => $tax,
                'product_discount_amount' => $productDiscount,
                'grand_total' => $grand,
                'due' => max(0, $grand - (float) $order->total_paid_amount),
            ]);
        });

        return response()->json(['status' => true, 'message' => 'Item marked unavailable.', 'server_time' => now()->toDateTimeString()]);
    }

    private function resolveKot($serverId, ?string $localUuid): ?OrderKot
    {
        $kot = $serverId ? OrderKot::find($serverId) : null;
        $localUuid = trim((string) $localUuid);
        if (!$kot && $localUuid !== '' && Schema::hasColumn('order_kots', 'offline_uuid')) {
            $kot = OrderKot::where('offline_uuid', $localUuid)->first();
        }
        return $kot;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'pending' => 'Pending',
            'cooking', 'preparing' => 'Cooking',
            'ready' => 'Ready',
            'delivered', 'served' => 'Delivered',
            'hold' => 'Hold',
            default => '',
        };
    }

    private function buildKitchenSummary($kots): array
    {
        $categorySummary = [];
        $foodSummary = [];
        $totalSummaryItems = 0;

        foreach ($kots as $kot) {
            if (!in_array((string) $kot->kitchen_status, ['Pending', 'Cooking'], true)) {
                continue;
            }

            foreach ($kot->orderDetails as $item) {
                if ((int) ($item->is_unavailable ?? 0) === 1) {
                    continue;
                }

                $qty = (int) ($item->quantity ?? 0);
                if ($qty < 1) {
                    continue;
                }

                $food = $item->foodItem;
                $categoryName = optional(optional($food)->category)->name
                    ?: optional(optional($food)->subCategory)->name
                    ?: 'Uncategorized';
                $foodName = $item->product_name ?: (optional($food)->name ?: 'Unknown Item');

                if (!isset($categorySummary[$categoryName])) {
                    $categorySummary[$categoryName] = ['total' => 0, 'items' => []];
                }
                if (!isset($categorySummary[$categoryName]['items'][$foodName])) {
                    $categorySummary[$categoryName]['items'][$foodName] = 0;
                }
                if (!isset($foodSummary[$foodName])) {
                    $foodSummary[$foodName] = 0;
                }

                $categorySummary[$categoryName]['items'][$foodName] += $qty;
                $categorySummary[$categoryName]['total'] += $qty;
                $foodSummary[$foodName] += $qty;
                $totalSummaryItems += $qty;
            }
        }

        uasort($categorySummary, fn ($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        foreach ($categorySummary as &$categoryData) {
            arsort($categoryData['items']);
        }
        unset($categoryData);
        arsort($foodSummary);

        return [
            'category_summary' => $categorySummary,
            'food_summary' => $foodSummary,
            'summary_count' => $totalSummaryItems,
        ];
    }

    private function statusDefinitions(): array
    {
        return [
            ['value' => 'Pending', 'display_label' => 'Pending', 'active_on_board' => true],
            ['value' => 'Cooking', 'display_label' => 'Preparing', 'active_on_board' => true],
            ['value' => 'Ready', 'display_label' => 'Ready', 'active_on_board' => true],
            ['value' => 'Delivered', 'display_label' => 'Served', 'active_on_board' => false],
            ['value' => 'Hold', 'display_label' => 'Hold', 'active_on_board' => false, 'system_only' => true],
        ];
    }

    private function serializeKot(OrderKot $kot): array
    {
        $data = $kot->toArray();
        $data['server_id'] = $kot->id;
        $data['local_uuid'] = $kot->offline_uuid ?? null;
        $data['display_status'] = match ((string) $kot->kitchen_status) {
            'Cooking' => 'Preparing',
            'Delivered' => 'Served',
            default => (string) $kot->kitchen_status,
        };
        return $data;
    }
}
