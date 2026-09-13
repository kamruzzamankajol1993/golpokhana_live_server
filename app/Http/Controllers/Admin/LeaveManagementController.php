<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeaveManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:leave-management-view')->only(['index', 'balances']);
        $this->middleware('permission:leave-management-create')->only('store');
        $this->middleware('permission:leave-management-edit')->only(['update', 'cancel']);
        $this->middleware('permission:leave-management-approve')->only('decision');
        $this->middleware('permission:leave-management-delete')->only('destroy');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = LeaveRequest::with(['employee.department', 'employee.designation', 'leaveType', 'approvedBy'])
                ->latest('id');

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('leave_type_id')) {
                $query->where('leave_type_id', $request->leave_type_id);
            }

            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('to_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('from_date', '<=', $request->to_date);
            }

            $leaves = $query->paginate(10)->withQueryString();
            return view('admin.hr.leaves.table', compact('leaves'))->render();
        }

        $today = now()->toDateString();

        return view('admin.hr.leaves.index', [
            'employees' => Employee::active()->orderBy('name')->get(['id', 'name', 'employee_code']),
            'leaveTypes' => LeaveType::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'pendingCount' => LeaveRequest::where('status', 'pending')->count(),
            'approvedThisMonth' => LeaveRequest::where('status', 'approved')->whereYear('from_date', now()->year)->whereMonth('from_date', now()->month)->count(),
            'onLeaveToday' => LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $today)->whereDate('to_date', '>=', $today)->count(),
            'rejectedThisMonth' => LeaveRequest::where('status', 'rejected')->whereYear('updated_at', now()->year)->whereMonth('updated_at', now()->month)->count(),
        ]);
    }

    public function balances(Employee $employee, Request $request)
    {
        $year = (int) ($request->year ?: now()->year);
        $leaveTypes = LeaveType::where('status', true)->orderBy('sort_order')->orderBy('name')->get();
        $this->syncAllBalances($employee, $year);
        $balances = EmployeeLeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->map(function ($balance) {
                return [
                    'leave_type_id' => $balance->leave_type_id,
                    'name' => $balance->leaveType?->name,
                    'mode' => $balance->entitlement_mode ?: 'global',
                    'global_entitled' => (float) ($balance->leaveType?->days_per_year ?? 0),
                    'entitled' => (float) $balance->entitled_days,
                    'used' => (float) $balance->used_days,
                    'available' => $balance->available_days,
                    'is_paid' => (bool) $balance->leaveType?->is_paid,
                ];
            });

        return response()->json(['success' => true, 'employee' => $employee->only(['id', 'name', 'employee_code']), 'balances' => $balances, 'year' => $year]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateLeave($request);
        $this->ensureNoOverlap($validated['employee_id'], $validated['from_date'], $validated['to_date']);

        DB::beginTransaction();
        try {
            $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
            if ($leaveType->requires_document && !$request->hasFile('attachment')) {
                throw ValidationException::withMessages(['attachment' => ['A supporting document is required for this leave type.']]);
            }
            $totalDays = $this->calculateWorkingDays($validated['from_date'], $validated['to_date']);
            if ($totalDays <= 0) {
                return $this->validationError('from_date', 'The selected period contains no working day.');
            }

            $attachment = $request->hasFile('attachment') ? $this->uploadAttachment($request->file('attachment')) : null;

            LeaveRequest::create([
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'total_days' => $totalDays,
                'reason' => $validated['reason'],
                'attachment' => $attachment,
                'status' => 'pending',
                'is_paid' => $leaveType->is_paid,
                'applied_by' => auth()->id(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => "Leave request submitted successfully for {$totalDays} day(s)."]);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to submit leave request. ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        if ($leave->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending leave requests can be edited.'], 422);
        }

        $validated = $this->validateLeave($request);
        $this->ensureNoOverlap($validated['employee_id'], $validated['from_date'], $validated['to_date'], $leave->id);

        DB::beginTransaction();
        try {
            $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
            if ($leaveType->requires_document && !$leave->attachment && !$request->hasFile('attachment')) {
                throw ValidationException::withMessages(['attachment' => ['A supporting document is required for this leave type.']]);
            }
            $totalDays = $this->calculateWorkingDays($validated['from_date'], $validated['to_date']);
            if ($totalDays <= 0) {
                return $this->validationError('from_date', 'The selected period contains no working day.');
            }

            if ($request->hasFile('attachment')) {
                $this->deleteAttachment($leave->attachment);
                $leave->attachment = $this->uploadAttachment($request->file('attachment'));
            }

            $leave->fill([
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'total_days' => $totalDays,
                'reason' => $validated['reason'],
                'is_paid' => $leaveType->is_paid,
            ])->save();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Leave request updated successfully.']);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to update leave request. ' . $e->getMessage()], 500);
        }
    }

    public function decision(Request $request, LeaveRequest $leave)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($leave->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'This leave request has already been processed.'], 422);
        }

        DB::beginTransaction();
        try {
            if ($validated['decision'] === 'approved') {
                $this->ensureAttendanceDatesAvailable($leave);
                $this->ensureLeaveBalanceAvailable($leave);
            }

            $leave->update([
                'status' => $validated['decision'],
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_note' => $validated['approval_note'] ?? null,
            ]);

            if ($validated['decision'] === 'approved') {
                $this->applyApprovedLeaveToAttendance($leave->fresh(['employee', 'leaveType']));
            }

            $this->syncAllBalances($leave->employee, (int) $leave->from_date->format('Y'));

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Leave request ' . $validated['decision'] . ' successfully.']);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(LeaveRequest $leave)
    {
        if (!in_array($leave->status, ['pending', 'approved'], true)) {
            return response()->json(['success' => false, 'message' => 'This leave request cannot be cancelled.'], 422);
        }

        DB::beginTransaction();
        try {
            if ($leave->status === 'approved') {
                Attendance::where('employee_id', $leave->employee_id)
                    ->whereBetween('attendance_date', [$leave->from_date, $leave->to_date])
                    ->where('status', 'leave')
                    ->where('source', 'system')
                    ->delete();

                ShiftRoster::where('employee_id', $leave->employee_id)
                    ->whereBetween('roster_date', [$leave->from_date, $leave->to_date])
                    ->where('status', 'leave')
                    ->update(['status' => 'scheduled']);
            }

            $leave->update(['status' => 'cancelled']);
            $this->syncAllBalances($leave->employee, (int) $leave->from_date->format('Y'));
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Leave request cancelled successfully.']);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to cancel leave request.'], 500);
        }
    }

    public function destroy(LeaveRequest $leave)
    {
        if (!in_array($leave->status, ['pending', 'rejected', 'cancelled'], true)) {
            return response()->json(['success' => false, 'message' => 'Approved leave cannot be deleted. Cancel it first.'], 422);
        }

        $this->deleteAttachment($leave->attachment);
        $leave->delete();
        return response()->json(['success' => true, 'message' => 'Leave request deleted successfully.']);
    }

    private function validateLeave(Request $request): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        if (Carbon::parse($validated['from_date'])->year !== Carbon::parse($validated['to_date'])->year) {
            throw ValidationException::withMessages([
                'to_date' => ['A leave request must remain within one calendar year. Create a separate request for the next year.'],
            ]);
        }

        return $validated;
    }

    private function ensureNoOverlap(int $employeeId, string $from, string $to, ?int $ignoreId = null): void
    {
        $overlap = LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'from_date' => ['Another pending or approved leave request overlaps the selected dates.'],
            ]);
        }
    }

    private function calculateWorkingDays(string|Carbon $from, string|Carbon $to): float
    {
        $fromDate = $from instanceof Carbon ? $from->copy() : Carbon::parse($from);
        $toDate = $to instanceof Carbon ? $to->copy() : Carbon::parse($to);
        $weeklyOff = collect(AttendanceSetting::first()?->weekly_off_days ?? [])->map(fn ($day) => strtolower($day))->all();
        $holidays = Holiday::where('status', true)
            ->whereBetween('holiday_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $days = 0;
        foreach (CarbonPeriod::create($fromDate, $toDate) as $date) {
            if (in_array(strtolower($date->format('l')), $weeklyOff, true)) {
                continue;
            }
            if (in_array($date->toDateString(), $holidays, true)) {
                continue;
            }
            $days++;
        }
        return (float) $days;
    }

    private function ensureLeaveBalanceAvailable(LeaveRequest $leave): void
    {
        $leaveType = $leave->leaveType;
        if (!$leaveType || (float) $leaveType->days_per_year <= 0) {
            return;
        }

        $year = (int) $leave->from_date->format('Y');
        $this->syncAllBalances($leave->employee, $year);
        $balance = EmployeeLeaveBalance::where([
            'employee_id' => $leave->employee_id,
            'leave_type_id' => $leave->leave_type_id,
            'year' => $year,
        ])->first();

        if ($balance && $balance->available_days < (float) $leave->total_days) {
            throw new \RuntimeException("Insufficient leave balance. Available: {$balance->available_days} day(s).");
        }
    }

    private function ensureAttendanceDatesAvailable(LeaveRequest $leave): void
    {
        $hasConflict = Attendance::where('employee_id', $leave->employee_id)
            ->whereBetween('attendance_date', [$leave->from_date, $leave->to_date])
            ->whereNotIn('status', ['leave', 'off_day'])
            ->exists();

        if ($hasConflict) {
            throw new \RuntimeException('Attendance is already marked for one or more selected leave dates. Remove or correct attendance first.');
        }
    }

    private function applyApprovedLeaveToAttendance(LeaveRequest $leave): void
    {
        $weeklyOff = collect(AttendanceSetting::first()?->weekly_off_days ?? [])->map(fn ($day) => strtolower($day))->all();
        $holidayDates = Holiday::where('status', true)
            ->whereBetween('holiday_date', [$leave->from_date, $leave->to_date])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        foreach (CarbonPeriod::create($leave->from_date, $leave->to_date) as $date) {
            if (in_array(strtolower($date->format('l')), $weeklyOff, true) || in_array($date->toDateString(), $holidayDates, true)) {
                continue;
            }

            $roster = ShiftRoster::where('employee_id', $leave->employee_id)->whereDate('roster_date', $date)->first();
            Attendance::updateOrCreate(
                ['employee_id' => $leave->employee_id, 'attendance_date' => $date->toDateString()],
                [
                    'shift_id' => $roster?->shift_id ?: $leave->employee?->default_shift_id,
                    'status' => 'leave',
                    'source' => 'system',
                    'check_in' => null,
                    'check_out' => null,
                    'late_minutes' => 0,
                    'early_leave_minutes' => 0,
                    'overtime_minutes' => 0,
                    'worked_minutes' => 0,
                    'notes' => $leave->leaveType?->name,
                    'marked_by' => auth()->id(),
                ]
            );

            if ($roster) {
                $roster->update(['status' => 'leave']);
            }
        }
    }

    private function syncAllBalances(Employee $employee, int $year): void
    {
        foreach (LeaveType::where('status', true)->get() as $type) {
            $used = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->whereDate('from_date', '<=', "{$year}-12-31")
                ->whereDate('to_date', '>=', "{$year}-01-01")
                ->get()
                ->sum(function ($leave) use ($year) {
                    $yearStart = Carbon::create($year, 1, 1)->startOfDay();
                    $yearEnd = Carbon::create($year, 12, 31)->startOfDay();
                    $from = $leave->from_date->greaterThan($yearStart) ? $leave->from_date->copy() : $yearStart;
                    $to = $leave->to_date->lessThan($yearEnd) ? $leave->to_date->copy() : $yearEnd;
                    return $this->calculateWorkingDays($from, $to);
                });

            $balance = EmployeeLeaveBalance::firstOrNew([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $year,
            ]);

            $mode = $balance->exists && $balance->entitlement_mode === 'custom'
                ? 'custom'
                : 'global';

            $balance->entitlement_mode = $mode;
            if ($mode === 'global') {
                $balance->entitled_days = $type->days_per_year;
            }
            $balance->used_days = $used;
            $balance->save();
        }
    }

    private function uploadAttachment($file): string
    {
        $directory = public_path('uploads/hr/leaves');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        $name = 'leave_' . now()->format('YmdHis') . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $name);
        return 'uploads/hr/leaves/' . $name;
    }

    private function deleteAttachment(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }

    private function validationError(string $field, string $message)
    {
        DB::rollBack();
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
