<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\LeaveType;
use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hr-setting-view', ['only' => ['index']]);
        $this->middleware('permission:hr-setting-update', ['only' => [
            'updateGeneral',
            'updateAttendance',
            'updatePayroll',
        ]]);
        $this->middleware('permission:hr-setting-create', ['only' => [
            'storeDepartment',
            'storeDesignation',
            'storeEmploymentType',
            'storeLeaveType',
            'storeSalaryComponent',
            'storeHoliday',
        ]]);
        $this->middleware('permission:hr-setting-edit', ['only' => [
            'updateDepartment',
            'updateDepartmentStatus',
            'updateDesignation',
            'updateDesignationStatus',
            'updateEmploymentType',
            'updateEmploymentTypeStatus',
            'updateLeaveType',
            'updateLeaveTypeStatus',
            'updateSalaryComponent',
            'updateSalaryComponentStatus',
            'updateHoliday',
            'updateHolidayStatus',
        ]]);
        $this->middleware('permission:hr-setting-delete', ['only' => [
            'destroyDepartment',
            'destroyDesignation',
            'destroyEmploymentType',
            'destroyLeaveType',
            'destroySalaryComponent',
            'destroyHoliday',
        ]]);
    }

    public function index(Request $request)
    {
        $hrSetting = HrSetting::first() ?? new HrSetting([
            'employee_code_prefix' => 'EMP',
            'employee_code_next_number' => 1,
            'employee_code_padding' => 4,
            'default_probation_months' => 3,
            'default_notice_period_days' => 30,
            'allow_employee_login' => true,
            'allow_waiter_access' => true,
            'date_format' => 'd-m-Y',
            'time_format' => 'h:i A',
            'timezone' => 'Asia/Dhaka',
            'status' => true,
        ]);

        $attendanceSetting = AttendanceSetting::first() ?? new AttendanceSetting([
            'grace_minutes' => 10,
            'half_day_after_minutes' => 240,
            'absent_after_minutes' => 480,
            'minimum_overtime_minutes' => 30,
            'default_working_hours' => 8,
            'weekly_off_days' => ['Friday'],
            'allow_manual_attendance' => true,
            'auto_calculate_late' => true,
            'auto_calculate_overtime' => true,
            'status' => true,
        ]);

        $payrollSetting = PayrollSetting::first() ?? new PayrollSetting([
            'salary_cycle_start_day' => 1,
            'salary_cycle_end_day' => null,
            'working_days_method' => 'calendar_days',
            'default_working_days' => 30,
            'absent_deduction_method' => 'per_day',
            'overtime_calculation_method' => 'hourly_rate',
            'overtime_rate_multiplier' => 1.5,
            'rounding_method' => 'nearest',
            'allow_negative_salary' => false,
            'lock_paid_payroll' => true,
            'allow_non_current_month_payroll' => false,
            'currency' => 'BDT',
            'status' => true,
        ]);

        $departments = Department::withCount('designations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $designations = Designation::with('department')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $employmentTypes = EmploymentType::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $leaveTypes = LeaveType::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $salaryComponents = SalaryComponent::orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $holidays = Holiday::orderBy('holiday_date', 'desc')
            ->orderBy('name')
            ->get();

        $activeTab = $request->get('tab', 'general');

        return view('admin.hr.settings.index', compact(
            'hrSetting',
            'attendanceSetting',
            'payrollSetting',
            'departments',
            'designations',
            'employmentTypes',
            'leaveTypes',
            'salaryComponents',
            'holidays',
            'activeTab'
        ));
    }

    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'employee_code_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'employee_code_next_number' => ['required', 'integer', 'min:1'],
            'employee_code_padding' => ['required', 'integer', 'min:2', 'max:10'],
            'default_probation_months' => ['required', 'integer', 'min:0', 'max:24'],
            'default_notice_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'date_format' => ['required', Rule::in(['d-m-Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'])],
            'time_format' => ['required', Rule::in(['h:i A', 'H:i'])],
            'timezone' => ['required', 'string', 'max:60'],
        ]);

        $validated['allow_employee_login'] = $request->boolean('allow_employee_login');
        $validated['allow_waiter_access'] = $request->boolean('allow_waiter_access');
        $validated['status'] = true;

        $setting = HrSetting::first() ?? new HrSetting();
        $setting->fill($validated)->save();

        return redirect()->route('hr.settings.index', ['tab' => 'general'])
            ->with('success', 'General HR settings updated successfully!');
    }

    public function updateAttendance(Request $request)
    {
        $validated = $request->validate([
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_after_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'absent_after_minutes' => ['required', 'integer', 'min:1', 'max:1440', 'gte:half_day_after_minutes'],
            'minimum_overtime_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'default_working_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'weekly_off_days' => ['nullable', 'array'],
            'weekly_off_days.*' => [Rule::in(['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])],
        ]);

        $validated['weekly_off_days'] = $request->input('weekly_off_days', []);
        $validated['allow_manual_attendance'] = $request->boolean('allow_manual_attendance');
        $validated['auto_calculate_late'] = $request->boolean('auto_calculate_late');
        $validated['auto_calculate_overtime'] = $request->boolean('auto_calculate_overtime');
        $validated['status'] = true;

        $setting = AttendanceSetting::first() ?? new AttendanceSetting();
        $setting->fill($validated)->save();

        return redirect()->route('hr.settings.index', ['tab' => 'attendance'])
            ->with('success', 'Attendance rules updated successfully!');
    }

    public function updatePayroll(Request $request)
    {
        $validated = $request->validate([
            'salary_cycle_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'salary_cycle_end_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'working_days_method' => ['required', Rule::in(['calendar_days', 'fixed_days', 'attendance_days'])],
            'default_working_days' => ['required', 'numeric', 'min:1', 'max:31'],
            'absent_deduction_method' => ['required', Rule::in(['per_day', 'none'])],
            'deduction_basis' => ['required', Rule::in(['basic_salary', 'gross_salary'])],
            'half_day_deduction_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'late_deduction_method' => ['required', Rule::in(['none', 'half_day_after_count', 'full_day_after_count'])],
            'late_count_threshold' => ['required', 'integer', 'min:1', 'max:31'],
            'overtime_calculation_method' => ['required', Rule::in(['hourly_rate', 'fixed_rate', 'none'])],
            'overtime_basis' => ['required', Rule::in(['employee_rate', 'basic_hourly'])],
            'overtime_rate_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
            'rounding_method' => ['required', Rule::in(['none', 'nearest', 'floor', 'ceil'])],
            'currency' => ['required', 'string', 'max:10'],
        ]);

        $validated['salary_cycle_end_day'] = $request->filled('salary_cycle_end_day')
            ? (int) $request->salary_cycle_end_day
            : null;
        $validated['allow_negative_salary'] = $request->boolean('allow_negative_salary');
        $validated['lock_paid_payroll'] = $request->boolean('lock_paid_payroll');
        $validated['allow_non_current_month_payroll'] = $request->boolean('allow_non_current_month_payroll');
        $validated['status'] = true;

        $setting = PayrollSetting::first() ?? new PayrollSetting();
        $setting->fill($validated)->save();

        return redirect()->route('hr.settings.index', ['tab' => 'payroll'])
            ->with('success', 'Payroll settings updated successfully!');
    }

    public function storeDepartment(Request $request)
    {
        $validated = $this->validateDepartment($request);
        return $this->transactionalCreate(Department::class, $validated, 'Department created successfully!');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $validated = $this->validateDepartment($request, $department->id);
        return $this->transactionalUpdate($department, $validated, 'Department updated successfully!');
    }

    public function updateDepartmentStatus(Request $request, Department $department)
    {
        return $this->updateStatus($request, $department, 'Department status updated successfully!');
    }

    public function destroyDepartment(Department $department)
    {
        if ($department->is_system) {
            return $this->jsonError('System department cannot be deleted.', 422);
        }
        if ($department->designations()->exists() || $department->employees()->exists()) {
            return $this->jsonError('This department has designations or employees. Move them first.', 422);
        }
        return $this->transactionalDelete($department, 'Department deleted successfully!');
    }

    public function storeDesignation(Request $request)
    {
        $validated = $this->validateDesignation($request);
        return $this->transactionalCreate(Designation::class, $validated, 'Designation created successfully!');
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        $validated = $this->validateDesignation($request, $designation->id);
        return $this->transactionalUpdate($designation, $validated, 'Designation updated successfully!');
    }

    public function updateDesignationStatus(Request $request, Designation $designation)
    {
        return $this->updateStatus($request, $designation, 'Designation status updated successfully!');
    }

    public function destroyDesignation(Designation $designation)
    {
        if ($designation->is_system) {
            return $this->jsonError('System designation cannot be deleted.', 422);
        }
        if ($designation->employees()->exists()) {
            return $this->jsonError('This designation is assigned to employees. Move them first.', 422);
        }
        return $this->transactionalDelete($designation, 'Designation deleted successfully!');
    }

    public function storeEmploymentType(Request $request)
    {
        $validated = $this->validateEmploymentType($request);
        return $this->transactionalCreate(EmploymentType::class, $validated, 'Employment type created successfully!');
    }

    public function updateEmploymentType(Request $request, EmploymentType $employmentType)
    {
        $validated = $this->validateEmploymentType($request, $employmentType->id);
        return $this->transactionalUpdate($employmentType, $validated, 'Employment type updated successfully!');
    }

    public function updateEmploymentTypeStatus(Request $request, EmploymentType $employmentType)
    {
        return $this->updateStatus($request, $employmentType, 'Employment type status updated successfully!');
    }

    public function destroyEmploymentType(EmploymentType $employmentType)
    {
        if ($employmentType->is_system) {
            return $this->jsonError('System employment type cannot be deleted.', 422);
        }
        if ($employmentType->employees()->exists()) {
            return $this->jsonError('This employment type is assigned to employees. Move them first.', 422);
        }
        return $this->transactionalDelete($employmentType, 'Employment type deleted successfully!');
    }

    public function storeLeaveType(Request $request)
    {
        $validated = $this->validateLeaveType($request);
        return $this->transactionalCreate(LeaveType::class, $validated, 'Leave type created successfully!');
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType)
    {
        $validated = $this->validateLeaveType($request, $leaveType->id);
        return $this->transactionalUpdate($leaveType, $validated, 'Leave type updated successfully!');
    }

    public function updateLeaveTypeStatus(Request $request, LeaveType $leaveType)
    {
        return $this->updateStatus($request, $leaveType, 'Leave type status updated successfully!');
    }

    public function destroyLeaveType(LeaveType $leaveType)
    {
        if ($leaveType->is_system) {
            return $this->jsonError('System leave type cannot be deleted.', 422);
        }
        if ($leaveType->leaveRequests()->exists() || $leaveType->employeeBalances()->exists()) {
            return $this->jsonError('This leave type has leave history or employee balances. Set it inactive instead.', 422);
        }
        return $this->transactionalDelete($leaveType, 'Leave type deleted successfully!');
    }

    public function storeSalaryComponent(Request $request)
    {
        $validated = $this->validateSalaryComponent($request);
        return $this->transactionalCreate(SalaryComponent::class, $validated, 'Salary component created successfully!');
    }

    public function updateSalaryComponent(Request $request, SalaryComponent $salaryComponent)
    {
        $validated = $this->validateSalaryComponent($request, $salaryComponent->id);
        return $this->transactionalUpdate($salaryComponent, $validated, 'Salary component updated successfully!');
    }

    public function updateSalaryComponentStatus(Request $request, SalaryComponent $salaryComponent)
    {
        return $this->updateStatus($request, $salaryComponent, 'Salary component status updated successfully!');
    }

    public function destroySalaryComponent(SalaryComponent $salaryComponent)
    {
        if ($salaryComponent->is_system) {
            return $this->jsonError('System salary component cannot be deleted.', 422);
        }

        if ($salaryComponent->employeeSalaryComponents()->exists()) {
            return $this->jsonError(
                'This salary component is already used in an employee salary structure. Set it to inactive instead of deleting.',
                422
            );
        }

        return $this->transactionalDelete($salaryComponent, 'Salary component deleted successfully!');
    }

    public function storeHoliday(Request $request)
    {
        $validated = $this->validateHoliday($request);
        return $this->transactionalCreate(Holiday::class, $validated, 'Holiday created successfully!');
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $validated = $this->validateHoliday($request, $holiday->id);
        return $this->transactionalUpdate($holiday, $validated, 'Holiday updated successfully!');
    }

    public function updateHolidayStatus(Request $request, Holiday $holiday)
    {
        return $this->updateStatus($request, $holiday, 'Holiday status updated successfully!');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        return $this->transactionalDelete($holiday, 'Holiday deleted successfully!');
    }

    private function validateDepartment(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('departments', 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['status'] = $request->boolean('status');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function validateDesignation(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('designations', 'name')
                    ->where(fn ($query) => $query->where('department_id', $request->department_id))
                    ->ignore($ignoreId),
            ],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('designations', 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['department_id'] = $request->filled('department_id') ? $request->department_id : null;
        $validated['status'] = $request->boolean('status');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function validateEmploymentType(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('employment_types', 'name')->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('employment_types', 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['is_hourly'] = $request->boolean('is_hourly');
        $validated['status'] = $request->boolean('status');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function validateLeaveType(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('leave_types', 'name')->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('leave_types', 'code')->ignore($ignoreId)],
            'days_per_year' => ['required', 'numeric', 'min:0', 'max:365'],
            'max_carry_forward_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['is_paid'] = $request->boolean('is_paid');
        $validated['allow_carry_forward'] = $request->boolean('allow_carry_forward');
        $validated['max_carry_forward_days'] = $validated['allow_carry_forward']
            ? (float) ($validated['max_carry_forward_days'] ?? 0)
            : 0;
        $validated['requires_document'] = $request->boolean('requires_document');
        $validated['status'] = $request->boolean('status');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function validateSalaryComponent(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('salary_components', 'name')->ignore($ignoreId)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('salary_components', 'code')->ignore($ignoreId)],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'calculation_type' => ['required', Rule::in(['fixed', 'percentage', 'manual'])],
            'percentage_of' => ['nullable', Rule::in(['basic_salary', 'gross_salary'])],
            'default_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'default_percentage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['percentage_of'] = $validated['calculation_type'] === 'percentage'
            ? ($validated['percentage_of'] ?? 'basic_salary')
            : null;
        $validated['default_amount'] = $validated['calculation_type'] === 'fixed'
            ? (float) ($validated['default_amount'] ?? 0)
            : 0;
        $validated['default_percentage'] = $validated['calculation_type'] === 'percentage'
            ? (float) ($validated['default_percentage'] ?? 0)
            : 0;
        $validated['is_taxable'] = $request->boolean('is_taxable');
        $validated['is_required'] = $request->boolean('is_required');
        $validated['status'] = $request->boolean('status');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function validateHoliday(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('holidays', 'name')
                    ->where(fn ($query) => $query->where('holiday_date', $request->holiday_date))
                    ->ignore($ignoreId),
            ],
            'holiday_date' => ['required', 'date'],
            'holiday_type' => ['required', Rule::in(['public', 'company', 'special'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['is_paid'] = $request->boolean('is_paid');
        $validated['status'] = $request->boolean('status');

        return $validated;
    }

    private function transactionalCreate(string $modelClass, array $data, string $message)
    {
        DB::beginTransaction();
        try {
            $modelClass::create($data);
            DB::commit();
            return $this->jsonSuccess($message);
        } catch (Exception $exception) {
            DB::rollBack();
            report($exception);
            return $this->jsonError('Unable to save the record. Please try again.');
        }
    }

    private function transactionalUpdate($model, array $data, string $message)
    {
        DB::beginTransaction();
        try {
            $model->update($data);
            DB::commit();
            return $this->jsonSuccess($message);
        } catch (Exception $exception) {
            DB::rollBack();
            report($exception);
            return $this->jsonError('Unable to update the record. Please try again.');
        }
    }

    private function transactionalDelete($model, string $message)
    {
        DB::beginTransaction();
        try {
            $model->delete();
            DB::commit();
            return $this->jsonSuccess($message);
        } catch (Exception $exception) {
            DB::rollBack();
            report($exception);
            return $this->jsonError('This record is already in use and cannot be deleted.', 422);
        }
    }

    private function updateStatus(Request $request, $model, string $message)
    {
        $request->validate(['status' => ['required', 'boolean']]);

        try {
            $model->update(['status' => $request->boolean('status')]);
            return $this->jsonSuccess($message);
        } catch (Exception $exception) {
            report($exception);
            return $this->jsonError('Unable to update status. Please try again.');
        }
    }

    private function jsonSuccess(string $message, array $data = [])
    {
        return response()->json(array_merge([
            'status' => 'success',
            'message' => $message,
        ], $data));
    }

    private function jsonError(string $message, int $statusCode = 500)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }
}
