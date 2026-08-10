<?php

namespace Database\Seeders;

use App\Models\AttendanceSetting;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmploymentType;
use App\Models\HrSetting;
use App\Models\LeaveType;
use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class HrSettingsSeeder extends Seeder
{
    public function run(): void
    {
        HrSetting::firstOrCreate([], [
            'employee_code_prefix' => 'EMP',
            'employee_code_next_number' => 1,
            'employee_code_padding' => 4,
            'default_probation_months' => 3,
            'default_notice_period_days' => 30,
            'allow_employee_login' => true,
            'allow_waiter_access' => true,
            'date_format' => 'd-m-Y',
            'time_format' => 'h:i A',
            'timezone' => 'Asia/Dhaka',
            'status' => true,
        ]);

        AttendanceSetting::firstOrCreate([], [
            'grace_minutes' => 10,
            'half_day_after_minutes' => 240,
            'absent_after_minutes' => 480,
            'minimum_overtime_minutes' => 30,
            'default_working_hours' => 8,
            'weekly_off_days' => ['Friday'],
            'allow_manual_attendance' => true,
            'auto_calculate_late' => true,
            'auto_calculate_overtime' => true,
            'status' => true,
        ]);

        PayrollSetting::firstOrCreate([], [
            'salary_cycle_start_day' => 1,
            'salary_cycle_end_day' => null,
            'working_days_method' => 'calendar_days',
            'default_working_days' => 30,
            'absent_deduction_method' => 'per_day',
            'overtime_calculation_method' => 'hourly_rate',
            'overtime_rate_multiplier' => 1.5,
            'rounding_method' => 'nearest',
            'allow_negative_salary' => false,
            'lock_paid_payroll' => true,
            'currency' => 'BDT',
            'status' => true,
        ]);

        $departments = [
            ['name' => 'Kitchen', 'code' => 'KIT', 'sort_order' => 1],
            ['name' => 'Service', 'code' => 'SER', 'sort_order' => 2],
            ['name' => 'Cash & Accounts', 'code' => 'ACC', 'sort_order' => 3],
            ['name' => 'Management', 'code' => 'MGT', 'sort_order' => 4],
            ['name' => 'Cleaning', 'code' => 'CLN', 'sort_order' => 5],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['name' => $department['name']],
                array_merge($department, ['is_system' => false, 'status' => true])
            );
        }

        $designationRows = [
            ['department' => 'Kitchen', 'name' => 'Head Chef', 'code' => 'HEAD-CHEF', 'sort_order' => 1],
            ['department' => 'Kitchen', 'name' => 'Chef', 'code' => 'CHEF', 'sort_order' => 2],
            ['department' => 'Kitchen', 'name' => 'Kitchen Assistant', 'code' => 'KIT-AST', 'sort_order' => 3],
            ['department' => 'Service', 'name' => 'Waiter', 'code' => 'WAITER', 'sort_order' => 1],
            ['department' => 'Service', 'name' => 'Captain', 'code' => 'CAPTAIN', 'sort_order' => 2],
            ['department' => 'Cash & Accounts', 'name' => 'Cashier', 'code' => 'CASHIER', 'sort_order' => 1],
            ['department' => 'Management', 'name' => 'Manager', 'code' => 'MANAGER', 'sort_order' => 1],
            ['department' => 'Cleaning', 'name' => 'Cleaner', 'code' => 'CLEANER', 'sort_order' => 1],
        ];

        foreach ($designationRows as $row) {
            $departmentId = Department::where('name', $row['department'])->value('id');
            Designation::firstOrCreate(
                ['department_id' => $departmentId, 'name' => $row['name']],
                [
                    'code' => $row['code'],
                    'sort_order' => $row['sort_order'],
                    'is_system' => false,
                    'status' => true,
                ]
            );
        }

        $employmentTypes = [
            ['name' => 'Permanent', 'code' => 'PERM', 'is_hourly' => false, 'sort_order' => 1],
            ['name' => 'Contract', 'code' => 'CONT', 'is_hourly' => false, 'sort_order' => 2],
            ['name' => 'Part-time', 'code' => 'PART', 'is_hourly' => true, 'sort_order' => 3],
            ['name' => 'Daily', 'code' => 'DAILY', 'is_hourly' => true, 'sort_order' => 4],
        ];

        foreach ($employmentTypes as $type) {
            EmploymentType::firstOrCreate(
                ['name' => $type['name']],
                array_merge($type, ['is_system' => false, 'status' => true])
            );
        }

        $leaveTypes = [
            ['name' => 'Casual Leave', 'code' => 'CL', 'days_per_year' => 10, 'is_paid' => true, 'color' => '#21352a', 'sort_order' => 1],
            ['name' => 'Sick Leave', 'code' => 'SL', 'days_per_year' => 10, 'is_paid' => true, 'color' => '#d5aa65', 'sort_order' => 2],
            ['name' => 'Annual Leave', 'code' => 'AL', 'days_per_year' => 15, 'is_paid' => true, 'color' => '#198754', 'sort_order' => 3],
            ['name' => 'Unpaid Leave', 'code' => 'UL', 'days_per_year' => 0, 'is_paid' => false, 'color' => '#dc3545', 'sort_order' => 4],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::firstOrCreate(
                ['name' => $leaveType['name']],
                array_merge($leaveType, [
                    'allow_carry_forward' => false,
                    'max_carry_forward_days' => 0,
                    'requires_document' => false,
                    'is_system' => false,
                    'status' => true,
                ])
            );
        }

        $salaryComponents = [
            ['name' => 'Basic Salary', 'code' => 'BASIC', 'type' => 'earning', 'calculation_type' => 'fixed', 'is_required' => true, 'sort_order' => 1],
            ['name' => 'House Rent', 'code' => 'HOUSE', 'type' => 'earning', 'calculation_type' => 'fixed', 'is_required' => false, 'sort_order' => 2],
            ['name' => 'Food Allowance', 'code' => 'FOOD', 'type' => 'earning', 'calculation_type' => 'fixed', 'is_required' => false, 'sort_order' => 3],
            ['name' => 'Transport Allowance', 'code' => 'TRANS', 'type' => 'earning', 'calculation_type' => 'fixed', 'is_required' => false, 'sort_order' => 4],
            ['name' => 'Overtime', 'code' => 'OT', 'type' => 'earning', 'calculation_type' => 'manual', 'is_required' => false, 'sort_order' => 5],
            ['name' => 'Absent Deduction', 'code' => 'ABSENT', 'type' => 'deduction', 'calculation_type' => 'manual', 'is_required' => false, 'sort_order' => 1],
            ['name' => 'Other Deduction', 'code' => 'OTHER-DED', 'type' => 'deduction', 'calculation_type' => 'manual', 'is_required' => false, 'sort_order' => 2],
        ];

        foreach ($salaryComponents as $component) {
            SalaryComponent::firstOrCreate(
                ['name' => $component['name']],
                array_merge($component, [
                    'percentage_of' => null,
                    'default_amount' => 0,
                    'default_percentage' => 0,
                    'is_taxable' => false,
                    'is_system' => false,
                    'status' => true,
                ])
            );
        }
    }
}
