<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeSalaryStructure;
use App\Models\SalaryComponent;
use App\Models\EmploymentType;
use App\Models\HrSetting;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Models\Waiter;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:employee-view')->only(['index', 'show']);
        $this->middleware('permission:employee-create')->only(['create', 'store']);
        $this->middleware('permission:employee-edit')->only(['edit', 'update', 'updateStatus']);
        $this->middleware('permission:employee-delete')->only(['destroy', 'bulkDestroy']);
    }

    public function index(Request $request)
    {
        $tableData = $this->employeeTableData($request);

        if ($request->ajax()) {
            return view('admin.hr.employees.table', $tableData)->render();
        }

        return view('admin.hr.employees.index', [
            'totalEmployees' => Employee::count(),
            'activeEmployees' => Employee::where('employment_status', 'active')->count(),
            'waiterEmployees' => Employee::where('is_waiter', true)->count(),
            'loginEmployees' => Employee::where('can_login', true)->count(),
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'designations' => Designation::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shifts' => Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'initialTableData' => $tableData,
        ]);
    }

    /**
     * Build the employee list for both the first page render and AJAX refreshes.
     * Rendering the first table together with the page avoids the initial AJAX
     * race/error while filters, search and pagination remain AJAX based.
     */
    private function employeeTableData(Request $request): array
    {
        $query = Employee::with([
            'department',
            'designation',
            'employmentType',
            'defaultShift',
            'currentSalaryStructure',
        ])->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nid', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('designation_id')) {
            $query->where('designation_id', $request->designation_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('default_shift_id', $request->shift_id);
        }

        if ($request->filled('status')) {
            $query->where('employment_status', $request->status);
        }

        if ($request->filled('access')) {
            if ($request->access === 'waiter') {
                $query->where('is_waiter', true);
            } elseif ($request->access === 'login') {
                $query->where('can_login', true);
            } elseif ($request->access === 'no_access') {
                $query->where('is_waiter', false)->where('can_login', false);
            }
        }

        return [
            'employees' => $query->paginate(10)->withQueryString(),
        ];
    }

    public function create()
    {
        return view('admin.hr.employees.create', $this->formData());
    }

    public function show(Employee $employee)
    {
        $employee->load([
            'department',
            'designation',
            'employmentType',
            'defaultShift',
            'zone',
            'user.roles',
            'waiter',
            'currentSalaryStructure.components.salaryComponent',
        ]);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthAttendances = $employee->attendances()
            ->whereBetween('attendance_date', [$monthStart, $monthEnd])
            ->get();

        $attendanceSummary = [
            'present' => $monthAttendances->whereIn('status', ['present', 'late'])->count(),
            'late' => $monthAttendances->where('status', 'late')->count(),
            'absent' => $monthAttendances->where('status', 'absent')->count(),
            'leave' => $monthAttendances->where('status', 'leave')->count(),
        ];

        $recentAttendances = $employee->attendances()
            ->with('shift')
            ->latest('attendance_date')
            ->limit(8)
            ->get();

        $recentLeaves = $employee->leaveRequests()
            ->with('leaveType')
            ->latest('id')
            ->limit(6)
            ->get();

        $leaveBalances = $employee->leaveBalances()
            ->with('leaveType')
            ->where('year', now()->year)
            ->get();

        $upcomingRoster = $employee->shiftRosters()
            ->with('shift')
            ->whereBetween('roster_date', [now()->toDateString(), now()->addDays(6)->toDateString()])
            ->orderBy('roster_date')
            ->get();

        return view('admin.hr.employees.show', compact(
            'employee',
            'attendanceSummary',
            'recentAttendances',
            'recentLeaves',
            'leaveBalances',
            'upcomingRoster'
        ));
    }

    public function edit(Employee $employee)
    {
        $employee->load([
            'user',
            'waiter',
            'currentSalaryStructure.components.salaryComponent',
            'leaveBalances' => fn ($query) => $query->where('year', now()->year),
        ]);

        return view('admin.hr.employees.edit', array_merge(
            $this->formData(),
            compact('employee')
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validateEmployee($request);

        DB::beginTransaction();

        try {
            $employeeCode = $this->nextEmployeeCode();
            $canLogin = $request->boolean('can_login');
            $isWaiter = $request->boolean('is_waiter');
            $imagePath = $request->hasFile('image')
                ? $this->uploadImage($request->file('image'))
                : null;
            $nidImagePath = $request->hasFile('nid_image')
                ? $this->uploadNidImage($request->file('nid_image'))
                : null;

            // Login access may be enabled during employee creation without
            // forcing credentials immediately. A system user is created only when
            // both email and password are supplied; credentials can be added later
            // from Employee Edit.
            $hasLoginCredentials = $request->filled('email') && $request->filled('password');
            $user = ($canLogin && $hasLoginCredentials)
                ? $this->createEmployeeUser($request, $employeeCode, $isWaiter)
                : null;

            $employee = Employee::create([
                'user_id' => $user?->id,
                'department_id' => $validated['department_id'],
                'designation_id' => $validated['designation_id'],
                'employment_type_id' => $validated['employment_type_id'],
                'default_shift_id' => $validated['default_shift_id'] ?? null,
                'zone_id' => $isWaiter ? ($validated['zone_id'] ?? null) : null,
                'employee_code' => $employeeCode,
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'nid' => $validated['nid'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'join_date' => $validated['join_date'],
                'probation_end_date' => $validated['probation_end_date'] ?? null,
                'exit_date' => $validated['exit_date'] ?? null,
                'employment_status' => $validated['employment_status'],
                'is_waiter' => $isWaiter,
                'can_login' => $canLogin,
                'image' => $imagePath,
                'nid_image' => $nidImagePath,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($isWaiter) {
                $this->syncWaiter($employee);
            }
            $this->syncSalarySetup($request, $employee);
            $this->syncLeaveBalanceSetup($request, $employee);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Employee created successfully. Employee ID: {$employeeCode}",
                    'redirect_url' => route('hr.employees.show', $employee),
                ]);
            }

            return redirect()
                ->route('hr.employees.show', $employee)
                ->with('success', "Employee created successfully. Employee ID: {$employeeCode}");
        } catch (Throwable $exception) {
            DB::rollBack();
            Log::error('Employee store error', ['message' => $exception->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employee. ' . $exception->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Failed to create employee. ' . $exception->getMessage());
        }
    }

    public function update(Request $request, Employee $employee)
    {
        $logReference = 'EMP-UPD-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(6));
        $step = 'request_received';

        $this->writeEmployeeEditLog('info', 'Employee update request received.', [
            'reference' => $logReference,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'actor_user_id' => auth()->id(),
            'can_login' => $request->boolean('can_login'),
            'is_waiter' => $request->boolean('is_waiter'),
            'salary_enabled' => $request->boolean('salary_enabled'),
        ]);

        try {
            $step = 'validation';
            $validated = $this->validateEmployee($request, $employee);

            $step = 'begin_transaction';
            DB::beginTransaction();

            $step = 'employee_update';
            $canLogin = $request->boolean('can_login');
            $isWaiter = $request->boolean('is_waiter');

            $step = 'employee_image';
            if ($request->hasFile('image')) {
                $oldImage = $employee->image;
                $employee->image = $this->uploadImage($request->file('image'));
                $this->deleteImage($oldImage);
            }

            $step = 'nid_image';
            if ($request->hasFile('nid_image')) {
                $oldNidImage = $employee->nid_image;
                $employee->nid_image = $this->uploadNidImage($request->file('nid_image'));
                $this->deleteImage($oldNidImage);
            }

            $step = 'login_user_sync';
            if ($canLogin) {
                $user = $employee->user;
                $hasLoginCredentials = $request->filled('email') && $request->filled('password');

                // If login access was enabled earlier without credentials, keep the
                // employee editable and create the actual user account only when both
                // email and password are supplied later.
                if (!$user && $hasLoginCredentials) {
                    $user = $this->createEmployeeUser($request, $employee->employee_code, $isWaiter);
                }

                if ($user) {
                    $user->name = $validated['name'];
                    $user->first_name = $validated['name'];
                    if ($request->filled('email')) {
                        $user->email = $validated['email'];
                    }
                    $user->phone = $validated['phone'];

                    if ($request->filled('password')) {
                        $user->password = Hash::make($request->password);
                    }

                    $user->save();
                    $user->syncRoles([$this->resolveEmployeeRole($isWaiter)]);
                    $employee->user_id = $user->id;
                }
            }

            $step = 'employee_model_save';
            $employee->fill([
                'department_id' => $validated['department_id'],
                'designation_id' => $validated['designation_id'],
                'employment_type_id' => $validated['employment_type_id'],
                'default_shift_id' => $validated['default_shift_id'] ?? null,
                'zone_id' => $isWaiter ? ($validated['zone_id'] ?? null) : null,
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'nid' => $validated['nid'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'join_date' => $validated['join_date'],
                'probation_end_date' => $validated['probation_end_date'] ?? null,
                'exit_date' => $validated['exit_date'] ?? null,
                'employment_status' => $validated['employment_status'],
                'is_waiter' => $isWaiter,
                'can_login' => $canLogin,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ])->save();

            $step = 'waiter_sync';
            if ($isWaiter) {
                $this->syncWaiter($employee->fresh());
            } elseif ($employee->waiter) {
                $employee->waiter->update([
                    'status' => false,
                    'hr_employee_id' => null,
                ]);
            }
            $step = 'salary_sync';
            $this->syncSalarySetup($request, $employee->fresh());
            $step = 'leave_balance_sync';
            $this->syncLeaveBalanceSetup($request, $employee->fresh());

            $step = 'commit';
            DB::commit();

            $this->writeEmployeeEditLog('info', 'Employee updated successfully.', [
                'reference' => $logReference,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'actor_user_id' => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee updated successfully.',
                    'redirect_url' => route('hr.employees.show', $employee),
                ]);
            }

            return redirect()
                ->route('hr.employees.show', $employee)
                ->with('success', 'Employee updated successfully.');
        } catch (ValidationException $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->writeEmployeeEditLog('warning', 'Employee update validation failed.', [
                'reference' => $logReference,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'actor_user_id' => auth()->id(),
                'step' => $step,
                'validation_errors' => $exception->errors(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee update validation failed.',
                    'errors' => $exception->errors(),
                    'log_reference' => $logReference,
                ], 422);
            }

            return back()
                ->withErrors($exception->validator)
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Employee was not updated because some fields are invalid. Please check the errors below.')
                ->with('employee_edit_log_ref', $logReference);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $context = [
                'reference' => $logReference,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'actor_user_id' => auth()->id(),
                'step' => $step,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];

            Log::error('Employee update error', $context);
            $this->writeEmployeeEditLog('error', 'Employee update failed.', $context);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update employee. ' . $exception->getMessage(),
                    'log_reference' => $logReference,
                ], 500);
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Failed to update employee: ' . $exception->getMessage())
                ->with('employee_edit_log_ref', $logReference);
        }
    }

    public function updateStatus(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'employment_status' => ['required', Rule::in(['active', 'inactive', 'resigned', 'terminated'])],
        ]);

        $employee->update([
            'employment_status' => $validated['employment_status'],
            'exit_date' => in_array($validated['employment_status'], ['resigned', 'terminated'], true)
                ? ($employee->exit_date ?: now()->toDateString())
                : null,
        ]);

        if ($employee->waiter) {
            $employee->waiter->update([
                'status' => $validated['employment_status'] === 'active',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee status updated successfully.',
        ]);
    }

    public function destroy(Employee $employee)
    {
        if ($this->employeeHasProtectedHistory($employee)) {
            return response()->json([
                'success' => false,
                'message' => 'This employee has login, attendance, leave, roster, salary, advance, loan or payroll history. Set the employee to inactive instead of deleting.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($employee->waiter) {
                $employee->waiter->update([
                    'status' => false,
                    'hr_employee_id' => null,
                ]);
            }

            $this->deleteImage($employee->image);
            $this->deleteImage($employee->nid_image);
            $employee->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully.',
            ]);
        } catch (Throwable $exception) {
            DB::rollBack();
            Log::error('Employee delete error', ['message' => $exception->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee.',
            ], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
        ]);

        $employees = Employee::whereIn('id', $validated['employee_ids'])->get();
        $deleted = [];
        $skipped = [];
        $imagesToDelete = [];

        DB::beginTransaction();

        try {
            foreach ($employees as $employee) {
                if ($this->employeeHasProtectedHistory($employee)) {
                    $skipped[] = $employee->name . ' (' . $employee->employee_code . ')';
                    continue;
                }

                if ($employee->waiter) {
                    $employee->waiter->update([
                        'status' => false,
                        'hr_employee_id' => null,
                    ]);
                }

                if ($employee->image) {
                    $imagesToDelete[] = $employee->image;
                }
                if ($employee->nid_image) {
                    $imagesToDelete[] = $employee->nid_image;
                }

                $deleted[] = $employee->name . ' (' . $employee->employee_code . ')';
                $employee->delete();
            }

            DB::commit();

            foreach ($imagesToDelete as $imagePath) {
                $this->deleteImage($imagePath);
            }

            $message = count($deleted) . ' employee(s) deleted.';
            if ($skipped) {
                $message .= ' ' . count($skipped) . ' employee(s) skipped because they have protected HR/login/salary history.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'deleted_count' => count($deleted),
                'skipped_count' => count($skipped),
                'skipped_employees' => $skipped,
            ]);
        } catch (Throwable $exception) {
            DB::rollBack();
            Log::error('Employee bulk delete error', ['message' => $exception->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete selected employees.',
            ], 500);
        }
    }

    private function formData(): array
    {
        return [
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'designations' => Designation::with('department')->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shifts' => Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'zones' => Zone::where('status', true)->orderBy('name')->get(),
            'hrSetting' => HrSetting::first(),
            'leaveTypes' => LeaveType::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'salaryComponents' => SalaryComponent::where('status', true)->orderByRaw("CASE component_group WHEN 'salary' THEN 1 WHEN 'allowance' THEN 2 ELSE 3 END")->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    /**
     * Write employee edit diagnostics to a dedicated log file in addition to
     * Laravel's normal application log. Passwords and raw request data are
     * intentionally never written here.
     */
    private function writeEmployeeEditLog(string $level, string $message, array $context = []): void
    {
        try {
            $logDirectory = storage_path('logs');

            if (!File::exists($logDirectory)) {
                File::makeDirectory($logDirectory, 0755, true);
            }

            $logger = Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/employee-edit.log'),
                'level' => 'debug',
                'locking' => true,
            ]);

            $level = in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true)
                ? $level
                : 'info';

            $logger->{$level}($message, $context);
        } catch (Throwable $loggingException) {
            // Logging must never break the employee update itself.
            Log::error('Unable to write employee edit diagnostic log.', [
                'message' => $loggingException->getMessage(),
            ]);
        }
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $needsNewLoginAccount = $request->boolean('can_login')
            && (!$employee || !$employee->user_id);

        // On create (or when an employee has no linked user yet), email/password
        // are optional as a pair. Leaving both blank is valid; supplying one requires
        // the other so we never create a half-configured login account.
        // Create: credentials remain optional as a pair. If only one is supplied,
        // require the other so a partial login account is not created.
        // Edit: password is NEVER required. Existing login users keep their current
        // password when the field is blank; employees without a linked user can also
        // be edited with login access enabled and credentials can be completed later.
        $isEditing = $employee !== null;
        $requiresLoginEmail = !$isEditing && $needsNewLoginAccount && $request->filled('password');
        $requiresLoginPassword = !$isEditing && $needsNewLoginAccount && $request->filled('email');

        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => [
                Rule::requiredIf($requiresLoginEmail),
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employee?->id),
                Rule::unique('users', 'email')->ignore($employee?->user_id),
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'designation_id' => ['required', 'exists:designations,id'],
            'employment_type_id' => ['required', 'exists:employment_types,id'],
            'default_shift_id' => ['nullable', 'exists:shifts,id'],
            'zone_id' => [
                Rule::requiredIf($request->boolean('is_waiter')),
                'nullable',
                'exists:zones,id',
            ],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'join_date' => ['required', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'employment_status' => ['required', Rule::in(['active', 'inactive', 'resigned', 'terminated'])],
            'nid' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'nid')->ignore($employee?->id),
            ],
            'image' => ['nullable', 'image', 'max:2048'],
            'nid_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:180'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'salary_enabled' => ['nullable', 'boolean'],
            'salary_effective_from' => [Rule::requiredIf($request->boolean('salary_enabled')), 'nullable', 'date', 'after_or_equal:join_date'],
            'basic_salary' => [Rule::requiredIf($request->boolean('salary_enabled')), 'nullable', 'numeric', 'min:0'],
            'salary_payment_method' => ['nullable', Rule::in(['cash', 'bank', 'mobile_banking'])],
            'salary_account_name' => ['nullable', 'string', 'max:180'],
            'salary_account_number' => ['nullable', 'string', 'max:120'],
            'salary_mobile_banking_provider' => ['nullable', 'string', 'max:60'],
            'payroll_components' => ['nullable', 'array'],
            'payroll_components.*.mode' => ['nullable', Rule::in(['global', 'custom', 'disabled'])],
            'payroll_components.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payroll_components.*.percentage' => ['nullable', 'numeric', 'min:0'],
            'leave_balances' => ['nullable', 'array'],
            'leave_balances.*.mode' => ['nullable', Rule::in(['global', 'custom'])],
            'leave_balances.*.entitled_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'password' => [
                Rule::requiredIf($requiresLoginPassword),
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);
    }

    private function syncSalarySetup(Request $request, Employee $employee): void
    {
        if (!$request->boolean('salary_enabled')) {
            return;
        }

        $effectiveFrom = $request->input('salary_effective_from') ?: $employee->join_date?->toDateString() ?: now()->toDateString();
        $structure = EmployeeSalaryStructure::with('components')->where('employee_id', $employee->id)
            ->whereDate('effective_from', $effectiveFrom)->first();

        if (!$structure) {
            $current = EmployeeSalaryStructure::where('employee_id', $employee->id)->where('status', true)
                ->whereDate('effective_from', '<=', $effectiveFrom)
                ->where(function ($q) use ($effectiveFrom) { $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom); })
                ->latest('effective_from')->first();
            if ($current && $current->effective_from?->toDateString() !== $effectiveFrom) {
                $current->update(['effective_to' => \Carbon\Carbon::parse($effectiveFrom)->subDay()->toDateString()]);
            }
            $structure = new EmployeeSalaryStructure(['employee_id' => $employee->id, 'effective_from' => $effectiveFrom]);
        }

        $structure->fill([
            'employee_id' => $employee->id,
            'effective_from' => $effectiveFrom,
            'basic_salary' => (float) $request->input('basic_salary', 0),
            'overtime_rate' => null,
            'payment_method' => $request->input('salary_payment_method', 'bank'),
            'account_name' => $request->input('salary_account_name'),
            'account_number' => $request->input('salary_account_number'),
            'mobile_banking_provider' => $request->input('salary_mobile_banking_provider'),
            'status' => true,
            'updated_by' => auth()->id(),
        ]);
        if (!$structure->exists) $structure->created_by = auth()->id();
        $structure->save();

        $input = $request->input('payroll_components', []);
        $masters = SalaryComponent::where('status', true)
            ->where('allow_employee_override', true)
            ->where('calculation_type', '!=', SalaryComponent::CALCULATION_MANUAL)
            ->get()->keyBy('id');
        foreach ($masters as $master) {
            if (strtoupper((string) $master->code) === 'BASIC') continue;
            $row = $input[$master->id] ?? [];
            $mode = $row['mode'] ?? 'global';
            $structure->components()->updateOrCreate(
                ['salary_component_id' => $master->id],
                [
                    'component_type' => $master->type,
                    'calculation_type' => $master->calculation_type,
                    'rule_mode' => $mode,
                    'amount' => $mode === 'custom' ? (float) ($row['amount'] ?? 0) : 0,
                    'percentage' => $mode === 'custom' ? (float) ($row['percentage'] ?? 0) : 0,
                    'is_active' => true,
                ]
            );
        }
    }

    private function syncLeaveBalanceSetup(Request $request, Employee $employee): void
    {
        $year = (int) now()->year;
        $input = $request->input('leave_balances', []);

        foreach (LeaveType::where('status', true)->get() as $leaveType) {
            $row = $input[$leaveType->id] ?? [];
            $mode = ($row['mode'] ?? 'global') === 'custom' ? 'custom' : 'global';
            $entitledDays = $mode === 'custom'
                ? (float) ($row['entitled_days'] ?? 0)
                : (float) $leaveType->days_per_year;

            EmployeeLeaveBalance::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'year' => $year,
                ],
                [
                    'entitlement_mode' => $mode,
                    'entitled_days' => $entitledDays,
                ]
            );
        }
    }

    private function employeeHasProtectedHistory(Employee $employee): bool
    {
        return (bool) $employee->user_id
            || $employee->attendances()->exists()
            || $employee->leaveRequests()->exists()
            || $employee->shiftRosters()->exists()
            || $employee->salaryStructures()->exists()
            || $employee->salaryAdvances()->exists()
            || $employee->loans()->exists()
            || $employee->payrollItems()->exists();
    }

    private function nextEmployeeCode(): string
    {
        $setting = HrSetting::query()->lockForUpdate()->first();

        if (!$setting) {
            $setting = HrSetting::create([
                'employee_code_prefix' => 'EMP',
                'employee_code_next_number' => 1,
                'employee_code_padding' => 4,
                'status' => true,
            ]);
        }

        $number = max(1, (int) $setting->employee_code_next_number);

        do {
            $code = strtoupper($setting->employee_code_prefix ?: 'EMP')
                . '-'
                . str_pad(
                    (string) $number,
                    (int) ($setting->employee_code_padding ?: 4),
                    '0',
                    STR_PAD_LEFT
                );
            $number++;
        } while (
            Employee::where('employee_code', $code)->exists()
            || Waiter::where('employee_id', $code)->exists()
        );

        $setting->update(['employee_code_next_number' => $number]);

        return $code;
    }

    private function createEmployeeUser(Request $request, string $employeeCode, bool $isWaiter): User
    {
        $role = $this->resolveEmployeeRole($isWaiter);

        $user = User::create([
            'user_id' => $employeeCode,
            'name' => $request->name,
            'first_name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * Reuse the existing Employee/Waiter role regardless of historical casing
     * (for example "Employee" versus "employee") so editing an older user
     * cannot fail with a Spatie role-not-found exception.
     */
    private function resolveEmployeeRole(bool $isWaiter): Role
    {
        $wanted = $isWaiter ? 'waiter' : 'employee';

        $role = Role::where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', [$wanted])
            ->first();

        return $role ?: Role::create([
            'name' => $isWaiter ? 'Waiter' : 'Employee',
            'guard_name' => 'web',
        ]);
    }

    private function syncWaiter(Employee $employee): void
    {
        $waiter = Waiter::where('hr_employee_id', $employee->id)->first();

        if (!$waiter) {
            $waiter = Waiter::where('employee_id', $employee->employee_code)->first();
        }

        if (!$waiter && $employee->email) {
            $waiter = Waiter::where('email', $employee->email)->first();
        }

        $data = [
            'hr_employee_id' => $employee->id,
            'user_id' => $employee->user_id,
            'zone_id' => $employee->zone_id,
            'shift_id' => $employee->default_shift_id,
            'employee_id' => $employee->employee_code,
            'name' => $employee->name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'image' => $employee->image,
            'join_date' => $employee->join_date,
            'notes' => $employee->notes,
            'status' => $employee->employment_status === 'active',
        ];

        $waiter ? $waiter->update($data) : Waiter::create($data);
    }

    private function uploadImage($file): string
    {
        $directory = public_path('uploads/employees');

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $name = 'employee_'
            . now()->format('YmdHis')
            . '_'
            . Str::random(6)
            . '.'
            . $file->getClientOriginalExtension();

        $file->move($directory, $name);

        return 'uploads/employees/' . $name;
    }

    private function uploadNidImage($file): string
    {
        $directory = public_path('uploads/employees/nid');

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $name = 'nid_'
            . now()->format('YmdHis')
            . '_'
            . Str::random(6)
            . '.'
            . $file->getClientOriginalExtension();

        $file->move($directory, $name);

        return 'uploads/employees/nid/' . $name;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
