<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HrPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'hr-dashboard-view',
            'employee-view', 'employee-create', 'employee-edit', 'employee-delete',
            'employee-salary-view', 'employee-salary-manage',
            'attendance-view', 'attendance-create', 'attendance-edit', 'attendance-delete',
            'leave-management-view', 'leave-management-create', 'leave-management-edit', 'leave-management-approve', 'leave-management-delete',
            'payroll-view', 'payroll-create', 'payroll-edit', 'payroll-approve', 'payroll-pay', 'payroll-delete', 'payroll-payslip',
            'shift-view', 'shift-create', 'shift-edit', 'shift-delete', 'shift-roster-manage',
            'hr-setting-view', 'hr-setting-create', 'hr-setting-edit', 'hr-setting-update', 'hr-setting-delete',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            if (Schema::hasColumn('permissions', 'group_name')) {
                $permission->update(['group_name' => 'Human Resources']);
            }
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::whereIn('name', $permissions)->get());

        $employeeRole = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $waiterRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        $waiterRole->givePermissionTo(Permission::whereIn('name', ['attendance-view'])->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
