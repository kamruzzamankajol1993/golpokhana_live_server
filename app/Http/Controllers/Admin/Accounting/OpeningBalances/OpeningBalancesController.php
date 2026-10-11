<?php
namespace App\Http\Controllers\Admin\Accounting\OpeningBalances;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingCloseService,AccountingMoney,AccountingReportService,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** OpeningBalances accounting module. A shared base provides posting dependencies and invariant checks. */
final class OpeningBalancesController extends \App\Http\Controllers\Admin\Accounting\Shared\AccountingClosingController
{
    public function cutover()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $cutovers=DB::table('accounting_cutovers as c')->join('journal_entries as j','j.id','=','c.opening_journal_entry_id')
            ->select('c.*','j.status as journal_status','j.number')->orderByDesc('c.id')->get();
        $accounts=DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)
            ->whereIn('type',['asset','liability','equity'])->orderBy('code')->get();
        $cashAccounts=DB::table('chart_of_accounts')->whereIn('id',DB::table('accounting_cash_flow_rules')
            ->where('activity','CASH')->pluck('account_id'))->where('is_active',true)->get();
        $openingItems=DB::table('accounting_opening_items')->orderBy('side')->orderBy('invoice_reference')->get();
        $allocations=DB::table('accounting_opening_allocations as a')->join('journal_entries as j','j.id','=','a.journal_entry_id')
            ->select('a.*','j.status as journal_status')->orderByDesc('a.id')->get();
        return view('admin.accounting.opening-balances.index',compact('cutovers','accounts','cashAccounts','openingItems','allocations'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function prepareCutover(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-cutover');$this->can('accounting-journal-create');
        $d=$r->validate(['starts_on'=>'required|date_format:Y-m-d',
            'evidence_reference'=>'required|string|min:8|max:250','notes'=>'nullable|string|max:2000',
            'lines'=>'required|array|min:2|max:80','lines.*.account_id'=>'required|exists:chart_of_accounts,id',
            'lines.*.debit'=>'required|numeric|min:0','lines.*.credit'=>'required|numeric|min:0']);
        $id=DB::transaction(function() use($d) {
            if (DB::table('accounting_cutovers')->lockForUpdate()->exists())
                throw ValidationException::withMessages(['cutover'=>'One-company cutover is already registered. It cannot be replaced without a documented migration procedure.']);
            $lines=[];
            foreach($d['lines'] as $row) {
                $account=DB::table('chart_of_accounts')->where('id',$row['account_id'])->first();
                if (!$account || $account->is_header || !$account->is_active || !in_array($account->type,['asset','liability','equity'],true))
                    throw ValidationException::withMessages(['account'=>'Opening balances permit active asset, liability and equity posting accounts only.']);
                $lines[]=['account_id'=>$row['account_id'],'debit'=>AccountingMoney::format($this->units($row['debit'])),
                    'credit'=>AccountingMoney::format($this->units($row['credit'])),'allow_control'=>true];
            }
            $this->posting->assertLines($lines);
            $journal=DB::table('accounting_journals')->where('code','ADJ')->where('is_active',true)->value('id');
            if (!$journal) throw ValidationException::withMessages(['journal'=>'Adjustment journal ADJ is missing.']);
            $id=$this->posting->createDraft(['journal_id'=>$journal,'entry_date'=>$d['starts_on'],
                'reference'=>'OPENING:'.$d['starts_on'],'origin'=>'OPENING',
                'narration'=>'Accountant-approved opening balance evidence '.$d['evidence_reference'],
                'created_by'=>auth()->id()],$lines);
            DB::table('accounting_cutovers')->insert(['starts_on'=>$d['starts_on'],'status'=>'PREPARED',
                'evidence_reference'=>$d['evidence_reference'],'notes'=>$d['notes']??null,
                'opening_journal_entry_id'=>$id,'created_by'=>auth()->id(),
                'created_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('cutover',$id,'PREPARE',null,['starts_on'=>$d['starts_on'],'evidence'=>$d['evidence_reference']]);
            return $id;
        },3);
        return redirect()->route('accounting.journals.show',$id)->with('success','Opening journal DRAFT prepared. Submit, approve by another user, and POST before signing cutover.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function addOpeningItem(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-cutover');
        $d=$r->validate(['side'=>'required|in:AR,AP','party_id'=>'nullable|integer|min:1',
            'invoice_reference'=>'required|string|max:150','due_on'=>'required|date_format:Y-m-d',
            'amount'=>'required|numeric|gt:0']);
        DB::transaction(function() use($d,$id) {
            $cutover=DB::table('accounting_cutovers')->where('id',$id)->lockForUpdate()->first();
            if (!$cutover || $cutover->status!=='PREPARED')
                throw ValidationException::withMessages(['cutover'=>'Opening subledger becomes immutable after sign-off.']);
            if (DB::table('accounting_opening_items')->where('cutover_id',$id)->where('side',$d['side'])
                ->where('invoice_reference',$d['invoice_reference'])->exists())
                throw ValidationException::withMessages(['reference'=>'Invoice reference already exists for this side.']);
            DB::table('accounting_opening_items')->insert(['cutover_id'=>$id,'side'=>$d['side'],
                'party_id'=>$d['party_id']??null,'invoice_reference'=>$d['invoice_reference'],
                'due_on'=>$d['due_on'],'amount'=>AccountingMoney::format($this->units($d['amount'])),
                'created_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('opening_item',$id,'ADD',null,$d);
        },3);
        return back()->with('success','Opening AR/AP invoice registered for cutover reconciliation.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function allocateOpening(Request $r,int $itemId)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-cutover');$this->can('accounting-journal-create');
        $d=$r->validate(['cash_account_id'=>'required|exists:chart_of_accounts,id',
            'paid_on'=>'required|date_format:Y-m-d','amount'=>'required|numeric|gt:0',
            'evidence'=>'required|string|min:8|max:250']);
        $id=DB::transaction(function() use($d,$itemId) {
            $item=DB::table('accounting_opening_items')->where('id',$itemId)->lockForUpdate()->first();
            if (!$item) throw ValidationException::withMessages(['item'=>'Opening invoice not found.']);
            $cutover=DB::table('accounting_cutovers')->where('id',$item->cutover_id)->first();
            if (!$cutover || $cutover->status!=='ACTIVE' || $d['paid_on']<$cutover->starts_on)
                throw ValidationException::withMessages(['item'=>'Active cutover and post-cutover settlement date required.']);
            $cash=DB::table('chart_of_accounts')->where('id',$d['cash_account_id'])->first();
            $allowed=DB::table('accounting_cash_flow_rules')->where('account_id',$d['cash_account_id'])
                ->where('activity','CASH')->exists();
            if (!$cash || !$cash->is_active || $cash->is_header || $cash->type!=='asset' || !$allowed)
                throw ValidationException::withMessages(['cash'=>'Choose an accountant-mapped CASH/BANK asset account.']);
            $controlCode=$item->side==='AR'?'1200':'2100';
            $control=DB::table('chart_of_accounts')->where('code',$controlCode)->where('is_active',true)->value('id');
            if (!$control) throw ValidationException::withMessages(['account'=>'Opening AR/AP control account missing.']);
            $paid=0;
            foreach(DB::table('accounting_opening_allocations')->where('opening_item_id',$itemId)->pluck('amount') as $amount)
                $paid+=$this->units($amount);
            $amount=$this->units($d['amount']);
            if ($paid+$amount>$this->units($item->amount))
                throw ValidationException::withMessages(['amount'=>'Total posted/pending settlement drafts exceed original opening invoice.']);
            $journalCode=$cash->code==='1120'?'BANK':'CASH';
            $journalId=DB::table('accounting_journals')->where('code',$journalCode)->where('is_active',true)->value('id');
            if (!$journalId) throw ValidationException::withMessages(['journal'=>'Cash/Bank journal missing.']);
            $value=AccountingMoney::format($amount);
            $lines=$item->side==='AR'
                ? [['account_id'=>$cash->id,'debit'=>$value,'credit'=>'0','allow_control'=>true],
                   ['account_id'=>$control,'debit'=>'0','credit'=>$value,'allow_control'=>true]]
                : [['account_id'=>$control,'debit'=>$value,'credit'=>'0','allow_control'=>true],
                   ['account_id'=>$cash->id,'debit'=>'0','credit'=>$value,'allow_control'=>true]];
            $id=$this->posting->createDraft(['journal_id'=>$journalId,'entry_date'=>$d['paid_on'],
                'origin'=>'OPENING_SETTLEMENT','reference'=>$item->side.':'.$item->invoice_reference,
                'narration'=>'Opening '.$item->side.' invoice '.$item->invoice_reference.' settlement: '.$d['evidence'],
                'created_by'=>auth()->id()],$lines);
            DB::table('accounting_opening_allocations')->insert([
                'opening_item_id'=>$itemId,'journal_entry_id'=>$id,'amount'=>$value,
                'evidence'=>$d['evidence'],'created_by'=>auth()->id(),
                'created_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('opening_allocation',$itemId,'PREPARE_DRAFT',null,$d);
            return $id;
        },3);
        return redirect()->route('accounting.journals.show',$id)
            ->with('success','Opening receivable/payable settlement created as a controlled DRAFT. Independent posting required.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function approveCutover(int $id,Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-cutover-approve');
        $d=$r->validate(['reason'=>'required|string|min:8|max:1000']);
        DB::transaction(function() use($id,$d) {
            $cutover=DB::table('accounting_cutovers')->where('id',$id)->lockForUpdate()->first();
            if (!$cutover || $cutover->status!=='PREPARED') throw ValidationException::withMessages(['cutover'=>'Cutover is not awaiting sign-off.']);
            if ((int)$cutover->created_by===(int)auth()->id())
                throw ValidationException::withMessages(['cutover'=>'Independent finance approver required.']);
            if (DB::table('accounting_cutovers')->where('status','ACTIVE')->exists())
                throw ValidationException::withMessages(['cutover'=>'An active cutover already exists.']);
            $entry=DB::table('journal_entries')->find($cutover->opening_journal_entry_id);
            if (!$entry || $entry->status!=='POSTED' || $entry->origin!=='OPENING' || $entry->entry_date!==$cutover->starts_on)
                throw ValidationException::withMessages(['cutover'=>'Original opening journal must be POSTED on the cutover date.']);
            // Opening AR/AP must tie to explicit invoice-level subledgers before activation.
            foreach (['AR'=>'1200','AP'=>'2100'] as $side=>$code) {
                $accountId=DB::table('chart_of_accounts')->where('code',$code)->value('id');
                $lines=DB::table('journal_lines')->where('journal_entry_id',$entry->id)
                    ->where('account_id',$accountId)->get();
                $gl=0;
                foreach($lines as $line) $gl+= $side==='AR'
                    ? $this->units($line->debit)-$this->units($line->credit)
                    : $this->units($line->credit)-$this->units($line->debit);
                $items=DB::table('accounting_opening_items')->where('cutover_id',$id)->where('side',$side)->pluck('amount');
                $detail=0;foreach($items as $amount) $detail+=$this->units($amount);
                if ($gl!==$detail) throw ValidationException::withMessages(['cutover'=>
                    'Opening '.$side.' GL amount does not match invoice-level register. Check signed trial balance and opening items.']);
            }
            // A previous posted automatic source journal would make the opening journal double-count history.
            if (DB::table('journal_entries')->where('status','POSTED')->where('origin','SOURCE')->where('entry_date','<',$cutover->starts_on)->exists())
                throw ValidationException::withMessages(['cutover'=>'Historical source journals already posted before cutover. Reconcile duplicates before activation.']);
            DB::table('accounting_cutovers')->where('id',$id)->update(['status'=>'ACTIVE',
                'approved_by'=>auth()->id(),'approved_at'=>now(),'updated_at'=>now()]);
            AccountingAuditService::record('cutover',$id,'ACTIVATE',null,['starts_on'=>$cutover->starts_on],$d['reason']);
        },3);
        return back()->with('success','Cutover signed. Source events before this date are now blocked.');
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
