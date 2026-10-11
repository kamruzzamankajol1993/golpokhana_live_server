<?php
namespace App\Http\Controllers\Admin\Accounting\FinancialDashboard;

use App\Services\Accounting\AccountingMoney;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Read-only company financial dashboard, sourced solely from POSTED ledger entries. */
final class FinancialDashboardController extends \App\Http\Controllers\Admin\Accounting\Shared\AccountingReportsController
{
    public function dashboard(Request $request)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        // The former Accounting Overview was accessible to accounting-view users.
        // Preserve that entry-point permission while moving to a real financial dashboard.
        abort_unless(auth()->user()?->can('accounting-view'), 403);

        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        if ($from > $to || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 740) {
            throw ValidationException::withMessages(['dates' => 'Choose a valid period of up to 24 months.']);
        }

        $pnl = $this->service->pnl($from, $to);
        $bs = $this->service->balanceSheet($to);
        $tb = $this->service->trialBalance($to);
        $aging = $this->service->aging($to);
        $settings = DB::table('accounting_settings')->find(1);
        $cutover = DB::table('accounting_cutovers')->where('status', 'ACTIVE')->first();
        $postedCount = DB::table('journal_entries')->where('status', 'POSTED')->where('entry_date', '<=', $to)->count();
        $periodPosted = DB::table('journal_entries')->where('status', 'POSTED')->whereBetween('entry_date', [$from, $to])->count();
        $counts = [
            'pending' => DB::table('accounting_events')->whereNotIn('status', ['POSTED', 'CANCELLED'])->count(),
            'draft' => DB::table('journal_entries')->whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED'])->count(),
            'negative' => DB::table('negative_inventory_cost_pending')->whereNotIn('status', ['POSTED', 'CANCELLED'])->count(),
            'bank' => DB::table('bank_statement_lines')->where('status', '!=', 'MATCHED')->count(),
            'cash' => DB::table('cash_reconciliations')->where('status', '!=', 'APPROVED')->count(),
        ];

        $checks = [
            ['label' => 'Accounting Capture', 'ready' => (bool) ($settings?->capture_enabled ?? false),
                'message' => $settings?->capture_enabled ? 'Enabled' : 'OFF — automatic events are not saved',
                'route' => 'accounting.settings', 'permission' => 'accounting-settings'],
            ['label' => 'Posting Period', 'ready' => DB::table('accounting_periods')->where('status', 'OPEN')->exists(),
                'message' => 'Requires an open fiscal period', 'route' => 'accounting.periods', 'permission' => 'accounting-view'],
            ['label' => 'Source Mappings', 'ready' => DB::table('account_mappings')->where('is_active', true)->exists(),
                'message' => 'Requires approved active mappings', 'route' => 'accounting.mappings', 'permission' => 'accounting-mapping'],
            ['label' => 'Opening Cutover', 'ready' => (bool) $cutover,
                'message' => 'Requires accountant-approved cutover', 'route' => 'accounting.cutover', 'permission' => 'accounting-cutover'],
        ];
        $checksReady = count(array_filter($checks, fn (array $check) => $check['ready']));
        $ledgerBalanced = $postedCount > 0 && $tb['debits'] === $tb['credits'] && $bs['difference'] === 0;

        // Six complete calendar-month buckets ending in the selected "through" month.
        // Use chart_of_accounts types and the same POSTED-only, non-closing P&L rules as statements.
        $endMonth = Carbon::parse($to)->startOfMonth();
        $chartStart = $endMonth->copy()->subMonths(5);
        $trend = [];
        for ($offset = 0; $offset < 6; $offset++) {
            $month = $chartStart->copy()->addMonths($offset);
            $trend[$month->format('Y-m')] = [
                'key' => $month->format('Y-m'), 'label' => $month->format('M y'),
                'revenue' => 0, 'profit' => 0,
            ];
        }
        $monthly = DB::table('journal_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.status', 'POSTED')
            ->where('j.origin', '!=', 'CLOSING')
            ->whereBetween('j.entry_date', [$chartStart->toDateString(), $to])
            ->whereIn('a.type', ['income', 'contra_revenue', 'cogs', 'expense'])
            ->selectRaw("DATE_FORMAT(j.entry_date, '%Y-%m') as entry_month, a.type, SUM(l.debit) as dr, SUM(l.credit) as cr")
            ->groupByRaw("DATE_FORMAT(j.entry_date, '%Y-%m'), a.type")
            ->get();
        foreach ($monthly as $row) {
            if (!isset($trend[$row->entry_month])) continue;
            $dr = AccountingMoney::units((string) $row->dr);
            $cr = AccountingMoney::units((string) $row->cr);
            $income = $row->type === 'income' ? $cr - $dr : 0;
            $cost = $row->type !== 'income' ? $dr - $cr : 0;
            $trend[$row->entry_month]['revenue'] += $row->type === 'contra_revenue' ? -$cost : $income;
            $trend[$row->entry_month]['profit'] += $income - $cost;
        }
        $trend = array_values($trend);
        $trendScale = max(1, ...array_map(fn ($point) => max(0, $point['revenue'], $point['profit']), $trend));
        $hasTrend = (bool) array_sum(array_map(fn ($point) => abs($point['revenue']) + abs($point['profit']), $trend));

        $recentJournals = DB::table('journal_entries as j')
            ->join('accounting_journals as a', 'a.id', '=', 'j.journal_id')
            ->leftJoin('journal_lines as l', 'l.journal_entry_id', '=', 'j.id')
            ->where('j.status', 'POSTED')->whereBetween('j.entry_date', [$from, $to])
            ->select('j.id', 'j.number', 'j.entry_date', 'j.narration', 'a.name as journal_name')
            ->selectRaw('COALESCE(SUM(l.debit),0) as debit_total')
            ->groupBy('j.id', 'j.number', 'j.entry_date', 'j.narration', 'a.name')
            ->orderByDesc('j.entry_date')->orderByDesc('j.id')->limit(6)->get();

        return view('admin.accounting.financial-dashboard.index', compact(
            'from', 'to', 'pnl', 'bs', 'tb', 'aging', 'settings', 'cutover', 'postedCount',
            'periodPosted', 'counts', 'checks', 'checksReady', 'ledgerBalanced', 'trend',
            'trendScale', 'hasTrend', 'recentJournals'
        ));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
