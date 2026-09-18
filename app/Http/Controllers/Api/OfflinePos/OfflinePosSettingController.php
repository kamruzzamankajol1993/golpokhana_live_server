<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\InvoiceSetting;
use App\Models\PosSetting;
use App\Models\RestaurantSetting;
use App\Models\TaxSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfflinePosSettingController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $pos = PosSetting::first();
        $controls = $this->offlineControls($pos);

        return response()->json([
            'status' => true,
            'message' => 'Offline POS API is reachable.',
            'server_time' => now()->toDateTimeString(),
            'api_version' => config('offline_pos.api_version', 'v1'),
            'api_prefix' => config('offline_pos.api_prefix', '/api/offline-pos/v1'),
            'offline_pos_enabled' => $controls['enabled'],
            'base_url' => $controls['base_url'],
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $restaurant = RestaurantSetting::first();
        $pos = PosSetting::first();
        $tax = TaxSetting::first();
        $invoice = InvoiceSetting::first();

        $restaurantData = $this->modelData($restaurant, 'restaurant_settings', [
            'id', 'name', 'phone', 'email', 'website', 'app_link', 'address', 'opening_time',
            'closing_time', 'currency', 'icon_name', 'logo', 'created_at', 'updated_at',
        ]);
        if ($restaurant) {
            $restaurantData['logo_url'] = $this->publicUrl($restaurant->logo ?? null);
            $restaurantData['icon_url'] = $this->publicUrl($restaurant->icon_name ?? null);
            $restaurantData['pos_action_password_configured'] = trim((string) ($restaurant->pos_action_password ?? '')) !== '';
        }

        $posData = $this->modelData($pos, 'pos_settings', [
            'id', 'default_view', 'items_per_page', 'auto_print_kitchen', 'auto_print_invoice',
            'require_table_selection', 'show_out_of_stock', 'final_payment_depends_on_kitchen_status',
            'order_list_random_half_enabled', 'random_half_order_button_visible', 'random_order_hide_percentage',
            'given_money_manual_toggle_enabled', 'allow_payment_with_insufficient_given_money',
            'show_honored_percentage_on_invoice', 'complimentary_note_required', 'dine_in_waiter_required',
            'opening_balance_enabled', 'offline_pos_enabled', 'offline_pos_base_url',
            'offline_pos_show_pull_button', 'offline_pos_show_push_button', 'offline_pos_auto_pull_enabled',
            'offline_pos_auto_push_enabled', 'offline_pos_sync_interval_seconds',
            'offline_pos_retry_interval_seconds', 'created_at', 'updated_at',
        ]);

        $taxData = $this->modelData($tax, 'tax_settings', [
            'id', 'vat_rate', 'tax_label', 'tax_registration_no', 'is_tax_included', 'service_charge',
            'created_at', 'updated_at',
        ]);

        $invoiceData = $this->modelData($invoice, 'invoice_settings', [
            'id', 'prefix', 'starting_number', 'footer_note', 'paper_size', 'show_logo',
            'order_prefix', 'order_starting_number', 'order_padding', 'invoice_padding',
            'kot_prefix', 'kot_starting_number', 'kot_padding', 'created_at', 'updated_at',
        ]);

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'offline_controls' => $this->offlineControls($pos),
            'restaurant_settings' => $restaurantData,
            'pos_settings' => $posData,
            'tax_settings' => $taxData,
            'invoice_settings' => $invoiceData,
        ]);
    }

    public function restaurantSettings(Request $request): JsonResponse
    {
        $data = $this->settings($request)->getData(true);
        return $this->single('restaurant_settings', $data['restaurant_settings'] ?? []);
    }

    public function posSettings(Request $request): JsonResponse
    {
        $data = $this->settings($request)->getData(true);
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'offline_controls' => $data['offline_controls'] ?? [],
            'pos_settings' => $data['pos_settings'] ?? [],
        ]);
    }

    public function taxSettings(Request $request): JsonResponse
    {
        $data = $this->settings($request)->getData(true);
        return $this->single('tax_settings', $data['tax_settings'] ?? []);
    }

    public function invoiceSettings(Request $request): JsonResponse
    {
        $data = $this->settings($request)->getData(true);
        return $this->single('invoice_settings', $data['invoice_settings'] ?? []);
    }

    private function single(string $key, array $data): JsonResponse
    {
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            $key => $data,
        ]);
    }

    private function offlineControls(?PosSetting $pos): array
    {
        $storedBaseUrl = trim((string) ($pos?->offline_pos_base_url ?? ''));
        $fallbackBaseUrl = trim((string) config('app.url', ''));
        $baseUrl = $storedBaseUrl !== '' ? $storedBaseUrl : $fallbackBaseUrl;

        if ($baseUrl === '') {
            $baseUrl = url('/');
        }

        return [
            'enabled' => (bool) ($pos?->offline_pos_enabled ?? true),
            'base_url' => rtrim($baseUrl, '/'),
            'api_prefix' => config('offline_pos.api_prefix', '/api/offline-pos/v1'),
            'show_pull_button' => (bool) ($pos?->offline_pos_show_pull_button ?? true),
            'show_push_button' => (bool) ($pos?->offline_pos_show_push_button ?? true),
            'auto_pull_enabled' => (bool) ($pos?->offline_pos_auto_pull_enabled ?? true),
            'auto_push_enabled' => (bool) ($pos?->offline_pos_auto_push_enabled ?? true),
            'sync_interval_seconds' => max(5, (int) ($pos?->offline_pos_sync_interval_seconds ?? 30)),
            'retry_interval_seconds' => max(5, (int) ($pos?->offline_pos_retry_interval_seconds ?? 15)),
        ];
    }

    private function modelData($model, string $table, array $wanted): array
    {
        if (!$model || !Schema::hasTable($table)) {
            return [];
        }

        $existing = Schema::getColumnListing($table);
        $allowed = array_values(array_intersect($wanted, $existing));
        $source = $model->toArray();
        $data = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $source)) {
                $data[$column] = $source[$column];
            }
        }

        if (array_key_exists('id', $data)) {
            $data['server_id'] = $data['id'];
        }

        return $data;
    }

    private function publicUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = preg_replace('#^/?public/#', '', ltrim($path, '/'));
        return asset('public/' . $path);
    }
}
