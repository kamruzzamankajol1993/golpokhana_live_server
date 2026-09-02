<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;

class BlockAllBranchWrites
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(BranchContext::class);

        if (!$context->isAllBranches() || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Explicit global/control-plane writes that are safe while viewing all branches.
        $allowed = [
            'branch.context.switch',
            'branches.*',
            'user.*',
            'hr.employees.transfer.*',
            'role.*',
            'permission.*',
            'profile.*',
            'customer.*',
            // Units and ingredients are global inventory masters in the Branch blueprint.
            'inventory.units.*',
            'inventory.ingredients.*',
            // Safe record-targeted notification actions. The controllers derive the
            // target branch from the Order/Waiter Call record and temporarily enter
            // that branch context; the request cannot choose branch_id itself.
            'notifications.accept_order',
            'notifications.resolve_waiter',
        ];

        foreach ($allowed as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        // Menu masters can be intentionally shared to several branches. Their
        // controllers validate branch_ids/all_branches and derive every branch-owned
        // copy server-side, so these record-targeted writes are safe in All Branches.
        if ($request->user()?->isSuperAdmin() && $request->routeIs(
            'food-item.*',
            'food-category.*',
            'cuisine-type.*',
            'allergen.*',
            'course-type.*'
        )) {
            return $next($request);
        }

        // A Super Admin may keep All Branches selected and submit a branch-owned
        // form by explicitly choosing one active branch inside that form. This is
        // intentionally different from POS/Kitchen workspace switching: the header
        // context remains All Branches and only this write is targeted.
        if ($request->user()?->isSuperAdmin() && $this->supportsFormTarget($request) && $request->filled('branch_id')) {
            $rawBranchId = (string) $request->input('branch_id');
            if (ctype_digit($rawBranchId)) {
                $targetBranchId = (int) $rawBranchId;
                if (Branch::query()->active()->whereKey($targetBranchId)->exists()) {
                    return $next($request);
                }
            }

            $message = 'The selected form branch is invalid or inactive.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }
            return back()->withErrors(['branch_id' => $message])->withInput();
        }

        $message = 'All Branches is active. Select a branch inside this form before saving branch-owned data.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message);
    }

    private function supportsFormTarget(Request $request): bool
    {
        return $request->routeIs(
            'food-item.*',
            'food-category.*',
            'cuisine-type.*',
            'allergen.*',
            'course-type.*',
            'waiter.*',
            'zone.*',
            'floor-zone.*',
            'delivery-partner.*',
            'shift.*',
            'table.*',
            'table-booking.*',
            'occasion.*',
            'settings.*',
            'reward-points.*',
            'hr.*',
            'offline-pos-devices.*',
            'inventory.stock.opening.*'
        );
    }
}
