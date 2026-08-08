<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderKot;

class KitchenController extends Controller
{
    public function index()
    {
        return view('admin.kitchen.index');
    }

    // AJAX: Get Live Orders & Category Wise Food Summary
    public function getLiveOrders()
    {
        // Load active KOTs with food item and category data for grouped kitchen summary.
        $kots = OrderKot::with([
                'order.table',
                'order.waiter',
                'orderDetails.foodItem.category',
                'orderDetails.foodItem.subCategory',
            ])
            ->whereIn('kitchen_status', ['Pending', 'Cooking', 'Ready'])
            ->orderBy('created_at', 'asc')
            ->get();

        $pendingCount = $kots->where('kitchen_status', 'Pending')->count();
        $cookingCount = $kots->where('kitchen_status', 'Cooking')->count();
        $readyCount   = $kots->where('kitchen_status', 'Ready')->count();

        // Category wise food summary for Pending and Cooking KOT items only.
        $categorySummary = [];
        $foodSummary = []; // Kept for backward compatibility if any old partial still reads it.
        $totalSummaryItems = 0;

        foreach ($kots as $kot) {
            if (!in_array($kot->kitchen_status, ['Pending', 'Cooking'], true)) {
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
                    $categorySummary[$categoryName] = [
                        'total' => 0,
                        'items' => [],
                    ];
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

        // Sort categories by total quantity, then sort foods inside each category by quantity.
        uasort($categorySummary, function ($a, $b) {
            return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
        });

        foreach ($categorySummary as &$categoryData) {
            arsort($categoryData['items']);
        }
        unset($categoryData);

        arsort($foodSummary);

        $html = view('admin.kitchen.partials._board_content', compact(
            'kots',
            'categorySummary',
            'foodSummary',
            'totalSummaryItems'
        ))->render();

        return response()->json([
            'html' => $html,
            'pendingCount' => $pendingCount,
            'cookingCount' => $cookingCount,
            'readyCount' => $readyCount,
            'summaryCount' => $totalSummaryItems,
            'csrfToken' => csrf_token(),
        ]);
    }

    // AJAX: Update Status
    public function updateStatus(Request $request)
    {
        $kot = OrderKot::findOrFail($request->kot_id);
        $kot->kitchen_status = $request->status; // Pending -> Cooking -> Ready -> Delivered
        $kot->save();

        return response()->json([
            'status' => 'success',
            'csrfToken' => csrf_token(),
        ]);
    }

    // KOT Print View
    public function printKot($id)
    {
        $kot = OrderKot::with(['order.table', 'order.waiter', 'orderDetails'])->findOrFail($id);

        // Add More Food / Running Order detection:
        // If this order already had a non-Hold KOT before the current KOT,
        // the current kitchen invoice is for a running order.
        $isRunningOrder = OrderKot::where('order_id', $kot->order_id)
            ->where('id', '<', $kot->id)
            ->where('kitchen_status', '!=', 'Hold')
            ->exists();

        return view('admin.kitchen.print_kot', compact('kot', 'isRunningOrder'));
    }

    // AJAX: Mark Item as Unavailable & Recalculate Bill
    public function markItemUnavailable(Request $request)
    {
        $detail = \App\Models\OrderDetail::findOrFail($request->detail_id);
        $detail->is_unavailable = 1;
        $detail->save();

        $order = \App\Models\Order::findOrFail($detail->order_id);

        // Recalculate subtotal using available items only.
        $newSubtotal = \App\Models\OrderDetail::where('order_id', $order->id)
            ->where('is_unavailable', 0)
            ->sum('subtotal');

        // VAT and service charge settings.
        $taxSetting = \Illuminate\Support\Facades\DB::table('tax_settings')->first();
        $vat_rate = $taxSetting->vat_rate ?? 0;
        $service_charge_rate = (strtolower($order->order_type) == 'dine-in' || strtolower($order->order_type) == 'dine_in')
            ? ($taxSetting->service_charge ?? 0)
            : 0;

        $discount_amount = $order->discount_amount;

        // Recalculate rounded bill values.
        $service_charge = round(($newSubtotal * $service_charge_rate) / 100);
        $tax = round((($newSubtotal + $service_charge) * $vat_rate) / 100);
        $grand_total = round(($newSubtotal + $tax + $service_charge) - $discount_amount);

        // Update order bill.
        $order->update([
            'subtotal' => $newSubtotal,
            'service_charge' => $service_charge,
            'vat_tax' => $tax,
            'grand_total' => $grand_total,
            'due' => $grand_total - $order->total_paid_amount,
        ]);

        return response()->json([
            'status' => 'success',
            'csrfToken' => csrf_token(),
        ]);
    }
}
