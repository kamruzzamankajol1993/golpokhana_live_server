<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\OfflinePosDevice;
class VerifyOfflinePosKey
{



public function handle(Request $request, Closure $next): Response
{
    $givenKey = $request->header('X-OFFLINE-POS-KEY');

    $validKey = OfflinePosDevice::where('device_key',$givenKey)
        ->where('status',1)
        ->exists();


    if(!$validKey){
        return response()->json([
            'status'=>false,
            'message'=>'Unauthorized offline POS request.',
        ],401);
    }

    return $next($request);
}
}
