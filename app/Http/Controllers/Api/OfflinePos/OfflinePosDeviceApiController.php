<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\OfflinePosDevice;
use Illuminate\Http\Request;

class OfflinePosDeviceApiController extends Controller
{
    public function verify(Request $request)
    {
        $data = $request->validate([
            'device_key' => ['required', 'string'],
            'device_uuid' => ['nullable', 'string', 'max:255'],
        ]);

        $device = $this->resolveActiveDevice($data['device_key'], $data['device_uuid'] ?? null);
        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Invalid device key or device UUID.'], 401);
        }

        $device->update(['last_seen_at' => now()]);

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'name' => $device->device_name,
                'device_name' => $device->device_name,
                'uuid' => $device->device_uuid,
                'device_uuid' => $device->device_uuid,
                'status' => (bool) $device->status,
                'last_seen_at' => optional($device->last_seen_at)->toDateTimeString(),
            ],
        ]);
    }

    public function heartbeat(Request $request)
    {
        $data = $request->validate([
            'device_key' => ['required', 'string'],
            'device_uuid' => ['nullable', 'string', 'max:255'],
        ]);

        $device = $this->resolveActiveDevice($data['device_key'], $data['device_uuid'] ?? null);
        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Invalid device key or device UUID.'], 401);
        }

        $device->update(['last_seen_at' => now()]);
        $device->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Device heartbeat updated',
            'device_id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'device_name' => $device->device_name,
            'last_seen_at' => optional($device->last_seen_at)->toDateTimeString(),
        ]);
    }

    private function resolveActiveDevice(string $deviceKey, ?string $deviceUuid): ?OfflinePosDevice
    {
        $device = OfflinePosDevice::query()
            ->where('device_key', $deviceKey)
            ->where('status', true)
            ->first();

        if (!$device) {
            return null;
        }

        $givenUuid = trim((string) $deviceUuid);
        $storedUuid = trim((string) $device->device_uuid);

        // Backward compatible: old clients may omit UUID. When a UUID is supplied,
        // bind an unbound device or reject a mismatched UUID.
        if ($givenUuid !== '') {
            if ($storedUuid !== '' && !hash_equals($storedUuid, $givenUuid)) {
                return null;
            }
            if ($storedUuid === '') {
                $device->forceFill(['device_uuid' => $givenUuid])->save();
                $device->refresh();
            }
        }

        return $device;
    }
}
