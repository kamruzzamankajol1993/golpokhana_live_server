<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * This migration is intentionally restart-safe.
         * MySQL DDL is not fully transactional, so an earlier failed run may have
         * already added columns and/or left salary_advances behind. The cleanup
         * below only targets the new tables introduced by this migration.
         */
        $this->dropNewPayrollRecoveryTables();

        $this->addSalaryComponentColumns();
        $this->addEmployeeSalaryComponentColumns();
        $this->addPayrollItemComponentColumns();
        $this->addPayrollItemColumns();

        if (Schema::hasColumn('salary_components', 'component_group')) {
            DB::table('salary_components')->whereNull('component_group')->update([
                'component_group' => DB::raw("CASE WHEN type = 'deduction' THEN 'deduction' ELSE 'salary' END"),
            ]);
        }

        if (Schema::hasColumn('payroll_item_components', 'component_group')) {
            DB::table('payroll_item_components')->whereNull('component_group')->update([
                'component_group' => DB::raw("CASE WHEN component_type = 'deduction' THEN 'deduction' ELSE 'salary' END"),
            ]);
        }

        if (Schema::hasColumn('payroll_items', 'salary_total') && Schema::hasColumn('payroll_items', 'allowance_total')) {
            DB::table('payroll_items')->update([
                'salary_total' => DB::raw('gross_salary'),
                'allowance_total' => 0,
            ]);
        }

        /*
         * branch_id is deliberately kept as an indexed nullable unsigned BIGINT
         * without a DB foreign key. Some existing/live installations have a
         * legacy branches.id definition that differs from Laravel foreignId(),
         * which causes MySQL errno 150. Branch integrity is already enforced by
         * the application's branch scope/guards, while this remains compatible
         * with both legacy and current branch tables.
         */
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('advance_date');
            $table->decimal('amount', 14, 2);
            $table->date('recovery_start_month');
            $table->decimal('installment_amount', 14, 2)->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('salary_advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('salary_advance_id')->constrained('salary_advances')->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained('payroll_items')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->enum('source', ['manual', 'payroll'])->default('manual');
            $table->string('reference_number', 180)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['salary_advance_id', 'payment_date']);
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('loan_date');
            $table->decimal('principal_amount', 14, 2);
            $table->enum('interest_type', ['none', 'flat_percent', 'fixed_amount'])->default('none');
            $table->decimal('interest_value', 14, 4)->default(0);
            $table->decimal('total_interest', 14, 2)->default(0);
            $table->decimal('total_payable', 14, 2);
            $table->date('repayment_start_month');
            $table->decimal('installment_amount', 14, 2)->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained('payroll_items')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->enum('source', ['manual', 'payroll'])->default('manual');
            $table->string('reference_number', 180)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_loan_id', 'payment_date']);
        });

        Schema::create('payroll_recovery_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->enum('source_type', ['salary_advance', 'loan']);
            $table->unsignedBigInteger('source_id');
            $table->decimal('amount', 14, 2);
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('repayment_id')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['payroll_item_id', 'posted_at']);
        });
    }

    public function down(): void
    {
        $this->dropNewPayrollRecoveryTables();

        if (Schema::hasTable('payroll_items')) {
            $columns = array_values(array_filter(['salary_total', 'allowance_total'], fn ($column) => Schema::hasColumn('payroll_items', $column)));
            if ($columns) {
                Schema::table('payroll_items', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('payroll_item_components')) {
            if ($this->indexExists('payroll_item_components', 'payroll_item_group_index')) {
                Schema::table('payroll_item_components', function (Blueprint $table) {
                    $table->dropIndex('payroll_item_group_index');
                });
            }
            $columns = array_values(array_filter(['component_group', 'source'], fn ($column) => Schema::hasColumn('payroll_item_components', $column)));
            if ($columns) {
                Schema::table('payroll_item_components', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('employee_salary_components') && Schema::hasColumn('employee_salary_components', 'rule_mode')) {
            Schema::table('employee_salary_components', function (Blueprint $table) {
                $table->dropColumn('rule_mode');
            });
        }

        if (Schema::hasTable('salary_components')) {
            if ($this->indexExists('salary_components', 'salary_components_group_status_index')) {
                Schema::table('salary_components', function (Blueprint $table) {
                    $table->dropIndex('salary_components_group_status_index');
                });
            }
            $wanted = [
                'component_group', 'payslip_label', 'rule_code', 'global_configured',
                'apply_to_all', 'allow_employee_override', 'show_zero_on_payslip',
            ];
            $columns = array_values(array_filter($wanted, fn ($column) => Schema::hasColumn('salary_components', $column)));
            if ($columns) {
                Schema::table('salary_components', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }

    private function addSalaryComponentColumns(): void
    {
        if (!Schema::hasTable('salary_components')) {
            return;
        }

        $missing = [
            'component_group' => !Schema::hasColumn('salary_components', 'component_group'),
            'payslip_label' => !Schema::hasColumn('salary_components', 'payslip_label'),
            'rule_code' => !Schema::hasColumn('salary_components', 'rule_code'),
            'global_configured' => !Schema::hasColumn('salary_components', 'global_configured'),
            'apply_to_all' => !Schema::hasColumn('salary_components', 'apply_to_all'),
            'allow_employee_override' => !Schema::hasColumn('salary_components', 'allow_employee_override'),
            'show_zero_on_payslip' => !Schema::hasColumn('salary_components', 'show_zero_on_payslip'),
        ];

        if (in_array(true, $missing, true)) {
            Schema::table('salary_components', function (Blueprint $table) use ($missing) {
                if ($missing['component_group']) $table->string('component_group', 20)->nullable();
                if ($missing['payslip_label']) $table->string('payslip_label', 120)->nullable();
                if ($missing['rule_code']) $table->string('rule_code', 60)->nullable();
                if ($missing['global_configured']) $table->boolean('global_configured')->default(true);
                if ($missing['apply_to_all']) $table->boolean('apply_to_all')->default(true);
                if ($missing['allow_employee_override']) $table->boolean('allow_employee_override')->default(true);
                if ($missing['show_zero_on_payslip']) $table->boolean('show_zero_on_payslip')->default(true);
            });
        }

        if (!$this->indexExists('salary_components', 'salary_components_group_status_index')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->index(['component_group', 'status'], 'salary_components_group_status_index');
            });
        }
    }

    private function addEmployeeSalaryComponentColumns(): void
    {
        if (Schema::hasTable('employee_salary_components') && !Schema::hasColumn('employee_salary_components', 'rule_mode')) {
            Schema::table('employee_salary_components', function (Blueprint $table) {
                $table->string('rule_mode', 20)->default('custom');
            });
        }
    }

    private function addPayrollItemComponentColumns(): void
    {
        if (!Schema::hasTable('payroll_item_components')) {
            return;
        }

        $groupMissing = !Schema::hasColumn('payroll_item_components', 'component_group');
        $sourceMissing = !Schema::hasColumn('payroll_item_components', 'source');
        if ($groupMissing || $sourceMissing) {
            Schema::table('payroll_item_components', function (Blueprint $table) use ($groupMissing, $sourceMissing) {
                if ($groupMissing) $table->string('component_group', 20)->nullable();
                if ($sourceMissing) $table->string('source', 30)->nullable();
            });
        }

        if (!$this->indexExists('payroll_item_components', 'payroll_item_group_index')) {
            Schema::table('payroll_item_components', function (Blueprint $table) {
                $table->index(['payroll_item_id', 'component_group'], 'payroll_item_group_index');
            });
        }
    }

    private function addPayrollItemColumns(): void
    {
        if (!Schema::hasTable('payroll_items')) {
            return;
        }

        $salaryMissing = !Schema::hasColumn('payroll_items', 'salary_total');
        $allowanceMissing = !Schema::hasColumn('payroll_items', 'allowance_total');
        if ($salaryMissing || $allowanceMissing) {
            Schema::table('payroll_items', function (Blueprint $table) use ($salaryMissing, $allowanceMissing) {
                if ($salaryMissing) $table->decimal('salary_total', 14, 2)->default(0);
                if ($allowanceMissing) $table->decimal('allowance_total', 14, 2)->default(0);
            });
        }
    }

    private function dropNewPayrollRecoveryTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('payroll_recovery_allocations');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('salary_advance_repayments');
        Schema::dropIfExists('salary_advances');
        Schema::enableForeignKeyConstraints();
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $database = DB::getDatabaseName();
        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
