<?php

use App\Http\Controllers\Api\OfflinePos\OfflinePosAuthController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosDataController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosMasterDataController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosSettingController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosSyncController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosKitchenController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosBookingController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosOperationController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosSessionController;
use App\Http\Middleware\EnsureOfflinePosEnabled;
use App\Http\Middleware\VerifyOfflinePosKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfflinePos\OfflinePosInitializeController;
use App\Http\Controllers\Api\OfflinePos\OfflinePosDeviceApiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/offline-pos/v1/initialize', [OfflinePosInitializeController::class, 'initialize']);
Route::post('/offline-pos/v1/device/verify', [OfflinePosDeviceApiController::class, 'verify']);
Route::post('/offline-pos/v1/device/heartbeat', [OfflinePosDeviceApiController::class, 'heartbeat']);

Route::prefix('offline-pos/v1')
    ->middleware([VerifyOfflinePosKey::class])
    ->group(function () {
        // Always reachable with a valid sync key so the offline app can discover current state.
        Route::get('/ping', [OfflinePosSettingController::class, 'ping']);
        Route::get('/settings', [OfflinePosSettingController::class, 'settings']);

        Route::middleware([EnsureOfflinePosEnabled::class])->group(function () {
            Route::post('/auth/login', [OfflinePosAuthController::class, 'login']);

            // Initial/manual full pull
            Route::get('/bootstrap', [OfflinePosDataController::class, 'bootstrap']);
            Route::get('/sync/pull', [OfflinePosSyncController::class, 'pull']);

            // Incremental/background pull endpoints
            Route::get('/master-data', [OfflinePosMasterDataController::class, 'masterData']);
            Route::get('/pull/users', [OfflinePosMasterDataController::class, 'users']);
            Route::get('/pull/floor-zones', [OfflinePosMasterDataController::class, 'floorZones']);
            Route::get('/pull/zones', [OfflinePosMasterDataController::class, 'zones']);
            Route::get('/pull/tables', [OfflinePosMasterDataController::class, 'tables']);
            Route::get('/pull/waiters', [OfflinePosMasterDataController::class, 'waiters']);
            Route::get('/pull/customers', [OfflinePosMasterDataController::class, 'customers']);
            Route::get('/pull/food-categories', [OfflinePosMasterDataController::class, 'foodCategories']);
            Route::get('/pull/foods', [OfflinePosMasterDataController::class, 'foodItems']);
            Route::get('/pull/food-addons', [OfflinePosMasterDataController::class, 'foodAddons']);
            Route::get('/pull/delivery-partners', [OfflinePosMasterDataController::class, 'deliveryPartners']);
            Route::get('/pull/occasions', [OfflinePosMasterDataController::class, 'occasions']);
            Route::get('/pull/active-orders', [OfflinePosDataController::class, 'activeOrdersResponse']);
            Route::get('/pull/order-changes', [OfflinePosDataController::class, 'changedOrdersResponse']);
            Route::get('/pull/table-bookings', [OfflinePosBookingController::class, 'index']);
            Route::get('/table-bookings/options', [OfflinePosBookingController::class, 'options']);
            Route::get('/pull/pos-sessions', [OfflinePosSessionController::class, 'history']);
            Route::get('/pull/table-states', [OfflinePosOperationController::class, 'tableStatesResponse']);
            Route::get('/pull/takeaway-delivery', [OfflinePosOperationController::class, 'takeawayDelivery']);

            // POS screen/dashboard and local print data
            Route::get('/dashboard', [OfflinePosOperationController::class, 'dashboard']);
            Route::get('/pos-status', [OfflinePosOperationController::class, 'posStatus']);
            Route::get('/payment-options', [OfflinePosOperationController::class, 'paymentOptions']);
            Route::get('/pull/settings/restaurant', [OfflinePosSettingController::class, 'restaurantSettings']);
            Route::get('/pull/settings/pos', [OfflinePosSettingController::class, 'posSettings']);
            Route::get('/pull/settings/tax', [OfflinePosSettingController::class, 'taxSettings']);
            Route::get('/pull/settings/invoice', [OfflinePosSettingController::class, 'invoiceSettings']);
            Route::get('/tables/{tableId}/active-order', [OfflinePosOperationController::class, 'orderByTable']);
            Route::get('/orders/{id}', [OfflinePosOperationController::class, 'order']);
            Route::get('/orders/{id}/print-data', [OfflinePosOperationController::class, 'printData']);
            Route::get('/orders/{orderId}/merged-kot-data', [OfflinePosOperationController::class, 'mergedKotPrintData']);
            Route::get('/kots/{id}/print-data', [OfflinePosOperationController::class, 'kotPrintData']);
            Route::post('/pos-action-password/verify', [OfflinePosOperationController::class, 'verifyActionPassword']);

            // Offline Kitchen Display sync (same kitchen_status as main POS)
            Route::get('/kitchen/board', [OfflinePosKitchenController::class, 'board']);
            Route::get('/kitchen/statuses', [OfflinePosKitchenController::class, 'statuses']);
            Route::post('/kitchen/status', [OfflinePosKitchenController::class, 'updateStatus']);
            Route::get('/kitchen/sync', [OfflinePosKitchenController::class, 'sync']);
            Route::post('/kitchen/status/bulk', [OfflinePosKitchenController::class, 'bulkStatus']);
            Route::post('/kitchen/item-unavailable', [OfflinePosKitchenController::class, 'markItemUnavailable']);

            // Offline-created data push endpoints
            Route::post('/push/customers', [OfflinePosSyncController::class, 'pushCustomers']);
            Route::post('/push/orders', [OfflinePosSyncController::class, 'pushOrders']);
            Route::post('/push/table-bookings', [OfflinePosBookingController::class, 'push']);
            Route::delete('/table-bookings/{id}', [OfflinePosBookingController::class, 'destroy']);
            Route::post('/push/pos-sessions', [OfflinePosSessionController::class, 'push']);
            Route::post('/sync/push', [OfflinePosSyncController::class, 'pushAll']);

            // Shared POS work-period controls used by table/add-food/session-history screens
            Route::get('/pos-sessions/current', [OfflinePosSessionController::class, 'current']);
            Route::post('/pos-sessions/activity', [OfflinePosSessionController::class, 'activity']);
            Route::post('/pos-sessions/start', [OfflinePosSessionController::class, 'start']);
            Route::post('/pos-sessions/end', [OfflinePosSessionController::class, 'end']);
            Route::get('/pos-sessions/{id}/report-data', [OfflinePosSessionController::class, 'reportData']);
        });
    });
