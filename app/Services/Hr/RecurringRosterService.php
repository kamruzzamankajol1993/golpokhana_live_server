<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeWeeklyRosterPattern;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecurringRosterService
{
    /**
     * Materialize recurring weekly or monthly-cycle rules into dated roster rows.
     * Explicitly saved date-specific roster rows always take precedence.
     */
    public function materialize(string|Carbon $start, string|Carbon $end, ?array $employeeIds = null): void
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        if ($end->lt($start) || (is_array($employeeIds) && count($employeeIds) === 0)) {
            return;
        }

        if (Schema::hasColumn('employees', 'branch_id')) {
            $branchContext = app(BranchContext::class);
            if ($branchContext->isResolved() && $branchContext->branchId() !== null) {
                $employeeQuery = Employee::query()->where('branch_id', $branchContext->branchId());
                if (is_array($employeeIds)) {
                    $employeeQuery->whereIn('id', $employeeIds);
                }
                $employeeIds = $employeeQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (!$employeeIds) {
                    return;
                }
            }
        }

        $hasPatternMode = Schema::hasColumn('employee_weekly_roster_patterns', 'pattern_mode');
        $hasWeekOfMonth = Schema::hasColumn('employee_weekly_roster_patterns', 'week_of_month');

        $patterns = EmployeeWeeklyRosterPattern::query()
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(function ($query) use ($start) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $start->toDateString());
            })
            ->when(is_array($employeeIds), fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->orderBy('effective_from')
            ->get()
            ->groupBy(function ($pattern) use ($hasPatternMode, $hasWeekOfMonth) {
                $mode = $hasPatternMode ? ($pattern->pattern_mode ?: 'weekly') : 'weekly';
                $week = ($hasWeekOfMonth && $mode === 'monthly_cycle') ? (int) $pattern->week_of_month : 0;
                return $pattern->employee_id . '|' . $mode . '|' . $week . '|' . $pattern->day_of_week;
            });

        if ($patterns->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($patterns, $start, $end, $hasPatternMode, $hasWeekOfMonth) {
            foreach ($patterns as $patternGroup) {
                /** @var EmployeeWeeklyRosterPattern $sample */
                $sample = $patternGroup->first();
                $weekday = (int) $sample->day_of_week;
                $mode = $hasPatternMode ? ($sample->pattern_mode ?: 'weekly') : 'weekly';
                $weekOfMonth = ($hasWeekOfMonth && $mode === 'monthly_cycle') ? (int) $sample->week_of_month : 0;

                foreach (CarbonPeriod::create($start, $end) as $date) {
                    if ((int) $date->dayOfWeek !== $weekday) {
                        continue;
                    }

                    if ($mode === 'monthly_cycle' && $weekOfMonth !== $this->monthlyCycleWeekForDate($date)) {
                        continue;
                    }

                    $dateKey = $date->toDateString();
                    $pattern = $patternGroup
                        ->filter(function ($candidate) use ($dateKey) {
                            return $candidate->effective_from->toDateString() <= $dateKey
                                && (!$candidate->effective_until || $candidate->effective_until->toDateString() >= $dateKey);
                        })
                        ->sortByDesc(fn ($candidate) => $candidate->effective_from->toDateString())
                        ->first();

                    if (!$pattern) {
                        continue;
                    }

                    $existing = ShiftRoster::query()
                        ->where('employee_id', $pattern->employee_id)
                        ->whereDate('roster_date', $dateKey)
                        ->first();

                    // A blank weekday in a pattern means no recurring assignment.
                    // Remove only rows created by this service, never manual entries.
                    if ($pattern->status === 'unassigned') {
                        if ($existing && (bool) $existing->is_recurring_generated) {
                            $existing->delete();
                        }
                        continue;
                    }

                    // A manually saved date or approved leave is an exception to the pattern.
                    if ($existing && !(bool) $existing->is_recurring_generated) {
                        continue;
                    }

                    $shiftId = $pattern->status === 'scheduled' ? $pattern->shift_id : null;
                    if ($shiftId && !Shift::whereKey($shiftId)->exists()) {
                        $shiftId = null;
                    }

                    $values = [
                        'shift_id' => $shiftId,
                        'status' => $pattern->status === 'off' ? 'off' : 'scheduled',
                        'assigned_by' => $pattern->assigned_by,
                        'is_recurring_generated' => true,
                    ];
                    if (Schema::hasColumn('shift_rosters', 'branch_id')) {
                        $values['branch_id'] = Employee::query()->whereKey($pattern->employee_id)->value('branch_id');
                    }

                    if ($existing) {
                        $existing->update($values);
                    } else {
                        ShiftRoster::create(array_merge($values, [
                            'employee_id' => $pattern->employee_id,
                            'roster_date' => $dateKey,
                        ]));
                    }
                }
            }
        });
    }

    /** Save a seven-day pattern that repeats every week. */
    public function saveWeekPattern(int $employeeId, Carbon $weekStart, array $assignments, ?int $assignedBy): void
    {
        $weekStart = $weekStart->copy()->startOfDay();
        $startDate = $weekStart->toDateString();
        $this->closeOtherModes($employeeId, 'weekly', $startDate);
        $this->replacePatternSlot($employeeId, 'weekly', null, $weekStart, $assignments, $assignedBy);
    }

    /**
     * Save one slot (Week 1..5) in a monthly cycle. Save each required week once;
     * the slot then repeats in the corresponding week of future months.
     * The slot is determined by the Saturday that starts the visible roster week.
     */
    public function saveMonthlyCyclePattern(int $employeeId, Carbon $weekStart, array $assignments, ?int $assignedBy): int
    {
        $weekStart = $weekStart->copy()->startOfWeek(Carbon::SATURDAY)->startOfDay();
        $weekNumber = $this->monthlyCycleWeekForWeekStart($weekStart);
        $this->closeOtherModes($employeeId, 'monthly_cycle', $weekStart->toDateString());
        $this->replacePatternSlot($employeeId, 'monthly_cycle', $weekNumber, $weekStart, $assignments, $assignedBy);

        return $weekNumber;
    }

    /** The slot number for a Saturday-starting roster week: day 1-7 => 1, 8-14 => 2, etc. */
    public function monthlyCycleWeekForWeekStart(Carbon $weekStart): int
    {
        $weekStart = $weekStart->copy()->startOfWeek(Carbon::SATURDAY);
        return intdiv($weekStart->day - 1, 7) + 1;
    }

    /**
     * Map any date to a monthly cycle slot. Weeks start on Saturday. Dates before
     * the first Saturday of a month belong to Week 1, so every day in that first
     * partial week can use the Week 1 rule when that rule is already effective.
     */
    private function monthlyCycleWeekForDate(Carbon $date): int
    {
        $date = $date->copy()->startOfDay();
        $firstDay = $date->copy()->startOfMonth();
        $firstSaturday = $firstDay->copy()->startOfWeek(Carbon::SATURDAY);
        if ($firstSaturday->lt($firstDay)) {
            $firstSaturday->addWeek();
        }

        if ($date->lt($firstSaturday)) {
            return 1;
        }

        return intdiv($firstSaturday->diffInDays($date), 7) + 1;
    }

    /** Stop other recurrence modes at the selected week, preserving past history. */
    private function closeOtherModes(int $employeeId, string $mode, string $startDate): void
    {
        $previousDay = Carbon::parse($startDate)->subDay()->toDateString();

        EmployeeWeeklyRosterPattern::query()
            ->where('employee_id', $employeeId)
            ->where('pattern_mode', '!=', $mode)
            ->whereDate('effective_from', '<', $startDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $startDate);
            })
            ->update(['effective_until' => $previousDay, 'updated_at' => now()]);

        EmployeeWeeklyRosterPattern::query()
            ->where('employee_id', $employeeId)
            ->where('pattern_mode', '!=', $mode)
            ->whereDate('effective_from', '>=', $startDate)
            ->delete();
    }

    /** Replace the weekly pattern or a single monthly-cycle week slot. */
    private function replacePatternSlot(
        int $employeeId,
        string $mode,
        ?int $weekOfMonth,
        Carbon $weekStart,
        array $assignments,
        ?int $assignedBy
    ): void {
        $startDate = $weekStart->toDateString();
        $previousDay = $weekStart->copy()->subDay()->toDateString();

        $query = EmployeeWeeklyRosterPattern::query()
            ->where('employee_id', $employeeId)
            ->where('pattern_mode', $mode);

        if ($mode === 'monthly_cycle') {
            $query->where('week_of_month', $weekOfMonth);
        } else {
            $query->whereNull('week_of_month');
        }

        (clone $query)
            ->whereDate('effective_from', '<', $startDate)
            ->where(function ($builder) use ($startDate) {
                $builder->whereNull('effective_until')->orWhereDate('effective_until', '>=', $startDate);
            })
            ->update(['effective_until' => $previousDay, 'updated_at' => now()]);

        (clone $query)->whereDate('effective_from', '>=', $startDate)->delete();

        foreach ($assignments as $assignment) {
            $date = Carbon::parse($assignment['date'])->startOfDay();
            $value = (string) ($assignment['value'] ?? '');
            $status = $value === '' ? 'unassigned' : ($value === 'OFF' ? 'off' : 'scheduled');
            $shiftId = $status === 'scheduled' ? (int) $value : null;

            EmployeeWeeklyRosterPattern::create([
                'employee_id' => $employeeId,
                'day_of_week' => (int) $date->dayOfWeek,
                'pattern_mode' => $mode,
                'week_of_month' => $weekOfMonth,
                'shift_id' => $shiftId,
                'status' => $status,
                'effective_from' => $startDate,
                'effective_until' => null,
                'assigned_by' => $assignedBy,
            ]);
        }
    }
}
