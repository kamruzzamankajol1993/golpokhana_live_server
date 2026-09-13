<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRepayment;
use App\Services\Hr\PayrollRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class SalaryAdvanceController extends Controller
{
    public function __construct(private PayrollRecoveryService $recoveryService)
    {
        $this->middleware('permission:salary-advance-view')->only('index');
        $this->middleware('permission:salary-advance-create')->only('store');
        $this->middleware('permission:salary-advance-edit')->only(['update', 'repay']);
        $this->middleware('permission:salary-advance-delete')->only('destroy');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = SalaryAdvance::with('employee.department')->withSum('repayments', 'amount')->latest('advance_date')->latest('id');
            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%"));
                });
            }
            if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
            if ($request->filled('status')) $query->where('status', $request->status);
            $advances = $query->paginate(10)->withQueryString();
            return view('admin.hr.salary_advances.table', compact('advances'))->render();
        }

        return view('admin.hr.salary_advances.index', [
            'employees' => Employee::active()->orderBy('employee_code')->get(['id','employee_code','name']),
            'activeCount' => SalaryAdvance::where('status', 'active')->count(),
            'totalIssued' => SalaryAdvance::where('status', '!=', 'cancelled')->sum('amount'),
            'totalRecovered' => SalaryAdvanceRepayment::sum('amount'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateAdvance($request);
        $data['created_by'] = auth()->id();
        $data['status'] = 'active';
        SalaryAdvance::create($data);
        return response()->json(['success' => true, 'message' => 'Salary advance created successfully.']);
    }

    public function update(Request $request, SalaryAdvance $salaryAdvance)
    {
        if ($salaryAdvance->repayments()->exists()) {
            return response()->json(['message' => 'An advance with recovery history cannot be edited. You can change future recovery by creating a new advance.'], 422);
        }
        $data = $this->validateAdvance($request);
        $salaryAdvance->update($data);
        return response()->json(['success' => true, 'message' => 'Salary advance updated successfully.']);
    }

    public function repay(Request $request, SalaryAdvance $salaryAdvance)
    {
        $validated = $request->validate([
            'payment_date' => ['required','date'], 'amount' => ['required','numeric','gt:0'],
            'reference_number' => ['nullable','string','max:180'], 'notes' => ['nullable','string','max:1000'],
        ]);
        $salaryAdvance->loadSum('repayments', 'amount');
        $outstanding = max(0, (float) $salaryAdvance->amount - (float) ($salaryAdvance->repayments_sum_amount ?? 0));
        if ((float) $validated['amount'] > $outstanding + .009) {
            return response()->json(['message' => 'Repayment cannot exceed the outstanding balance of ' . number_format($outstanding, 2) . '.'], 422);
        }
        DB::transaction(function () use ($validated, $salaryAdvance) {
            SalaryAdvanceRepayment::create([
                'salary_advance_id' => $salaryAdvance->id,
                'payment_date' => $validated['payment_date'], 'amount' => $validated['amount'], 'source' => 'manual',
                'reference_number' => $validated['reference_number'] ?? null, 'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $this->recoveryService->syncAdvanceStatus($salaryAdvance->fresh());
        });
        return response()->json(['success' => true, 'message' => 'Advance repayment recorded successfully.']);
    }

    public function destroy(SalaryAdvance $salaryAdvance)
    {
        if ($salaryAdvance->repayments()->exists()) return response()->json(['message' => 'Advance with recovery history cannot be deleted.'], 422);
        $salaryAdvance->delete();
        return response()->json(['message' => 'Salary advance deleted successfully.']);
    }

    private function validateAdvance(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required','exists:employees,id'], 'advance_date' => ['required','date'],
            'amount' => ['required','numeric','gt:0'], 'recovery_start_month' => ['required','date'],
            'installment_amount' => ['nullable','numeric','gt:0'], 'notes' => ['nullable','string','max:1000'],
        ]);
    }
}
