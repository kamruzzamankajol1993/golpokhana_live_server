<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\HrSetting;
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
use Spatie\Permission\Models\Role;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:employee-view')->only(['index', 'show']);
        $this->middleware('permission:employee-create')->only(['create', 'store']);
        $this->middleware('permission:employee-edit')->only(['edit', 'update', 'updateStatus']);
        $this->middleware('permission:employee-delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Employee::with([
                'department',
                'designation',
                'employmentType',
                'defaultShift',
                'zone',
                'user',
                'waiter',
                'currentSalaryStructure.components',
            ])->latest('id');

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
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

            $employees = $query->paginate(10)->withQueryString();

            return view('admin.hr.employees.table', compact('employees'))->render();
        }

        return view('admin.hr.employees.index', [
            'totalEmployees' => Employee::count(),
            'activeEmployees' => Employee::where('employment_status', 'active')->count(),
            'waiterEmployees' => Employee::where('is_waiter', true)->count(),
            'loginEmployees' => Employee::where('can_login', true)->count(),
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'designations' => Designation::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shifts' => Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
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
        $employee->load(['user', 'waiter']);

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

            $user = $canLogin
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
                'gender' => $validated['gender'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'join_date' => $validated['join_date'],
                'probation_end_date' => $validated['probation_end_date'] ?? null,
                'exit_date' => $validated['exit_date'] ?? null,
                'employment_status' => $validated['employment_status'],
                'is_waiter' => $isWaiter,
                'can_login' => $canLogin,
                'image' => $imagePath,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($isWaiter) {
                $this->syncWaiter($employee);
            }

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
        $validated = $this->validateEmployee($request, $employee);

        DB::beginTransaction();

        try {
            $canLogin = $request->boolean('can_login');
            $isWaiter = $request->boolean('is_waiter');

            if ($request->hasFile('image')) {
                $oldImage = $employee->image;
                $employee->image = $this->uploadImage($request->file('image'));
                $this->deleteImage($oldImage);
            }

            if ($canLogin) {
                $user = $employee->user
                    ?: $this->createEmployeeUser($request, $employee->employee_code, $isWaiter);

                $user->name = $validated['name'];
                $user->first_name = $validated['name'];
                $user->email = $validated['email'];
                $user->phone = $validated['phone'];

                if ($request->filled('password')) {
                    $user->password = Hash::make($request->password);
                }

                $user->save();
                $user->syncRoles([$isWaiter ? 'waiter' : 'employee']);
                $employee->user_id = $user->id;
            }

            $employee->fill([
                'department_id' => $validated['department_id'],
                'designation_id' => $validated['designation_id'],
                'employment_type_id' => $validated['employment_type_id'],
                'default_shift_id' => $validated['default_shift_id'] ?? null,
                'zone_id' => $isWaiter ? ($validated['zone_id'] ?? null) : null,
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
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

            if ($isWaiter) {
                $this->syncWaiter($employee->fresh());
            } elseif ($employee->waiter) {
                $employee->waiter->update([
                    'status' => false,
                    'hr_employee_id' => null,
                ]);
            }

            DB::commit();

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
        } catch (Throwable $exception) {
            DB::rollBack();
            Log::error('Employee update error', ['message' => $exception->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update employee. ' . $exception->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Failed to update employee. ' . $exception->getMessage());
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
        if (
            $employee->user_id
            || $employee->attendances()->exists()
            || $employee->leaveRequests()->exists()
            || $employee->shiftRosters()->exists()
            || $employee->salaryStructures()->exists()
            || $employee->payrollItems()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This employee has login, attendance, leave, roster, salary or payroll history. Set the employee to inactive instead of deleting.',
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

    private function formData(): array
    {
        return [
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'designations' => Designation::with('department')->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shifts' => Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'zones' => Zone::where('status', true)->orderBy('name')->get(),
            'hrSetting' => HrSetting::first(),
        ];
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $requiresNewPassword = $request->boolean('can_login')
            && (!$employee || !$employee->user_id || !$employee->can_login);

        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => [
                Rule::requiredIf($request->boolean('can_login')),
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
            'image' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:180'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'password' => [
                Rule::requiredIf($requiresNewPassword),
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);
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
        $roleName = $isWaiter ? 'waiter' : 'employee';
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $user = User::create([
            'user_id' => $employeeCode,
            'name' => $request->name,
            'first_name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($roleName);

        return $user;
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

    private function deleteImage(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
