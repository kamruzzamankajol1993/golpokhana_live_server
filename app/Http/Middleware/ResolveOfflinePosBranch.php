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
            return response()->json([
                'status' => false,
                'message' => 'Offline POS device id is required.',
            ], 403);
        }

        $device = OfflinePosDevice::query()
            ->with('branch')
            ->where('device_uuid', $deviceUuid)
            ->where('is_active', true)
            ->first();

        if (!$device || !$device->branch || !$device->branch->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'This Offline POS device is not active or is not bound to an active branch.',
            ], 403);
        }

        $request->attributes->set('offline_pos_device', $device);
        $request->attributes->set('offline_pos_branch_id', (int) $device->branch_id);
        $request->attributes->set('offline_pos_branch', $device->branch);

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $next($request);
    }
}
