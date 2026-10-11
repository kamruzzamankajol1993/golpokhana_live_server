<?php
namespace App\Http\Controllers\Admin\Accounting\Payroll;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingEventService,AccountingMoney,JournalPostingService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4: company-level registers. Only POSTED journals affect financial balances. */

/** Payroll accounting module. A shared base provides posting dependencies and invariant checks. */
final class PayrollAccountingController extends \App\Http\Controllers\Admin\Accounting\Shared\ExtendedLedgerController
{
    public function payroll()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate();
        return view('admin.accounting.payroll.index',[
            'runs'=>DB::table('payroll_runs')->orderByDesc('id')->limit(50)->get(),
            'items'=>DB::table('payroll_items as i')->leftJoin('accounting_events as e',function($q){$q->on('e.source_id','=',DB::raw('CAST(i.id AS CHAR)'))->where('e.source_type','payroll_items')->where('e.event_type','PAYROLL_ACCRUED');})
                ->select('i.*','e.id as event_id','e.status as accounting_status')->whereIn('i.status',['approved','paid'])
                ->orderByDesc('i.id')->paginate(25),
            'advances'=>DB::table('salary_advances')->where('status','!=','cancelled')->orderByDesc('id')->paginate(20, ['*'], 'acc_payroll_2')->withQueryString(),
            'loans'=>DB::table('employee_loans')->where('status','!=','cancelled')->orderByDesc('id')->paginate(20, ['*'], 'acc_payroll_3')->withQueryString(),
            'fundings'=>DB::table('accounting_employee_funding')->orderByDesc('id')->paginate(20, ['*'], 'acc_payroll_6')->withQueryString(),
            'manualRepayments'=>DB::table('salary_advance_repayments')->where('source','manual')->orderByDesc('id')->paginate(20, ['*'], 'acc_payroll_4')->withQueryString(),
            'manualLoanRepayments'=>DB::table('loan_repayments')->where('source','manual')->orderByDesc('id')->paginate(20, ['*'], 'acc_payroll_5')->withQueryString(),
        ]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function verifyFunding(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-payroll');
        $d=$r->validate(['source_type'=>'required|in:salary_advances,employee_loans','source_id'=>'required|integer|min:1',
            'paid_on'=>'required|date_format:Y-m-d','method'=>'required|in:cash,bank,mfs',
            'reference'=>'required|string|max:150','evidence'=>'required|string|min:10|max:1000']);
        $id=DB::transaction(function() use($d) {
            $s=DB::table('accounting_settings')->where('id',1)->lockForUpdate()->first();
            if (!$s || !$s->capture_enabled || !$s->activated_at || Carbon::parse($d['paid_on'])->endOfDay()->lt(Carbon::parse($s->activated_at)))
                throw ValidationException::withMessages(['paid_on'=>'Accounting Capture must be ON; do not backfill disbursements from OFF windows.']);
            $source=DB::table($d['source_type'])->where('id',$d['source_id'])->lockForUpdate()->first();
            if (!$source || $source->status==='cancelled') throw ValidationException::withMessages(['source_id'=>'Active HR advance/loan not found.']);
            if (DB::table('accounting_employee_funding')->where('source_type',$d['source_type'])->where('source_id',$d['source_id'])->exists())
                throw ValidationException::withMessages(['source_id'=>'This source disbursement is already registered.']);
            $amount=$this->u($d['source_type']==='salary_advances'?$source->amount:$source->principal_amount);
            if ($amount<=0) throw ValidationException::withMessages(['amount'=>'Disbursement must be positive.']);
            $amounts=['principal'=>$this->f($amount),'cash_paid'=>$this->f($d['method']==='cash'?$amount:0),
                'bank_paid'=>$this->f($d['method']==='bank'?$amount:0),'mfs_paid'=>$this->f($d['method']==='mfs'?$amount:0)];
            $event=$this->events->capture($d['source_type'],$d['source_id'],
                $d['source_type']==='employee_loans'?'LOAN_DISBURSED':'ADVANCE_DISBURSED',1,
                now()->toDateTimeString(),$d['paid_on'],['total'=>$this->f($amount),'amounts'=>$amounts,
                    'manual_only'=>true,'source'=>['evidence'=>$d['evidence'],'reference'=>$d['reference'],'paid_on'=>$d['paid_on']]]);
            if (!$event) throw ValidationException::withMessages(['event'=>'Accounting event was not captured.']);
            DB::table('accounting_employee_funding')->insert($d+['amount'=>$this->f($amount),
                'accounting_event_id'=>$event,'verified_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            return $event;
        },3);
        return redirect()->route('accounting.events')->with('success','Verified disbursement captured as review event #'.$id.'; activate mapping and approve before posting.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function verifyRepayment(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-payroll');
        $d=$r->validate(['source_type'=>'required|in:salary_advance_repayments,loan_repayments',
            'source_id'=>'required|integer|min:1','received_on'=>'required|date_format:Y-m-d',
            'method'=>'required|in:cash,bank,mfs','principal'=>'required|numeric|min:0',
            'interest'=>'required|numeric|min:0','reference'=>'required|string|max:150',
            'evidence'=>'required|string|min:10|max:3000']);
        DB::transaction(function() use($d){
            $settings=DB::table('accounting_settings')->where('id',1)->lockForUpdate()->first();
            if(!$settings || !$settings->capture_enabled || !$settings->activated_at ||
                Carbon::parse($d['received_on'])->endOfDay()->lt(Carbon::parse($settings->activated_at)))
                throw ValidationException::withMessages(['received_on'=>'Cannot capture an OFF-window repayment.']);
            $row=DB::table($d['source_type'])->where('id',$d['source_id'])->lockForUpdate()->first();
            if(!$row || $row->source!=='manual' || substr((string)$row->payment_date,0,10)!==$d['received_on'])
                throw ValidationException::withMessages(['source_id'=>'Manual HR repayment/date not found. Payroll recoveries must NOT be entered twice.']);
            if(DB::table('accounting_employee_repayment_verifications')->where('source_type',$d['source_type'])
                ->where('source_id',$d['source_id'])->exists())
                throw ValidationException::withMessages(['source_id'=>'This repayment is already verified.']);
            $principal=$this->u($d['principal']);$interest=$this->u($d['interest']);
            if($principal+$interest!==$this->u($row->amount) || $principal<=0)
                throw ValidationException::withMessages(['principal'=>'Principal + interest must equal the saved HR repayment amount.']);
            $parentType=$d['source_type']==='loan_repayments'?'employee_loans':'salary_advances';
            $parentId=$d['source_type']==='loan_repayments'?$row->employee_loan_id:$row->salary_advance_id;
            $parent=DB::table($parentType)->where('id',$parentId)->first();
            if(!$parent || $parent->status==='cancelled' || ($parentType==='salary_advances' && $interest>0))
                throw ValidationException::withMessages(['interest'=>'Advance repayments cannot contain interest; verify source loan.']);
            if(!DB::table('accounting_events')->where('source_type',$parentType)->where('source_id',(string)$parentId)
                ->where('event_type',$parentType==='employee_loans'?'LOAN_DISBURSED':'ADVANCE_DISBURSED')
                ->where('status','POSTED')->exists())
                throw ValidationException::withMessages(['source_id'=>'Funded source must have a POSTED disbursement journal.']);
            $amount=$principal+$interest;
            $event=$this->events->capture($d['source_type'],$d['source_id'],
                $parentType==='employee_loans'?'LOAN_REPAID':'ADVANCE_REPAID',1,now()->toDateTimeString(),$d['received_on'],
                ['total'=>$this->f($amount),'amounts'=>[
                    'principal'=>$this->f($principal),'interest'=>$this->f($interest),
                    'cash_received'=>$this->f($d['method']==='cash'?$amount:0),
                    'bank_received'=>$this->f($d['method']==='bank'?$amount:0),
                    'mfs_received'=>$this->f($d['method']==='mfs'?$amount:0)],
                    'source'=>['parent_id'=>$parentId,'reference'=>$d['reference'],'evidence'=>$d['evidence']],
                    'manual_only'=>true]);
            if(!$event) throw ValidationException::withMessages(['event'=>'Event capture was not accepted.']);
            DB::table('accounting_employee_repayment_verifications')->insert([
                'source_type'=>$d['source_type'],'source_id'=>$d['source_id'],'received_on'=>$d['received_on'],
                'method'=>$d['method'],'principal'=>$this->f($principal),'interest'=>$this->f($interest),
                'reference'=>$d['reference'],'evidence'=>$d['evidence'],'verified_by'=>auth()->id(),
                'accounting_event_id'=>$event,'created_at'=>now(),'updated_at'=>now()]);
        },3);
        return redirect()->route('accounting.events')->with('success','Verified manual repayment pending accountant mapping/posting.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
