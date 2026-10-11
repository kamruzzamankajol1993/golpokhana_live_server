<?php
namespace App\Http\Controllers\Admin\Accounting\Budgets;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingCloseService,AccountingMoney,AccountingReportService,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Budgets accounting module. A shared base provides posting dependencies and invariant checks. */
final class BudgetsController extends \App\Http\Controllers\Admin\Accounting\Shared\AccountingClosingController
{
    public function budgets(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $years=DB::table('fiscal_years')->orderByDesc('starts_on')->get();
        $budgets=DB::table('accounting_budgets as b')->join('fiscal_years as y','y.id','=','b.fiscal_year_id')
            ->select('b.*','y.name as year_name')->orderByDesc('b.id')->get();
        $selected=$r->integer('budget_id') ?: ($budgets->first()->id??null);
        $budget=$selected?DB::table('accounting_budgets')->find($selected):null;
        $periods=$budget?DB::table('accounting_periods')->where('fiscal_year_id',$budget->fiscal_year_id)->orderBy('starts_on')->get():collect();
        $accounts=DB::table('chart_of_accounts')->where('is_active',true)->where('is_header',false)
            ->whereIn('type',['income','contra_revenue','cogs','expense'])->orderBy('code')->get();
        $centers=DB::table('cost_centers')->where('is_active',true)->get();
        $lines=$budget?DB::table('accounting_budget_lines as b')->join('chart_of_accounts as a','a.id','=','b.account_id')
            ->join('accounting_periods as p','p.id','=','b.period_id')->leftJoin('cost_centers as c','c.id','=','b.cost_center_id')
            ->where('b.budget_id',$budget->id)->select('b.*','a.code','a.name as account_name',
                'p.name as period_name','p.starts_on','p.ends_on','c.name as center_name','a.type')->orderBy('p.starts_on')->orderBy('a.code')->get():collect();
        $rows=[];$budgetTotal=0;$actualTotal=0;
        foreach($lines as $line) {
            $actual=$this->budgetActual($line);
            $target=$this->units($line->amount);
            $budgetTotal+=$target;$actualTotal+=$actual;
            $rows[]=['line'=>$line,'actual'=>$actual,'target'=>$target,'variance'=>$actual-$target];
        }
        return view('admin.accounting.budgets.index',compact('years','budgets','budget','periods','accounts','centers','rows','budgetTotal','actualTotal'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function createBudget(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-budget');
        $d=$r->validate(['fiscal_year_id'=>'required|exists:fiscal_years,id',
            'name'=>'required|string|min:3|max:150']);
        $id=DB::table('accounting_budgets')->insertGetId(['fiscal_year_id'=>$d['fiscal_year_id'],
            'name'=>$d['name'],'status'=>'DRAFT','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        return redirect()->route('accounting.budgets',['budget_id'=>$id])->with('success','Budget draft created.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveBudgetLine(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-budget');
        $d=$r->validate(['period_id'=>'required|exists:accounting_periods,id','account_id'=>'required|exists:chart_of_accounts,id',
            'cost_center_id'=>'nullable|exists:cost_centers,id','amount'=>'required|numeric|min:0',
            'notes'=>'nullable|string|max:500']);
        DB::transaction(function() use($d,$id) {
            $budget=DB::table('accounting_budgets')->where('id',$id)->lockForUpdate()->first();
            if (!$budget || $budget->status!=='DRAFT') throw ValidationException::withMessages(['budget'=>'Only DRAFT budget can change.']);
            $period=DB::table('accounting_periods')->find($d['period_id']);
            $account=DB::table('chart_of_accounts')->find($d['account_id']);
            if ($period->fiscal_year_id!=$budget->fiscal_year_id || !$account || !$account->is_active || $account->is_header
                || !in_array($account->type,['income','contra_revenue','cogs','expense'],true))
                throw ValidationException::withMessages(['budget'=>'Use matching fiscal period and active P&L account.']);
            $center=$d['cost_center_id']??null;
            $match=DB::table('accounting_budget_lines')->where('budget_id',$id)
                ->where('period_id',$d['period_id'])->where('account_id',$d['account_id']);
            $center===null ? $match->whereNull('cost_center_id') : $match->where('cost_center_id',$center);
            if ($match->exists()) throw ValidationException::withMessages(['budget'=>'A budget line for this period, account and cost center already exists.']);
            DB::table('accounting_budget_lines')->insert(['budget_id'=>$id,'period_id'=>$d['period_id'],
                'account_id'=>$d['account_id'],'cost_center_id'=>$center,
                'amount'=>AccountingMoney::format($this->units($d['amount'])),'notes'=>$d['notes']??null,
                'created_at'=>now(),'updated_at'=>now()]);
        },3);
        return back()->with('success','Budget line saved.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function approveBudget(int $id,Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-budget-approve');
        $d=$r->validate(['reason'=>'required|string|min:8|max:500']);
        DB::transaction(function() use($id,$d) {
            $budget=DB::table('accounting_budgets')->where('id',$id)->lockForUpdate()->first();
            if (!$budget || $budget->status!=='DRAFT') throw ValidationException::withMessages(['budget'=>'Budget must be DRAFT.']);
            if ((int)$budget->created_by===(int)auth()->id())
                throw ValidationException::withMessages(['budget'=>'Maker cannot approve their own budget.']);
            if (!DB::table('accounting_budget_lines')->where('budget_id',$id)->exists())
                throw ValidationException::withMessages(['budget'=>'Add budget lines before approval.']);
            DB::table('accounting_budgets')->where('id',$id)->update(['status'=>'APPROVED',
                'approved_by'=>auth()->id(),'approved_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('budget',$id,'APPROVE',null,['status'=>'APPROVED'],$d['reason']);
        },3);
        return back()->with('success','Budget approved and locked. Budget does not change actual GL.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function deleteBudget(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-budget');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->draftBudget($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
