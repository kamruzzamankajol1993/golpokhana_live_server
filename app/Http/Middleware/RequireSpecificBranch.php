<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;

class RequireSpecificBranch
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(BranchContext::class);

        // Super Admin roles may explicitly target any active branch from a branch-owned
        // form, regardless of whether the header is on All Branches, Main Branch, or
        // another branch. Run only this request in that branch context.
        if ($request->user()?->isSuperAdmin() && $this->supportsFormTarget($request)) {
            $requested = $request->input('branch_id');
            if ($requested !== null && $requested !== '' && ctype_digit((string) $requested)) {
                $branchId = (int) $requested;
                if (!Branch::query()->active()->whereKey($branchId)->exists()) {
                    $message = 'The selected branch is invalid or inactive.';
                    return $request->expectsJson()
                        ? response()->json(['message' => $message], 422)
                        : back()->withErrors(['branch_id' => $message])->withInput();
                }

                if ($context->isAllBranches() || (int) $context->branchId() !== $branchId) {
                    return $context->runForBranch($branchId, fn () => $next($request));
                }
            }
        }

        if (!$context->isAllBranches()) {
            $context->requireSpecificBranch();
            return $next($request);
        }

        if ($request->user()?->isSuperAdmin()) {
            if ($this->isOperationalWorkspace($request) && in_array($request->method(), ['GET', 'HEAD'], true)) {
                $message = $this->operationalBlockMessage($request);

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }

                return redirect()->route('home')
                    ->with('blocked_module_title', 'Specific Branch Required')
                    ->with('blocked_module_message', $message);
            }

            $requested = $request->input('branch_id');
            if ($this->supportsFormTarget($request) && $requested !== null && $requested !== '' && ctype_digit((string) $requested)) {
                $branchId = (int) $requested;
                if (!Branch::query()->active()->whereKey($branchId)->exists()) {
                    $message = 'The selected branch is invalid or inactive.';
                    return $request->expectsJson()
                        ? response()->json(['message' => $message], 422)
                        : back()->withErrors(['branch_id' => $message])->withInput();
                }

                // Keep the persistent header selection on All Branches. Only the
                // current request runs under the form-selected branch context.
                return $context->runForBranch($branchId, fn () => $next($request));
            }

            // Complex HR screens need branch-scoped lookup data before their actual
            // create/edit forms can render. Present a branch selector gate while the
            // Super Admin remains in All Branches.
            if (in_array($request->method(), ['GET', 'HEAD'], true) && $request->routeIs(
                'hr.attendance.*',
                'hr.leaves.*',
                'hr.payroll.*',
                'hr.shifts.*',
                'hr.settings.*',
                'hr.employees.salary.*',
                'reward-points.*'
            )) {
                return response()->view('admin.branch.select_target', [
                    'moduleName' => $this->moduleName($request),
                    'targetUrl' => $request->url(),
                ]);
            }
        }

        $message = 'Select a branch in this form or switch the header to a specific branch before performing this action.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message);
    }



    private function isOperationalWorkspace(Request $request): bool
    {
        return $request->routeIs('pos.*', 'kitchen.*', 'qrcode.*');
    }

    private function operationalBlockMessage(Request $request): string
    {
        return match (true) {
            $request->routeIs('pos.*') => 'POS is a live transactional workspace. Select one specific branch from the header before opening POS so orders, tables, waiters and the Super Admin POS session cannot cross branches.',
            $request->routeIs('kitchen.*') => 'Kitchen is a live operational workspace. Select one specific branch from the header before opening Kitchen so KOTs from different branches are never mixed.',
            $request->routeIs('qrcode.*') => 'QR Code Builder is branch-specific because every QR code belongs to a table and branch. Select one specific branch from the header before opening this module.',
            default => 'This operational module is disabled while All Branches is selected. Select one specific branch from the header and try again.',
        };
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
            'reward-points.*',
            'offline-pos-devices.*',
            'settings.*',
            'user.*',
            'users.*',
            'hr.*',
            'inventory.stock.opening.*',
            'inventory.purchases.*',
            'inventory.kitchen-requests.*',
            'inventory.transfers.*'
        );
    }

    private function moduleName(Request $request): string
    {
        return match (true) {
            $request->routeIs('hr.attendance.*') => 'Attendance',
            $request->routeIs('hr.leaves.*') => 'Leave Management',
            $request->routeIs('hr.payroll.*') => 'Payroll',
            $request->routeIs('hr.shifts.*') => 'HR Shifts & Roster',
            $request->routeIs('hr.settings.*') => 'HR Settings',
            $request->routeIs('hr.employees.salary.*') => 'Employee Salary',
            $request->routeIs('reward-points.*') => 'Reward Point Settings',
            default => 'Branch Workspace',
        };
    }
}
