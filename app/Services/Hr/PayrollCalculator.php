<?php

namespace App\Services\Hr;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PayrollCalculator
{
    private PayrollSetting $payrollSetting;
    private AttendanceSetting $attendanceSetting;
    private Collection $salaryComponents;

    public function __construct()
    {
        $this->payrollSetting = PayrollSetting::firstOrCreate([], [
            'salary_cycle_start_day' => 1,
            'working_days_method' => 'calendar_days',
            'default_working_days' => 30,
            'absent_deduction_method' => 'per_day',
            'deduction_basis' => 'basic_salary',
            'half_day_deduction_percentage' => 50,
            'late_deduction_method' => 'none',
            'late_count_threshold' => 3,
            'overtime_calculation_method' => 'hourly_rate',
            'overtime_basis' => 'employee_rate',
            'overtime_rate_multiplier' => 1.5,
            'rounding_method' => 'nearest',
            'allow_negative_salary' => false,
            'lock_paid_payroll' => true,
            'allow_non_current_month_payroll' => false,
            'currency' => 'BDT',
            'status' => true,
        ]);

        $this->attendanceSetting = AttendanceSetting::firstOrCreate([], [
            'grace_minutes' => 10,
            'half_day_after_minutes' => 240,
            'absent_after_minutes' => 480,
            'minimum_overtime_minutes' => 30,
            'default_working_hours' => 8,
            'weekly_off_days' => [],
            'allow_manual_attendance' => true,
            'auto_calculate_late' => true,
            'auto_calculate_overtime' => true,
            'status' => true,
        ]);

        $this->salaryComponents = SalaryComponent::where('status', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy(fn (SalaryComponent $component) => strtoupper((string) ($component->code ?: $component->name)));
    }

    public function period(string $month): array
    {
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return [$monthDate->copy()->startOfMonth(), $monthDate->copy()->endOfMonth()];
    }

    public function eligibleEmployeesQuery(Carbon $start, Carbon $end): Builder
    {
        return Employee::query()
            ->whereDate('join_date', '<=', $end)
            ->where(function (Builder $query) use ($start) {
                $query->whereNull('exit_date')->orWhereDate('exit_date', '>=', $start);
            });
    }

    public function precheck(string $month): array
    {
        [$start, $end] = $this->period($month);
        $employees = $this->eligibleEmployeesQuery($start, $end)
            ->with(['department', 'designation'])
            ->orderBy('employee_code')
            ->get();

        $missingSalary = [];
        $missingAttendance = 0;

        foreach ($employees as $employee) {
            if (!$this->salaryStructureFor($employee, $end)) {
                $missingSalary[] = [
                    'id' => $employee->id,
                    'code' => $employee->employee_code,
                    'name' => $employee->name,
                ];
            }

            $missingAttendance += $this->missingAttendanceCount($employee, $start, $end);
        }

        $pendingLeave = LeaveRequest::where('status', 'pending')
            ->whereDate('from_date', '<=', $end)
            ->whereDate('to_date', '>=', $start)
            ->count();

        return [
            'month' => $start->format('Y-m'),
            'month_label' => $start->format('F Y'),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'eligible_employees' => $employees->count(),
            'salary_ready' => $employees->count() - count($missingSalary),
            'missing_salary_count' => count($missingSalary),
            'missing_salary' => $missingSalary,
            'missing_attendance_count' => $missingAttendance,
            'pending_leave_count' => $pendingLeave,
        ];
    }


    public function precheckEmployee(Employee $employee, string $month): array
    {
        [$start, $end] = $this->period($month);

        $eligible = $employee->join_date
            && $employee->join_date->lte($end)
            && (!$employee->exit_date || $employee->exit_date->gte($start));

        $hasSalary = $eligible && (bool) $this->salaryStructureFor($employee, $end);
        $missingAttendance = $eligible ? $this->missingAttendanceCount($employee, $start, $end) : 0;
        $pendingLeave = $eligible
            ? LeaveRequest::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->whereDate('from_date', '<=', $end)
                ->whereDate('to_date', '>=', $start)
                ->count()
            : 0;

        return [
            'month' => $start->format('Y-m'),
            'month_label' => $start->format('F Y'),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'eligible' => $eligible,
            'salary_ready' => $hasSalary,
            'missing_attendance_count' => $missingAttendance,
            'pending_leave_count' => $pendingLeave,
        ];
    }

    public function calculate(Employee $employee, Carbon $start, Carbon $end): array
    {
        $structure = $this->salaryStructureFor($employee, $end);
        if (!$structure) {
            throw new \RuntimeException("Salary structure is missing for {$employee->employee_code}.");
        }

        $employmentStart = $employee->join_date && $employee->join_date->gt($start)
            ? $employee->join_date->copy()->startOfDay()
            : $start->copy();
        $employmentEnd = $employee->exit_date && $employee->exit_date->lt($end)
            ? $employee->exit_date->copy()->startOfDay()
            : $end->copy();

        $monthDays = max(1, $start->daysInMonth);
        $payableCalendarDays = max(0, $employmentStart->diffInDays($employmentEnd) + 1);
        $prorationFactor = min(1, $payableCalendarDays / $monthDays);
        $fullBasicSalary = (float) $structure->basic_salary;
        $proratedBasic = round($fullBasicSalary * $prorationFactor, 2);

        $attendanceRows = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$employmentStart, $employmentEnd])
            ->get();
        $statusCounts = $attendanceRows->countBy('status');
        $overtimeMinutes = (int) $attendanceRows->sum('overtime_minutes');
        $unpaidLeaveDays = $this->unpaidLeaveDays($employee, $employmentStart, $employmentEnd);
        $paidLeaveDays = max(0, (float) ($statusCounts['leave'] ?? 0) - $unpaidLeaveDays);
        $expectedWorkingDays = max(1, $this->expectedWorkingDates($employee, $employmentStart, $employmentEnd)->count());

        $salaryDivisor = match ($this->payrollSetting->working_days_method) {
            'fixed_days' => max(1, (float) $this->payrollSetting->default_working_days),
            'attendance_days' => $expectedWorkingDays,
            default => $monthDays,
        };

        // Use the full monthly rate for per-day deductions. The earning side is
        // prorated separately for joining/exit dates; using the prorated amount here
        // would under-deduct an absence for a mid-month joiner.
        $baseForDeduction = $this->payrollSetting->deduction_basis === 'gross_salary'
            ? $this->estimatedProratedGross($structure, 1, $fullBasicSalary)
            : $fullBasicSalary;
        $dailyRate = $salaryDivisor > 0 ? $baseForDeduction / $salaryDivisor : 0;

        $components = [];
        $components[] = $this->componentRow(
            $this->componentByCode('BASIC'),
            'Basic Salary',
            'BASIC',
            'earning',
            'employee_fixed',
            $fullBasicSalary,
            $prorationFactor,
            $proratedBasic,
            false,
            1
        );

        $reservedCodes = ['BASIC', 'OT', 'ABSENT', 'UNPAID', 'HALF-DAY', 'LATE'];
        foreach ($structure->components as $employeeComponent) {
            if (!$employeeComponent->is_active || !$employeeComponent->salaryComponent) {
                continue;
            }

            $master = $employeeComponent->salaryComponent;
            $code = strtoupper((string) ($master->code ?: 'COMP-' . $master->id));
            if (in_array($code, $reservedCodes, true)) {
                continue;
            }

            $amount = 0;
            $rate = 0;
            $quantity = 1;
            $isManual = $employeeComponent->calculation_type === 'manual';

            if ($employeeComponent->calculation_type === 'fixed') {
                $rate = (float) $employeeComponent->amount;
                $quantity = $prorationFactor;
                $amount = round($rate * $quantity, 2);
            } elseif ($employeeComponent->calculation_type === 'percentage') {
                $rate = (float) $employeeComponent->percentage;
                $quantity = $proratedBasic;
                $amount = round($proratedBasic * $rate / 100, 2);
            }

            $components[] = $this->componentRow(
                $master,
                $master->name,
                $code,
                $employeeComponent->component_type,
                $employeeComponent->calculation_type,
                $rate,
                $quantity,
                $amount,
                $isManual,
                (int) $master->sort_order + ($master->type === 'deduction' ? 100 : 10)
            );
        }

        $overtimeHours = round($overtimeMinutes / 60, 4);
        $overtimeRate = $this->resolveOvertimeRate($structure, $fullBasicSalary, $salaryDivisor);
        $overtimeAmount = $this->payrollSetting->overtime_calculation_method === 'none'
            ? 0
            : round($overtimeHours * $overtimeRate, 2);
        $components[] = $this->componentRow(
            $this->componentByCode('OT'),
            'Overtime',
            'OT',
            'earning',
            'overtime_hours',
            $overtimeRate,
            $overtimeHours,
            $overtimeAmount,
            false,
            90
        );

        $absentDays = (float) ($statusCounts['absent'] ?? 0);
        $absentAmount = $this->payrollSetting->absent_deduction_method === 'per_day'
            ? round($dailyRate * $absentDays, 2)
            : 0;
        $components[] = $this->componentRow(
            $this->componentByCode('ABSENT'),
            'Absent Deduction',
            'ABSENT',
            'deduction',
            'attendance_days',
            $dailyRate,
            $absentDays,
            $absentAmount,
            false,
            101
        );

        $unpaidAmount = round($dailyRate * $unpaidLeaveDays, 2);
        $components[] = $this->componentRow(
            $this->componentByCode('UNPAID'),
            'Unpaid Leave Deduction',
            'UNPAID',
            'deduction',
            'leave_days',
            $dailyRate,
            $unpaidLeaveDays,
            $unpaidAmount,
            false,
            102
        );

        $halfDays = (float) ($statusCounts['half_day'] ?? 0);
        $halfRate = max(0, min(100, (float) $this->payrollSetting->half_day_deduction_percentage)) / 100;
        $halfDayAmount = round($dailyRate * $halfRate * $halfDays, 2);
        $components[] = $this->componentRow(
            $this->componentByCode('HALF-DAY'),
            'Half Day Deduction',
            'HALF-DAY',
            'deduction',
            'half_days',
            $dailyRate * $halfRate,
            $halfDays,
            $halfDayAmount,
            false,
            103
        );

        $lateDays = (float) ($statusCounts['late'] ?? 0);
        $lateEquivalentDays = $this->lateEquivalentDays($lateDays);
        $lateAmount = round($dailyRate * $lateEquivalentDays, 2);
        $components[] = $this->componentRow(
            $this->componentByCode('LATE'),
            'Late Deduction',
            'LATE',
            'deduction',
            'late_count',
            $dailyRate,
            $lateEquivalentDays,
            $lateAmount,
            false,
            104
        );

        $gross = round(collect($components)->where('component_type', 'earning')->sum('amount'), 2);
        $deduction = round(collect($components)->where('component_type', 'deduction')->sum('amount'), 2);
        $net = $this->roundNet($gross - $deduction);
        if (!$this->payrollSetting->allow_negative_salary) {
            $net = max(0, $net);
        }

        return [
            'item' => [
                'employee_id' => $employee->id,
                'employee_salary_structure_id' => $structure->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department_name' => $employee->department?->name,
                'designation_name' => $employee->designation?->name,
                'joining_date' => $employee->join_date?->toDateString(),
                'exit_date' => $employee->exit_date?->toDateString(),
                'salary_divisor' => $salaryDivisor,
                'payable_days' => $payableCalendarDays,
                'basic_salary' => $fullBasicSalary,
                'prorated_basic_salary' => $proratedBasic,
                'gross_salary' => $gross,
                'total_deduction' => $deduction,
                'net_salary' => $net,
                'present_days' => (float) ($statusCounts['present'] ?? 0),
                'late_days' => $lateDays,
                'absent_days' => $absentDays,
                'half_days' => $halfDays,
                'paid_leave_days' => $paidLeaveDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'off_days' => (float) ($statusCounts['off_day'] ?? 0),
                'overtime_minutes' => $overtimeMinutes,
                'attendance_summary' => [
                    'present' => (float) ($statusCounts['present'] ?? 0),
                    'late' => $lateDays,
                    'absent' => $absentDays,
                    'half_day' => $halfDays,
                    'paid_leave' => $paidLeaveDays,
                    'unpaid_leave' => $unpaidLeaveDays,
                    'off_day' => (float) ($statusCounts['off_day'] ?? 0),
                    'not_marked' => $this->missingAttendanceCount($employee, $employmentStart, $employmentEnd),
                    'overtime_minutes' => $overtimeMinutes,
                ],
                'payment_method' => $structure->payment_method,
                'account_name' => $structure->account_name,
                'account_number' => $structure->account_number,
                'mobile_banking_provider' => $structure->mobile_banking_provider,
                'payment_status' => 'unpaid',
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
            ],
            'components' => $components,
        ];
    }

    public function settings(): PayrollSetting
    {
        return $this->payrollSetting;
    }

    private function salaryStructureFor(Employee $employee, Carbon $date): ?EmployeeSalaryStructure
    {
        return EmployeeSalaryStructure::with('components.salaryComponent')
            ->where('employee_id', $employee->id)
            ->where('status', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first();
    }

    private function expectedWorkingDates(Employee $employee, Carbon $start, Carbon $end): Collection
    {
        $weeklyOff = collect($this->attendanceSetting->weekly_off_days ?? [])
            ->map(fn ($day) => strtolower((string) $day));
        $holidays = Holiday::where('status', true)
            ->whereBetween('holiday_date', [$start, $end])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();
        $rosterOff = ShiftRoster::where('employee_id', $employee->id)
            ->whereBetween('roster_date', [$start, $end])
            ->where('status', 'off')
            ->pluck('roster_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        return collect(CarbonPeriod::create($start, $end))
            ->filter(function (Carbon $date) use ($weeklyOff, $holidays, $rosterOff) {
                return !$weeklyOff->contains(strtolower($date->format('l')))
                    && !$holidays->has($date->toDateString())
                    && !$rosterOff->has($date->toDateString());
            })
            ->values();
    }

    private function missingAttendanceCount(Employee $employee, Carbon $start, Carbon $end): int
    {
        $checkEnd = $end->isFuture() ? now()->startOfDay() : $end->copy();
        if ($checkEnd->lt($start)) {
            return 0;
        }

        $expected = $this->expectedWorkingDates($employee, $start, $checkEnd);
        $marked = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$start, $checkEnd])
            ->pluck('attendance_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();
        $approvedLeaveDates = $this->approvedLeaveDates($employee, $start, $checkEnd);

        return $expected->filter(function (Carbon $date) use ($marked, $approvedLeaveDates) {
            return !$marked->has($date->toDateString()) && !$approvedLeaveDates->has($date->toDateString());
        })->count();
    }

    private function approvedLeaveDates(Employee $employee, Carbon $start, Carbon $end, ?bool $paid = null): Collection
    {
        $query = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $end)
            ->whereDate('to_date', '>=', $start);

        if ($paid !== null) {
            $query->where('is_paid', $paid);
        }

        $dates = collect();
        foreach ($query->get() as $leave) {
            $leaveFrom = Carbon::parse($leave->from_date)->startOfDay();
            $leaveTo = Carbon::parse($leave->to_date)->startOfDay();
            $from = $leaveFrom->gt($start) ? $leaveFrom : $start->copy();
            $to = $leaveTo->lt($end) ? $leaveTo : $end->copy();
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $dates->put($date->toDateString(), true);
            }
        }

        return $dates;
    }

    private function unpaidLeaveDays(Employee $employee, Carbon $start, Carbon $end): float
    {
        $unpaidDates = $this->approvedLeaveDates($employee, $start, $end, false);
        $expected = $this->expectedWorkingDates($employee, $start, $end)
            ->map(fn (Carbon $date) => $date->toDateString())
            ->flip();

        return (float) $unpaidDates->keys()->filter(fn ($date) => $expected->has($date))->count();
    }

    private function estimatedProratedGross(EmployeeSalaryStructure $structure, float $factor, float $proratedBasic): float
    {
        $gross = $proratedBasic;
        foreach ($structure->components as $component) {
            if (!$component->is_active || $component->component_type !== 'earning') {
                continue;
            }
            $code = strtoupper((string) ($component->salaryComponent?->code ?? ''));
            if (in_array($code, ['OT', 'BASIC'], true)) {
                continue;
            }
            if ($component->calculation_type === 'fixed') {
                $gross += (float) $component->amount * $factor;
            } elseif ($component->calculation_type === 'percentage') {
                $gross += $proratedBasic * (float) $component->percentage / 100;
            }
        }

        return round($gross, 2);
    }

    private function resolveOvertimeRate(EmployeeSalaryStructure $structure, float $basicSalary, float $salaryDivisor): float
    {
        $employeeRate = (float) $structure->overtime_rate;

        // Fixed-rate mode always uses the employee-specific hourly OT rate.
        // A missing rate intentionally produces zero so it can be reviewed in Draft.
        if ($this->payrollSetting->overtime_calculation_method === 'fixed_rate') {
            return max(0, $employeeRate);
        }

        if ($this->payrollSetting->overtime_basis === 'employee_rate' && $employeeRate > 0) {
            return $employeeRate;
        }

        $hours = max(1, (float) $this->attendanceSetting->default_working_hours);
        $hourly = ($basicSalary / max(1, $salaryDivisor)) / $hours;

        return round($hourly * (float) $this->payrollSetting->overtime_rate_multiplier, 4);
    }

    private function lateEquivalentDays(float $lateCount): float
    {
        $threshold = max(1, (int) $this->payrollSetting->late_count_threshold);
        $groups = floor($lateCount / $threshold);

        return match ($this->payrollSetting->late_deduction_method) {
            'half_day_after_count' => $groups * 0.5,
            'full_day_after_count' => $groups,
            default => 0,
        };
    }

    private function roundNet(float $amount): float
    {
        return match ($this->payrollSetting->rounding_method) {
            'nearest' => round($amount),
            'floor' => floor($amount),
            'ceil' => ceil($amount),
            default => round($amount, 2),
        };
    }

    private function componentByCode(string $code): ?SalaryComponent
    {
        return $this->salaryComponents->get(strtoupper($code));
    }

    private function componentRow(
        ?SalaryComponent $master,
        string $name,
        string $code,
        string $type,
        string $calculationType,
        float $rate,
        float $quantity,
        float $amount,
        bool $manual,
        int $sortOrder
    ): array {
        return [
            'salary_component_id' => $master?->id,
            'component_name' => $master?->name ?: $name,
            'component_code' => $master?->code ?: $code,
            'component_type' => $type,
            'calculation_type' => $calculationType,
            'rate' => round($rate, 4),
            'quantity' => round($quantity, 4),
            'calculated_amount' => round($amount, 2),
            'amount' => round($amount, 2),
            'is_manual' => $manual,
            'is_overridden' => false,
            'override_reason' => null,
            'sort_order' => $sortOrder,
        ];
    }
}
