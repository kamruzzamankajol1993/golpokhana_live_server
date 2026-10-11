<?php
namespace App\Http\Controllers\Admin\Accounting\BankReconciliation;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingEventService,AccountingMoney,JournalPostingService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4: company-level registers. Only POSTED journals affect financial balances. */

/** BankReconciliation accounting module. A shared base provides posting dependencies and invariant checks. */
final class BankReconciliationController extends \App\Http\Controllers\Admin\Accounting\Shared\ExtendedLedgerController
{
    public function bank()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate();
        $accounts=DB::table('payment_accounts')->where('method','Bank')->where('active',true)->get();
        $lines=DB::table('bank_statement_lines as s')->join('payment_accounts as p','p.id','=','s.payment_account_id')
            ->select('s.*','p.name as account_name')->orderByDesc('s.id')->paginate(25);
        $journals=DB::table('journal_entries as j')->join('journal_lines as l','l.journal_entry_id','=','j.id')
            ->join('payment_accounts as p','p.gl_account_id','=','l.account_id')
            ->where('p.method','Bank')->where('j.status','POSTED')
            ->whereNotIn('j.id',DB::table('bank_statement_lines')->whereNotNull('matched_journal_entry_id')->select('matched_journal_entry_id'))
            ->select('j.id','j.number','j.entry_date','l.debit','l.credit','p.name')->orderByDesc('j.id')->paginate(20, ['*'], 'acc_bank_reconciliation_2')->withQueryString();
        return view('admin.accounting.bank-reconciliation.index',compact('accounts','lines','journals'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function unmatchBank(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-bank-match');
        $d=$r->validate(['reason'=>'required|string|min:8|max:1000']);
        DB::transaction(function() use($id,$d){
            $line=DB::table('bank_statement_lines')->where('id',$id)->lockForUpdate()->first();
            if(!$line || $line->status!=='MATCHED') throw ValidationException::withMessages(['statement'=>'Only matched statement lines can be released.']);
            DB::table('bank_statement_lines')->where('id',$id)->update([
                'status'=>'UNMATCHED','matched_journal_entry_id'=>null,
                'matched_at'=>null,'matched_by'=>null,'updated_at'=>now()]);
            AccountingAuditService::record('bank_statement_line',$id,'UNMATCH',
                ['journal_entry_id'=>$line->matched_journal_entry_id],null,$d['reason']);
        },3);
        return back()->with('success','Bank statement match released with audit trail.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function saveBankLine(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-bank-match');
        $d=$r->validate(['payment_account_id'=>'required|exists:payment_accounts,id','statement_date'=>'required|date_format:Y-m-d',
            'statement_reference'=>'required|string|max:180','direction'=>'required|in:IN,OUT',
            'amount'=>'required|numeric|gt:0','description'=>'nullable|string|max:2000']);
        if(!DB::table('payment_accounts')->where('id',$d['payment_account_id'])->where('method','Bank')->exists())
            throw ValidationException::withMessages(['payment_account_id'=>'Select a Bank account.']);
        $d['amount']=$this->f($this->u($d['amount']));
        DB::table('bank_statement_lines')->insert($d+['status'=>'UNMATCHED','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Bank statement line recorded; GL has not changed.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function matchBank(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-bank-match');
        $d=$r->validate(['journal_entry_id'=>'required|exists:journal_entries,id']);
        DB::transaction(function() use($id,$d){
            $statement=DB::table('bank_statement_lines')->where('id',$id)->lockForUpdate()->first();
            if (!$statement || $statement->status!=='UNMATCHED') throw ValidationException::withMessages(['statement'=>'Statement line already matched.']);
            $this->posted((int)$d['journal_entry_id']);
            $gl=DB::table('payment_accounts')->where('id',$statement->payment_account_id)->value('gl_account_id');
            $lines=DB::table('journal_lines')->where('journal_entry_id',$d['journal_entry_id'])->where('account_id',$gl)->get();
            $dr=0;$cr=0;
            foreach($lines as $l) {$dr+=$this->u($l->debit);$cr+=$this->u($l->credit);}
            $net=$dr-$cr;
            if (($statement->direction==='IN'?$net:-$net)!==$this->u($statement->amount))
                throw ValidationException::withMessages(['journal_entry_id'=>'Posted Bank GL net movement does not match statement amount/direction.']);
            if(DB::table('bank_statement_lines')->where('matched_journal_entry_id',$d['journal_entry_id'])->exists())
                throw ValidationException::withMessages(['journal_entry_id'=>'Journal already matched to a statement line.']);
            DB::table('bank_statement_lines')->where('id',$id)->update(['status'=>'MATCHED',
                'matched_journal_entry_id'=>$d['journal_entry_id'],'matched_by'=>auth()->id(),
                'matched_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('bank_statement_line',$id,'MATCH',null,['journal_entry_id'=>$d['journal_entry_id']]);
        },3);
        return back()->with('success','Bank movement matched to POSTED GL only; no new journal created.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
    public function deleteBankLine(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-bank-match');
        app(\App\Services\Accounting\AccountingSafeDeletionService::class)->bankStatement($id);
        return back()->with('success','Unused draft/master record deleted. Accounting audit history retained.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

}
