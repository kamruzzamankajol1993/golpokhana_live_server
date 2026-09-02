<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $permission = 'report-due-view';

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissionId = DB::table('permissions')
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permissionId) {
            $row = [
                'name' => $this->permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('permissions', 'group_name')) {
                $row['group_name'] = 'Reports';
            }
            $permissionId = DB::table('permissions')->insertGetId($row);
        }

        $salesPermissionId = DB::table('permissions')
            ->where('name', 'report-sales-order-view')
            ->where('guard_name', 'web')
            ->value('id');

        if (Schema::hasTable('role_has_permissions')) {
            $roleIds = collect();

            if ($salesPermissionId) {
                $roleIds = DB::table('role_has_permissions')
                    ->where('permission_id', $salesPermissionId)
                    ->pluck('role_id');
            }

            if (Schema::hasTable('roles')) {
                $superAdminId = DB::table('roles')
                    ->where('name', 'Super Admin')
                    ->where('guard_name', 'web')
                    ->value('id');
                if ($superAdminId) {
                    $roleIds->push($superAdminId);
                }
            }

            foreach ($roleIds->unique()->filter() as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        if ($salesPermissionId && Schema::hasTable('model_has_permissions')) {
            $directRows = DB::table('model_has_permissions')
                ->where('permission_id', $salesPermissionId)
                ->get();

            foreach ($directRows as $directRow) {
                $row = (array) $directRow;
                $row['permission_id'] = $permissionId;
                DB::table('model_has_permissions')->insertOrIgnore($row);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', $this->permission)
                ->where('guard_name', 'web')
                ->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
