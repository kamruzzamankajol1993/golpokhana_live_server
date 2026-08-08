<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOfflinePosKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $validKey = config('offline_pos.sync_key') ?: config('services.offline_pos.sync_key') ?: env('OFFLINE_POS_SYNC_KEY');
        $givenKey = $request->header('X-OFFLINE-POS-KEY');

        if (!$validKey || !$givenKey || !hash_equals((string) $validKey, (string) $givenKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized offline POS request.',
            ], 401);
        }

        return $next($request);
    }
}
