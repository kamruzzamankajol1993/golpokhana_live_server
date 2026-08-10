<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\HrSetting;
use App\Models\Waiter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HrEmployeeDataSeeder extends Seeder
{
    public function run(): void
    {
        $serviceDepartment = Department::firstOrCreate(
            ['name' => 'Service'],
            ['code' => 'SER', 'status' => true, 'sort_order' => 2]
        );
        $waiterDesignation = Designation::firstOrCreate(
            ['department_id' => $serviceDepartment->id, 'name' => 'Waiter'],
            ['code' => 'WAITER', 'status' => true, 'sort_order' => 1]
        );
        $employmentType = EmploymentType::where('status', true)->orderBy('sort_order')->first()
            ?: EmploymentType::create(['name' => 'Permanent', 'code' => 'PERM', 'status' => true]);

        DB::transaction(function () use ($serviceDepartment, $waiterDesignation, $employmentType) {
            foreach (Waiter::with('user')->orderBy('id')->get() as $waiter) {
                $employee = Employee::firstOrCreate(
                    ['employee_code' => $waiter->employee_id],
                    [
                        'user_id' => $waiter->user_id,
                        'department_id' => $serviceDepartment->id,
                        'designation_id' => $waiterDesignation->id,
                        'employment_type_id' => $employmentType->id,
                        'default_shift_id' => $waiter->shift_id,
                        'zone_id' => $waiter->zone_id,
                        'name' => $waiter->name,
                        'phone' => $waiter->phone,
                        'email' => $waiter->email,
                        'join_date' => $waiter->join_date ?: now()->toDateString(),
                        'employment_status' => $waiter->status ? 'active' : 'inactive',
                        'is_waiter' => true,
                        'can_login' => (bool) $waiter->user_id,
                        'image' => $waiter->image,
                        'notes' => $waiter->notes,
                    ]
                );
                $waiter->update(['hr_employee_id' => $employee->id]);
            }

            $setting = HrSetting::first();
            if ($setting) {
                $setting->update([
                    'employee_code_next_number' => max(
                        (int) $setting->employee_code_next_number,
                        Employee::count() + 1
                    ),
                ]);
            }
        });
    }
}
