<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanRepayment;
use App\Models\PayrollItem;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRepayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class PayrollRecoveryService
{
    public function preview(Employee $employee, Carbon $payrollMonth): array
    {
        $month = $payrollMonth->copy()->startOfMonth();
        $allocations = collect();

        $advances = SalaryAdvance::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereDate('recovery_start_month', '<=', $month)
            ->withSum('repayments', 'amount')
            ->orderBy('recovery_start_month')->orderBy('id')->get();

        foreach ($advances as $advance) {
            $outstanding = max(0, round((float) $advance->amount - (float) ($advance->repayments_sum_amount ?? 0), 2));
            if ($outstanding <= 0) continue;
            $installment = (float) $advance->installment_amount;
            $amount = min($outstanding, $installment > 0 ? $installment : $outstanding);
            $allocations->push([
                'source_type' => 'salary_advance',
                'source_id' => $advance->id,
                'amount' => round($amount, 2),
                'label' => 'Salary Advance',
            ]);
        }

        $loans = EmployeeLoan::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereDate('repayment_start_month', '<=', $month)
            ->withSum('repayments', 'amount')
            ->orderBy('repayment_start_month')->orderBy('id')->get();

        foreach ($loans as $loan) {
            $outstanding = max(0, round((float) $loan->total_payable - (float) ($loan->repayments_sum_amount ?? 0), 2));
            if ($outstanding <= 0) continue;
            $installment = (float) $loan->installment_amount;
            $amount = min($outstanding, $installment > 0 ? $installment : $outstanding);
            $allocations->push([
                'source_type' => 'loan',
                'source_id' => $loan->id,
                'amount' => round($amount, 2),
                'label' => 'Loan Adjustment',
            ]);
        }

        return [
            'allocations' => $allocations->values()->all(),
            'salary_advance' => round((float) $allocations->where('source_type', 'salary_advance')->sum('amount'), 2),
            'loan' => round((float) $allocations->where('source_type', 'loan')->sum('amount'), 2),
        ];
    }

    public function post(PayrollItem $item, Carbon $paymentDate, ?int $userId): void
    {
        $allocations = $item->recoveryAllocations()->whereNull('posted_at')->lockForUpdate()->get();

        foreach ($allocations as $allocation) {
            if ($allocation->source_type === 'salary_advance') {
                $advance = SalaryAdvance::query()->withSum('repayments', 'amount')->lockForUpdate()->findOrFail($allocation->source_id);
                $outstanding = max(0, round((float) $advance->amount - (float) ($advance->repayments_sum_amount ?? 0), 2));
                if ((float) $allocation->amount > $outstanding + 0.009) {
                    throw new RuntimeException('Salary advance balance changed. Regenerate this employee payroll before payment.');
                }
                $repayment = SalaryAdvanceRepayment::create([
                    'salary_advance_id' => $advance->id,
                    'payroll_item_id' => $item->id,
                    'payment_date' => $paymentDate,
                    'amount' => $allocation->amount,
                    'source' => 'payroll',
                    'reference_number' => $item->payrollRun?->payroll_code,
                    'notes' => 'Automatically recovered from payroll.',
                    'created_by' => $userId,
                ]);
                $allocation->update(['posted_at' => now(), 'repayment_id' => $repayment->id]);
                $this->syncAdvanceStatus($advance->fresh());
            } elseif ($allocation->source_type === 'loan') {
                $loan = EmployeeLoan::query()->withSum('repayments', 'amount')->lockForUpdate()->findOrFail($allocation->source_id);
                $outstanding = max(0, round((float) $loan->total_payable - (float) ($loan->repayments_sum_amount ?? 0), 2));
                if ((float) $allocation->amount > $outstanding + 0.009) {
                    throw new RuntimeException('Loan balance changed. Regenerate this employee payroll before payment.');
                }
                $repayment = LoanRepayment::create([
                    'employee_loan_id' => $loan->id,
                    'payroll_item_id' => $item->id,
                    'payment_date' => $paymentDate,
                    'amount' => $allocation->amount,
                    'source' => 'payroll',
                    'reference_number' => $item->payrollRun?->payroll_code,
                    'notes' => 'Automatically recovered from payroll.',
                    'created_by' => $userId,
                ]);
                $allocation->update(['posted_at' => now(), 'repayment_id' => $repayment->id]);
                $this->syncLoanStatus($loan->fresh());
            }
        }
    }

    public function syncAdvanceStatus(SalaryAdvance $advance): void
    {
        if ($advance->status === 'cancelled') return;
        $paid = (float) $advance->repayments()->sum('amount');
        $advance->update(['status' => $paid + 0.009 >= (float) $advance->amount ? 'completed' : 'active']);
    }

    public function syncLoanStatus(EmployeeLoan $loan): void
    {
        if ($loan->status === 'cancelled') return;
        $paid = (float) $loan->repayments()->sum('amount');
        $loan->update(['status' => $paid + 0.009 >= (float) $loan->total_payable ? 'completed' : 'active']);
    }
}
