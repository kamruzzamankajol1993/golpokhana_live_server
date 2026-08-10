<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Models\SalaryComponent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeSalaryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:employee-salary-view')->only('show');
        $this->middleware('permission:employee-salary-manage')->only('store');
    }

    public function show(Employee $employee)
    {
        $employee->load(['department', 'designation', 'employmentType']);

        $salaryComponents = SalaryComponent::query()
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('code')
                    ->orWhereNotIn('code', ['BASIC', 'OT', 'ABSENT', 'UNPAID', 'HALF-DAY', 'LATE']);
            })
            ->orderByRaw("CASE WHEN type = 'earning' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $currentStructure = $employee->salaryStructures()
            ->with('components.salaryComponent')
            ->where('status', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->latest('effective_from')
            ->first();

        $latestStructure = $employee->salaryStructures()
            ->with('components.salaryComponent')
            ->latest('effective_from')
            ->first();

        $salaryHistory = $employee->salaryStructures()
            ->with('components.salaryComponent')
            ->latest('effective_from')
            ->get();

        return view('admin.hr.employees.salary', compact(
            'employee',
            'salaryComponents',
            'currentStructure',
            'latestStructure',
            'salaryHistory'
        ));
    }

    public function store(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'effective_from' => ['required', 'date', 'after_or_equal:' . $employee->join_date->format('Y-m-d')],
            'basic_salary' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'payment_method' => ['required', 'in:cash,bank,mobile_banking'],
            'account_name' => ['nullable', 'string', 'max:180'],
            'account_number' => ['nullable', 'string', 'max:120'],
            'mobile_banking_provider' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'components' => ['nullable', 'array'],
        ]);

        $effectiveFrom = Carbon::parse($validated['effective_from'])->startOfDay();

        DB::beginTransaction();

        try {
            $structures = $employee->salaryStructures()
                ->lockForUpdate()
                ->orderBy('effective_from')
                ->get();

            $sameDateStructure = $structures->first(function ($structure) use ($effectiveFrom) {
                return $structure->effective_from->isSameDay($effectiveFrom);
            });

            $latestStructure = $structures->last();

            if (!$sameDateStructure && $latestStructure && $effectiveFrom->lt($latestStructure->effective_from)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'A newer salary structure already exists. Update the latest effective date or add a later date.',
                ]);
            }

            if (!$sameDateStructure && $latestStructure) {
                $latestStructure->update([
                    'effective_to' => $effectiveFrom->copy()->subDay()->toDateString(),
                    'updated_by' => auth()->id(),
                ]);
            }

            $structure = $sameDateStructure ?: new EmployeeSalaryStructure();
            $structure->employee_id = $employee->id;
            $structure->effective_from = $effectiveFrom->toDateString();
            $structure->effective_to = null;
            $structure->basic_salary = $validated['basic_salary'];
            $structure->overtime_rate = $validated['overtime_rate'] ?? null;
            $structure->payment_method = $validated['payment_method'];
            $structure->account_name = $validated['account_name'] ?? null;
            $structure->account_number = $validated['account_number'] ?? null;
            $structure->mobile_banking_provider = $validated['mobile_banking_provider'] ?? null;
            $structure->status = true;
            $structure->notes = $validated['notes'] ?? null;
            $structure->created_by = $structure->exists ? $structure->created_by : auth()->id();
            $structure->updated_by = auth()->id();
            $structure->save();

            $activeComponents = SalaryComponent::query()
                ->where('status', true)
                ->where(function ($query) {
                    $query->whereNull('code')
                        ->orWhereNotIn('code', ['BASIC', 'OT', 'ABSENT', 'UNPAID', 'HALF-DAY', 'LATE']);
                })
                ->get()
                ->keyBy('id');

            $componentRows = [];
            foreach ((array) $request->input('components', []) as $componentId => $input) {
                $component = $activeComponents->get((int) $componentId);
                if (!$component || empty($input['enabled'])) {
                    continue;
                }

                $amount = 0;
                $percentage = 0;

                if ($component->calculation_type === 'fixed') {
                    $amount = max(0, (float) ($input['amount'] ?? 0));
                } elseif ($component->calculation_type === 'percentage') {
                    $percentage = max(0, min(100, (float) ($input['percentage'] ?? 0)));
                }

                $componentRows[] = [
                    'salary_component_id' => $component->id,
                    'component_type' => $component->type,
                    'calculation_type' => $component->calculation_type,
                    'amount' => $amount,
                    'percentage' => $percentage,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $structure->components()->delete();
            if ($componentRows) {
                $structure->components()->createMany($componentRows);
            }

            DB::commit();

            return redirect()
                ->route('hr.employees.salary.show', $employee)
                ->with('success', 'Employee salary structure saved successfully.');
        } catch (ValidationException $exception) {
            DB::rollBack();
            throw $exception;
        } catch (Throwable $exception) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Could not save salary structure. ' . $exception->getMessage());
        }
    }
}
