<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Older Employee Create forms defaulted the first salary Effective From to
     * the day the employee record was created, even when Join Date was earlier.
     * That made previous-month payroll report "salary setup missing" although
     * salary had been configured from the Employee screen.
     *
     * Repair only the narrow, recognisable legacy case:
     * - this is the employee's earliest salary structure;
     * - its Effective From is later than Join Date;
     * - salary structure and employee were created within ten minutes; and
     * - no payroll item has ever used the structure.
     */
    public function up(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasTable('employee_salary_structures')) {
            return;
        }

        $rows = DB::table('employee_salary_structures as ess')
            ->join('employees as e', 'e.id', '=', 'ess.employee_id')
            ->where('ess.status', 1)
            ->whereNotNull('e.join_date')
            ->whereColumn('ess.effective_from', '>', 'e.join_date')
            ->select([
                'ess.id', 'ess.employee_id', 'ess.effective_from',
                'ess.created_at as salary_created_at',
                'e.join_date', 'e.created_at as employee_created_at',
            ])
            ->get();

        foreach ($rows as $row) {
            if (!$row->salary_created_at || !$row->employee_created_at) {
                continue;
            }

            $salaryCreated = Carbon::parse($row->salary_created_at);
            $employeeCreated = Carbon::parse($row->employee_created_at);
            $effectiveFrom = Carbon::parse($row->effective_from);
            if (abs($salaryCreated->diffInSeconds($employeeCreated, false)) > 600
                || $effectiveFrom->toDateString() !== $salaryCreated->toDateString()
                || $effectiveFrom->toDateString() !== $employeeCreated->toDateString()) {
                continue;
            }

            $hasEarlierStructure = DB::table('employee_salary_structures')
                ->where('employee_id', $row->employee_id)
                ->where('id', '!=', $row->id)
                ->whereDate('effective_from', '<', $row->effective_from)
                ->exists();
            if ($hasEarlierStructure) {
                continue;
            }

            if (Schema::hasTable('payroll_items')) {
                $usedInPayroll = DB::table('payroll_items')
                    ->where('employee_salary_structure_id', $row->id)
                    ->exists();
                if ($usedInPayroll) {
                    continue;
                }
            }

            DB::table('employee_salary_structures')
                ->where('id', $row->id)
                ->update([
                    'effective_from' => Carbon::parse($row->join_date)->toDateString(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally not reversed: the old values were created by a UI default bug,
        // and reverting a repaired salary effective date could invalidate payroll again.
    }
};
