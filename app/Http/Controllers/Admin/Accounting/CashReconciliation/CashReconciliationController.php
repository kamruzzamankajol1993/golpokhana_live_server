<?php
namespace App\Http\Controllers\Admin\Accounting\CashReconciliation;

use App\Http\Controllers\Controller;
use App\Services\Accounting\{AccountingEventService,AccountingInventoryCostService,AccountingMoney,JournalPostingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** CashReconciliation accounting module. A shared base provides posting dependencies and invariant checks. */
final class CashReconciliationController extends \App\Http\Controllers\Admin\Accounting\Shared\OperationsController
{
    public function approveSession(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

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
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function sessions()
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

        $this->can('accounting-view');
        $sessions=DB::table('pos_sessions')->where('status','Closed')->orderByDesc('id')->paginate(20, ['*'], 'acc_cash_reconciliation_1')->withQueryString();
        $reconciliations=DB::table('cash_reconciliations')->whereIn('pos_session_id',$sessions->pluck('id'))->get()->keyBy('pos_session_id');
        return view('admin.accounting.cash-reconciliation.index',compact('sessions','reconciliations'));
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }

    public function reconcileSession(Request $r,int $id)
    {
        try {
            // Preserve the original accounting validation, permission and transaction flow.

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
    
        } catch (\Throwable $accountingException) {
            // Log unexpected failures and rethrow; never report a failed transaction as successful.
            $this->logAccountingException($accountingException, __METHOD__);
            throw $accountingException;
        }
    }
}
