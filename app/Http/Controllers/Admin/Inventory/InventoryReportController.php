<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryReportService;
use App\Services\Inventory\InventorySiteContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class InventoryReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:inventory-reports-view');
    }

    public function index(Request $request, InventorySiteContext $site, InventoryReportService $reports)
    {
        $site->ensureDefaultLocations();
        $rows = $reports->stockRows([
            'location_type' => $request->location_type,
            'state' => $request->state,
            'search' => $request->search,
        ]);
        $rows->appends($request->query());

        return view('admin.inventory.reports.index', [
            'summary' => $reports->overviewSummary(),
            'rows' => $rows,
        ]);
    }

    public function usage(Request $request, InventorySiteContext $site, InventoryReportService $reports)
    {
        $site->ensureDefaultLocations();
        [$start, $end] = $reports->resolveDateRange($request->date_from, $request->date_to, 30);
        $rows = $reports->usageRows($start, $end);
        $rows->appends($request->query());

        return view('admin.inventory.reports.usage', [
            'rows' => $rows,
            'foodRows' => $reports->foodUsageRows($start, $end),
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function requestVariance(Request $request, InventorySiteContext $site, InventoryReportService $reports)
    {
        $site->ensureDefaultLocations();
        [$start, $end] = $reports->resolveDateRange($request->date_from, $request->date_to, 30);
        $rows = $reports->requestVarianceRows($start, $end);
        $rows->appends($request->query());

        return view('admin.inventory.reports.request_variance', compact('rows', 'start', 'end') + [
        ]);
    }

    public function reconciliation(Request $request, InventorySiteContext $site, InventoryReportService $reports)
    {
        $site->ensureDefaultLocations();
        [$start, $end] = $reports->resolveDateRange($request->date_from, $request->date_to, 1);
        $rows = $reports->reconciliationRows($start, $end);
        $rows->appends($request->query());

        return view('admin.inventory.reports.reconciliation', compact('rows', 'start', 'end') + [
        ]);
    }

    public function qa(InventorySiteContext $site, InventoryReportService $reports)
    {
        $site->ensureDefaultLocations();
        if (!auth()->user()?->hasRole('Super Admin')) {
            throw new AccessDeniedHttpException('Inventory rollout verification is restricted to Super Admin roles.');
        }

        $checks = $reports->qaChecks();
        return view('admin.inventory.reports.qa', [
            'checks' => $checks,
            'passed' => $checks->where('passed', true)->count(),
            'failed' => $checks->where('passed', false)->count(),
        ]);
    }
}
