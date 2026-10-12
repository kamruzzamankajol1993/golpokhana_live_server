<?php
namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingMoney,JournalPostingService,AccountingAuditService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit accountant scheduling: never silently auto-posts deferrals, asset depreciation or loan payments. */
final class ExtendedSchedulesController extends Controller
{
    public function __construct(private JournalPostingService $posting) {}
    private function gate(string $name): void { abort_unless(auth()->user()?->can($name),403); }
    private function u(mixed $n): int { return AccountingMoney::units((string)($n??'0')); }
    private function f(int $u): string { return AccountingMoney::format($u); }
    private function leaf(int $id,string $type): object
    {
        $a=DB::table('chart_of_accounts')->where('id',$id)->where('is_header',false)->where('is_active',true)->first();
        if (!$a || $a->type!==$type) throw ValidationException::withMessages(['account'=>'Account must be active '.$type.' posting account.']);
        return $a;
    }
    private function posted(int $id): object
    {
        $entry=DB::table('journal_entries')->find($id);
        if (!$entry || $entry->status!=='POSTED') throw ValidationException::withMessages(['source_journal_entry_id'=>'Only POSTED source journals qualify.']);
        return $entry;
    }
    private function journal(string $code): int
    {
        $id=DB::table('accounting_journals')->where('code',$code)->where('is_active',true)->value('id');
        if (!$id) throw ValidationException::withMessages(['journal'=>'Journal is not configured.']);
        return $id;
    }
    private function line(int $account,int $dr,int $cr,string $memo): array
    { return ['account_id'=>$account,'debit'=>$this->f($dr),'credit'=>$this->f($cr),'memo'=>$memo]; }
    private function draft(string $date,string $reference,string $memo,array $lines,string $journal='ADJ'): int
    {
        return $this->posting->createDraft(['entry_date'=>$date,'journal_id'=>$this->journal($journal),
            'reference'=>$reference,'narration'=>$memo,'created_by'=>auth()->id()],$lines);
    }
    private function validateSourceValue(int $id,int $accountId,int $max,int $sign=1): void
    {
        $this->posted($id);
        $lines=DB::table('journal_lines')->where('journal_entry_id',$id)->where('account_id',$accountId)->get();
        $posted=0;
        foreach($lines as $l) $posted+= $this->u($l->debit)-$this->u($l->credit);
        if ($posted*$sign<$max) throw ValidationException::withMessages(['total_amount'=>'Source journal amount is insufficient for the proposed schedule.']);
    }
    public function deferrals()
    {
        $this->gate('accounting-view');
        return view('admin.accounting.extended.deferrals',[
            'schedules'=>DB::table('accounting_deferral_schedules')->orderByDesc('id')->paginate(20),
            'lines'=>DB::table('accounting_deferral_lines as l')->join('accounting_deferral_schedules as s','s.id','=','l.schedule_id')
                ->leftJoin('journal_entries as j','j.id','=','l.journal_entry_id')
                ->select('l.*','s.name','j.status as journal_status')->orderByDesc('l.period_month')->limit(80)->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->orderBy('code')->get(),
        ]);
    }
    public function createDeferral(Request $r)
    {
        $this->gate('accounting-deferrals');
        $d=$r->validate(['type'=>'required|in:PREPAYMENT,DEFERRED_REVENUE',
            'name'=>'required|string|max:160','source_journal_entry_id'=>'required|exists:journal_entries,id',
            'balance_account_id'=>'required|exists:chart_of_accounts,id',
            'recognition_account_id'=>'required|exists:chart_of_accounts,id',
            'total_amount'=>'required|numeric|gt:0','start_month'=>'required|date_format:Y-m-d',
            'months'=>'required|integer|min:1|max:240']);
        $kind=$d['type']==='PREPAYMENT'?'asset':'liability';
        $this->leaf($d['balance_account_id'],$kind);
        $this->leaf($d['recognition_account_id'],$d['type']==='PREPAYMENT'?'expense':'income');
        $amount=$this->u($d['total_amount']);
        if($amount < (int)$d['months']) throw ValidationException::withMessages(['total_amount'=>'Amount is too small for this many 4-decimal monthly entries.']);
        $this->validateSourceValue($d['source_journal_entry_id'],$d['balance_account_id'],$amount,$d['type']==='PREPAYMENT'?1:-1);
        if(Carbon::parse($d['start_month'])->day!==1) throw ValidationException::withMessages(['start_month'=>'Start month must be the first day of the month.']);
        DB::transaction(function() use($d,$amount){
            $existing=DB::table('accounting_deferral_schedules')->where('source_journal_entry_id',$d['source_journal_entry_id'])
                ->where('balance_account_id',$d['balance_account_id'])->sum('total_amount');
            $this->validateSourceValue($d['source_journal_entry_id'],$d['balance_account_id'],$amount+$this->u($existing),$d['type']==='PREPAYMENT'?1:-1);
            $id=DB::table('accounting_deferral_schedules')->insertGetId(array_merge($d,['total_amount'=>$this->f($amount),
                'created_by'=>auth()->id(),'status'=>'ACTIVE','created_at'=>now(),'updated_at'=>now()]));
            $base=intdiv($amount,(int)$d['months']); $rem=$amount-$base*$d['months'];
            for($i=0;$i<$d['months'];$i++) DB::table('accounting_deferral_lines')->insert([
                'schedule_id'=>$id,'period_month'=>Carbon::parse($d['start_month'])->addMonthsNoOverflow($i)->toDateString(),
                'amount'=>$this->f($base+($i===$d['months']-1?$rem:0)),
                'created_at'=>now(),'updated_at'=>now()]);
        },3);
        return back()->with('success','Schedule created. Each recognition needs a separate reviewed journal.');
    }
    public function generateDeferral(int $id)
    {
        $this->gate('accounting-deferrals');
        $journal=DB::transaction(function() use($id){
            $line=DB::table('accounting_deferral_lines')->where('id',$id)->lockForUpdate()->first();
            if (!$line || $line->journal_entry_id) throw ValidationException::withMessages(['period'=>'Recognition already generated.']);
            $s=DB::table('accounting_deferral_schedules')->find($line->schedule_id);
            if (!$s || $s->status!=='ACTIVE') throw ValidationException::withMessages(['schedule'=>'Inactive schedule.']);
            $amount=$this->u($line->amount);
            $date=Carbon::parse($line->period_month)->endOfMonth()->toDateString();
            $lines=$s->type==='PREPAYMENT' ? [
                $this->line($s->recognition_account_id,$amount,0,'Expense recognized'),
                $this->line($s->balance_account_id,0,$amount,'Prepaid asset released')
            ] : [
                $this->line($s->balance_account_id,$amount,0,'Deferred liability released'),
                $this->line($s->recognition_account_id,0,$amount,'Revenue recognized')
            ];
            $id=$this->draft($date,'DEF-'.$s->id.'-'.$line->period_month,$s->name,$lines);
            DB::table('accounting_deferral_lines')->where('id',$line->id)->update(['journal_entry_id'=>$id,'updated_at'=>now()]);
            return $id;
        },3);
        return redirect()->route('accounting.journals.show',$journal)->with('success','Deferral DRAFT created; independent approval/post required.');
    }

    public function assets()
    {
        $this->gate('accounting-view');
        return view('admin.accounting.extended.assets',[
            'policy'=>DB::table('accounting_asset_policies')->where('id',1)->first(),
            'assets'=>DB::table('fixed_assets as a')->join('asset_categories as c','c.id','=','a.asset_category_id')
                ->select('a.*','c.name as category')->orderByDesc('a.id')->paginate(20),
            'depreciations'=>DB::table('asset_depreciations')->orderByDesc('period_month')->limit(80)->get(),
            'categories'=>DB::table('asset_categories')->where('is_active',true)->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->get(),
        ]);
    }
    public function approveAssetPolicy(Request $r)
    {
        $this->gate('accounting-settings');
        $d=$r->validate(['approval_reference'=>'required|string|min:10|max:3000','is_approved'=>'required|boolean']);
        DB::transaction(function() use($d){
            $old=DB::table('accounting_asset_policies')->where('id',1)->lockForUpdate()->first();
            DB::table('accounting_asset_policies')->where('id',1)->update($d+[
                'approved_by'=>auth()->id(),'approved_at'=>$d['is_approved']?now():null,'updated_at'=>now()]);
            AccountingAuditService::record('asset_policy',1,'CHANGE',(array)$old,$d,$d['approval_reference']);
        });
        return back()->with('success','Asset policy updated with approval history.');
    }
    public function addAsset(Request $r)
    {
        $this->gate('accounting-assets');
        $d=$r->validate(['asset_category_id'=>'required|exists:asset_categories,id',
            'code'=>'required|string|max:60|unique:fixed_assets,code','name'=>'required|string|max:180',
            'source_journal_entry_id'=>'required|exists:journal_entries,id',
            'asset_account_id'=>'required|exists:chart_of_accounts,id',
            'accumulated_depreciation_account_id'=>'required|exists:chart_of_accounts,id',
            'depreciation_expense_account_id'=>'required|exists:chart_of_accounts,id',
            'cost'=>'required|numeric|gt:0','residual_value'=>'required|numeric|min:0',
            'useful_life_months'=>'required|integer|min:1|max:600','in_service_on'=>'required|date_format:Y-m-d']);
        $cost=$this->u($d['cost']);$residual=$this->u($d['residual_value']);
        if($cost-$residual<(int)$d['useful_life_months']) throw ValidationException::withMessages(['cost'=>'Depreciable cost must support a positive 4-decimal monthly charge.']);
        if($residual>=$cost) throw ValidationException::withMessages(['residual_value'=>'Residual must be lower than cost.']);
        $this->leaf($d['asset_account_id'],'asset');$this->leaf($d['accumulated_depreciation_account_id'],'asset');
        $this->leaf($d['depreciation_expense_account_id'],'expense');
        $this->validateSourceValue($d['source_journal_entry_id'],$d['asset_account_id'],$cost);
        if(DB::table('fixed_assets')->where('source_journal_entry_id',$d['source_journal_entry_id'])->exists())
            throw ValidationException::withMessages(['source_journal_entry_id'=>'Acquisition journal already linked to an asset.']);
        DB::table('fixed_assets')->insert($d+['created_by'=>auth()->id(),'status'=>'ACTIVE',
            'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Asset registered. Depreciation remains disabled until accountant policy approval.');
    }
    public function depreciation(Request $r,int $id)
    {
        $this->gate('accounting-assets');
        $d=$r->validate(['period_month'=>'required|date_format:Y-m-d']);
        if(!DB::table('accounting_asset_policies')->where('id',1)->where('is_approved',true)->exists())
            throw ValidationException::withMessages(['policy'=>'Depreciation requires approved accounting policy.']);
        $journal=DB::transaction(function() use($id,$d){
            $a=DB::table('fixed_assets')->where('id',$id)->lockForUpdate()->first();
            if (!$a || $a->status!=='ACTIVE') throw ValidationException::withMessages(['asset'=>'Asset is not active.']);
            $month=Carbon::parse($d['period_month']);
            if ($month->day!==1 || $month->lt(Carbon::parse($a->in_service_on)->startOfMonth()))
                throw ValidationException::withMessages(['period_month'=>'Invalid depreciation month.']);
            $elapsed=(int) Carbon::parse($a->in_service_on)->startOfMonth()->diffInMonths($month);
            if ($elapsed>=$a->useful_life_months) throw ValidationException::withMessages(['asset'=>'Useful life has ended.']);
            if(DB::table('asset_depreciations')->where('fixed_asset_id',$id)->where('period_month',$month->toDateString())->exists())
                throw ValidationException::withMessages(['asset'=>'Month already scheduled.']);
            $total=$this->u($a->cost)-$this->u($a->residual_value);
            $monthly=intdiv($total,$a->useful_life_months);
            $value=$elapsed===$a->useful_life_months-1 ? $total-$monthly*($a->useful_life_months-1) : $monthly;
            $j=$this->draft($month->copy()->endOfMonth()->toDateString(),'DEP-'.$id.'-'.$month->format('Ym'),
                'Monthly straight-line depreciation: '.$a->name,[
                    $this->line($a->depreciation_expense_account_id,$value,0,'Depreciation expense'),
                    $this->line($a->accumulated_depreciation_account_id,0,$value,'Accumulated depreciation')],'DEP');
            DB::table('asset_depreciations')->insert(['fixed_asset_id'=>$id,'period_month'=>$month->toDateString(),
                'amount'=>$this->f($value),'journal_entry_id'=>$j,'created_at'=>now(),'updated_at'=>now()]);
            return $j;
        },3);
        return redirect()->route('accounting.journals.show',$journal)->with('success','Depreciation DRAFT generated. Submit, approve and post separately.');
    }

    public function loans()
    {
        $this->gate('accounting-view');
        return view('admin.accounting.extended.loans',[
            'loans'=>DB::table('company_loans')->orderByDesc('id')->paginate(20),
            'payments'=>DB::table('company_loan_payments')->orderByDesc('id')->limit(80)->get(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->get(),
        ]);
    }
    public function createLoan(Request $r)
    {
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
    }
    public function repayLoan(Request $r,int $id)
    {
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
    }
}
