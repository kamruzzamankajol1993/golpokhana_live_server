<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanRepayment;
use App\Services\Hr\PayrollRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function __construct(private PayrollRecoveryService $recoveryService)
    {
        $this->middleware('permission:loan-view')->only('index');
        $this->middleware('permission:loan-create')->only('store');
        $this->middleware('permission:loan-edit')->only(['update', 'repay']);
        $this->middleware('permission:loan-delete')->only('destroy');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = EmployeeLoan::with('employee.department')
                ->withSum('repayments', 'amount')
                ->latest('loan_date')
                ->latest('id');

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($e) => $e
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%"));
                });
            }

            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $loans = $query->paginate(10)->withQueryString();

            return view('admin.hr.loans.table', compact('loans'))->render();
        }

        return view('admin.hr.loans.index', [
            'employees' => Employee::active()->orderBy('employee_code')->get(['id', 'employee_code', 'name']),
            'activeCount' => EmployeeLoan::where('status', 'active')->count(),
            'totalIssued' => EmployeeLoan::where('status', '!=', 'cancelled')->sum('principal_amount'),
            'totalRecovered' => LoanRepayment::sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedLoan($request);

        [$interest, $total] = $this->loanTotals(
            (float) $data['principal_amount'],
            $data['interest_type'],
            (float) ($data['interest_value'] ?? 0)
        );

        $data['total_interest'] = $interest;
        $data['total_payable'] = $total;
        $data['installment_amount'] = $this->monthlyInstallment($total, (int) $data['number_of_installments']);
        $data['created_by'] = auth()->id();
        $data['status'] = 'active';

        EmployeeLoan::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Loan created successfully.',
            'total_payable' => $total,
            'monthly_installment' => $data['installment_amount'],
        ]);
    }

    public function update(Request $request, EmployeeLoan $loan)
    {
        if ($loan->repayments()->exists()) {
            return response()->json(['message' => 'A loan with repayment history cannot be edited.'], 422);
        }

        $data = $this->validatedLoan($request);

        [$interest, $total] = $this->loanTotals(
            (float) $data['principal_amount'],
            $data['interest_type'],
            (float) ($data['interest_value'] ?? 0)
        );

        $data['total_interest'] = $interest;
        $data['total_payable'] = $total;
        $data['installment_amount'] = $this->monthlyInstallment($total, (int) $data['number_of_installments']);

        $loan->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Loan updated successfully.',
            'total_payable' => $total,
            'monthly_installment' => $data['installment_amount'],
        ]);
    }

    public function repay(Request $request, EmployeeLoan $loan)
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $loan->loadSum('repayments', 'amount');
        $outstanding = max(0, (float) $loan->total_payable - (float) ($loan->repayments_sum_amount ?? 0));

        if ((float) $validated['amount'] > $outstanding + .009) {
            return response()->json([
                'message' => 'Repayment cannot exceed the outstanding balance of '.number_format($outstanding, 2).'.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $loan) {
            LoanRepayment::create([
                'employee_loan_id' => $loan->id,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'source' => 'manual',
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->recoveryService->syncLoanStatus($loan->fresh());
        });

        return response()->json(['success' => true, 'message' => 'Loan repayment recorded successfully.']);
    }

    public function destroy(EmployeeLoan $loan)
    {
        if ($loan->repayments()->exists()) {
            return response()->json(['message' => 'Loan with repayment history cannot be deleted.'], 422);
        }

        $loan->delete();

        return response()->json(['message' => 'Loan deleted successfully.']);
    }

    private function validatedLoan(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'loan_date' => ['required', 'date'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'interest_type' => ['required', Rule::in(['none', 'flat_percent', 'fixed_amount'])],
            'interest_value' => ['nullable', 'numeric', 'min:0'],
            'number_of_installments' => ['required', 'integer', 'min:1', 'max:120'],
            'repayment_start_month' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function loanTotals(float $principal, string $type, float $value): array
    {
        $interest = match ($type) {
            'flat_percent' => round($principal * $value / 100, 2),
            'fixed_amount' => round($value, 2),
            default => 0.0,
        };

        return [$interest, round($principal + $interest, 2)];
    }

    private function monthlyInstallment(float $totalPayable, int $numberOfInstallments): float
    {
        return round($totalPayable / max(1, $numberOfInstallments), 2);
    }
}
