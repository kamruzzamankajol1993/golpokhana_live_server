<?php
namespace App\Http\Controllers\Admin\Accounting\PaymentClearing;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** PaymentClearing accounting module. A shared base provides posting dependencies and invariant checks. */
final class PaymentClearingController extends \App\Http\Controllers\Admin\Accounting\Shared\OperationsController
{
    public function clearing()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $clearing=DB::table('payment_account_transactions as t')
            ->join('payment_accounts as a','a.id','=','t.payment_account_id')
            ->whereIn('a.method',['Card','MFS'])
            ->select('t.*','a.name as account_name','a.gl_account_id')->orderByDesc('t.id')->paginate(25);
        return view('admin.accounting.payment-clearing.index',compact('clearing'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function settle(Request $r,int $transactionId)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-reconcile');
        $d=$r->validate(['entry_date'=>'required|date','fee'=>'required|numeric|min:0',
            'reference'=>'required|string|max:200']);
        $journalId=DB::transaction(function() use($transactionId,$d) {
            $t=DB::table('payment_account_transactions')->where('id',$transactionId)->lockForUpdate()->first();
            if (!$t || $t->status!=='UNSETTLED' || $t->direction!=='IN') {
                throw ValidationException::withMessages(['settlement'=>'Only unsettled incoming transactions can be settled.']);
            }
            if (DB::table('accounting_bank_settlements')->where('payment_account_transaction_id',$transactionId)->exists()) {
                throw ValidationException::withMessages(['settlement'=>'This clearing transaction already has a settlement request.']);
            }
            $account=DB::table('payment_accounts')->where('id',$t->payment_account_id)->first();
            if (!$account || !in_array($account->method,['Card','MFS'],true)) throw ValidationException::withMessages(['settlement'=>'Only Card/MFS clearing is supported.']);
            $gross=$this->amount($t->amount); $fee=$this->amount($d['fee']);
            if ($fee>$gross) throw ValidationException::withMessages(['fee'=>'Settlement fee cannot exceed gross.']);
            $lines=[];
            if ($gross-$fee>0) $lines[]=$this->line($this->account('1120'),$gross-$fee,0,'Net bank received');
            if ($fee>0) $lines[]=$this->line($this->account('6500'),$fee,0,'Provider fee');
            $lines[]=$this->line((int)$account->gl_account_id,0,$gross,'Clear provider receivable');
            $j=$this->posting->createDraft(['journal_id'=>$this->journal('BANK'),
                'entry_date'=>$d['entry_date'],'reference'=>$d['reference'],
                'narration'=>'Provider settlement '.$t->reference,'created_by'=>auth()->id()],$lines);
            DB::table('accounting_bank_settlements')->insert([
                'payment_account_transaction_id'=>$t->id,'journal_entry_id'=>$j,
                'gross_amount'=>AccountingMoney::format($gross),'fee'=>AccountingMoney::format($fee),
                'bank_net'=>AccountingMoney::format($gross-$fee),'reference'=>$d['reference'],
                'status'=>'DRAFT','created_at'=>now(),'updated_at'=>now(),
            ]);
            return $j;
        },3);
        return redirect()->route('accounting.journals.show',$journalId)->with('success','Settlement journal drafted; clearing stays unsettled until posted.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
