<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
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

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $hasGroup = Schema::hasColumn('permissions', 'group_name');
        foreach ($this->permissions as $name) {
            $existing = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            if ($existing) {
                if ($hasGroup && empty($existing->group_name)) {
                    DB::table('permissions')->where('id', $existing->id)->update(['group_name' => 'Inventory', 'updated_at' => now()]);
                }
                continue;
            }

            $row = [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasGroup) {
                $row['group_name'] = 'Inventory';
            }
            DB::table('permissions')->insert($row);
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super Admin', 'Super Admin Limited'])
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => (int) $permissionId,
                    'role_id' => (int) $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        }
        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        }
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
