<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\ArrayReportExport;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:report-sales-order-view');
    }

    private function filteredAuditQuery(Request $request, BranchContext $context)
    {
        $query = AuditLog::query()->with(['branch', 'user'])->latest('id');

        if ($context->branchId()) {
            $query->where('branch_id', $context->branchId());
        } elseif (!$request->user()?->isSuperAdmin()) {
            abort(403);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . trim((string) $request->action) . '%');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }
        if ($request->filled('start_date')) {
            try { $query->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay()); }
            catch (\Throwable $e) { $this->logHandledException($e, __METHOD__); }
        }
        if ($request->filled('end_date')) {
            try { $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay()); }
            catch (\Throwable $e) { $this->logHandledException($e, __METHOD__); }
        }

        return $query;
    }

    public function index(Request $request, BranchContext $context)
    {
        $logs = $this->filteredAuditQuery($request, $context)->paginate(30)->withQueryString();

        // Actor filters come from the audit history itself rather than the user's current
        // branch. This preserves transferred users and global Super Admin actors in history.
        $auditUserIds = AuditLog::query()->whereNotNull('user_id');
        if ($context->branchId()) {
            $auditUserIds->where('branch_id', $context->branchId());
        }
        $auditUserIds = $auditUserIds->distinct()->pluck('user_id')->filter()->all();
        $usersQuery = User::query()->withoutGlobalScopes()->whereIn('id', $auditUserIds)->orderBy('name');

        return view('admin.reports.audit_logs', [
            'logs' => $logs,
            'auditUsers' => $usersQuery->get(['id', 'name', 'user_id']),
            'auditScopeLabel' => $context->isAllBranches()
                ? 'All Branches'
                : (Branch::query()->whereKey($context->branchId())->value('name') ?: 'Selected Branch'),
        ]);
    }

    private function auditExportData(Request $request, BranchContext $context): array
    {
        $logs = $this->filteredAuditQuery($request, $context)->get();
        $headings = ['Time', 'Branch', 'User', 'Action', 'Description', 'Changes', 'Route / Method', 'IP'];
        $rows = $logs->map(function ($log) {
            $changes = [];
            $before = $log->before_values ?: [];
            $after = $log->after_values ?: [];
            $keys = collect(array_keys($before))->merge(array_keys($after))->unique()->values();
            foreach ($keys as $key) {
                $from = $before[$key] ?? null;
                $to = $after[$key] ?? null;
                $from = is_scalar($from) || is_null($from) ? ($from ?? 'empty') : json_encode($from, JSON_UNESCAPED_UNICODE);
                $to = is_scalar($to) || is_null($to) ? ($to ?? 'empty') : json_encode($to, JSON_UNESCAPED_UNICODE);
                $changes[] = $key . ': ' . $from . ' -> ' . $to;
            }
            return [
                optional($log->created_at)->format('d M Y, h:i:s A'),
                optional($log->branch)->name ?? ($log->branch_id ? 'Branch #' . $log->branch_id : 'Global'),
                optional($log->user)->name ?? ($log->user_id ? 'User #' . $log->user_id : 'System / Public'),
                $log->action,
                $log->description ?: 'N/A',
                implode("\n", $changes) ?: 'N/A',
                $log->route_name ?: $log->http_method,
                $log->ip_address ?: 'N/A',
            ];
        })->all();

        $scopeLabel = $context->isAllBranches()
            ? 'All Branches'
            : (Branch::query()->whereKey($context->branchId())->value('name') ?: 'Selected Branch');
        $meta = [
            'Scope' => $scopeLabel,
            'Action' => $request->filled('action') ? trim((string) $request->action) : 'All',
            'Period' => ($request->start_date ?: 'Beginning') . ' - ' . ($request->end_date ?: 'Today'),
        ];

        return [$headings, $rows, $meta];
    }

    public function exportExcel(Request $request, BranchContext $context)
    {
        [$headings, $rows] = $this->auditExportData($request, $context);
        return Excel::download(new ArrayReportExport($headings, $rows, 'Audit Log'), 'audit-log-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportPdf(Request $request, BranchContext $context)
    {
        [$headings, $rows, $meta] = $this->auditExportData($request, $context);
        $restaurant = app(\App\Services\BranchSettingResolver::class)->get(\App\Models\RestaurantSetting::class);
        $html = view('admin.reports.simple_table_pdf', [
            'title' => 'Audit Log Report',
            'subtitle' => $restaurant->restaurant_name ?? $restaurant->name ?? 'Restaurant',
            'headings' => $headings,
            'rows' => $rows,
            'meta' => $meta,
        ])->render();

        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);
        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0775, true);
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A3', 'orientation' => 'L',
            'margin_left' => 6, 'margin_right' => 6, 'margin_top' => 8, 'margin_bottom' => 8,
            'tempDir' => $tempDir, 'autoScriptToLang' => true, 'autoLangToFont' => true,
        ]);
        $fileName = 'audit-log-' . now()->format('Y-m-d-His') . '.pdf';
        $mpdf->SetTitle($fileName);
        $mpdf->WriteHTML($html);
        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

}
