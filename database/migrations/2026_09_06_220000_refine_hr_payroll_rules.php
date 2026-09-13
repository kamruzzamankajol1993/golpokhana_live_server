<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_components')) {
            return;
        }

        $requiredColumns = ['calculation_type', 'allow_employee_override', 'apply_to_all', 'global_configured'];
        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn('salary_components', $column)) {
                return;
            }
        }

        // Manual-at-payroll components are monthly values. They should not create
        // permanent employee overrides or require a global amount.
        DB::table('salary_components')
            ->where('calculation_type', 'manual')
            ->where(function ($query) {
                $query->whereNull('rule_code')
                    ->orWhereNotIn('rule_code', ['salary_advance', 'loan_adjustment']);
            })
            ->update([
                'allow_employee_override' => false,
                'apply_to_all' => true,
                'global_configured' => true,
                'default_amount' => 0,
                'default_percentage' => 0,
                'updated_at' => now(),
            ]);

        // Salary Advance and Loan Adjustment are automatic recovery rows.
        DB::table('salary_components')
            ->whereIn('rule_code', ['salary_advance', 'loan_adjustment'])
            ->update([
                'allow_employee_override' => false,
                'apply_to_all' => true,
                'global_configured' => true,
                'default_amount' => 0,
                'default_percentage' => 0,
                'updated_at' => now(),
            ]);


        // Remove stale employee-level rows for components that are now monthly
        // payroll inputs. Historical payroll snapshots are stored elsewhere and
        // are not touched by this cleanup.
        if (Schema::hasTable('employee_salary_components') && Schema::hasColumn('employee_salary_components', 'salary_component_id')) {
            $manualIds = DB::table('salary_components')
                ->where('calculation_type', 'manual')
                ->pluck('id');

            if ($manualIds->isNotEmpty()) {
                DB::table('employee_salary_components')
                    ->whereIn('salary_component_id', $manualIds)
                    ->delete();
            }
        }

        // Keep the two overtime master rows in the Allowance section and allow
        // employee-specific rate overrides. The configured amount/percentage is
        // preserved.
        DB::table('salary_components')
            ->whereIn('rule_code', ['ot_day_off', 'ot_gov_off'])
            ->update([
                'component_group' => 'allowance',
                'type' => 'earning',
                'allow_employee_override' => true,
                'apply_to_all' => true,
                'show_zero_on_payslip' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This migration only normalizes configuration flags. Reverting would
        // re-introduce the old ambiguous manual/employee override behavior.
    }
};
