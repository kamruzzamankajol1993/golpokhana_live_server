<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Granular wastage permissions added by this migration.
     *
     * inventory-wastage-post is kept in the permission table for backward
     * compatibility, but inventory roles are moved to the granular permissions.
     */
    private array $newPermissions = [
        'inventory-wastage-view',
        'inventory-wastage-create',
        'inventory-wastage-edit',
        'inventory-wastage-delete',
    ];

    private array $requiredInventoryPermissions = [
        'inventory-dashboard-view',
        'inventory-kitchen-stock-view',
        'inventory-view',
        'inventory-units-manage',
        'inventory-ingredients-manage',
        'inventory-vendors-manage',
        'inventory-purchase-create',
        'inventory-purchase-receive',
        'inventory-kitchen-request-create',
        'inventory-kitchen-request-review',
        'inventory-transfer-post',
        'inventory-return-post',
        'inventory-wastage-post',
        'inventory-wastage-view',
        'inventory-wastage-create',
        'inventory-wastage-edit',
        'inventory-wastage-delete',
        'inventory-adjustment-post',
        'inventory-reports-view',
    ];

    /**
     * Inventory Manager:
     * - manages Main Inventory and reviews Kitchen requests
     * - issues Main -> Kitchen stock
     * - can record Main Stock wastage
     * - does NOT create Kitchen requests or return Kitchen stock
     */
    private array $inventoryManagerPermissions = [
        'inventory-dashboard-view',
        'inventory-view',
        'inventory-units-manage',
        'inventory-ingredients-manage',
        'inventory-vendors-manage',
        'inventory-purchase-create',
        'inventory-purchase-receive',
        'inventory-kitchen-request-review',
        'inventory-transfer-post',
        'inventory-wastage-view',
        'inventory-wastage-create',
        'inventory-wastage-edit',
        'inventory-wastage-delete',
        'inventory-adjustment-post',
        'inventory-reports-view',
    ];

    /**
     * Kitchen:
     * - sees its own Kitchen Stock
     * - creates/maintains Kitchen requests
     * - returns unused Kitchen stock to Main
     * - records Kitchen wastage
     * - cannot issue Main Stock or adjust inventory
     */
    private array $kitchenPermissions = [
        'inventory-kitchen-stock-view',
        'inventory-kitchen-request-create',
        'inventory-return-post',
        'inventory-wastage-view',
        'inventory-wastage-create',
        'inventory-wastage-edit',
        'inventory-wastage-delete',
    ];

    private array $storeManagerPermissions = [
        'inventory-kitchen-request-review',
        'inventory-transfer-post',
    ];

    private array $inventoryManagerFoodPermissions = [
        'food-item-view',
        'food-item-create',
        'food-item-edit',
        'food-item-update',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        $this->ensurePermissions($this->requiredInventoryPermissions, 'Inventory', $now);
        $this->ensurePermissions($this->inventoryManagerFoodPermissions, 'Food Item', $now);

        $inventoryManagerId = $this->ensureRole('Inventory Manager', $now);
        $kitchenId = $this->ensureRole('Kitchen', $now);
        $storeManagerId = $this->ensureRole('Store Manager', $now);
        $this->ensureRole('Super Admin', $now);
        $this->ensureRole('Super Admin Limited', $now);

        // Replace only Inventory permissions so unrelated application access is preserved.
        $this->syncInventoryPermissions($inventoryManagerId, $this->inventoryManagerPermissions);
        $this->syncInventoryPermissions($kitchenId, $this->kitchenPermissions);
        $this->syncInventoryPermissions($storeManagerId, $this->storeManagerPermissions);

        // Inventory Manager also owns Food add/edit access required for recipe setup.
        $this->grantPermissions($inventoryManagerId, $this->inventoryManagerFoodPermissions);

        // Keep the Kitchen screen permission without widening any other access.
        if ($this->permissionExists('kitchen-view')) {
            $this->grantPermissions($kitchenId, ['kitchen-view']);
        }

        // Both Super Admin roles keep every ordinary application permission.
        $allPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->pluck('id');

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
        if (!Schema::hasTable('permissions')) {
            return;
        }

        // Only permissions introduced here are removed. Roles and older permissions
        // are left intact so rollback does not unexpectedly destroy application access.
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

    private function ensurePermissions(array $names, string $group, $now): void
    {
        $hasGroup = Schema::hasColumn('permissions', 'group_name');

        foreach (array_values(array_unique($names)) as $name) {
            $existing = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->first();

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

    private function permissionExists(string $name): bool
    {
        return DB::table('permissions')
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->exists();
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
