<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeSalaryComponentUniqueIndexes();
        $this->seedPayrollComponents();
        $this->seedPermissions();
    }

    public function down(): void
    {
        // Configuration is intentionally retained on rollback so live payroll data and
        // manually adjusted component values are never deleted by a schema rollback.
    }

    /**
     * Older databases can still have the original global unique indexes on name/code.
     * The HR module is branch aware, so those indexes must be converted to branch-wise
     * unique indexes before the default component rows are seeded for each branch.
     */
    private function normalizeSalaryComponentUniqueIndexes(): void
    {
        if (!Schema::hasTable('salary_components') || !Schema::hasColumn('salary_components', 'branch_id')) {
            return;
        }

        $indexes = $this->salaryComponentIndexes();

        foreach ($indexes as $indexName => $index) {
            if (!$index['unique']) {
                continue;
            }

            if ($index['columns'] === ['name'] || $index['columns'] === ['code']) {
                DB::statement('ALTER TABLE `salary_components` DROP INDEX `'.$this->escapeIdentifier($indexName).'`');
            }
        }

        $indexes = $this->salaryComponentIndexes();

        if (!$this->hasUniqueIndexForColumns($indexes, ['branch_id', 'name'])) {
            DB::statement(
                'ALTER TABLE `salary_components` ADD UNIQUE INDEX `salary_components_branch_name_unique` (`branch_id`, `name`)'
            );
        }

        $indexes = $this->salaryComponentIndexes();

        if (!$this->hasUniqueIndexForColumns($indexes, ['branch_id', 'code'])) {
            DB::statement(
                'ALTER TABLE `salary_components` ADD UNIQUE INDEX `salary_components_branch_code_unique` (`branch_id`, `code`)'
            );
        }
    }

    private function salaryComponentIndexes(): array
    {
        $rows = DB::select('SHOW INDEX FROM `salary_components`');
        $indexes = [];

        foreach ($rows as $row) {
            $name = (string) $row->Key_name;

            if ($name === 'PRIMARY') {
                continue;
            }

            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'unique' => ((int) $row->Non_unique) === 0,
                    'columns' => [],
                ];
            }

            $indexes[$name]['columns'][(int) $row->Seq_in_index] = (string) $row->Column_name;
        }

        foreach ($indexes as &$index) {
            ksort($index['columns']);
            $index['columns'] = array_values($index['columns']);
        }
        unset($index);

        return $indexes;
    }

    private function hasUniqueIndexForColumns(array $indexes, array $columns): bool
    {
        foreach ($indexes as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }

    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    private function seedPayrollComponents(): void
    {
        if (!Schema::hasTable('salary_components') || !Schema::hasColumn('salary_components', 'component_group')) {
            return;
        }

        $hasBranch = Schema::hasColumn('salary_components', 'branch_id') && Schema::hasTable('branches');
        $branches = $hasBranch
            ? DB::table('branches')->orderBy('id')->pluck('id')->all()
            : [null];

        if (!$branches) {
            $branches = [null];
        }

        $components = [
            ['name'=>'Basic','payslip_label'=>'Basic','code'=>'BASIC','component_group'=>'salary','type'=>'earning','calculation_type'=>'fixed','rule_code'=>'basic','is_required'=>true,'is_system'=>true,'sort_order'=>10,'allow_employee_override'=>false],
            ['name'=>'House Rent','code'=>'HOUSE_RENT','component_group'=>'salary','type'=>'earning','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>50,'rule_code'=>'standard','sort_order'=>20],
            ['name'=>'Medical','code'=>'MEDICAL','component_group'=>'salary','type'=>'earning','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>10,'rule_code'=>'standard','sort_order'=>30],
            ['name'=>'Conveyance','code'=>'CONVEYANCE','component_group'=>'salary','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>40],

            ['name'=>'OT Amount (Day Off)','code'=>'OT_DAY_OFF','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'ot_day_off','sort_order'=>110],
            ['name'=>'OT Amount (GOV Off)','code'=>'OT_GOV_OFF','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'ot_gov_off','sort_order'=>120],
            ['name'=>'Breakfast','code'=>'BREAKFAST','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>130],
            ['name'=>'Other','code'=>'OTHER_ALLOWANCE','component_group'=>'allowance','type'=>'earning','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>140,'allow_employee_override'=>false],
            ['name'=>'Arrear','code'=>'ARREAR','component_group'=>'allowance','type'=>'earning','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>150,'allow_employee_override'=>false],
            ['name'=>'Adjustment (Last Month) Addition','code'=>'LAST_MONTH_ADDITION','component_group'=>'allowance','type'=>'earning','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>160,'allow_employee_override'=>false],
            ['name'=>'Lunch','code'=>'LUNCH','component_group'=>'allowance','type'=>'earning','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>170],

            ['name'=>'Late Deduction Amount','code'=>'LATE','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>100,'rule_code'=>'late','sort_order'=>210],
            ['name'=>'LWP + Absent Amount','code'=>'LWP_ABSENT','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'percentage','percentage_of'=>'basic_salary','default_percentage'=>100,'rule_code'=>'lwp_absent','sort_order'=>220],
            ['name'=>'Fine','code'=>'FINE','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>230,'allow_employee_override'=>false],
            ['name'=>'Salary Advance','code'=>'SALARY_ADVANCE','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'salary_advance','sort_order'=>240,'allow_employee_override'=>false],
            ['name'=>'Loan Adjustment','code'=>'LOAN_ADJUSTMENT','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'loan_adjustment','sort_order'=>250,'allow_employee_override'=>false],
            ['name'=>'Sales Adjustment','code'=>'SALES_ADJUSTMENT','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>260,'allow_employee_override'=>false],
            ['name'=>'TDS','code'=>'TDS','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>270],
            ['name'=>'GYM','code'=>'GYM','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>280],
            ['name'=>'Other Deduction','payslip_label'=>'Other','code'=>'OTHER_DEDUCTION','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>290,'allow_employee_override'=>false],
            ['name'=>'Bed Facility','code'=>'BED_FACILITY','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'fixed','default_amount'=>0,'rule_code'=>'standard','sort_order'=>300],
            ['name'=>'Adjustment (Last Month) Deduction','code'=>'LAST_MONTH_DEDUCTION','component_group'=>'deduction','type'=>'deduction','calculation_type'=>'manual','default_amount'=>0,'rule_code'=>'standard','sort_order'=>310,'allow_employee_override'=>false],
        ];

        foreach ($branches as $branchId) {
            foreach ($components as $component) {
                $values = array_merge([
                    'payslip_label'=>null,
                    'percentage_of'=>null,
                    'default_amount'=>0,
                    'default_percentage'=>0,
                    'global_configured'=>true,
                    'apply_to_all'=>true,
                    'allow_employee_override'=>true,
                    'show_zero_on_payslip'=>true,
                    'is_taxable'=>false,
                    'is_required'=>false,
                    'is_system'=>false,
                    'description'=>'Configurable payroll component. Employee override takes priority over this global rule.',
                    'status'=>true,
                    'updated_at'=>now(),
                ], $component);

                if ($hasBranch) {
                    $values['branch_id'] = $branchId;
                }

                $existing = $this->findExistingComponent($component, $branchId, $hasBranch);

                if ($existing) {
                    DB::table('salary_components')->where('id', $existing->id)->update($values);
                } else {
                    $values['created_at'] = now();
                    DB::table('salary_components')->insert($values);
                }
            }

            $legacy = DB::table('salary_components')->whereIn('code', ['OT', 'ABSENT', 'UNPAID', 'HALF-DAY']);
            if ($hasBranch) {
                $legacy->where('branch_id', $branchId);
            }
            $legacy->update(['status'=>false, 'updated_at'=>now()]);
        }
    }

    /**
     * Re-use partially seeded or legacy rows by code first, then by name. This is
     * important for old databases where e.g. "House Rent" already exists with a
     * different/null code. It also makes a failed migration safe to re-run.
     */
    private function findExistingComponent(array $component, $branchId, bool $hasBranch): ?object
    {
        $base = DB::table('salary_components');
        if ($hasBranch) {
            $base->where('branch_id', $branchId);
        }

        $byCode = (clone $base)->where('code', $component['code'])->first();
        if ($byCode) {
            return $byCode;
        }

        $byName = (clone $base)->where('name', $component['name'])->first();
        if ($byName) {
            return $byName;
        }

        // If the table was converted to branch mode after old global data existed,
        // claim a matching NULL-branch legacy row for the first branch instead of
        // inserting a duplicate record.
        if ($hasBranch) {
            $legacy = DB::table('salary_components')
                ->whereNull('branch_id')
                ->where(function ($query) use ($component) {
                    $query->where('code', $component['code'])
                        ->orWhere('name', $component['name']);
                })
                ->first();

            if ($legacy) {
                return $legacy;
            }
        }

        return null;
    }

    private function seedPermissions(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $names = [
            'salary-advance-view', 'salary-advance-create', 'salary-advance-edit', 'salary-advance-delete',
            'loan-view', 'loan-create', 'loan-edit', 'loan-delete',
        ];

        foreach ($names as $name) {
            $existing = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            if (!$existing) {
                $row = ['name'=>$name, 'guard_name'=>'web'];
                if (Schema::hasColumn('permissions', 'group_name')) $row['group_name'] = 'Human Resources';
                if (Schema::hasColumn('permissions', 'created_at')) $row['created_at'] = now();
                if (Schema::hasColumn('permissions', 'updated_at')) $row['updated_at'] = now();
                DB::table('permissions')->insert($row);
            } elseif (Schema::hasColumn('permissions', 'group_name')) {
                DB::table('permissions')->where('id', $existing->id)->update(['group_name'=>'Human Resources']);
            }
        }

        $permissionIds = DB::table('permissions')->whereIn('name', $names)->where('guard_name', 'web')->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['Super Admin', 'Super Admin Limited'])->where('guard_name', 'web')->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id'=>$permissionId,
                    'role_id'=>$roleId,
                ]);
            }
        }
    }
};
