<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

final class OperationsController extends Controller
{
    public function __construct(private JournalPostingService $posting, private AccountingEventService $events,
                                private AccountingInventoryCostService $costing) {}
    private function can(string $permission): void { abort_unless(auth()->user()?->can($permission),403); }
    private function amount(mixed $raw): int { return AccountingMoney::units((string)$raw); }
    private function account(string $code): int
    {
        $id=DB::table('chart_of_accounts')->where('code',$code)->where('is_active',true)->where('is_header',false)->value('id');
        if (!$id) throw ValidationException::withMessages(['account'=>'Required accounting account '.$code.' is not configured.']);
        return (int)$id;
    }
    private function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Required journal '.$code.' is inactive.']);
        return (int)$id;
    }
    private function line(int $account,int $debit,int $credit,string $memo='',bool $control=false): array
    {
        return ['account_id'=>$account,'debit'=>AccountingMoney::format($debit),'credit'=>AccountingMoney::format($credit),
            'memo'=>$memo,'allow_control'=>$control];
    }
    public function vouchers()
    {
        $this->can('accounting-view');
        $vouchers=DB::table('accounting_vouchers as v')->join('journal_entries as j','j.id','=','v.journal_entry_id')
            ->select('v.*','j.status','j.entry_date','j.number','j.reference')->orderByDesc('v.id')->paginate(20);
        $accounts=DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->orderBy('code')->get();
        return view('admin.accounting.vouchers',compact('vouchers','accounts'));
    }
    public function storeVoucher(Request $r)
    {
        $this->can('accounting-voucher');
        $d=$r->validate([
            'voucher_type'=>'required|in:RV,PV,CV','entry_date'=>'required|date',
            'party_name'=>'nullable|string|max:150','reference'=>'nullable|string|max:100',
            'narration'=>'required|string|min:5|max:2000','payment_reference'=>'nullable|string|max:200',
            'lines'=>'required|array|min:2|max:30','lines.*.account_id'=>'required|exists:chart_of_accounts,id',
            'lines.*.debit'=>'required|numeric|min:0','lines.*.credit'=>'required|numeric|min:0',
        ]);
        $id=DB::transaction(function () use($d) {
            $code=$d['voucher_type']==='CV'?'BANK':($d['voucher_type']==='RV'?'CASH':'MISC');
            $lines=array_map(fn($l)=>['account_id'=>(int)$l['account_id'],
                'debit'=>AccountingMoney::format($this->amount($l['debit'])),
                'credit'=>AccountingMoney::format($this->amount($l['credit']))],$d['lines']);
            $this->posting->assertLines($lines);
            $id=$this->posting->createDraft(['journal_id'=>$this->journal($code),'entry_date'=>$d['entry_date'],
                'reference'=>$d['reference']??null,'narration'=>$d['narration'],'created_by'=>auth()->id()],$lines);
            DB::table('accounting_vouchers')->insert(['voucher_type'=>$d['voucher_type'],'journal_entry_id'=>$id,
                'party_name'=>$d['party_name']??null,'payment_reference'=>$d['payment_reference']??null,
                'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
        return redirect()->route('accounting.journals.show',$id)->with('success','Voucher created as DRAFT. Submit and obtain approval before posting.');
    }
    public function expenses()
    {
        $this->can('accounting-view');
        $expenses=DB::table('expenses as e')->join('expense_categories as c','c.id','=','e.expense_category_id')
            ->select('e.*','c.name as category')->orderByDesc('e.id')->paginate(20);
        $categories=DB::table('expense_categories')->where('is_active',true)->orderBy('name')->get();
        return view('admin.accounting.expenses',compact('expenses','categories'));
    }
    public function storeExpense(Request $r)
    {
        $this->can('accounting-expense');
        $d=$r->validate(['expense_category_id'=>'required|exists:expense_categories,id','expense_date'=>'required|date',
            'amount'=>'required|numeric|gt:0','description'=>'required|string|max:3000']);
        $amount=AccountingMoney::format($this->amount($d['amount']));
        DB::transaction(function () use($d,$amount) {
            $id=DB::table('expenses')->insertGetId([
                'expense_no'=>'EXP-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(4))),
                'expense_category_id'=>$d['expense_category_id'],'expense_date'=>$d['expense_date'],
                'amount'=>$amount,'description'=>$d['description'],'status'=>'DRAFT','created_by'=>auth()->id(),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
        return back()->with('success','Expense DRAFT saved. Approval does not automatically pay the expense.');
    }
    public function approveExpense(int $id)
    {
        $this->can('accounting-expense');
        $this->can('accounting-journal-approve');
        DB::transaction(function () use($id) {
            $e=DB::table('expenses')->where('id',$id)->lockForUpdate()->first();
            if (!$e || $e->status!=='DRAFT') throw ValidationException::withMessages(['expense'=>'Only DRAFT expense can be approved.']);
            if ((int)$e->created_by===(int)auth()->id()) throw ValidationException::withMessages(['expense'=>'Maker cannot approve own expense.']);
            DB::table('expenses')->where('id',$id)->update(['status'=>'APPROVED',
                'approved_by'=>auth()->id(),'approved_at'=>now(),'updated_at'=>now()]);
            $callback=function() use($id) {
                try { $this->captureExpense($id); }
                catch (\Throwable $e) { Log::error('Accounting expense capture needs manual review',['expense_id'=>$id,'reason'=>$e->getMessage()]); }
            };
            DB::afterCommit($callback);
        },3);
        return back()->with('success','Expense approved; if Capture ON, a controlled journal DRAFT is generated.');
    }
    public function captureExpense(int $id): void
    {
        $e=DB::table('expenses')->where('id',$id)->first();
        $s=DB::table('accounting_settings')->where('id',1)->first();
        if (!$e || $e->status!=='APPROVED' || !$s || !$s->capture_enabled || $e->journal_entry_id) return;
        $c=DB::table('expense_categories')->where('id',$e->expense_category_id)->first();
        if (!$c) return;
        $event=$this->events->capture('expenses',$id,'EXPENSE_APPROVED',1,(string)$e->approved_at,null,
            ['total'=>(string)$e->amount,'amounts'=>['expense'=>(string)$e->amount,
             'accrued_payable'=>(string)$e->amount],
             'source'=>['category'=>$c->name,'expense_no'=>$e->expense_no,'description'=>$e->description],
             'manual_only'=>true]);
        if (!$event) return;
        DB::transaction(function() use($event,$e,$c) {
            $lock=DB::table('expenses')->where('id',$e->id)->lockForUpdate()->first();
            if (!$lock || $lock->journal_entry_id) return;
            $amount=$this->amount($lock->amount);
            $id=$this->posting->createDraft(['journal_id'=>$this->journal('MISC'),
                'entry_date'=>$lock->expense_date,'reference'=>$lock->expense_no,
                'narration'=>$lock->description,'created_by'=>$lock->approved_by],
                [$this->line((int)$c->expense_account_id,$amount,0,'Expense'),
                 $this->line($this->account('2150'),0,$amount,'Accrued expense liability')],$event);
            DB::table('expenses')->where('id',$lock->id)->update(['journal_entry_id'=>$id,'updated_at'=>now()]);
            DB::table('accounting_events')->where('id',$event)->update([
                'journal_entry_id'=>$id,'status'=>'DRAFT','updated_at'=>now(),
            ]);
        },3);
    }
    public function attachExpense(Request $r,int $id)
    {
        $this->can('accounting-expense');
        $d=$r->validate(['attachment'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $e=DB::table('expenses')->where('id',$id)->first();
        if (!$e || !in_array($e->status,['DRAFT','APPROVED'],true)) {
            throw ValidationException::withMessages(['attachment'=>'Attachment allowed only before journal posting.']);
        }
        $path=$r->file('attachment')->store('accounting/expenses','local');
        DB::table('expense_attachments')->insert(['expense_id'=>$id,
            'filename'=>basename($r->file('attachment')->getClientOriginalName()),
            'path'=>$path,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Expense receipt attached securely.');
    }
    public function downloadExpenseAttachment(int $id)
    {
        $this->can('accounting-view');
        $item=DB::table('expense_attachments')->find($id);
        abort_unless($item,404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($item->path),404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($item->path,$item->filename);
    }
    public function approveSession(Request $r,int $id)
    {
        $this->can('accounting-reconcile');
        $d=$r->validate(['reason'=>'required|string|min:12|max:2000']);
        DB::transaction(function() use($id,$d) {
            $row=DB::table('cash_reconciliations')->where('pos_session_id',$id)->lockForUpdate()->first();
            if (!$row || $row->status!=='REVIEW') throw ValidationException::withMessages(['session'=>'No reconciliation awaiting approval.']);
            if ((int)$row->checked_by===(int)auth()->id()) throw ValidationException::withMessages(['session'=>'Reconciler cannot approve own cash check.']);
            $variance=(string)$row->variance;
            if (!in_array($variance,['0.0000','0','0.00'],true)) {
                throw ValidationException::withMessages(['variance'=>'Cash variance must be adjusted by a separately approved journal before approval.']);
            }
            DB::table('cash_reconciliations')->where('id',$row->id)->update([
                'status'=>'APPROVED','reason'=>(string)$row->reason."\nAPPROVED: ".$d['reason'],
                'updated_at'=>now(),
            ]);
        },3);
        return back()->with('success','Cash check approved by independent reviewer.');
    }
    public function costs()
    {
        $this->can('accounting-view');
        $pending=DB::table('negative_inventory_cost_pending as p')
            ->join('stock_movements as m','m.id','=','p.stock_movement_id')
            ->select('p.*','m.movement_type','m.movement_no')->orderByDesc('p.id')->paginate(25);
        $balances=DB::table('inventory_cost_balances as b')
            ->leftJoin('ingredients as i','i.id','=','b.ingredient_id')
            ->leftJoin('stock_locations as s','s.id','=','b.stock_location_id')
            ->select('b.*','i.name as ingredient','s.name as location')->orderBy('s.name')->limit(120)->get();
        return view('admin.accounting.costs',compact('pending','balances'));
    }
    public function verifyCost(Request $r,int $id)
    {
        $this->can('accounting-cost-review');
        $d=$r->validate(['verified_cost'=>'required|numeric|gt:0','reason'=>'required|string|min:10|max:3000']);
        $event=$this->costing->submitCostVerification($id,AccountingMoney::format($this->amount($d['verified_cost'])),
            $d['reason'],auth()->id());
        return back()->with('success','Cost verification submitted as event #'.$event.'. GL remains unchanged until journal approval and posting.');
    }
    public function clearing()
    {
        $this->can('accounting-view');
        $clearing=DB::table('payment_account_transactions as t')
            ->join('payment_accounts as a','a.id','=','t.payment_account_id')
            ->whereIn('a.method',['Card','MFS'])
            ->select('t.*','a.name as account_name','a.gl_account_id')->orderByDesc('t.id')->paginate(25);
        return view('admin.accounting.clearing',compact('clearing'));
    }
    public function settle(Request $r,int $transactionId)
    {
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
    }
    public function withholdings()
    {
        $this->can('accounting-view');
        $records=DB::table('accounting_withholdings as w')->leftJoin('purchases as p','p.id','=','w.purchase_id')
            ->select('w.*','p.purchase_no')->orderByDesc('w.id')->paginate(20);
        $purchases=DB::table('vendor_payables as vp')->join('purchases as p','p.id','=','vp.purchase_id')
            ->select('p.id','p.purchase_no','p.total')->orderByDesc('p.id')->limit(200)->get();
        return view('admin.accounting.withholdings',compact('records','purchases'));
    }
    public function storeWithholding(Request $r)
    {
        $this->can('accounting-reconcile');
        $d=$r->validate(['purchase_id'=>'required|exists:purchases,id','tax_type'=>'required|in:TDS,VDS',
            'amount'=>'required|numeric|gt:0','entry_date'=>'required|date',
            'reference'=>'required|string|max:200','evidence'=>'required|string|min:10|max:3000']);
        $id=DB::transaction(function() use($d) {
            if (DB::getSchemaBuilder()->hasColumn('vendor_payments','tds_amount') &&
                DB::table('vendor_payments')->where('purchase_id',$d['purchase_id'])
                    ->where(fn ($q)=>$q->where('tds_amount','>',0)->orWhere('vds_amount','>',0))->exists()) {
                throw ValidationException::withMessages(['purchase_id'=>'This purchase already has automatic vendor withholding. Do not create a second manual withholding entry.']);
            }
            $payable=DB::table('vendor_payables')->where('purchase_id',$d['purchase_id'])->first();
            if (!$payable) throw ValidationException::withMessages(['purchase_id'=>'Purchase AP must first be captured for accounting.']);
            if (!DB::table('accounting_events')->where('source_type','purchases')->where('source_id',(string)$d['purchase_id'])
                ->where('event_type','PURCHASE_RECEIVED')->where('status','POSTED')->exists()) {
                throw ValidationException::withMessages(['purchase_id'=>'Supplier purchase journal must be POSTED before withholding.']);
            }
            if (DB::table('accounting_withholdings')->where('purchase_id',$d['purchase_id'])
                ->where('tax_type',$d['tax_type'])->where('reference',$d['reference'])->exists()) {
                throw ValidationException::withMessages(['reference'=>'Withholding already recorded for this reference.']);
            }
            $amount=$this->amount($d['amount']);
            $paid=$this->amount(DB::table('payable_allocations')->where('vendor_payable_id',$payable->id)->sum('amount'));
            $withheld=$this->amount(DB::table('accounting_withholdings')->where('purchase_id',$d['purchase_id'])->sum('amount'));
            $credits=$this->amount(DB::table('accounting_supplier_credit_notes')->where('purchase_id',$d['purchase_id'])->sum('credit_amount'));
            if ($amount>$this->amount($payable->original_amount)-$paid-$withheld-$credits) {
                throw ValidationException::withMessages(['amount'=>'Withholding exceeds remaining accounting supplier payable.']);
            }
            $j=$this->posting->createDraft(['journal_id'=>$this->journal('PUR'),
                'entry_date'=>$d['entry_date'],'reference'=>$d['reference'],
                'narration'=>$d['tax_type'].' withholding: '.$d['evidence'],
                'origin'=>'SOURCE','created_by'=>auth()->id()],
                [$this->line($this->account('2100'),$amount,0,'Supplier payable settled by tax withholding',true),
                 $this->line($this->account($d['tax_type']==='TDS'?'2141':'2142'),0,$amount,$d['tax_type'].' withheld')]);
            DB::table('accounting_withholdings')->insert(['purchase_id'=>$d['purchase_id'],
                'tax_type'=>$d['tax_type'],'amount'=>AccountingMoney::format($amount),
                'reference'=>$d['reference'],'evidence'=>$d['evidence'],
                'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now()]);
            return $j;
        });
        return redirect()->route('accounting.journals.show',$id)->with('success','Withholding adjustment DRAFT created; accountant approval required.');
    }
    public function sessions()
    {
        $this->can('accounting-view');
        $sessions=DB::table('pos_sessions')->where('status','Closed')->orderByDesc('id')->limit(80)->get();
        $reconciliations=DB::table('cash_reconciliations')->whereIn('pos_session_id',$sessions->pluck('id'))->get()->keyBy('pos_session_id');
        return view('admin.accounting.sessions',compact('sessions','reconciliations'));
    }
    public function reconcileSession(Request $r,int $id)
    {
        $this->can('accounting-reconcile');
        $d=$r->validate(['counted_cash'=>'required|numeric|min:0','reason'=>'required|string|min:10|max:2000']);
        $s=DB::table('pos_sessions')->find($id);
        if (!$s || $s->status!=='Closed' || !$s->end_time) throw ValidationException::withMessages(['session'=>'Close the session before reconciliation.']);
        // Posted source events only; no duplicate count of later due receipts in the order's cumulative paid fields.
        $cash=0;
        $events=DB::table('accounting_events')->where('status','POSTED')
            ->whereBetween('occurred_at',[$s->start_time,$s->end_time])
            ->whereIn('event_type',['SALES_COMPLETED','AR_SETTLEMENT','BOOKING_ADVANCE_RECEIVED','TIPS_RECEIVED'])
            ->get();
        foreach ($events as $event) {
            $snapshot=json_decode((string)$event->snapshot,true);
            $cash+=$this->amount($snapshot['amounts']['cash_paid']??$snapshot['amounts']['cash_tips']??'0');
        }
        $expected=$cash+$this->amount($s->opening_balance);
        $counted=$this->amount($d['counted_cash']); $variance=$counted-$expected;
        $value=$variance>=0 ? AccountingMoney::format($variance) : '-'.AccountingMoney::format(-$variance);
        $existing=DB::table('cash_reconciliations')->where('pos_session_id',$id)->first();
        if ($existing && $existing->status==='APPROVED') {
            throw ValidationException::withMessages(['session'=>'Approved cash reconciliations are immutable. Use an audited adjustment workflow for corrections.']);
        }
        DB::table('cash_reconciliations')->updateOrInsert(['pos_session_id'=>$id],[
            'expected_cash'=>AccountingMoney::format($expected),'counted_cash'=>AccountingMoney::format($counted),
            'variance'=>$value,'status'=>'REVIEW','reason'=>$d['reason'],
            'checked_by'=>auth()->id(),'updated_at'=>now(),'created_at'=>now(),
        ]);
        return back()->with('success','Session cash review saved. Expected cash excludes unposted items and unverifiable drawer withdrawals. Variance requires a separate approved adjustment.');
    }
}
