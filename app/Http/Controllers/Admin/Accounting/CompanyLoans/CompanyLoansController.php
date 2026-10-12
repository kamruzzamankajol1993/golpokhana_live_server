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
            'installments'=>DB::table('company_loan_installments as i')->join('company_loans as l','l.id','=','i.company_loan_id')
                ->select('i.*','l.reference as loan_reference')->orderBy('i.company_loan_id')->orderBy('i.installment_no')->get(),
            'installmentAllocations'=>DB::table('company_loan_installment_allocations as a')
                ->join('company_loan_payments as p','p.id','=','a.company_loan_payment_id')
                ->join('journal_entries as j','j.id','=','p.journal_entry_id')
                ->where('j.status','POSTED')
                ->whereNotIn('j.id',DB::table('journal_entries')->where('status','POSTED')->whereNotNull('reversal_of_id')->select('reversal_of_id'))
                ->select('a.*')->get()->groupBy('installment_id'),
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

    /** Manual, explicit monthly equal-principal schedule. Estimated interest only, never GL posting. */
    public function schedule(Request $r,int $id)
    {
        $this->gate('accounting-company-loans');
        $d=$r->validate(['installment_count'=>'required|integer|min:1|max:360',
            'first_due_date'=>'required|date_format:Y-m-d']);
        DB::transaction(function() use($id,$d) {
            $loan=DB::table('company_loans')->where('id',$id)->lockForUpdate()->first();
            if (!$loan) throw ValidationException::withMessages(['loan'=>'Loan not found.']);
            if (DB::table('company_loan_installments')->where('company_loan_id',$id)->exists())
                throw ValidationException::withMessages(['loan'=>'Schedule already exists. Do not regenerate booked installments.']);
            if (DB::table('company_loan_payments')->where('company_loan_id',$id)->exists())
                throw ValidationException::withMessages(['loan'=>'Existing repayments require accountant reconciliation before an installment schedule is created.']);
            $first=Carbon::parse($d['first_due_date']);
            if ($first->lte(Carbon::parse($loan->received_on)))
                throw ValidationException::withMessages(['first_due_date'=>'First due date must follow funding date.']);
            $n=(int)$d['installment_count']; $total=$this->u($loan->principal);
            $last=$first->copy()->addMonthsNoOverflow($n-1);
            if($loan->maturity_on && $last->toDateString()>$loan->maturity_on)
                throw ValidationException::withMessages(['first_due_date'=>'Final installment exceeds agreed loan maturity.']);
            if ($n>$total) throw ValidationException::withMessages(['installment_count'=>'Each installment needs positive principal at 4-decimal precision.']);
            $step=intdiv($total,$n); $remaining=$total;
            for($i=1;$i<=$n;$i++) {
                $principal=$i===$n?$remaining:$step;
                // Scheduled interest is an estimate; bank agreement governs actual interest.
                $rate=(float)$loan->annual_interest_rate;
                $interest=(int) round(($remaining/10000)*($rate/1200)*10000);
                if ($interest<0) $interest=0;
                DB::table('company_loan_installments')->insert([
                    'company_loan_id'=>$id,'installment_no'=>$i,
                    'due_date'=>$first->copy()->addMonthsNoOverflow($i-1)->toDateString(),
                    'principal_due'=>$this->f($principal),'interest_due'=>$this->f($interest),
                    'created_at'=>now(),'updated_at'=>now()]);
                $remaining-=$principal;
            }
        },3);
        return back()->with('success','Monthly installment schedule created. Interest amounts are estimates, not posted expenses.');
    }

    /** Correct an unallocated forecast to the signed bank agreement, with audit history. */
    public function adjustInstallment(Request $r,int $id)
    {
        $this->gate('accounting-company-loans');
        $d=$r->validate(['due_date'=>'required|date_format:Y-m-d',
            'interest_due'=>'required|numeric|min:0', 'reason'=>'required|string|min:8|max:300']);
        DB::transaction(function() use($id,$d){
            $row=DB::table('company_loan_installments')->where('id',$id)->lockForUpdate()->first();
            if(!$row) throw ValidationException::withMessages(['installment'=>'Installment not found.']);
            if(DB::table('company_loan_installment_allocations')->where('installment_id',$id)->exists())
                throw ValidationException::withMessages(['installment'=>'Installment already has a linked payment. Adjustment requires reconciliation.']);
            $loan=DB::table('company_loans')->where('id',$row->company_loan_id)->first();
            if($d['due_date']<=$loan->received_on)
                throw ValidationException::withMessages(['due_date'=>'Due date must follow funding.']);
            DB::table('company_loan_installments')->where('id',$id)->update([
                'due_date'=>$d['due_date'],'interest_due'=>$this->f($this->u($d['interest_due'])),'updated_at'=>now()]);
            AccountingAuditService::record('company_loan_installment',$id,'ADJUST',(array)$row,$d,$d['reason']);
        },3);
        return back()->with('success','Unpaid installment forecast adjusted with audit history. No GL posting.');
    }

    public function repayLoan(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-company-loans');
        $d=$r->validate(['paid_on'=>'required|date_format:Y-m-d','principal'=>'required|numeric|min:0',
            'interest'=>'required|numeric|min:0','installment_id'=>'nullable|integer|exists:company_loan_installments,id','reference'=>'required|string|max:120',
            'payment_account_id'=>'required|exists:chart_of_accounts,id']);
        $journal=DB::transaction(function() use($id,$d){
            $loan=DB::table('company_loans')->where('id',$id)->lockForUpdate()->first();
            if(!$loan) throw ValidationException::withMessages(['loan'=>'Loan not found.']);
            $this->leaf($d['payment_account_id'],'asset');
            if(!in_array(DB::table('chart_of_accounts')->where('id',$d['payment_account_id'])->value('code'),['1100','1110','1120','1150'],true))
                throw ValidationException::withMessages(['payment_account_id'=>'Choose Cash, Petty Cash, Bank or MFS Wallet.']);
            $paid=$this->u($d['principal']);$interest=$this->u($d['interest']);
            if($paid+$interest<=0) throw ValidationException::withMessages(['principal'=>'Repayment principal plus interest must be positive.']);
            $selected=null;
            if(DB::table('company_loan_installments')->where('company_loan_id',$id)->exists()) {
                if (empty($d['installment_id'])) throw ValidationException::withMessages(['installment_id'=>'Choose the installment to apply this repayment to.']);
                $selected=DB::table('company_loan_installments')->where('id',$d['installment_id'])
                    ->where('company_loan_id',$id)->lockForUpdate()->first();
                if (!$selected) throw ValidationException::withMessages(['installment_id'=>'Installment does not belong to this loan.']);
                if ($d['paid_on']<$loan->received_on)
                    throw ValidationException::withMessages(['paid_on'=>'Repayment cannot precede funding.']);
                // All non-reversed draft/posted allocations reserve remaining due, preventing duplicated drafts.
                $allocated=DB::table('company_loan_installment_allocations as a')
                    ->join('company_loan_payments as p','p.id','=','a.company_loan_payment_id')
                    ->join('journal_entries as j','j.id','=','p.journal_entry_id')
                    ->where('a.installment_id',$selected->id)->where('j.status','!=','REVERSED')
                    ->whereNotIn('j.id',DB::table('journal_entries')->where('status','POSTED')
                        ->whereNotNull('reversal_of_id')->select('reversal_of_id'))
                    ->select('a.principal_amount','a.interest_amount')->get();
                $usedPrincipal=0;$usedInterest=0;
                foreach($allocated as $row){$usedPrincipal+=$this->u($row->principal_amount);$usedInterest+=$this->u($row->interest_amount);}
                if($usedPrincipal+$paid>$this->u($selected->principal_due) || $usedInterest+$interest>$this->u($selected->interest_due))
                    throw ValidationException::withMessages(['installment_id'=>'Payment exceeds remaining installment principal or interest.']);
            }

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
            $paymentId=DB::table('company_loan_payments')->insertGetId(['company_loan_id'=>$id,'paid_on'=>$d['paid_on'],
                'principal'=>$this->f($paid),'interest'=>$this->f($interest),'reference'=>$d['reference'],
                'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now()]);
            if($selected) DB::table('company_loan_installment_allocations')->insert([
                'installment_id'=>$selected->id,'company_loan_payment_id'=>$paymentId,
                'principal_amount'=>$this->f($paid),'interest_amount'=>$this->f($interest),
                'created_at'=>now(),'updated_at'=>now()]);
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
