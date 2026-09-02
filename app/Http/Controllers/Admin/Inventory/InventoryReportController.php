<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Inventory\InventoryReportService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class InventoryReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-reports-view');
    }

    public function index(Request $request, BranchContext $context, InventoryReportService $reports)
    {
        $branchId = $this->reportBranchId($context);
        $rows = $reports->stockRows($branchId, [
            'location_type' => $request->location_type,
            'state' => $request->state,
            'search' => $request->search,
        ]);
        $rows->appends($request->query());

        return view('admin.inventory.reports.index', [
            'summary' => $reports->overviewSummary($branchId),
            'rows' => $rows,
            'branches' => $this->branchesForFilter($context),
            'allBranches' => $branchId === null,
        ]);
    }

    public function usage(Request $request, BranchContext $context, InventoryReportService $reports)
    {
        $branchId = $this->reportBranchId($context);
        [$start, $end] = $reports->resolveDateRange($request->date_from, $request->date_to, 30);
        $rows = $reports->usageRows($branchId, $start, $end);
        $rows->appends($request->query());

        return view('admin.inventory.reports.usage', [
            'rows' => $rows,
            'foodRows' => $reports->foodUsageRows($branchId, $start, $end),
            'start' => $start,
            'end' => $end,
            'branches' => $this->branchesForFilter($context),
            'allBranches' => $branchId === null,
        ]);
    }

    public function requestVariance(Request $request, BranchContext $context, InventoryReportService $reports)
    {
        $branchId = $this->reportBranchId($context);
        [$start, $end] = $reports->resolveDateRange($request->date_from, $request->date_to, 30);
        $rows = $reports->requestVarianceRows($branchId, $start, $end);
        $rows->appends($request->query());

        return view('admin.inventory.reports.request_variance', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'branches' => $this->branchesForFilter($context),
            'allBranches' => $branchId === null,
        ]);
    }

    public function reconciliation(Request $request, BranchContext $context, InventoryReportService $reports)
    {
        $branchId = $this->reportBranchId($context);
        [$start, $end] = $reports->resolveDateRange($request->date_from, $request->date_to, 1);
        $rows = $reports->reconciliationRows($branchId, $start, $end);
        $rows->appends($request->query());

        return view('admin.inventory.reports.reconciliation', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'branches' => $this->branchesForFilter($context),
            'allBranches' => $branchId === null,
        ]);
    }

    public function branchComparison(Request $request, BranchContext $context, InventoryReportService $reports)
    {
        if ($context->user()?->isSuperAdmin()
            && $context->user()?->can('inventory-branch-all-view')
            && !$request->query->has('branch_id')) {
            return redirect()->route('reports.inventory.branch-comparison', ['branch_id' => 'all'] + $request->except('branch_id'));
        }

        $branchId = $this->reportBranchId($context);
        [$start, $end] = $reports->resolveDateRange($request->date_from, $request->date_to, 30);

        return view('admin.inventory.reports.branch_comparison', [
            'rows' => $reports->branchComparisonRows($start, $end, $branchId),
            'start' => $start,
            'end' => $end,
            'branches' => $this->branchesForFilter($context),
            'allBranches' => $branchId === null,
        ]);
    }

    public function qa(BranchContext $context, InventoryReportService $reports)
    {
        if (!$context->user()?->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Inventory rollout verification is restricted to Super Admin roles.');
        }

        $checks = $reports->qaChecks();

        return view('admin.inventory.reports.qa', [
            'checks' => $checks,
            'passed' => $checks->where('passed', true)->count(),
            'failed' => $checks->where('passed', false)->count(),
        ]);
    }

    private function reportBranchId(BranchContext $context): ?int
    {
        if ($context->isAllBranches() && !$context->user()?->can('inventory-branch-all-view')) {
            throw new AccessDeniedHttpException('Cross-branch inventory reports require inventory-branch-all-view permission.');
        }

        return $context->branchId();
    }

    private function branchesForFilter(BranchContext $context)
    {
        if ($context->user()?->isSuperAdmin() && $context->user()?->can('inventory-branch-all-view')) {
            return Branch::query()->active()->orderBy('name')->get(['id', 'name']);
        }

        return Branch::query()->active()->whereKey($context->branchId())->get(['id', 'name']);
    }
}
