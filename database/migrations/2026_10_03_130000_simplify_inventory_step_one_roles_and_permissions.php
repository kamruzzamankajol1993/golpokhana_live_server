<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newPermissions = [
        'inventory-kitchen-dashboard-view',
        'inventory-transaction-history-view',
        'inventory-recipes-manage',
    ];

    private array $kitchenManagerPermissions = [
        'inventory-kitchen-dashboard-view',
        'inventory-kitchen-stock-view',
        'inventory-kitchen-request-create',
        'inventory-return-post',
        'inventory-wastage-view',
        'inventory-wastage-create',
        'inventory-wastage-edit',
        'inventory-wastage-delete',
        'inventory-transaction-history-view',
    ];

    private array $inventoryManagerPermissions = [
        'inventory-dashboard-view',
        'inventory-view',
        'inventory-units-manage',
        'inventory-ingredients-manage',
        'inventory-recipes-manage',
        'inventory-vendors-manage',
        'inventory-purchase-create',
        'inventory-purchase-receive',
        'inventory-kitchen-request-review',
        // Kept during Step 1 because the existing issue service uses this permission.
        // Step 2 will expose it as the dedicated "Assign Ingredient to Kitchen" action.
        'inventory-transfer-post',
        'inventory-wastage-view',
        'inventory-wastage-create',
        'inventory-wastage-edit',
        'inventory-wastage-delete',
        'inventory-adjustment-post',
        'inventory-reports-view',
        'inventory-transaction-history-view',
    ];


    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        $this->ensurePermissions($this->newPermissions, 'Inventory', $now);

        $kitchenManagerId = $this->upgradeKitchenRole($now);
        $inventoryManagerId = $this->ensureRole('Inventory Manager', $now);

        $this->syncInventoryPermissions($kitchenManagerId, $this->kitchenManagerPermissions);
        $this->syncInventoryPermissions($inventoryManagerId, $this->inventoryManagerPermissions);
        // Old inventory-role migrations also granted Food Item CRUD. The simplified
        // Inventory Manager uses the dedicated Food Recipes screen instead.
        $this->revokePermissions($inventoryManagerId, [
            'food-item-view',
            'food-item-create',
            'food-item-edit',
            'food-item-update',
            'food-item-delete',
        ]);

        if ($this->permissionExists('kitchen-view')) {
            $this->grantPermissions($kitchenManagerId, ['kitchen-view']);
        }

        // Store Manager is no longer an inventory actor in the simplified single-site workflow.
        $storeManager = DB::table('roles')->where('name', 'Store Manager')->where('guard_name', 'web')->first();
        if ($storeManager) {
            $this->syncInventoryPermissions((int) $storeManager->id, []);
        }

        // Super Admin roles retain every application permission.
        $allPermissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id');
        $superRoleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super Admin', 'Super Admin Limited'])
            ->pluck('id');

        foreach ($superRoleIds as $roleId) {
            foreach ($allPermissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => (int) $permissionId,
                    'role_id' => (int) $roleId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $kitchenManager = DB::table('roles')->where('name', 'Kitchen Manager')->where('guard_name', 'web')->first();
        $legacyKitchen = DB::table('roles')->where('name', 'Kitchen')->where('guard_name', 'web')->first();
        if ($kitchenManager && !$legacyKitchen) {
            DB::table('roles')->where('id', $kitchenManager->id)->update([
                'name' => 'Kitchen',
                'updated_at' => now(),
            ]);
        }

        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->newPermissions)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            }
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        $this->forgetPermissionCache();
    }

    private function upgradeKitchenRole($now): int
    {
        $manager = DB::table('roles')->where('name', 'Kitchen Manager')->where('guard_name', 'web')->first();
        $legacy = DB::table('roles')->where('name', 'Kitchen')->where('guard_name', 'web')->first();

        if ($legacy && !$manager) {
            DB::table('roles')->where('id', $legacy->id)->update([
                'name' => 'Kitchen Manager',
                'updated_at' => $now,
            ]);
            return (int) $legacy->id;
        }

        if ($legacy && $manager && (int) $legacy->id !== (int) $manager->id) {
            if (Schema::hasTable('model_has_roles')) {
                $assignedModels = DB::table('model_has_roles')->where('role_id', $legacy->id)->get();
                foreach ($assignedModels as $assignment) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => (int) $manager->id,
                        'model_type' => $assignment->model_type,
                        'model_id' => (int) $assignment->model_id,
                    ]);
                }
                DB::table('model_has_roles')->where('role_id', $legacy->id)->delete();
            }

            $permissionIds = DB::table('role_has_permissions')->where('role_id', $legacy->id)->pluck('permission_id');
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => (int) $permissionId,
                    'role_id' => (int) $manager->id,
                ]);
            }
            DB::table('role_has_permissions')->where('role_id', $legacy->id)->delete();
            DB::table('roles')->where('id', $legacy->id)->delete();
            return (int) $manager->id;
        }

        if ($manager) {
            return (int) $manager->id;
        }

        return $this->ensureRole('Kitchen Manager', $now);
    }

    private function ensurePermissions(array $names, string $group, $now): void
    {
        $hasGroup = Schema::hasColumn('permissions', 'group_name');
        foreach (array_values(array_unique($names)) as $name) {
            $existing = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            if ($existing) {
                if ($hasGroup && empty($existing->group_name)) {
                    DB::table('permissions')->where('id', $existing->id)->update([
                        'group_name' => $group,
                        'updated_at' => $now,
                    ]);
                }
                continue;
            }

            $row = [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasGroup) {
                $row['group_name'] = $group;
            }
            DB::table('permissions')->insert($row);
        }
    }

    private function ensureRole(string $name, $now): int
    {
        $role = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->first();
        if ($role) {
            return (int) $role->id;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function syncInventoryPermissions(int $roleId, array $allowedNames): void
    {
        $inventoryPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'like', 'inventory-%')
            ->pluck('id');

        if ($inventoryPermissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $inventoryPermissionIds)
                ->delete();
        }

        $this->grantPermissions($roleId, $allowedNames);
    }

    private function grantPermissions(int $roleId, array $names): void
    {
        if ($names === []) {
            return;
        }

        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_values(array_unique($names)))
            ->pluck('id');

        foreach ($ids as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => (int) $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }


    private function revokePermissions(int $roleId, array $names): void
    {
        if ($names === []) {
            return;
        }

        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_values(array_unique($names)))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $ids)
                ->delete();
        }
    }

    private function permissionExists(string $name): bool
    {
        return DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
