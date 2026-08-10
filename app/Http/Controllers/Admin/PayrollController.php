<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\RestaurantSetting;
use App\Services\Hr\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

class PayrollController extends Controller
{
    public function __construct(private PayrollCalculator $calculator)
    {
        $this->middleware('permission:payroll-view')->only([
            'index', 'show', 'itemsTable', 'itemShow', 'summaryPdf', 'precheck', 'singlePrecheck',
        ]);
        $this->middleware('permission:payroll-payslip')->only('payslip');
        $this->middleware('permission:payroll-create')->only([
            'create', 'store', 'regenerate', 'createSingle', 'storeSingle',
        ]);
        $this->middleware('permission:payroll-edit')->only('updateItem');
        $this->middleware('permission:payroll-approve')->only(['approve', 'approveItem']);
        $this->middleware('permission:payroll-pay')->only(['payItem', 'payAll']);
        $this->middleware('permission:payroll-delete')->only('destroy');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = PayrollRun::with('generatedBy')
                ->withCount([
                    'items',
                    'items as non_draft_items_count' => fn ($itemQuery) => $itemQuery->where('status', '!=', 'draft'),
                ])
                ->latest('payroll_month');

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($builder) use ($search) {
                    $builder->where('payroll_code', 'like', "%{$search}%")
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery->where('employee_name', 'like', "%{$search}%")
                                ->orWhere('employee_code', 'like', "%{$search}%");
                        });
                });
            }

            if ($request->filled('month')) {
                $query->whereDate('payroll_month', Carbon::createFromFormat('Y-m', $request->month)->startOfMonth());
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $runs = $query->paginate(10)->withQueryString();

            return view('admin.hr.payroll.table', compact('runs'))->render();
        }

        $currentMonth = now()->startOfMonth();
        $currentRun = PayrollRun::whereDate('payroll_month', $currentMonth)->first();

        return view('admin.hr.payroll.index', [
            'currentRun' => $currentRun,
            'totalRuns' => PayrollRun::count(),
            'draftRuns' => PayrollRun::where('status', 'draft')->count(),
            'approvedRuns' => PayrollRun::where('status', 'approved')->count(),
            'paidRuns' => PayrollRun::where('status', 'paid')->count(),
            'allowNonCurrentMonth' => (bool) $this->calculator->settings()->allow_non_current_month_payroll,
        ]);
    }

    public function create(Request $request)
    {
        $month = $this->normaliseMonth((string) $request->get('month', now()->format('Y-m')));
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        if ($message = $this->monthPolicyError($monthDate)) {
            return redirect()->route('hr.payroll.create')->with('error', $message);
        }

        $precheck = $this->calculator->precheck($month);
        $existingRun = PayrollRun::whereDate('payroll_month', $monthDate)->first();

        return view('admin.hr.payroll.create', [
            'month' => $month,
            'precheck' => $precheck,
            'existingRun' => $existingRun,
            'allowNonCurrentMonth' => (bool) $this->calculator->settings()->allow_non_current_month_payroll,
        ]);
    }

    public function precheck(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        if ($message = $this->monthPolicyError($monthDate)) {
            return response()->json(['message' => $message], 422);
        }

        $data = $this->calculator->precheck($validated['month']);
        $existingRun = PayrollRun::whereDate('payroll_month', $monthDate)->first();
        $data['existing_run'] = $existingRun ? [
            'id' => $existingRun->id,
            'code' => $existingRun->payroll_code,
            'status' => $existingRun->status,
            'url' => route('hr.payroll.show', $existingRun),
        ] : null;
        $data['allow_non_current_month_payroll'] = (bool) $this->calculator->settings()->allow_non_current_month_payroll;
        $data['is_future_month'] = $monthDate->gt(now()->startOfMonth());

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        if ($message = $this->monthPolicyError($monthDate)) {
            return back()->withInput()->with('error', $message);
        }

        $precheck = $this->calculator->precheck($validated['month']);
        if ($precheck['eligible_employees'] < 1) {
            return back()->withInput()->with('error', 'No eligible employees were found for this month.');
        }
        if ($precheck['missing_salary_count'] > 0) {
            return back()->withInput()->with('error', 'Salary setup is missing for ' . $precheck['missing_salary_count'] . ' employee(s). Complete salary setup first.');
        }
        if ($precheck['missing_attendance_count'] > 0 && !$request->boolean('confirm_incomplete_attendance')) {
            return back()->withInput()->with('error', 'Attendance is incomplete. Check the confirmation box to continue with not-marked days excluded from deduction.');
        }
        if ($precheck['pending_leave_count'] > 0 && !$request->boolean('confirm_pending_leave')) {
            return back()->withInput()->with('error', 'Pending leave requests exist for this month. Resolve them or confirm that you want to continue.');
        }

        if (PayrollRun::whereDate('payroll_month', $monthDate)->exists()) {
            return back()->withInput()->with('error', 'A payroll run already exists for ' . $monthDate->format('F Y') . '. Use Single Employee Payroll to add a missing employee.');
        }

        DB::beginTransaction();
        try {
            $run = $this->createRun($validated['month'], $validated['notes'] ?? null);
            $this->generateItems($run);
            $run->syncWorkflowStatus(auth()->id());

            DB::commit();

            return redirect()->route('hr.payroll.show', $run)
                ->with('success', 'Payroll draft generated successfully. Review employee amounts before approval.');
        } catch (Throwable $exception) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Payroll generation failed. ' . $exception->getMessage());
        }
    }

    public function createSingle(Request $request)
    {
        $month = $this->normaliseMonth((string) $request->get('month', now()->format('Y-m')));
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        if ($message = $this->monthPolicyError($monthDate)) {
            return redirect()->route('hr.payroll.single.create')->with('error', $message);
        }

        $employees = Employee::with(['department', 'designation'])
            ->orderBy('employee_code')
            ->get();

        $selectedEmployee = null;
        $precheck = null;
        if ($request->filled('employee')) {
            $selectedEmployee = $employees->firstWhere('id', (int) $request->employee);
            if ($selectedEmployee) {
                $precheck = $this->singlePrecheckData($selectedEmployee, $month);
            }
        }

        return view('admin.hr.payroll.create_single', [
            'month' => $month,
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'precheck' => $precheck,
            'allowNonCurrentMonth' => (bool) $this->calculator->settings()->allow_non_current_month_payroll,
        ]);
    }

    public function singlePrecheck(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        if ($message = $this->monthPolicyError($monthDate)) {
            return response()->json(['message' => $message], 422);
        }

        $employee = Employee::with(['department', 'designation'])->findOrFail($validated['employee_id']);

        return response()->json($this->singlePrecheckData($employee, $validated['month']));
    }

    public function storeSingle(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        if ($message = $this->monthPolicyError($monthDate)) {
            return back()->withInput()->with('error', $message);
        }

        $employee = Employee::with(['department', 'designation'])->findOrFail($validated['employee_id']);
        $precheck = $this->singlePrecheckData($employee, $validated['month']);

        if (!$precheck['eligible']) {
            return back()->withInput()->with('error', 'This employee is not eligible for the selected payroll month based on joining/exit dates.');
        }
        if (!$precheck['salary_ready']) {
            return back()->withInput()->with('error', 'Salary setup is missing for this employee in the selected month.');
        }
        if ($precheck['existing_item']) {
            return redirect($precheck['existing_item']['url'])
                ->with('error', 'Payroll already exists for this employee and month.');
        }
        if ($precheck['run_locked']) {
            return back()->withInput()->with('error', 'This month is already fully paid and Payroll Settings is configured to lock paid payroll.');
        }
        if ($precheck['missing_attendance_count'] > 0 && !$request->boolean('confirm_incomplete_attendance')) {
            return back()->withInput()->with('error', 'Attendance is incomplete for this employee. Confirm to continue with not-marked days excluded from deduction.');
        }
        if ($precheck['pending_leave_count'] > 0 && !$request->boolean('confirm_pending_leave')) {
            return back()->withInput()->with('error', 'This employee has pending leave in the selected month. Resolve it or confirm to continue.');
        }

        DB::beginTransaction();
        try {
            $run = PayrollRun::whereDate('payroll_month', $monthDate)->lockForUpdate()->first();
            if (!$run) {
                $run = $this->createRun($validated['month'], 'Created from Single Employee Payroll.');
            }

            if ($run->status === 'cancelled') {
                throw new \RuntimeException('Cannot add employee payroll to a cancelled payroll run.');
            }
            if ($run->status === 'paid' && $this->calculator->settings()->lock_paid_payroll) {
                throw new \RuntimeException('Paid payroll is locked by Payroll Settings.');
            }
            if ($run->items()->where('employee_id', $employee->id)->exists()) {
                throw new \RuntimeException('Payroll already exists for this employee and month.');
            }

            $item = $this->generateSingleItem($run, $employee, $validated['notes'] ?? null);
            $run->syncWorkflowStatus(auth()->id());

            DB::commit();

            return redirect()->route('hr.payroll.items.show', [$run, $item])
                ->with('success', 'Single employee payroll created as Draft. You can review and approve it individually.');
        } catch (Throwable $exception) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Single employee payroll creation failed. ' . $exception->getMessage());
        }
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load(['generatedBy', 'approvedBy', 'paidBy']);
        $counts = $payrollRun->items()
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_count")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count")
            ->selectRaw("SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count")
            ->first();

        return view('admin.hr.payroll.show', [
            'run' => $payrollRun,
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'draftCount' => (int) ($counts->draft_count ?? 0),
            'approvedCount' => (int) ($counts->approved_count ?? 0),
            'paidCount' => (int) ($counts->paid_count ?? 0),
            'unpaidCount' => (int) ($counts->unpaid_count ?? 0),
        ]);
    }

    public function itemsTable(Request $request, PayrollRun $payrollRun)
    {
        $query = $payrollRun->items()->with('employee')->orderBy('employee_code');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($builder) use ($search) {
                $builder->where('employee_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('department_name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('department')) {
            $query->where('department_name', $request->department);
        }
        if ($request->filled('item_status')) {
            $query->where('status', $request->item_status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $items = $query->paginate(10)->withQueryString();

        return view('admin.hr.payroll.items_table', [
            'run' => $payrollRun,
            'items' => $items,
        ])->render();
    }

    public function itemShow(PayrollRun $payrollRun, PayrollItem $payrollItem)
    {
        $this->ensureItemBelongsToRun($payrollRun, $payrollItem);
        $payrollItem->load([
            'components', 'employee.department', 'employee.designation', 'payment.paidBy', 'approvedBy',
        ]);

        return view('admin.hr.payroll.item', [
            'run' => $payrollRun,
            'item' => $payrollItem,
            'earnings' => $payrollItem->components->where('component_type', 'earning'),
            'deductions' => $payrollItem->components->where('component_type', 'deduction'),
        ]);
    }

    public function updateItem(Request $request, PayrollRun $payrollRun, PayrollItem $payrollItem)
    {
        $this->ensureItemBelongsToRun($payrollRun, $payrollItem);
        if ($payrollItem->status !== 'draft') {
            return back()->with('error', 'Only a Draft employee payroll can be edited.');
        }

        $validated = $request->validate([
            'components' => ['required', 'array'],
            'components.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'components.*.reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $components = $payrollItem->components()->lockForUpdate()->get()->keyBy('id');
            foreach ($validated['components'] as $componentId => $input) {
                $component = $components->get((int) $componentId);
                if (!$component) {
                    continue;
                }

                $amount = round((float) $input['amount'], 2);
                $changed = abs($amount - (float) $component->calculated_amount) >= 0.01;
                $reason = trim((string) ($input['reason'] ?? '')) ?: null;

                if ($changed && !$component->is_manual && !$reason) {
                    DB::rollBack();
                    return back()->withInput()->with('error', 'Reason is required when overriding an automatically calculated component.');
                }

                $component->update([
                    'amount' => $amount,
                    'is_overridden' => $changed,
                    'override_reason' => $changed ? $reason : null,
                ]);
            }

            $payrollItem->update(['notes' => $validated['notes'] ?? null]);
            $payrollItem->recalculateFromComponents((bool) $this->calculator->settings()->allow_negative_salary);
            $payrollRun->recalculateTotals();

            DB::commit();

            return redirect()->route('hr.payroll.items.show', [$payrollRun, $payrollItem])
                ->with('success', 'Employee payroll updated successfully.');
        } catch (Throwable $exception) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Could not update employee payroll. ' . $exception->getMessage());
        }
    }

    public function approveItem(PayrollRun $payrollRun, PayrollItem $payrollItem)
    {
        $this->ensureItemBelongsToRun($payrollRun, $payrollItem);
        if ($payrollItem->status !== 'draft') {
            return back()->with('error', 'Only a Draft employee payroll can be approved.');
        }

        DB::transaction(function () use ($payrollRun, $payrollItem) {
            $payrollItem->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $payrollRun->syncWorkflowStatus(auth()->id());
        });

        return back()->with('success', $payrollItem->employee_name . ' payroll approved successfully.');
    }

    public function approve(PayrollRun $payrollRun)
    {
        $draftCount = $payrollRun->items()->where('status', 'draft')->count();
        if ($draftCount < 1) {
            return back()->with('error', 'There are no Draft employee payroll records to approve.');
        }

        DB::transaction(function () use ($payrollRun) {
            $payrollRun->items()->where('status', 'draft')->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $payrollRun->syncWorkflowStatus(auth()->id());
        });

        return back()->with('success', 'All remaining Draft employee payroll records were approved.');
    }

    public function payItem(Request $request, PayrollRun $payrollRun, PayrollItem $payrollItem)
    {
        $this->ensureItemBelongsToRun($payrollRun, $payrollItem);
        if ($payrollItem->status !== 'approved') {
            return back()->with('error', 'Approve this employee payroll before recording payment.');
        }
        if ($payrollItem->payment_status === 'paid') {
            return back()->with('error', 'This employee salary is already marked as paid.');
        }

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking'])],
            'reference_number' => ['nullable', 'string', 'max:180'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();
        try {
            PayrollPayment::create([
                'payroll_item_id' => $payrollItem->id,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'amount' => max(0, (float) $payrollItem->net_salary),
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['payment_notes'] ?? null,
                'paid_by' => auth()->id(),
            ]);
            $payrollItem->update([
                'payment_status' => 'paid',
                'status' => 'paid',
            ]);
            $payrollRun->syncWorkflowStatus(auth()->id());

            DB::commit();

            return back()->with('success', 'Salary payment recorded successfully.');
        } catch (Throwable $exception) {
            DB::rollBack();
            return back()->with('error', 'Could not record payment. ' . $exception->getMessage());
        }
    }

    public function payAll(Request $request, PayrollRun $payrollRun)
    {
        if ($payrollRun->items()->where('status', 'draft')->exists()) {
            return back()->with('error', 'Approve all Draft employee payroll records before using Pay All.');
        }

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank', 'mobile_banking'])],
            'reference_number' => ['nullable', 'string', 'max:180'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();
        try {
            $items = $payrollRun->items()
                ->where('status', 'approved')
                ->where('payment_status', 'unpaid')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                DB::rollBack();
                return back()->with('error', 'There are no approved unpaid employee payroll records.');
            }

            foreach ($items as $item) {
                PayrollPayment::create([
                    'payroll_item_id' => $item->id,
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'] ?: $item->payment_method,
                    'amount' => max(0, (float) $item->net_salary),
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => $validated['payment_notes'] ?? null,
                    'paid_by' => auth()->id(),
                ]);
                $item->update([
                    'payment_status' => 'paid',
                    'status' => 'paid',
                ]);
            }

            $payrollRun->syncWorkflowStatus(auth()->id());
            DB::commit();

            return back()->with('success', $items->count() . ' employee payment(s) recorded successfully.');
        } catch (Throwable $exception) {
            DB::rollBack();
            return back()->with('error', 'Could not complete salary payments. ' . $exception->getMessage());
        }
    }

    public function regenerate(PayrollRun $payrollRun)
    {
        $draftItems = $payrollRun->items()->where('status', 'draft')->get(['id', 'employee_id', 'notes']);
        if ($draftItems->isEmpty()) {
            return back()->with('error', 'There are no Draft employee payroll records to recalculate.');
        }

        DB::beginTransaction();
        try {
            foreach ($draftItems as $draftItem) {
                $employee = Employee::with(['department', 'designation'])->find($draftItem->employee_id);
                if (!$employee) {
                    continue;
                }

                $payrollRun->items()->whereKey($draftItem->id)->delete();
                $this->generateSingleItem($payrollRun, $employee, $draftItem->notes);
            }

            $payrollRun->update([
                'generated_by' => auth()->id(),
                'generated_at' => now(),
            ]);
            $payrollRun->syncWorkflowStatus(auth()->id());
            DB::commit();

            return back()->with('success', 'Draft employee payroll records were recalculated. Approved and paid records were kept unchanged.');
        } catch (Throwable $exception) {
            DB::rollBack();
            return back()->with('error', 'Payroll recalculation failed. ' . $exception->getMessage());
        }
    }

    public function destroy(PayrollRun $payrollRun)
    {
        if ($payrollRun->items()->where('status', '!=', 'draft')->exists()) {
            return response()->json(['message' => 'A payroll containing Approved or Paid employee records cannot be deleted.'], 422);
        }

        $payrollRun->delete();

        return response()->json(['message' => 'Draft payroll deleted successfully.']);
    }

    public function summaryPdf(PayrollRun $payrollRun)
    {
        $payrollRun->load(['items.payment', 'generatedBy', 'approvedBy', 'paidBy']);
        $restaurant = RestaurantSetting::first();
        $html = view('admin.hr.payroll.pdf.summary', [
            'run' => $payrollRun,
            'restaurant' => $restaurant,
        ])->render();

        return $this->inlinePdf($html, 'payroll_summary_' . $payrollRun->payroll_month->format('Y_m') . '.pdf', 'A4', 'L');
    }

    public function payslip(PayrollRun $payrollRun, PayrollItem $payrollItem)
    {
        $this->ensureItemBelongsToRun($payrollRun, $payrollItem);
        $payrollItem->load(['components', 'payment.paidBy', 'employee', 'approvedBy']);
        $restaurant = RestaurantSetting::first();
        $html = view('admin.hr.payroll.pdf.payslip', [
            'run' => $payrollRun,
            'item' => $payrollItem,
            'restaurant' => $restaurant,
        ])->render();

        $fileName = 'payslip_' . $payrollItem->employee_code . '_' . $payrollRun->payroll_month->format('Y_m') . '.pdf';

        return $this->inlinePdf($html, $fileName, 'A4', 'P');
    }

    private function createRun(string $month, ?string $notes = null): PayrollRun
    {
        [$start, $end] = $this->calculator->period($month);

        return PayrollRun::create([
            'payroll_code' => 'PAY-' . $start->format('Ym'),
            'payroll_month' => $start->toDateString(),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => 'draft',
            'notes' => $notes,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }

    private function generateItems(PayrollRun $run): void
    {
        $employees = $this->calculator
            ->eligibleEmployeesQuery($run->period_start, $run->period_end)
            ->with(['department', 'designation'])
            ->orderBy('employee_code')
            ->get();

        foreach ($employees as $employee) {
            $this->generateSingleItem($run, $employee);
        }
    }

    private function generateSingleItem(PayrollRun $run, Employee $employee, ?string $notes = null): PayrollItem
    {
        $calculation = $this->calculator->calculate($employee, $run->period_start, $run->period_end);
        if ($notes !== null) {
            $calculation['item']['notes'] = $notes;
        }

        $item = $run->items()->create($calculation['item']);
        $item->components()->createMany($calculation['components']);

        return $item;
    }

    private function singlePrecheckData(Employee $employee, string $month): array
    {
        $data = $this->calculator->precheckEmployee($employee, $month);
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $run = PayrollRun::whereDate('payroll_month', $monthDate)->first();
        $item = $run?->items()->where('employee_id', $employee->id)->first();

        $data['existing_run'] = $run ? [
            'id' => $run->id,
            'code' => $run->payroll_code,
            'status' => $run->status,
            'url' => route('hr.payroll.show', $run),
        ] : null;
        $data['existing_item'] = $item ? [
            'id' => $item->id,
            'status' => $item->status,
            'url' => route('hr.payroll.items.show', [$run, $item]),
        ] : null;
        $data['run_locked'] = (bool) ($run
            && $run->status === 'paid'
            && $this->calculator->settings()->lock_paid_payroll);
        $data['is_future_month'] = $monthDate->gt(now()->startOfMonth());

        return $data;
    }

    private function normaliseMonth(string $month): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $month)->format('Y-m');
        } catch (Throwable) {
            return now()->format('Y-m');
        }
    }

    private function monthPolicyError(Carbon $monthDate): ?string
    {
        if ($this->calculator->settings()->allow_non_current_month_payroll) {
            return null;
        }

        if ($monthDate->format('Y-m') !== now()->format('Y-m')) {
            return 'Payroll Settings currently allows Current Month payroll only. Enable “Allow Previous / Next Month Payroll” to use another month.';
        }

        return null;
    }

    private function ensureItemBelongsToRun(PayrollRun $run, PayrollItem $item): void
    {
        abort_unless((int) $item->payroll_run_id === (int) $run->id, 404);
    }

    private function inlinePdf(string $html, string $fileName, string $format, string $orientation)
    {
        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $tempDir = storage_path('app/mpdf-payroll');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetTitle($fileName);
        $mpdf->SetFooter('Generated: ' . now()->format('d M Y, h:i A') . '||Page {PAGENO} of {nbpg}');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }
}
