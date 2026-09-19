<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\OfflinePosDevice;
use Illuminate\Http\Request;

class OfflinePosDeviceApiController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate(['device_key'=>['required','string']]);

        $device = OfflinePosDevice::where('device_key',$request->device_key)
            ->where('is_active',true)->first();

        if (!$device) {
            return response()->json(['success'=>false,'message'=>'Invalid device key'],401);
        }

        $device->update(['last_seen_at'=>now()]);

        return response()->json([
            'success'=>true,
            'device'=>[
                'id'=>$device->id,
                'name'=>$device->name,
                'uuid'=>$device->device_uuid,
                            ]
        ]);
    }

    public function heartbeat(Request $request)
    {
        $request->validate([
            'device_key'=>['required','string'],
            'device_uuid'=>['nullable','string'],
        ]);

        $device = OfflinePosDevice::where('device_key',$request->device_key)
            ->where('is_active',true)->first();

        if (!$device) {
            return response()->json(['success'=>false,'message'=>'Invalid device'],401);
        }

        $device->update(['last_seen_at'=>now()]);

        return response()->json([
            'success'=>true,
            'message'=>'Device heartbeat updated',
            'last_seen_at'=>$device->last_seen_at,
        ]);
    }
}
