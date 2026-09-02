<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $guard = 'web';
        $roleId = DB::table('roles')
            ->where('name', 'Super Admin Limited')
            ->where('guard_name', $guard)
            ->value('id');

        if (!$roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'Super Admin Limited',
                'guard_name' => $guard,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasTable('role_has_permissions') || !Schema::hasTable('permissions')) {
            return;
        }

        $fullRoleId = DB::table('roles')
            ->where('name', 'Super Admin')
            ->where('guard_name', $guard)
            ->value('id');

        $permissionIds = collect();
        if ($fullRoleId) {
            $permissionIds = DB::table('role_has_permissions')
                ->where('role_id', $fullRoleId)
                ->pluck('permission_id');
        }

        // Fresh installations or older databases can have a Super Admin role before
        // its permission pivot is populated. The limited Super Admin still needs the
        // same ordinary application permissions; the two restricted capabilities are
        // enforced by explicit server-side role checks, not by omitting permissions.
        if ($permissionIds->isEmpty()) {
            $permissionIds = DB::table('permissions')
                ->where('guard_name', $guard)
                ->pluck('id');
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => (int) $permissionId,
                'role_id' => (int) $roleId,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'Super Admin Limited')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$roleId) {
            return;
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        }
        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
        }
        DB::table('roles')->where('id', $roleId)->delete();
    }
};
