<?php

namespace App\Services\Hr;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeBranchTransfer;
use App\Models\HrSetting;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\ShiftRoster;
use App\Models\User;
use App\Models\Waiter;
use App\Services\BranchModeManager;
use App\Services\BranchSettingResolver;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EmployeeBranchTransferService
{
    public function transfer(Employee $employee, int $toBranchId, string $effectiveDate, ?string $note, User $actor): EmployeeBranchTransfer
    {
        if (!$actor->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Only Super Admin can transfer an employee between branches.');
        }

        $target = Branch::query()->active()->findOrFail($toBranchId);

        return DB::transaction(function () use ($employee, $target, $effectiveDate, $note, $actor) {
            $locked = Employee::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($employee->id);
            $fromBranchId = (int) $locked->branch_id;

            if (!$fromBranchId || $fromBranchId === (int) $target->id) {
                throw ValidationException::withMessages([
                    'to_branch_id' => 'Choose a different active target branch.',
                ]);
            }

            $effective = Carbon::parse($effectiveDate)->startOfDay();
            if ($locked->join_date && $effective->lt($locked->join_date->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'effective_date' => 'Transfer date cannot be earlier than the employee join date.',
                ]);
            }

            $lastTransfer = EmployeeBranchTransfer::query()
                ->where('employee_id', $locked->id)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if ($lastTransfer && $effective->lte($lastTransfer->effective_date->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'effective_date' => 'Transfer date must be later than the previous branch transfer date.',
                ]);
            }

            $openPayroll = PayrollItem::query()
                ->withoutGlobalScopes()
                ->where('employee_id', $locked->id)
                ->where('branch_id', $fromBranchId)
                ->whereIn('status', ['draft', 'approved'])
                ->exists();

            if ($openPayroll) {
                throw ValidationException::withMessages([
                    'to_branch_id' => 'Complete or remove this employee from the current branch draft/approved payroll before transfer.',
                ]);
            }

            $openLeave = LeaveRequest::query()
                ->withoutGlobalScopes()
                ->where('employee_id', $locked->id)
                ->where('branch_id', $fromBranchId)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('to_date', '>=', $effectiveDate)
                ->exists();

            if ($openLeave) {
                throw ValidationException::withMessages([
                    'to_branch_id' => 'Resolve or cancel pending/approved leave that reaches the transfer date before moving this employee.',
                ]);
            }

            // Future planning in the source branch must not follow a transferred employee.
            ShiftRoster::query()
                ->withoutGlobalScopes()
                ->where('employee_id', $locked->id)
                ->where('branch_id', $fromBranchId)
                ->whereDate('roster_date', '>=', $effectiveDate)
                ->delete();

            // Preserve the old waiter as historical identity but detach it from the HR employee.
            $oldWaiter = Waiter::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $fromBranchId)
                ->where('hr_employee_id', $locked->id)
                ->first();

            if ($oldWaiter) {
                DB::table('waiters')->where('id', $oldWaiter->id)->update([
                    'status' => false,
                    'hr_employee_id' => null,
                    'user_id' => null,
                    'updated_at' => now(),
                ]);
            }

            $targetEmployeeCode = $this->targetEmployeeCode($locked, (int) $target->id);

            // Source-branch master IDs are intentionally cleared. The target branch must assign
            // its own Department/Designation/Employment Type/Shift/Zone and new salary structure.
            DB::table('employees')->where('id', $locked->id)->update([
                'branch_id' => $target->id,
                'employee_code' => $targetEmployeeCode,
                'department_id' => null,
                'designation_id' => null,
                'employment_type_id' => null,
                'default_shift_id' => null,
                'zone_id' => null,
                'is_waiter' => false,
                'updated_at' => now(),
            ]);

            if ($locked->user_id) {
                $user = User::query()->withoutGlobalScopes()->find($locked->user_id);
                if ($user && !$user->isSuperAdmin()) {
                    $userUpdates = [
                        'branch_id' => $target->id,
                        'updated_at' => now(),
                    ];
                    if ((string) $user->user_id === (string) $locked->employee_code) {
                        $userUpdates['user_id'] = $targetEmployeeCode;
                    }
                    DB::table('users')->where('id', $user->id)->update($userUpdates);
                }
            }

            $transfer = EmployeeBranchTransfer::create([
                'employee_id' => $locked->id,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $target->id,
                'effective_date' => $effectiveDate,
                'approved_by' => $actor->id,
                'employee_code_snapshot' => $locked->employee_code,
                'employee_name_snapshot' => $locked->name,
                'note' => $note,
            ]);

            app(BranchModeManager::class)->lockForSecondaryBranch((int) $target->id, 'employees');

            app(AuditLogger::class)->log(
                action: 'employee.transferred',
                branchId: (int) $target->id,
                description: 'Transferred employee ' . $locked->name . ' from branch #' . $fromBranchId . ' to ' . $target->name,
                before: [
                    'branch_id' => $fromBranchId,
                    'employee_code' => $locked->employee_code,
                ],
                after: [
                    'branch_id' => (int) $target->id,
                    'employee_code' => $targetEmployeeCode,
                    'effective_date' => $effectiveDate,
                ],
                auditableType: Employee::class,
                auditableId: (int) $locked->id,
                metadata: [
                    'transfer_id' => (int) $transfer->id,
                    'from_branch_id' => $fromBranchId,
                    'to_branch_id' => (int) $target->id,
                    'approved_by' => (int) $actor->id,
                    'note' => $note,
                ]
            );

            return $transfer;
        });
    }

    private function targetEmployeeCode(Employee $employee, int $targetBranchId): string
    {
        $current = (string) $employee->employee_code;
        $conflict = Employee::query()->withoutGlobalScopes()
            ->where('branch_id', $targetBranchId)
            ->where('employee_code', $current)
            ->whereKeyNot($employee->id)
            ->exists()
            || Waiter::query()->withoutGlobalScopes()
                ->where('branch_id', $targetBranchId)
                ->where('employee_id', $current)
                ->exists()
            || User::query()->withoutGlobalScopes()
                ->where('user_id', $current)
                ->when($employee->user_id, fn ($query) => $query->whereKeyNot($employee->user_id))
                ->exists();

        if (!$conflict) {
            return $current;
        }

        app(BranchSettingResolver::class)->ensureForBranch(HrSetting::class, $targetBranchId);
        $setting = HrSetting::query()->withoutGlobalScopes()
            ->where('branch_id', $targetBranchId)
            ->lockForUpdate()
            ->firstOrFail();

        $number = max(1, (int) $setting->employee_code_next_number);
        do {
            $candidate = strtoupper($setting->employee_code_prefix ?: 'EMP') . '-'
                . str_pad((string) $number, (int) ($setting->employee_code_padding ?: 4), '0', STR_PAD_LEFT);
            $number++;
            $used = Employee::query()->withoutGlobalScopes()->where('branch_id', $targetBranchId)->where('employee_code', $candidate)->exists()
                || Waiter::query()->withoutGlobalScopes()->where('branch_id', $targetBranchId)->where('employee_id', $candidate)->exists()
                || User::query()->withoutGlobalScopes()->where('user_id', $candidate)->exists();
        } while ($used);

        $setting->forceFill(['employee_code_next_number' => $number])->saveQuietly();
        return $candidate;
    }

}
