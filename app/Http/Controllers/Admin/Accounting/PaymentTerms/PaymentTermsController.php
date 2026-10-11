<?php
namespace App\Http\Controllers\Admin\Accounting\PaymentTerms;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingEventService,AccountingMoney,JournalPostingService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4: company-level registers. Only POSTED journals affect financial balances. */

/** PaymentTerms accounting module. A shared base provides posting dependencies and invariant checks. */
final class PaymentTermsController extends \App\Http\Controllers\Admin\Accounting\Shared\ExtendedLedgerController
{
    public function dueTerms(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate();
        $type=$r->input('type','AR'); if (!in_array($type,['AR','AP'],true)) $type='AR';
        $table=$type==='AR'?'customer_receivables':'vendor_payables';
        return view('admin.accounting.payment-terms.index',[
            'type'=>$type,'terms'=>DB::table('payment_terms')->where('is_active',true)->get(),
            'receivables'=>DB::table($table.' as b')->leftJoin('accounting_due_terms as t',function($q) use($type){
                $q->on('t.source_id','=','b.id')->where('t.source_type',$type);
            })->select('b.*','t.id as tracking_id','t.due_date as tracked_due_date','t.followup_status','t.next_followup_on')
                ->orderByDesc('b.id')->paginate(25)->withQueryString(),
            'followups'=>DB::table('payment_followups as f')->join('accounting_due_terms as t','t.id','=','f.accounting_due_term_id')
                ->where('t.source_type',$type)->select('f.*','t.source_id')->orderByDesc('f.id')->paginate(20, ['*'], 'acc_payment_terms_2')->withQueryString(),
        ]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveTerms(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-due-terms');
        $d=$r->validate(['source_type'=>'required|in:AR,AP','source_id'=>'required|integer|min:1',
            'payment_term_id'=>'required|exists:payment_terms,id','invoice_date'=>'required|date_format:Y-m-d']);
        $table=$d['source_type']==='AR'?'customer_receivables':'vendor_payables';
        if (!DB::table($table)->where('id',$d['source_id'])->exists()) throw ValidationException::withMessages(['source_id'=>'Receivable/payable not found.']);
        $term=DB::table('payment_terms')->find($d['payment_term_id']);
        $date=Carbon::parse($d['invoice_date'])->addDays($term->days_due)->toDateString();
        DB::table('accounting_due_terms')->updateOrInsert(['source_type'=>$d['source_type'],'source_id'=>$d['source_id']],
            ['payment_term_id'=>$term->id,'due_date'=>$date,'updated_by'=>auth()->id(),'updated_at'=>now(),'created_at'=>now()]);
        return back()->with('success','Follow-up due date set. Source invoice values are unchanged.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function followup(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-due-terms');
        $d=$r->validate(['tracking_id'=>'required|exists:accounting_due_terms,id','stage'=>'required|in:OPEN,CONTACTED,PROMISED,DISPUTED,RESOLVED',
            'note'=>'required|string|min:5|max:2500','promise_to_pay_on'=>'nullable|date_format:Y-m-d',
            'next_followup_on'=>'nullable|date_format:Y-m-d']);
        DB::transaction(function() use($d){
            DB::table('accounting_due_terms')->where('id',$d['tracking_id'])->update([
                'followup_status'=>$d['stage'],'next_followup_on'=>$d['next_followup_on']??null,
                'updated_by'=>auth()->id(),'updated_at'=>now()]);
            DB::table('payment_followups')->insert(['accounting_due_term_id'=>$d['tracking_id'],'stage'=>$d['stage'],
                'note'=>$d['note'],'promise_to_pay_on'=>$d['promise_to_pay_on']??null,
                'next_followup_on'=>$d['next_followup_on']??null,
                'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Follow-up added to permanent history.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
