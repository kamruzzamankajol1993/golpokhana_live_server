<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $reportPermissions = [
        'report-sales-order-view',
        'report-complimentary-orders-view',
        'report-payment-type-sales-view',
        'report-food-sales-view',
        'report-waiter-daily-orders-view',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        foreach ($this->reportPermissions as $name) {
            $existing = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->first();

            if (!$existing) {
                $row = [
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (Schema::hasColumn('permissions', 'group_name')) {
                    $row['group_name'] = 'Reports';
                }
                DB::table('permissions')->insert($row);
            } elseif (Schema::hasColumn('permissions', 'group_name') && empty($existing->group_name)) {
                DB::table('permissions')->where('id', $existing->id)->update([
                    'group_name' => 'Reports',
                    'updated_at' => $now,
                ]);
            }
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->reportPermissions)
            ->pluck('id')
            ->all();

        // Preserve current access: roles that had the old generic report-view get every report permission.
        $legacyPermissionId = DB::table('permissions')
            ->where('name', 'report-view')
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = collect();
        if ($legacyPermissionId && Schema::hasTable('role_has_permissions')) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $legacyPermissionId)
                ->pluck('role_id');
        }

        // Super Admin must always retain full report access.
        if (Schema::hasTable('roles')) {
            $superAdminId = DB::table('roles')
                ->where('name', 'Super Admin')
                ->where('guard_name', 'web')
                ->value('id');
            if ($superAdminId) {
                $roleIds->push($superAdminId);
            }
        }

        if (Schema::hasTable('role_has_permissions')) {
            foreach ($roleIds->unique()->filter() as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }

        // Preserve any direct user/model grants of the legacy report-view permission as well.
        if ($legacyPermissionId && Schema::hasTable('model_has_permissions')) {
            $legacyDirectRows = DB::table('model_has_permissions')
                ->where('permission_id', $legacyPermissionId)
                ->get();

            foreach ($legacyDirectRows as $legacyRow) {
                $base = (array) $legacyRow;
                foreach ($permissionIds as $permissionId) {
                    $row = $base;
                    $row['permission_id'] = $permissionId;
                    DB::table('model_has_permissions')->insertOrIgnore($row);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->reportPermissions)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
