<?php
namespace App\Services\Accounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountingCloseService
{
    public function __construct(private AccountingReportService $reports, private JournalPostingService $posting) {}

    /** Live checklist. No GL writes. A failed hard close is never silently approved. */
    public function checks(object $period): array
    {
        $start=(string)$period->starts_on; $end=(string)$period->ends_on;
        $checks=[];
        $add=function(string $key,string $severity,int $count,string $description) use (&$checks): void {
            $checks[]=['key'=>$key,'severity'=>$severity,'status'=>$count===0?'PASS':'FAIL',
                'count'=>$count,'summary'=>$description];
        };
        $tb=$this->reports->trialBalance($end);
        $add('trial_balance','BLOCK',abs($tb['debits']-$tb['credits']), 'Posted GL debit and credit must balance (BDT 0.0001 units).');
        $balance=$this->reports->balanceSheet($end);
        $add('balance_sheet','BLOCK',abs($balance['difference']), 'Balance Sheet must satisfy Assets = Liabilities + Equity + Earnings.');
        $unposted=(int)DB::table('journal_entries')->whereBetween('entry_date',[$start,$end])->where('status','!=','POSTED')->count();
        $add('unposted_journals','BLOCK',$unposted,'All journals in the period must be posted or formally rejected before hard close.');
        $events=(int)DB::table('accounting_events')->where('occurred_at','>=',$start)
            ->where('occurred_at','<',Carbon::parse($end)->addDay()->toDateTimeString())->where('status','!=','POSTED')->count();
        $add('pending_events','BLOCK',$events,'Pending, mapping-error or draft accounting source events.');
        $negative=(int)DB::table('negative_inventory_cost_pending as c')->join('stock_movements as m','m.id','=','c.stock_movement_id')
            ->where('m.occurred_at','>=',$start)->where('m.occurred_at','<',Carbon::parse($end)->addDay()->toDateTimeString())
            ->where('c.status','!=','POSTED')->count();
        $add('negative_cost','BLOCK',$negative,'Unverified negative inventory cost is not posted COGS.');
        $unmatched=(int)DB::table('bank_statement_lines')->whereBetween('statement_date',[$start,$end])->where('status','!=','MATCHED')->count();
        $add('bank_reconciliation','BLOCK',$unmatched,'Unmatched bank statement entries require review.');
        $cash=(int)DB::table('cash_reconciliations as r')->join('pos_sessions as s','s.id','=','r.pos_session_id')
            ->whereDate('s.end_time','>=',$start)->whereDate('s.end_time','<=',$end)
            ->where('r.status','!=','APPROVED')->count();
        $add('cash_reconciliation','BLOCK',$cash,'POS cash reconciliation not approved.');
        $signed=(int)DB::table('accounting_cutovers')->where('status','ACTIVE')->count();
        $add('cutover','BLOCK',$signed ? 0 : 1,'Approved opening balance and cutover required before hard close.');
        $aging=$this->reports->aging($end);
        $balances=$this->reports->totals(null,$end);
        foreach (['AR'=>['1200','asset'],'AP'=>['2100','liability']] as $side=>[$code,$type]) {
            $account=collect($balances)->firstWhere('code',$code);
            $posted=$account ? ($type==='asset'?$account['dr']-$account['cr']:$account['cr']-$account['dr']) : 0;
            $variance=abs($posted-$aging[$side]['total']);
            $add(strtolower($side).'_reconcile','BLOCK',$variance, $side.' posted control account must agree with posted-source aged outstanding.');
        }
        $cashflow=$this->reports->cashFlow($start,$end);
        $add('unclassified_cash_flow','WARNING',$cashflow['unclassified'],'Unclassified cash movements require mapping before final cash flow publication.');
        $captured=DB::table('accounting_settings')->where('id',1)->first();
        $add('capture_state','WARNING',!$captured || !$captured->capture_enabled ? 1 : 0,
            'Accounting Capture is OFF; automatic operational events are absent for OFF windows.');
        return $checks;
    }

    public function changePeriod(int $id,string $status,string $reason,?int $actor): void
    {
        DB::transaction(function() use($id,$status,$reason,$actor) {
            $period=DB::table('accounting_periods')->where('id',$id)->lockForUpdate()->first();
            if (!$period) throw ValidationException::withMessages(['period'=>'Period not found.']);
            if ($period->status===$status) return;
            $year=DB::table('fiscal_years')->where('id',$period->fiscal_year_id)->first();
            if ($year && $year->status==='CLOSED')
                throw ValidationException::withMessages(['period'=>'Fiscal year is CLOSED; reopening requires a separate controlled year-reopen policy.']);
            if ($period->status==='CLOSED' && $status!=='CLOSED') {
                abort_unless(auth()->user()?->can('accounting-reopen'),403);
            } elseif ($status==='CLOSED') {
                abort_unless(auth()->user()?->can('accounting-close'),403);
                $checks=$this->checks($period);
                $failed=array_values(array_filter($checks,fn($c)=>$c['severity']==='BLOCK' && $c['status']==='FAIL'));
                if ($failed) throw ValidationException::withMessages(['closing'=>implode(' ',array_map(fn($c)=>$c['key'].': '.$c['summary'].' ('.$c['count'].')',$failed))]);
                $this->recordReview($period,$checks,$actor,$reason);
            }
            DB::table('accounting_periods')->where('id',$id)->update(['status'=>$status,'updated_at'=>now()]);
            DB::table('period_close_logs')->insert(['accounting_period_id'=>$id,'old_status'=>$period->status,
                'new_status'=>$status,'reason'=>$reason,'user_id'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('period',$id,'CHANGE_STATUS',
                ['status'=>$period->status],['status'=>$status],$reason);
        },3);
    }

    private function recordReview(object $period,array $checks,?int $actor,string $note): void
    {
        $key='P:'.$period->id;
        DB::table('accounting_closing_runs')->updateOrInsert(['run_key'=>$key],['fiscal_year_id'=>$period->fiscal_year_id,
            'accounting_period_id'=>$period->id,'run_type'=>'MONTH_END','status'=>'CLOSED',
            'reviewed_by'=>$actor,'approved_by'=>$actor,'approved_at'=>now(),'review_note'=>$note,
            'updated_at'=>now(),'created_at'=>now()]);
        $run=DB::table('accounting_closing_runs')->where('run_key',$key)->first();
        foreach ($checks as $check) DB::table('accounting_closing_check_items')->updateOrInsert([
            'closing_run_id'=>$run->id,'check_key'=>$check['key']],['severity'=>$check['severity'],
            'status'=>$check['status'],'summary'=>$check['summary'],'detail'=>'Count/variance: '.$check['count'],
            'updated_at'=>now(),'created_at'=>now()]);
    }

    /** Refresh a still-DRAFT closing journal after legitimate late posted activity.
     * Never changes APPROVED/POSTED journals; audited, permissioned, explicit action only.
     */
    public function refreshYearEndDraft(int $yearId,?int $actor,string $reason): int
    {
        return DB::transaction(function() use($yearId,$actor,$reason) {
            $run=DB::table('accounting_closing_runs')->where('run_key','Y:'.$yearId)->lockForUpdate()->first();
            if (!$run || $run->status!=='DRAFT' || !$run->journal_entry_id)
                throw ValidationException::withMessages(['year'=>'No year-end DRAFT registered for refresh.']);
            $journal=DB::table('journal_entries')->where('id',$run->journal_entry_id)->lockForUpdate()->first();
            if (!$journal || $journal->status!=='DRAFT' || $journal->origin!=='CLOSING')
                throw ValidationException::withMessages(['year'=>'Only an unsubmitted closing DRAFT can be refreshed. Reject submitted drafts before refreshing.']);
            $year=DB::table('fiscal_years')->find($yearId);
            $newLines=[];$net=0;
            foreach($this->reports->totals((string)$year->starts_on,(string)$year->ends_on,true) as $a) {
                if ($a['is_header'] || !in_array($a['type'],['income','contra_revenue','cogs','expense'],true)) continue;
                $value=$a['dr']-$a['cr'];if (!$value) continue;
                $net+=$value;
                $newLines[]=['account_id'=>$a['id'],'debit'=>$this->reports->fmt(max(0,-$value)),
                    'credit'=>$this->reports->fmt(max(0,$value))];
            }
            if ($net!==0) {
                $retained=DB::table('chart_of_accounts')->where('code','3200')->value('id');
                if (!$retained) throw ValidationException::withMessages(['year'=>'Retained Earnings account missing.']);
                $newLines[]=['account_id'=>$retained,'debit'=>$this->reports->fmt(max(0,$net)),
                    'credit'=>$this->reports->fmt(max(0,-$net))];
            }
            $this->posting->assertLines($newLines);
            $old=DB::table('journal_lines')->where('journal_entry_id',$journal->id)->get()->toArray();
            DB::table('journal_lines')->where('journal_entry_id',$journal->id)->delete();
            foreach($newLines as $i=>$line) DB::table('journal_lines')->insert([
                'journal_entry_id'=>$journal->id,'account_id'=>$line['account_id'],'sort_order'=>$i+1,
                'debit'=>$line['debit'],'credit'=>$line['credit'],
                'created_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('year_end',$journal->id,'REFRESH_DRAFT',$old,$newLines,$reason);
            return (int)$journal->id;
        },3);
    }

    public function yearEndDraft(int $yearId,?int $actor): int
    {
        return DB::transaction(function() use($yearId,$actor) {
            $year=DB::table('fiscal_years')->where('id',$yearId)->lockForUpdate()->first();
            if (!$year) throw ValidationException::withMessages(['year'=>'Fiscal year not found.']);
            $key='Y:'.$year->id;
            if (DB::table('accounting_closing_runs')->where('run_key',$key)->exists())
                throw ValidationException::withMessages(['year'=>'A year-end closing journal already exists; do not repeat it.']);
            $last=DB::table('accounting_periods')->where('fiscal_year_id',$yearId)->orderByDesc('ends_on')->first();
            if (!$last || $last->status!=='OPEN' || $last->ends_on!=$year->ends_on)
                throw ValidationException::withMessages(['year'=>'Final fiscal period must be OPEN and cover fiscal year end.']);
            $closingAccounts=[]; $net=0;
            foreach ($this->reports->totals($year->starts_on,$year->ends_on,true) as $a) {
                if ($a['is_header'] || !in_array($a['type'],['income','contra_revenue','cogs','expense'],true)) continue;
                $movement=$a['dr']-$a['cr']; if ($movement===0) continue;
                $net+=$movement;
                $closingAccounts[]=['account_id'=>$a['id'],'debit'=>$this->reports->fmt(max(0,-$movement)),
                    'credit'=>$this->reports->fmt(max(0,$movement))];
            }
            if (!$closingAccounts) throw ValidationException::withMessages(['year'=>'No revenue or expense balances to close.']);
            
            $retained=DB::table('chart_of_accounts')->where('code','3200')->where('is_active',true)->where('is_header',false)->value('id');
            $journal=DB::table('accounting_journals')->where('code','ADJ')->where('is_active',true)->value('id');
            if (!$retained || !$journal) throw ValidationException::withMessages(['year'=>'Retained Earnings or Adjustment Journal not configured.']);
            if ($net!==0) $closingAccounts[]=['account_id'=>$retained,'debit'=>$this->reports->fmt(max(0,$net)),
                'credit'=>$this->reports->fmt(max(0,-$net))];
            $id=$this->posting->createDraft(['journal_id'=>$journal,'entry_date'=>$year->ends_on,
                'origin'=>'CLOSING','reference'=>'YEAR_END:'.$yearId,
                'narration'=>'Fiscal year '.$year->name.' profit transfer to retained earnings; requires independent approval',
                'created_by'=>$actor],$closingAccounts);
            DB::table('accounting_closing_runs')->insert(['run_key'=>$key,'fiscal_year_id'=>$yearId,
                'accounting_period_id'=>$last->id,'run_type'=>'YEAR_END','status'=>'DRAFT',
                'journal_entry_id'=>$id,'reviewed_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
    }
}
