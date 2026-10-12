<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingAuditService,AccountingEventService,AccountingMoney,JournalPostingService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Phase 4: company-level registers. Only POSTED journals affect financial balances. */
final class ExtendedLedgerController extends Controller
{
    public function __construct(private JournalPostingService $posting, private AccountingEventService $events) {}
    private function gate(string $p='accounting-view'): void { abort_unless(auth()->user()?->can($p),403); }
    private function u(mixed $value): int { return AccountingMoney::units((string)($value??'0')); }
    private function f(int $value): string { return AccountingMoney::format($value); }
    private function account(int $id, ?string $type=null): object
    {
        $a=DB::table('chart_of_accounts')->find($id);
        if (!$a || !$a->is_active || $a->is_header || ($type && $a->type!==$type))
            throw ValidationException::withMessages(['account'=>'Select an active non-header '.($type?:'').' account.']);
        return $a;
    }
    private function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Required journal is not configured: '.$code]);
        return (int)$id;
    }
    private function line(int $id,int $dr,int $cr,string $memo=''): array
    { return ['account_id'=>$id,'debit'=>$this->f($dr),'credit'=>$this->f($cr),'memo'=>$memo]; }
    private function draft(string $date,string $ref,string $memo,array $lines,string $type='ADJ'): int
    { return $this->posting->createDraft(['entry_date'=>$date,'journal_id'=>$this->journal($type),
        'reference'=>$ref,'narration'=>$memo,'created_by'=>auth()->id()],$lines); }
    private function posted(int $id): object
    {
        $j=DB::table('journal_entries')->find($id);
        if (!$j || $j->status!=='POSTED') throw ValidationException::withMessages(['journal'=>'A POSTED source journal is required.']);
        return $j;
    }

    public function payroll()
    {
        $this->gate();
        return view('admin.accounting.extended.payroll',[
            'runs'=>DB::table('payroll_runs')->orderByDesc('id')->limit(50)->get(),
            'items'=>DB::table('payroll_items as i')->leftJoin('accounting_events as e',function($q){$q->on('e.source_id','=',DB::raw('CAST(i.id AS CHAR)'))->where('e.source_type','payroll_items')->where('e.event_type','PAYROLL_ACCRUED');})
                ->select('i.*','e.id as event_id','e.status as accounting_status')->whereIn('i.status',['approved','paid'])
                ->orderByDesc('i.id')->paginate(25),
            'advances'=>DB::table('salary_advances')->where('status','!=','cancelled')->orderByDesc('id')->limit(100)->get(),
            'loans'=>DB::table('employee_loans')->where('status','!=','cancelled')->orderByDesc('id')->limit(100)->get(),
            'fundings'=>DB::table('accounting_employee_funding')->orderByDesc('id')->limit(50)->get(),
            'manualRepayments'=>DB::table('salary_advance_repayments')->where('source','manual')->orderByDesc('id')->limit(40)->get(),
            'manualLoanRepayments'=>DB::table('loan_repayments')->where('source','manual')->orderByDesc('id')->limit(40)->get(),
        ]);
    }
    public function verifyFunding(Request $r)
    {
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
    }

    public function verifyRepayment(Request $r)
    {
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
    }

    public function dueTerms(Request $r)
    {
        $this->gate();
        $type=$r->input('type','AR'); if (!in_array($type,['AR','AP'],true)) $type='AR';
        $table=$type==='AR'?'customer_receivables':'vendor_payables';
        return view('admin.accounting.extended.due-terms',[
            'type'=>$type,'terms'=>DB::table('payment_terms')->where('is_active',true)->get(),
            'receivables'=>DB::table($table.' as b')->leftJoin('accounting_due_terms as t',function($q) use($type){
                $q->on('t.source_id','=','b.id')->where('t.source_type',$type);
            })->select('b.*','t.id as tracking_id','t.due_date as tracked_due_date','t.followup_status','t.next_followup_on')
                ->orderByDesc('b.id')->paginate(25)->withQueryString(),
            'followups'=>DB::table('payment_followups as f')->join('accounting_due_terms as t','t.id','=','f.accounting_due_term_id')
                ->where('t.source_type',$type)->select('f.*','t.source_id')->orderByDesc('f.id')->limit(40)->get(),
        ]);
    }
    public function saveTerms(Request $r)
    {
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
    }
    public function followup(Request $r)
    {
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
    }

    public function costCenters()
    {
        $this->gate();
        return view('admin.accounting.extended.cost-centers',[
            'centers'=>DB::table('cost_centers')->orderBy('code')->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->get(),
            'allocations'=>DB::table('cost_center_allocations as x')->join('journal_lines as l','l.id','=','x.journal_line_id')
                ->join('journal_entries as j','j.id','=','l.journal_entry_id')
                ->join('cost_centers as c','c.id','=','x.cost_center_id')
                ->select('x.*','j.number','j.status','c.name as center','l.debit','l.credit')->orderByDesc('x.id')->limit(40)->get(),
        ]);
    }
    public function saveCenter(Request $r)
    {
        $this->gate('accounting-cost-centers');
        $d=$r->validate(['code'=>'required|alpha_dash|max:32','name'=>'required|string|max:120','is_active'=>'required|boolean']);
        DB::table('cost_centers')->updateOrInsert(['code'=>$d['code']],$d+['created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Cost center saved; no separate financial ledger created.');
    }
    public function allocate(Request $r)
    {
        $this->gate('accounting-cost-centers');
        $r->merge(['shares'=>array_values(array_filter((array)$r->input('shares',[]),
            fn($share)=>is_array($share) && !empty($share['cost_center_id']) && ($share['percentage']??'')!==''))]);
        $d=$r->validate(['journal_line_id'=>'required|exists:journal_lines,id','shares'=>'required|array|min:1|max:30',
            'shares.*.cost_center_id'=>'required|exists:cost_centers,id',
            'shares.*.percentage'=>'required|numeric|gt:0|max:100']);
        DB::transaction(function() use($d){
            $line=DB::table('journal_lines as l')->join('journal_entries as j','j.id','=','l.journal_entry_id')
                ->where('l.id',$d['journal_line_id'])->select('j.status','l.id')->lockForUpdate()->first();
            if (!$line || $line->status!=='DRAFT') throw ValidationException::withMessages(['journal_line_id'=>'Allocation can only change on DRAFT journals.']);
            $sum=0;$ids=[];
            foreach($d['shares'] as $s) {
                $sum+=$this->u($s['percentage']); $ids[]=$s['cost_center_id'];
                if (!DB::table('cost_centers')->where('id',$s['cost_center_id'])->where('is_active',true)->exists())
                    throw ValidationException::withMessages(['shares'=>'Inactive cost center.']);
            }
            if (count(array_unique($ids))!==count($ids) || $sum!==1000000)
                throw ValidationException::withMessages(['shares'=>'Unique center allocations must total exactly 100%.']);
            DB::table('cost_center_allocations')->where('journal_line_id',$line->id)->delete();
            foreach($d['shares'] as $s) DB::table('cost_center_allocations')->insert([
                'journal_line_id'=>$line->id,'cost_center_id'=>$s['cost_center_id'],'percentage'=>$this->f($this->u($s['percentage'])),
                'created_at'=>now(),'updated_at'=>now()]);
            DB::table('journal_lines')->where('id',$line->id)->update(['cost_center_id'=>count($d['shares'])===1?$ids[0]:null,'updated_at'=>now()]);
        },3);
        return back()->with('success','100% analytic allocation saved; GL value unchanged.');
    }

    public function bank()
    {
        $this->gate();
        $accounts=DB::table('payment_accounts')->where('method','Bank')->where('active',true)->get();
        $lines=DB::table('bank_statement_lines as s')->join('payment_accounts as p','p.id','=','s.payment_account_id')
            ->select('s.*','p.name as account_name')->orderByDesc('s.id')->paginate(25);
        $journals=DB::table('journal_entries as j')->join('journal_lines as l','l.journal_entry_id','=','j.id')
            ->join('payment_accounts as p','p.gl_account_id','=','l.account_id')
            ->where('p.method','Bank')->where('j.status','POSTED')
            ->whereNotIn('j.id',DB::table('bank_statement_lines')->whereNotNull('matched_journal_entry_id')->select('matched_journal_entry_id'))
            ->select('j.id','j.number','j.entry_date','l.debit','l.credit','p.name')->orderByDesc('j.id')->limit(120)->get();
        return view('admin.accounting.extended.bank',compact('accounts','lines','journals'));
    }
    public function unmatchBank(Request $r,int $id)
    {
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
    }
    public function saveBankLine(Request $r)
    {
        $this->gate('accounting-bank-match');
        $d=$r->validate(['payment_account_id'=>'required|exists:payment_accounts,id','statement_date'=>'required|date_format:Y-m-d',
            'statement_reference'=>'required|string|max:180','direction'=>'required|in:IN,OUT',
            'amount'=>'required|numeric|gt:0','description'=>'nullable|string|max:2000']);
        if(!DB::table('payment_accounts')->where('id',$d['payment_account_id'])->where('method','Bank')->exists())
            throw ValidationException::withMessages(['payment_account_id'=>'Select a Bank account.']);
        $d['amount']=$this->f($this->u($d['amount']));
        DB::table('bank_statement_lines')->insert($d+['status'=>'UNMATCHED','created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Bank statement line recorded; GL has not changed.');
    }
    public function matchBank(Request $r,int $id)
    {
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
    }
}
