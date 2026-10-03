<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $assignPermission = 'inventory-kitchen-request-assign';

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        $permission = DB::table('permissions')
            ->where('name', $this->assignPermission)
            ->where('guard_name', 'web')
            ->first();

        if (!$permission) {
            $row = [
                'name' => $this->assignPermission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('permissions', 'group_name')) {
                $row['group_name'] = 'Inventory';
            }
            $permissionId = (int) DB::table('permissions')->insertGetId($row);
        } else {
            $permissionId = (int) $permission->id;
            if (Schema::hasColumn('permissions', 'group_name') && empty($permission->group_name)) {
                DB::table('permissions')->where('id', $permissionId)->update([
                    'group_name' => 'Inventory',
                    'updated_at' => $now,
                ]);
            }
        }

        // Dedicated Step 2 action: Inventory Manager receives requests and assigns ingredients.
        $inventoryManager = DB::table('roles')
            ->where('name', 'Inventory Manager')
            ->where('guard_name', 'web')
            ->first();
        if ($inventoryManager) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => (int) $inventoryManager->id,
            ]);

            // The old transfer permission is an implementation detail now. The UI/action uses
            // inventory-kitchen-request-assign instead, so remove the legacy role grant.
            $legacyTransferPermission = DB::table('permissions')
                ->where('name', 'inventory-transfer-post')
                ->where('guard_name', 'web')
                ->value('id');
            if ($legacyTransferPermission) {
                DB::table('role_has_permissions')
                    ->where('role_id', (int) $inventoryManager->id)
                    ->where('permission_id', (int) $legacyTransferPermission)
                    ->delete();
            }
        }

        // Super Admin roles retain the new action automatically.
        $superRoleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super Admin', 'Super Admin Limited'])
            ->pluck('id');
        foreach ($superRoleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => (int) $roleId,
            ]);
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', $this->assignPermission)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->where('permission_id', (int) $permissionId)->delete();
            }
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->where('permission_id', (int) $permissionId)->delete();
            }
            DB::table('permissions')->where('id', (int) $permissionId)->delete();
        }

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        try {
            if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }
        } catch (\Throwable $e) {
            // Migration must stay safe in CLI/deploy contexts where the permission registrar is unavailable.
        }
    }
};
