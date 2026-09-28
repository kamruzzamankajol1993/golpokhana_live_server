<?php
namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RestaurantSetting;
use App\Models\OfflinePosDevice;
use Illuminate\Http\Request;

class OfflinePosInitializeController extends Controller
{
    public function initialize(Request $request)
    {
        $request->validate([
            'device_key' => ['required','string'],
            'device_uuid' => ['nullable','string'],
        ]);

        $device = OfflinePosDevice::query()
            ->where('device_key', $request->device_key)
            ->where('status', true)
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive device key.'
            ], 401);
        }

        $givenUuid = trim((string) ($request->device_uuid ?? ''));
        $storedUuid = trim((string) ($device->device_uuid ?? ''));

        // Device records are created in the Main RMS before the physical/native
        // Offline POS exists, so the admin-created UUID is only a provisional value.
        // The first successful initialize request is the authoritative device bind.
        // After the device has connected once (last_seen_at is set), UUID mismatches
        // are rejected to prevent the same key being reused by another terminal.
        $hasBeenBound = !is_null($device->last_seen_at);

        if ($givenUuid !== '' && $storedUuid !== '' && !hash_equals($storedUuid, $givenUuid) && $hasBeenBound) {
            return response()->json([
                'success' => false,
                'message' => 'This device key is already bound to another device UUID.'
            ], 401);
        }

        $deviceUpdate = ['last_seen_at' => now()];
        if ($givenUuid !== '' && ($storedUuid === '' || !$hasBeenBound || !hash_equals($storedUuid, $givenUuid))) {
            $deviceUpdate['device_uuid'] = $givenUuid;
        }
        $device->update($deviceUpdate);
        $device->refresh();

        $restaurant = RestaurantSetting::first();

        $settings = [
            'system_name' => $restaurant->name ?? config('app.name'),
            'logo' => $this->publicUrl($restaurant->logo ?? null),
            'device_id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
        ];

        return response()->json([
            'success' => true,
            // Current offline clients authenticate subsequent sync calls with
            // X-OFFLINE-POS-KEY. Expose the same value as sync_token as well for
            // compatibility with the bootstrap client state gate.
            'sync_token' => $device->device_key,
            'settings' => $settings,
            'users' => User::with('roles')->get()->map(function($user){
                return [
                    'mother_user_id'=>$user->id,
                    'name'=>$user->name,
                    'email'=>$user->email,
                    'password'=>$user->password,
                    'status'=>isset($user->status) ? $user->status : 1,
                    'roles'=>$user->roles->pluck('name')
                ];
            })
        ]);
    }

    private function publicUrl($path)
    {
        if (!$path) {
            return null;
        }

        return url($path);
    }
}
