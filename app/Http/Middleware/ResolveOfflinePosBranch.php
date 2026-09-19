<?php

namespace App\Http\Middleware;

use App\Models\OfflinePosDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOfflinePosBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceUuid = trim((string) $request->header('X-OFFLINE-POS-DEVICE-ID'));

        if ($deviceUuid === '') {
            return response()->json(['status' => false, 'message' => 'Offline POS device id is required.'], 403);
        }

        $device = OfflinePosDevice::query()
            ->where('device_uuid', $deviceUuid)
            ->where('status', true)
            ->first();

        if (!$device) {
            return response()->json(['status' => false, 'message' => 'This Offline POS device is not active.'], 403);
        }

        $request->attributes->set('offline_pos_device', $device);
        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $next($request);
    }
}
