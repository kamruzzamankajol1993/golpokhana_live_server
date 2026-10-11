<?php
namespace App\Http\Controllers\Admin\Accounting\CompanyLoans;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingMoney,JournalPostingService,AccountingAuditService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit accountant scheduling: never silently auto-posts deferrals, asset depreciation or loan payments. */

/** CompanyLoans accounting module. A shared base provides posting dependencies and invariant checks. */
final class CompanyLoansController extends \App\Http\Controllers\Admin\Accounting\Shared\ExtendedSchedulesController
{
    public function loans()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-view');
        return view('admin.accounting.company-loans.index',[
            'loans'=>DB::table('company_loans')->orderByDesc('id')->paginate(20),
            'payments'=>DB::table('company_loan_payments')->orderByDesc('id')->paginate(20, ['*'], 'acc_company_loans_2')->withQueryString(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->get(),
        ]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function createLoan(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-company-loans');
        $d=$r->validate(['reference'=>'required|string|max:80|unique:company_loans,reference',
            'lender'=>'required|string|max:180','liability_account_id'=>'required|exists:chart_of_accounts,id',
            'funding_journal_entry_id'=>'required|exists:journal_entries,id',
            'principal'=>'required|numeric|gt:0','received_on'=>'required|date_format:Y-m-d',
            'maturity_on'=>'nullable|date_format:Y-m-d|after:received_on',
            'annual_interest_rate'=>'required|numeric|min:0|max:100','notes'=>'nullable|string|max:2000']);
        $this->leaf($d['liability_account_id'],'liability');
        $j=$this->posted($d['funding_journal_entry_id']);
        if(DB::table('company_loans')->where('funding_journal_entry_id',$j->id)->exists())
            throw ValidationException::withMessages(['funding_journal_entry_id'=>'Funding journal already linked.']);
        $posted=DB::table('journal_lines')->where('journal_entry_id',$j->id)->where('account_id',$d['liability_account_id'])->get();
        $credit=0;foreach($posted as $line) $credit+=$this->u($line->credit)-$this->u($line->debit);
        if($credit!==$this->u($d['principal'])) throw ValidationException::withMessages(['principal'=>'Loan liability must equal funding journal credit.']);
        DB::table('company_loans')->insert($d+['created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Company loan linked to existing POSTED funding journal.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function repayLoan(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-company-loans');
        $d=$r->validate(['paid_on'=>'required|date_format:Y-m-d','principal'=>'required|numeric|gt:0',
            'interest'=>'required|numeric|min:0','reference'=>'required|string|max:120',
            'payment_account_id'=>'required|exists:chart_of_accounts,id']);
        $journal=DB::transaction(function() use($id,$d){
            $loan=DB::table('company_loans')->where('id',$id)->lockForUpdate()->first();
            if(!$loan) throw ValidationException::withMessages(['loan'=>'Loan not found.']);
            $this->leaf($d['payment_account_id'],'asset');
            if(!in_array(DB::table('chart_of_accounts')->where('id',$d['payment_account_id'])->value('code'),['1100','1110','1120','1150'],true))
                throw ValidationException::withMessages(['payment_account_id'=>'Choose Cash, Petty Cash, Bank or MFS Wallet.']);
            $paid=$this->u($d['principal']);$interest=$this->u($d['interest']);
            $used=0;
            foreach(DB::table('company_loan_payments as p')->join('journal_entries as j','j.id','=','p.journal_entry_id')
                ->where('p.company_loan_id',$id)
                ->whereNotIn('j.id',DB::table('journal_entries')->where('status','POSTED')
                    ->whereNotNull('reversal_of_id')->select('reversal_of_id'))->get(['p.principal']) as $row) $used+=$this->u($row->principal);
            if($used+$paid>$this->u($loan->principal)) throw ValidationException::withMessages(['principal'=>'Payment exceeds remaining loan principal.']);
            if(DB::table('company_loan_payments')->where('company_loan_id',$id)->where('reference',$d['reference'])->exists())
                throw ValidationException::withMessages(['reference'=>'Repayment reference already used.']);
            $lines=[$this->line($loan->liability_account_id,$paid,0,'Loan principal repaid')];
            if($interest>0) $lines[]=$this->line((int)DB::table('chart_of_accounts')->where('code','6550')->value('id'),$interest,0,'Interest expense');
            $lines[]=$this->line($d['payment_account_id'],0,$paid+$interest,'Bank/cash loan payment');
            $j=$this->draft($d['paid_on'],$d['reference'],'Company loan payment: '.$loan->lender,$lines,'BANK');
            DB::table('company_loan_payments')->insert(['company_loan_id'=>$id,'paid_on'=>$d['paid_on'],
                'principal'=>$this->f($paid),'interest'=>$this->f($interest),'reference'=>$d['reference'],
                'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now()]);
            return $j;
        },3);
        return redirect()->route('accounting.journals.show',$journal)->with('success','Principal and interest DRAFT created; not posted yet.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
