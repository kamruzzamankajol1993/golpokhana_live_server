<?php

namespace Database\Seeders;

use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PayrollModuleSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            ['name' => 'Basic Salary', 'code' => 'BASIC', 'type' => 'earning', 'calculation_type' => 'fixed', 'is_required' => true, 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Overtime', 'code' => 'OT', 'type' => 'earning', 'calculation_type' => 'manual', 'is_required' => false, 'is_system' => true, 'sort_order' => 90],
            ['name' => 'Absent Deduction', 'code' => 'ABSENT', 'type' => 'deduction', 'calculation_type' => 'manual', 'is_required' => false, 'is_system' => true, 'sort_order' => 101],
            ['name' => 'Unpaid Leave Deduction', 'code' => 'UNPAID', 'type' => 'deduction', 'calculation_type' => 'manual', 'is_required' => false, 'is_system' => true, 'sort_order' => 102],
            ['name' => 'Half Day Deduction', 'code' => 'HALF-DAY', 'type' => 'deduction', 'calculation_type' => 'manual', 'is_required' => false, 'is_system' => true, 'sort_order' => 103],
            ['name' => 'Late Deduction', 'code' => 'LATE', 'type' => 'deduction', 'calculation_type' => 'manual', 'is_required' => false, 'is_system' => true, 'sort_order' => 104],
        ];

        foreach ($components as $component) {
            $values = array_merge($component, [
                'percentage_of' => null,
                'default_amount' => 0,
                'default_percentage' => 0,
                'is_taxable' => false,
                'description' => 'System payroll component generated from salary, attendance or leave data.',
                'status' => true,
            ]);

            $model = SalaryComponent::where('code', $component['code'])
                ->orWhere('name', $component['name'])
                ->first();

            if ($model) {
                $model->fill($values)->save();
            } else {
                SalaryComponent::create($values);
            }
        }

        PayrollSetting::firstOrCreate([], [
            'salary_cycle_start_day' => 1,
            'salary_cycle_end_day' => null,
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'payroll-view', 'payroll-create', 'payroll-edit', 'payroll-approve',
            'payroll-pay', 'payroll-delete', 'payroll-payslip',
        ];

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if (Schema::hasColumn('permissions', 'group_name')) {
                $permission->update(['group_name' => 'Human Resources']);
            }
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::whereIn('name', $permissions)->get());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
