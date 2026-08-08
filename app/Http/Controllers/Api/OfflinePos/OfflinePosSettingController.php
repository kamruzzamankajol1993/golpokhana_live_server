<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\RestaurantSetting;
use App\Models\TaxSetting;
use App\Models\InvoiceSetting;
use App\Models\PosSetting;
use Illuminate\Http\Request;

class OfflinePosSettingController extends Controller
{
    private function guard(Request $request)
{
    $validKey = config('offline_pos.sync_key') ?: config('services.offline_pos.sync_key') ?: env('OFFLINE_POS_SYNC_KEY');
    $givenKey = $request->header('X-OFFLINE-POS-KEY');

    if (!$validKey || !$givenKey || !hash_equals($validKey, $givenKey)) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized offline POS request.',
        ], 401);
    }

    return null;
}

    private function success(string $key, array $data)
    {
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            $key => $data,
        ]);
    }

    public function restaurantSettings(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $restaurant = RestaurantSetting::first();

        $data = $restaurant ? $restaurant->toArray() : [];

        $data['server_id'] = $restaurant->id ?? null;
        $data['logo_url'] = !empty($restaurant?->logo)
            ? asset('public/' . $restaurant->logo)
            : null;

        $data['icon_url'] = !empty($restaurant?->icon_name)
            ? asset('public/' . $restaurant->icon_name)
            : null;

        return $this->success('restaurant_settings', $data);
    }

    public function posSettings(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $pos = PosSetting::first();

        $data = $pos ? $pos->toArray() : [];

        $data['server_id'] = $pos->id ?? null;

        return $this->success('pos_settings', $data);
    }

    public function taxSettings(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $tax = TaxSetting::first();

        $data = $tax ? $tax->toArray() : [];

        $data['server_id'] = $tax->id ?? null;

        return $this->success('tax_settings', $data);
    }

    public function invoiceSettings(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $invoice = InvoiceSetting::first();

        $data = $invoice ? $invoice->toArray() : [];

        $data['server_id'] = $invoice->id ?? null;

        return $this->success('invoice_settings', $data);
    }
}
