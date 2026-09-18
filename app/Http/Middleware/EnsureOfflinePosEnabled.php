<?php

namespace App\Http\Middleware;

use App\Models\PosSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfflinePosEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $pos = PosSetting::first();

        if ($pos && Schema::hasColumn('pos_settings', 'offline_pos_enabled') && !$pos->offline_pos_enabled) {
            return response()->json([
                'status' => false,
                'message' => 'Offline POS is disabled from the main project settings.',
                'offline_pos_enabled' => false,
            ], 423);
        }

        return $next($request);
    }
}
