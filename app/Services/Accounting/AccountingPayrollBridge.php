<?php
namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

/** Captures actual finalized HR source records, never changes HR amounts. */
final class AccountingPayrollBridge
{
    public function __construct(private AccountingEventService $events) {}
    private function units(mixed $v): int { return AccountingMoney::units((string)($v ?? '0')); }
    private function fmt(int $v): string { return AccountingMoney::format($v); }

    public function approvedItem(int $id): void
    {
        $item=DB::table('payroll_items')->find($id);
        if (!$item || !in_array($item->status,['approved','paid'],true) || !$item->approved_at) return;
        $gross=$this->units($item->gross_salary);
        $net=$this->units($item->net_salary);
        $deductions=$this->units($item->total_deduction);
        if ($gross<=0) return;
        $recoveries=DB::table('payroll_recovery_allocations')->where('payroll_item_id',$id)
            ->selectRaw('source_type, COALESCE(SUM(amount),0) AS total')->groupBy('source_type')->pluck('total','source_type');
        $advance=$this->units($recoveries['salary_advance']??0);
        $loan=$this->units($recoveries['loan']??0);
        $other=$deductions-$advance-$loan;
        $reason=$other<0 || $gross!==$net+$deductions
            ? 'Approved payroll gross / net / recovery amounts do not reconcile; verify payroll components.' : null;
        // HR recovery allocation must identify a funded advance/loan with a POSTED originating journal.
        foreach (DB::table('payroll_recovery_allocations')->where('payroll_item_id',$id)->get() as $r) {
            $type=$r->source_type==='loan' ? 'employee_loans' : 'salary_advances';
            if ($type==='employee_loans' && DB::table('employee_loans')->where('id',$r->source_id)->where('total_interest','>',0)->exists()) {
                $reason='Interest-bearing employee loan recovery requires approved principal/interest allocation; cannot credit all recovery to loan principal.';
                break;
            }
            if (!DB::table('accounting_events')->where('source_type',$type)->where('source_id',(string)$r->source_id)
                ->where('event_type',$type==='employee_loans'?'LOAN_DISBURSED':'ADVANCE_DISBURSED')->where('status','POSTED')->exists()) {
                $reason='Recovery references an employee advance/loan without a POSTED accounting disbursement; review before accounting recognition.';
                break;
            }
        }
        $snapshot=['total'=>$this->fmt($gross), 'amounts'=>[
            'gross'=>$this->fmt($gross),'net'=>$this->fmt($net),'advance_recovery'=>$this->fmt($advance),
            'loan_recovery'=>$this->fmt($loan),'other_deductions'=>$this->fmt(max(0,$other))],
            'source'=>['employee_id'=>$item->employee_id,'payroll_run_id'=>$item->payroll_run_id,
                'approval_time'=>$item->approved_at], 'manual_only'=>true];
        if ($reason) $snapshot['invalid_reason']=$reason;
        $this->events->capture('payroll_items',$id,'PAYROLL_ACCRUED',1,(string)$item->approved_at,null,$snapshot);
    }

    public function paidItem(int $id): void
    {
        $payment=DB::table('payroll_payments')->find($id);
        if (!$payment) return;
        $item=DB::table('payroll_items')->find($payment->payroll_item_id);
        if (!$item || $item->payment_status!=='paid') return;
        $amt=$this->units($payment->amount);
        if ($amt<=0) return;
        $method=(string)$payment->payment_method;
        $snapshot=['total'=>$this->fmt($amt),'amounts'=>[
            'salary_paid'=>$this->fmt($amt),'cash_paid'=>$this->fmt($method==='cash'?$amt:0),
            'bank_paid'=>$this->fmt($method==='bank'?$amt:0),
            'mfs_paid'=>$this->fmt($method==='mobile_banking'?$amt:0)],
            'source'=>['payroll_item_id'=>$payment->payroll_item_id,'employee_id'=>$item->employee_id,
                'payment_method'=>$method,'reference'=>$payment->reference_number], 'manual_only'=>true];
        if ($amt!==$this->units($item->net_salary)) $snapshot['invalid_reason']='Salary payment amount differs from approved net payroll.';
        $activated=DB::table('accounting_settings')->where('id',1)->value('activated_at');
        if ($activated && \Carbon\Carbon::parse((string)$payment->payment_date)->endOfDay()->lt(\Carbon\Carbon::parse($activated)))
            return; // Never capture a historical OFF-window payment merely because the HR row was entered later.

        $this->events->capture('payroll_payments',$id,'PAYROLL_PAID',1,
            (string)($payment->created_at??now()),(string)$payment->payment_date,$snapshot);
    }
}
