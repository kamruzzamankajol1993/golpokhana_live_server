<?php
namespace App\Http\Controllers\Admin\Accounting\PeriodClosing;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingCloseService,AccountingMoney,AccountingReportService,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** PeriodClosing accounting module. A shared base provides posting dependencies and invariant checks. */
final class PeriodClosingController extends \App\Http\Controllers\Admin\Accounting\Shared\AccountingClosingController
{
    public function closingReview(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $periods=DB::table('accounting_periods as p')->join('fiscal_years as f','f.id','=','p.fiscal_year_id')
            ->select('p.*','f.name as fiscal_year_name')->orderByDesc('p.starts_on')->get();
        $selected=$r->integer('period_id') ?: ($periods->first()->id??0);
        $period=$selected?DB::table('accounting_periods')->find($selected):null;
        $checks=$period?$this->closing->checks($period):[];
        $years=DB::table('fiscal_years')->orderByDesc('starts_on')->get();
        $runs=DB::table('accounting_closing_runs')->orderByDesc('id')->paginate(20, ['*'], 'acc_period_closing_2')->withQueryString();
        return view('admin.accounting.period-closing.index',compact('periods','period','checks','years','runs'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function yearEnd(Request $r,int $yearId)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-year-end');$this->can('accounting-journal-create');
        $r->validate(['confirmation'=>'required|in:CREATE_CLOSING_DRAFT']);
        $id=$this->closing->yearEndDraft($yearId,auth()->id());
        return redirect()->route('accounting.journals.show',$id)->with('success','Year-end transfer DRAFT only. Independent approval and POST are required.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function refreshYearEnd(Request $r,int $yearId)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-year-end');$this->can('accounting-journal-create');
        $d=$r->validate(['reason'=>'required|string|min:8|max:1000']);
        $id=$this->closing->refreshYearEndDraft($yearId,auth()->id(),$d['reason']);
        return redirect()->route('accounting.journals.show',$id)
            ->with('success','Year-end DRAFT reconciled to latest posted GL, with audit. Submit and approve again.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function finishYear(Request $r,int $yearId)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-year-end');
        $d=$r->validate(['reason'=>'required|string|min:8|max:1000']);
        DB::transaction(function() use($yearId,$d) {
            $year=DB::table('fiscal_years')->where('id',$yearId)->lockForUpdate()->first();
            if (!$year || $year->status==='CLOSED') throw ValidationException::withMessages(['year'=>'Fiscal year already closed or missing.']);
            $run=DB::table('accounting_closing_runs')->where('run_key','Y:'.$yearId)->first();
            if (!$run || !$run->journal_entry_id || !DB::table('journal_entries')->where('id',$run->journal_entry_id)->where('status','POSTED')->exists())
                throw ValidationException::withMessages(['year'=>'Year-end retained earnings journal is not POSTED.']);
            if (DB::table('accounting_periods')->where('fiscal_year_id',$yearId)->where('status','!=','CLOSED')->exists())
                throw ValidationException::withMessages(['year'=>'Every month must pass checklist and be CLOSED.']);
            DB::table('fiscal_years')->where('id',$yearId)->update(['status'=>'CLOSED','updated_at'=>now()]);
            DB::table('accounting_closing_runs')->where('id',$run->id)->update(['status'=>'CLOSED',
                'approved_by'=>auth()->id(),'approved_at'=>now(),'review_note'=>$d['reason'],'updated_at'=>now()]);
            AccountingAuditService::record('fiscal_year',$yearId,'CLOSE',null,['status'=>'CLOSED'],$d['reason']);
        },3);
        return back()->with('success','Fiscal year marked CLOSED with posted year-end journal and closed monthly periods.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
