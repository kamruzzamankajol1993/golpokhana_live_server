<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Spatie-এর ক্যাশ ক্লিয়ার করা
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // পারমিশনের তালিকা
        $permissions = [
            // User Permissions
            'user-view',
            'user-create',
            'user-edit',
            'user-update',

            // Role Permissions
            'role-view',
            'role-create',
            'role-edit',
            'role-update',

            // Permission Permissions
            'permission-view',
            'permission-create',
            'permission-edit',
            'permission-update',
        ];

        // ডাটাবেসে পারমিশনগুলো তৈরি করা
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Reports: each report has a separate permission.
        $reportPermissions = [
            'report-sales-order-view',
            'report-complimentary-orders-view',
            'report-payment-type-sales-view',
            'report-food-sales-view',
            'report-waiter-daily-orders-view',
        ];

        foreach ($reportPermissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['group_name' => 'Reports']
            );
        }

        // 'Super Admin' রোল তৈরি করা এবং সব পারমিশন দেওয়া
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $role->givePermissionTo(Permission::all());

        // প্রোফাইল পেজের ডেমো অনুযায়ী নতুন ইউজার তৈরি করা (নতুন কলাম সহ)
        $user = User::firstOrCreate(
            ['email' => 'admin@progga-rms.com'], // এই ইমেইল থাকলে নতুন করে বানাবে না
            [
                'name'       => 'Admin User',
                'first_name' => 'Admin',
                'last_name'  => 'User',
                'phone'      => '01711-123456',
                'image'      => 'https://ui-avatars.com/api/?name=Admin+User&background=21352a&color=d5aa65&size=200', // Default profile image
                'last_login' => Carbon::now(), // বর্তমান সময়
                'password'   => Hash::make('password'), // ডিফল্ট পাসওয়ার্ড: password
            ]
        );

        // ইউজারকে 'Super Admin' রোল অ্যাসাইন করা
        $user->assignRole($role);
    }
}
