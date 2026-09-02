<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Permissions introduced specifically by this access-structure update. */
    private array $newPermissions = [
        'inventory-dashboard-view',
        'inventory-kitchen-stock-view',
    ];

    /**
     * Required inventory permissions for this project. Existing permissions are
     * preserved; a missing permission is created so a partially seeded database
     * can still receive the role structure safely.
     */
    private array $requiredPermissions = [
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
        'inventory-adjustment-post',
        'inventory-reports-view',
        'inventory-branch-all-view',
    ];

    private array $kitchenPermissions = [
        'inventory-kitchen-stock-view',
        'inventory-kitchen-request-create',
        'inventory-return-post',
        'inventory-wastage-post',
    ];

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
        'inventory-return-post',
        'inventory-wastage-post',
        'inventory-adjustment-post',
        'inventory-reports-view',
    ];

    private array $storeManagerPermissions = [
        'inventory-kitchen-request-review',
        'inventory-transfer-post',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        $this->ensurePermissions($this->requiredPermissions, $now);

        $this->ensureRole('Super Admin', $now);
        $this->ensureRole('Super Admin Limited', $now);
        $inventoryManagerId = $this->ensureRole('Inventory Manager', $now);
        $kitchenId = $this->ensureRole('Kitchen', $now);
        $storeManagerId = $this->ensureRole('Store Manager', $now);

        // Kitchen gets only its operational inventory surface. Existing non-inventory
        // permissions (for example kitchen-view) are intentionally left untouched.
        $this->syncInventoryPermissions($kitchenId, $this->kitchenPermissions);

        // Inventory Manager owns all branch-local inventory management/review work.
        // Cross-branch permission is deliberately omitted so BranchContext keeps the
        // manager locked to the assigned branch.
        $this->syncInventoryPermissions($inventoryManagerId, $this->inventoryManagerPermissions);

        // Store Manager may review and issue Kitchen Requests without becoming a
        // full inventory administrator.
        $this->syncInventoryPermissions($storeManagerId, $this->storeManagerPermissions);

        // Both Super Admin roles have every ordinary application permission.
        // Super Admin Limited remains blocked from Branch Management/Mode and
        // Offline POS Devices by the existing explicit full-Super-Admin checks.
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

        // Remove only the two permissions introduced by this migration. Existing
        // inventory permissions/roles are never destroyed by a rollback.
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

    private function ensurePermissions(array $names, $now): void
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
                        'group_name' => 'Inventory',
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
                $row['group_name'] = 'Inventory';
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

        $allowedIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $allowedNames)
            ->pluck('id');

        foreach ($allowedIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => (int) $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
