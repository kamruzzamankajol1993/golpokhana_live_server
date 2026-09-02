<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BranchContext
{
    private bool $resolved = false;
    private bool $allBranches = false;
    private ?int $branchId = null;
    private ?User $user = null;

    public function resolve(Request $request): void
    {
        $user = $request->user();
        $this->user = $user;
        $this->resolved = true;

        if (!$user) {
            $this->allBranches = true;
            $this->branchId = null;
            return;
        }

        $mode = app(\App\Services\BranchModeManager::class);

        if ($mode->isSingleMode()) {
            $this->allBranches = false;
            $this->branchId = $mode->mainBranchId();
            return;
        }

        if ($user->isSuperAdmin()) {
            // Reports have their own transient branch filter. It never changes the
            // persistent header/session branch, so Super Admin and Super Admin Limited
            // can compare/report another branch from any header workspace.
            if ($request->routeIs('reports.*')) {
                $hasReportBranch = $request->query->has('branch_id');
                $requestedReportBranch = $request->query('branch_id');

                // Branch Performance is a comparison report; default it to all active
                // branches when no report-specific branch has been chosen yet.
                if (!$hasReportBranch && $request->routeIs('reports.branch_summary', 'reports.branch_summary.csv')) {
                    $this->allBranches = true;
                    $this->branchId = null;
                    return;
                }

                if ($hasReportBranch) {
                    if ($requestedReportBranch === null || $requestedReportBranch === '' || $requestedReportBranch === 'all') {
                        $this->allBranches = true;
                        $this->branchId = null;
                        return;
                    }

                    if (ctype_digit((string) $requestedReportBranch)) {
                        $reportBranch = Branch::query()->active()->find((int) $requestedReportBranch);
                        if ($reportBranch) {
                            $this->allBranches = false;
                            $this->branchId = (int) $reportBranch->id;
                            return;
                        }
                    }
                }
            }

            $selected = $request->session()->get('active_branch_id');

            if ($selected === null || $selected === 'all') {
                $this->allBranches = true;
                $this->branchId = null;
                return;
            }

            $branchId = (int) $selected;
            $branch = Branch::query()->active()->find($branchId);

            if (!$branch) {
                $request->session()->forget('active_branch_id');
                $this->allBranches = true;
                $this->branchId = null;
                return;
            }

            $this->allBranches = false;
            $this->branchId = $branch->id;
            return;
        }

        if (!$user->branch_id) {
            throw new AccessDeniedHttpException('No branch is assigned to this user.');
        }

        $branch = Branch::query()->active()->find($user->branch_id);
        if (!$branch) {
            throw new AccessDeniedHttpException('The assigned branch is inactive or unavailable.');
        }

        $this->allBranches = false;
        $this->branchId = $branch->id;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function isAllBranches(): bool
    {
        return $this->resolved && $this->allBranches;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function allowsBranch(?int $branchId): bool
    {
        if (!$this->resolved) {
            return true;
        }

        if ($branchId === null) {
            return false;
        }

        if ($this->user?->isSuperAdmin()) {
            return Branch::query()->active()->whereKey($branchId)->exists();
        }

        return $this->branchId === (int) $branchId;
    }

    /**
     * Standard branch filter for raw Query Builder queries that bypass Eloquent scopes.
     */
    public function scopeQuery($query, string $qualifiedBranchColumn = 'branch_id')
    {
        if ($this->resolved && $this->branchId !== null) {
            $query->where($qualifiedBranchColumn, $this->branchId);
        }

        return $query;
    }

    /**
     * Execute one server-targeted action inside a specific branch without changing
     * the user's persistent/session branch selection.
     *
     * Super Admin roles may target any active branch from any header workspace.
     * Non-Super Admin users remain locked to their assigned/current branch. The
     * previous context is always restored, even when the callback throws.
     */
    public function runForBranch(int $branchId, callable $callback)
    {
        if (!$this->resolved || !$this->user) {
            throw new AccessDeniedHttpException('An authenticated branch context is required.');
        }

        $branch = Branch::query()->active()->find($branchId);
        if (!$branch) {
            throw new AccessDeniedHttpException('The target branch is inactive or unavailable.');
        }

        if (!$this->user->isSuperAdmin() && $this->branchId !== $branchId) {
            throw new AccessDeniedHttpException('You cannot target another branch.');
        }

        $previousAllBranches = $this->allBranches;
        $previousBranchId = $this->branchId;

        $this->allBranches = false;
        $this->branchId = $branchId;

        try {
            return $callback();
        } finally {
            $this->allBranches = $previousAllBranches;
            $this->branchId = $previousBranchId;
        }
    }

    public function requireSpecificBranch(): int
    {
        if (!$this->resolved) {
            return app(\App\Services\BranchModeManager::class)->mainBranchId();
        }

        // Super Admin roles may target a branch explicitly from any header workspace
        // (All Branches, Main Branch, or another branch). This is request-scoped only.
        if ($this->user?->isSuperAdmin()) {
            $requested = request()->input('branch_id');
            if ($requested !== null && $requested !== '' && ctype_digit((string) $requested)) {
                $targetBranchId = (int) $requested;
                if (Branch::query()->active()->whereKey($targetBranchId)->exists()) {
                    return $targetBranchId;
                }
            }
        }

        if ($this->branchId === null) {
            throw new UnprocessableEntityHttpException(
                'Select a branch in this form before creating or changing branch-owned data.'
            );
        }

        return $this->branchId;
    }
}
