<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingCloseService,AccountingMoney,AccountingReportService,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountingClosingController extends Controller
{
    public function __construct(private AccountingCloseService $closing, private AccountingReportService $reports,
        private JournalPostingService $posting) {}

    private function can(string $p): void { abort_unless(auth()->user()?->can($p),403); }
    private function units(mixed $s): int { return AccountingMoney::units((string)$s); }

    public function cutover()
    {
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
        return view('admin.accounting.reports.cutover',compact('cutovers','accounts','cashAccounts','openingItems','allocations'));
    }

    public function prepareCutover(Request $r)
    {
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
    }

    public function addOpeningItem(Request $r,int $id)
    {
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
    }

    public function allocateOpening(Request $r,int $itemId)
    {
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
    }

    public function approveCutover(int $id,Request $r)
    {
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
    }

    public function budgets(Request $r)
    {
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
        return view('admin.accounting.reports.budgets',compact('years','budgets','budget','periods','accounts','centers','rows','budgetTotal','actualTotal'));
    }

    private function budgetActual(object $line): int
    {
        $q=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
            ->where('j.status','POSTED')->where('j.origin','!=','CLOSING')->where('l.account_id',$line->account_id)
            ->whereBetween('j.entry_date',[$line->starts_on,$line->ends_on]);
        if ($line->cost_center_id) {
            $q->leftJoin('cost_center_allocations as c','c.journal_line_id','=','l.id')
                ->where(function($w) use($line) {
                    $w->where('c.cost_center_id',$line->cost_center_id)
                        ->orWhere(function($inner) use($line) {
                            $inner->whereNull('c.id')->where('l.cost_center_id',$line->cost_center_id);
                        });
                });
            $raw=$q->selectRaw('COALESCE(SUM((CASE WHEN c.id IS NOT NULL THEN c.percentage/100 ELSE 1 END) * (CASE WHEN ? = ? THEN l.credit-l.debit ELSE l.debit-l.credit END)),0) as actual',[$line->type,'income'])->first();
        } else {
            $raw=$q->selectRaw('COALESCE(SUM(CASE WHEN ? = ? THEN l.credit-l.debit ELSE l.debit-l.credit END),0) as actual',[$line->type,'income'])->first();
        }
        // SQL weighted aggregates may have up to 8 decimal places; four-decimal BDT rounding is required.
        return $this->signedUnits((string)$raw->actual);
    }

    private function signedUnits(string $s): int
    {
        $negative=str_starts_with($s,'-');$s=ltrim($s,'+-');
        // Financial aggregates are decimal strings. Round half up to 4 decimal places, never via float.
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/',$s,$m)) return 0;
        $fraction=str_pad($m[2]??'',5,'0');
        $v=((int)$m[1])*10000+(int)substr($fraction,0,4)+(int)($fraction[4]>='5');
        return $negative?-$v:$v;
    }

    public function createBudget(Request $r)
    {
        $this->can('accounting-budget');
        $d=$r->validate(['fiscal_year_id'=>'required|exists:fiscal_years,id',
            'name'=>'required|string|min:3|max:150']);
        $id=DB::table('accounting_budgets')->insertGetId(['fiscal_year_id'=>$d['fiscal_year_id'],
            'name'=>$d['name'],'status'=>'DRAFT','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        return redirect()->route('accounting.budgets',['budget_id'=>$id])->with('success','Budget draft created.');
    }
    public function saveBudgetLine(Request $r,int $id)
    {
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
    }

    public function approveBudget(int $id,Request $r)
    {
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
    }

    public function closingReview(Request $r)
    {
        $this->can('accounting-view');
        $periods=DB::table('accounting_periods as p')->join('fiscal_years as f','f.id','=','p.fiscal_year_id')
            ->select('p.*','f.name as fiscal_year_name')->orderByDesc('p.starts_on')->get();
        $selected=$r->integer('period_id') ?: ($periods->first()->id??0);
        $period=$selected?DB::table('accounting_periods')->find($selected):null;
        $checks=$period?$this->closing->checks($period):[];
        $years=DB::table('fiscal_years')->orderByDesc('starts_on')->get();
        $runs=DB::table('accounting_closing_runs')->orderByDesc('id')->limit(25)->get();
        return view('admin.accounting.reports.closing',compact('periods','period','checks','years','runs'));
    }

    public function yearEnd(Request $r,int $yearId)
    {
        $this->can('accounting-year-end');$this->can('accounting-journal-create');
        $r->validate(['confirmation'=>'required|in:CREATE_CLOSING_DRAFT']);
        $id=$this->closing->yearEndDraft($yearId,auth()->id());
        return redirect()->route('accounting.journals.show',$id)->with('success','Year-end transfer DRAFT only. Independent approval and POST are required.');
    }

    public function refreshYearEnd(Request $r,int $yearId)
    {
        $this->can('accounting-year-end');$this->can('accounting-journal-create');
        $d=$r->validate(['reason'=>'required|string|min:8|max:1000']);
        $id=$this->closing->refreshYearEndDraft($yearId,auth()->id(),$d['reason']);
        return redirect()->route('accounting.journals.show',$id)
            ->with('success','Year-end DRAFT reconciled to latest posted GL, with audit. Submit and approve again.');
    }

    public function finishYear(Request $r,int $yearId)
    {
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
    }
}
