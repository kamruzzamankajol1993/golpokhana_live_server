<?php

namespace App\Support;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderVisibility
{
    /**
     * Safely read the global random order visibility setting.
     * The original database flag is retained for backward compatibility.
     */
    public static function isRandomHalfEnabled(): bool
    {
        if (!Schema::hasTable('pos_settings')
            || !Schema::hasColumn('pos_settings', 'order_list_random_half_enabled')) {
            return false;
        }

        return (bool) (DB::table('pos_settings')->value('order_list_random_half_enabled') ?? false);
    }

    /**
     * Percentage of matching orders that will be hidden.
     * Existing installations without the new migration continue to use 50%.
     */
    public static function hidePercentage(): int
    {
        if (!Schema::hasTable('pos_settings')
            || !Schema::hasColumn('pos_settings', 'random_order_hide_percentage')) {
            return 50;
        }

        $percentage = (int) (DB::table('pos_settings')->value('random_order_hide_percentage') ?? 50);

        return max(1, min(100, $percentage));
    }

    /**
     * Return deterministic visible order IDs for the supplied base query.
     *
     * Calendar-today orders are always kept visible. The configured hide
     * percentage is applied only to matching non-today orders. This keeps
     * Today's Orders / Revenue Today complete while still hiding history.
     */
    public static function visibleIds($query, array $seedContext = []): array
    {
        $matchingIds = (clone $query)
            ->reorder()
            ->pluck('orders.id')
            ->unique()
            ->values();

        if ($matchingIds->isEmpty()) {
            return [];
        }

        $todayStart = Carbon::today()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();

        // Today's matching orders must never be hidden by Order Visibility.
        $todayIds = (clone $query)
            ->reorder()
            ->where('orders.created_at', '>=', $todayStart)
            ->where('orders.created_at', '<', $tomorrowStart)
            ->pluck('orders.id')
            ->unique()
            ->values();

        // Apply the configured percentage only to the remaining matching orders.
        $nonTodayIds = $matchingIds
            ->diff($todayIds)
            ->values();

        if ($nonTodayIds->isEmpty()) {
            return $todayIds->all();
        }

        $hidePercentage = self::hidePercentage();
        $totalHideableCount = $nonTodayIds->count();
        $hiddenCount = $hidePercentage >= 100
            ? $totalHideableCount
            : (int) round(($totalHideableCount * $hidePercentage) / 100);
        $visibleHistoricalCount = max(0, $totalHideableCount - $hiddenCount);

        $normalizedContext = self::normalizeSeedContext($seedContext);

        // Keep the historical sample deterministic for equivalent requests.
        $seedBusinessDate = (string) ($normalizedContext['business_date']
            ?? $normalizedContext['system_day']
            ?? Carbon::today()->format('Y-m-d'));

        $seed = $seedBusinessDate . '|hide:' . $hidePercentage . '|'
            . hash('sha256', json_encode($normalizedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $visibleHistoricalIds = $visibleHistoricalCount > 0
            ? $nonTodayIds
                ->sortBy(function ($id) use ($seed) {
                    return hash('sha256', $seed . '|' . $id);
                })
                ->take($visibleHistoricalCount)
                ->values()
            : collect();

        return $todayIds
            ->concat($visibleHistoricalIds)
            ->unique()
            ->values()
            ->all();
    }

    /** Apply the visibility rule to an Order query only when enabled. */
    public static function apply($query, array $seedContext = [])
    {
        if (!self::isRandomHalfEnabled()) {
            return $query;
        }

        return $query->whereIn('orders.id', self::visibleIds($query, $seedContext));
    }

    /**
     * Calculate the shared global visible IDs once for Dashboard aggregates.
     * Null means the option is off and no visibility restriction is applied.
     */
    public static function globalVisibleIds(): ?array
    {
        if (!self::isRandomHalfEnabled()) {
            return null;
        }

        return self::visibleIds(Order::query());
    }

    /** Constrain any query that contains the orders table to the shared visible IDs. */
    public static function constrain($query, ?array $visibleIds)
    {
        if ($visibleIds === null) {
            return $query;
        }

        return $query->whereIn('orders.id', $visibleIds);
    }

    /** Empty URL values are removed so equivalent filters use the same daily sample. */
    private static function normalizeSeedContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = self::normalizeSeedContext($value);
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);

        return $normalized;
    }
}
