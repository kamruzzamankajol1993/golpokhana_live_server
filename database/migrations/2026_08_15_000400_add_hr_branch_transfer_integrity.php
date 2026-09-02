<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_branch_transfers')) {
            Schema::create('employee_branch_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
                $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
                $table->date('effective_date');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('employee_code_snapshot', 80)->nullable();
                $table->string('employee_name_snapshot', 180)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'effective_date'], 'employee_transfer_employee_date_idx');
                $table->index(['from_branch_id', 'effective_date'], 'employee_transfer_from_date_idx');
                $table->index(['to_branch_id', 'effective_date'], 'employee_transfer_to_date_idx');
            });
        }

        // Phase 1 intentionally used nullable expansion columns. Phase 8 owns HR operational
        // conversion, so backfill any legacy HR nulls again before branch-aware controllers run.
        $mainBranchId = Schema::hasTable('branches')
            ? (DB::table('branches')->where('is_main', true)->value('id') ?: DB::table('branches')->min('id'))
            : null;

        if ($mainBranchId) {
            foreach ([
                'employees', 'shift_rosters', 'attendances', 'leave_requests', 'employee_leave_balances',
                'employee_salary_structures', 'employee_salary_components', 'payroll_runs', 'payroll_items',
                'payroll_item_components', 'payroll_payments', 'departments', 'designations', 'employment_types',
                'leave_types', 'salary_components', 'holidays', 'shifts', 'hr_settings', 'attendance_settings',
                'payroll_settings',
            ] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                    DB::table($table)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_branch_transfers');
    }
};
