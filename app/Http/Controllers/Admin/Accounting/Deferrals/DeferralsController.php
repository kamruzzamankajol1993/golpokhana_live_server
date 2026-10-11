<?php
namespace App\Http\Controllers\Admin\Accounting\Deferrals;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingMoney,JournalPostingService,AccountingAuditService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Explicit accountant scheduling: never silently auto-posts deferrals, asset depreciation or loan payments. */

/** Deferrals accounting module. A shared base provides posting dependencies and invariant checks. */
final class DeferralsController extends \App\Http\Controllers\Admin\Accounting\Shared\ExtendedSchedulesController
{
    public function deferrals()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->gate('accounting-view');
        return view('admin.accounting.deferrals.index',[
            'schedules'=>DB::table('accounting_deferral_schedules')->orderByDesc('id')->paginate(20),
            'lines'=>DB::table('accounting_deferral_lines as l')->join('accounting_deferral_schedules as s','s.id','=','l.schedule_id')
                ->leftJoin('journal_entries as j','j.id','=','l.journal_entry_id')
                ->select('l.*','s.name','j.status as journal_status')->orderByDesc('l.period_month')->paginate(20, ['*'], 'acc_deferrals_1')->withQueryString(),
            'accounts'=>DB::table('chart_of_accounts')->where('is_header',false)->where('is_active',true)->orderBy('code')->get(),
        ]);
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function createDeferral(Request $r)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

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
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function generateDeferral(int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

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
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
