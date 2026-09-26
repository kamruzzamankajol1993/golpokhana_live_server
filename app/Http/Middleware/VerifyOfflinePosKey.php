<?php

namespace App\Http\Middleware;

use App\Models\OfflinePosDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOfflinePosKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $givenKey = trim((string) $request->header('X-OFFLINE-POS-KEY'));
        $givenUuid = trim((string) $request->header('X-OFFLINE-DEVICE-UUID'));

        $device = OfflinePosDevice::query()
            ->where('device_key', $givenKey)
            ->where('status', 1)
            ->first();

        if (!$device) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized offline POS request.',
            ], 401);
        }

        // New offline clients send their stored UUID on every sync request.
        // UUID remains optional for backward compatibility with already deployed clients.
        $storedUuid = trim((string) $device->device_uuid);
        if ($givenUuid !== '' && $storedUuid !== '' && !hash_equals($storedUuid, $givenUuid)) {
            return response()->json([
                'status' => false,
                'message' => 'Offline POS device UUID mismatch.',
            ], 401);
        }

        $request->attributes->set('offline_pos_device', $device);
        return $next($request);
    }
}
