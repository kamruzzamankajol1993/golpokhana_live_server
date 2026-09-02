<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchModeManager;
use App\Services\AuditLogger;
use App\Services\SuperAdminPosSessionManager;
use App\Support\BranchContext;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    public function switch(
        Request $request,
        BranchModeManager $mode,
        AuditLogger $audit,
        BranchContext $branchContext,
        SuperAdminPosSessionManager $posSessions
    )
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        if ($mode->isSingleMode()) {
            $mainBranchId = (int) $mode->mainBranchId();
            $previousBranchId = $request->session()->get('active_branch_id');

            if ((int) $previousBranchId !== $mainBranchId) {
                $posSessions->closeAllForUser($request->user(), 'super_admin_session.branch_changed');
            }

            $request->session()->put('active_branch_id', $mainBranchId);
            $branchContext->resolve($request);

            return back()->with('success', 'Single branch mode is active. Main Branch is selected.');
        }

        $request->validate([
            'branch_id' => ['required'],
        ]);

        $previousBranchId = $request->session()->get('active_branch_id');

        if ($request->branch_id === 'all') {
            // All Branches is a reporting context, so a Super Admin must not keep
            // a branch-specific cash session open after leaving that branch.
            $posSessions->closeAllForUser($request->user(), 'super_admin_session.branch_changed');
            $request->session()->forget('active_branch_id');
            $branchContext->resolve($request);
            $audit->log(
                action: 'branch_context.switched',
                branchId: null,
                description: 'Super Admin switched report/workspace context to All Branches',
                before: ['active_branch_id' => $previousBranchId],
                after: ['active_branch_id' => null],
                metadata: ['scope' => 'all']
            );

            return back()->with('success', 'All Branches view selected.');
        }

        $branch = Branch::query()->active()->findOrFail((int) $request->branch_id);

        // Only a real branch change ends the old session. Selecting the same
        // branch again keeps its current running session and simply ensures one exists.
        if ((int) $previousBranchId !== (int) $branch->id) {
            $posSessions->closeAllForUser($request->user(), 'super_admin_session.branch_changed');
        }

        $request->session()->put('active_branch_id', $branch->id);
        $branchContext->resolve($request);

        $audit->log(
            action: 'branch_context.switched',
            branchId: (int) $branch->id,
            description: 'Super Admin switched context to ' . $branch->name,
            before: ['active_branch_id' => $previousBranchId],
            after: ['active_branch_id' => (int) $branch->id],
            auditableType: Branch::class,
            auditableId: (int) $branch->id,
            metadata: ['scope' => 'branch', 'branch_name' => $branch->name]
        );

        return back()->with('success', $branch->name . ' selected.');
    }
}
