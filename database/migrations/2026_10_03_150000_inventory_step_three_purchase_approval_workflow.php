<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'inventory-purchase-voucher-manage',
        'inventory-purchase-approve',
        'inventory-purchase-approval-settings',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('inventory_purchase_approval_settings')) {
            Schema::create('inventory_purchase_approval_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('sequential_approval')->default(true);
                $table->unsignedInteger('minimum_approvers')->default(3);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('inventory_purchase_approvers')) {
            Schema::create('inventory_purchase_approvers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('approval_order');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('user_id', 'inventory_purchase_approvers_user_unique');
                $table->unique('approval_order', 'inventory_purchase_approvers_order_unique');
                $table->index(['is_active', 'approval_order'], 'inventory_purchase_approvers_active_order_idx');
            });
        }

        if (!Schema::hasTable('purchase_vouchers')) {
            Schema::create('purchase_vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors');
                $table->string('voucher_no', 100)->unique();
                $table->date('voucher_date');
                $table->string('status', 30)->default('DRAFT')->index();
                $table->unsignedInteger('revision_no')->default(1);
                $table->decimal('subtotal', 18, 4)->default(0);
                $table->decimal('discount', 18, 4)->default(0);
                $table->decimal('tax', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->text('notes')->nullable();
                $table->text('reapproval_reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('sent_to_vendor_at')->nullable();
                $table->foreignId('sent_to_vendor_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('converted_purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->timestamps();
                $table->index(['vendor_id', 'voucher_date'], 'purchase_vouchers_vendor_date_idx');
                $table->index(['status', 'voucher_date'], 'purchase_vouchers_status_date_idx');
            });
        }

        if (!Schema::hasTable('purchase_voucher_items')) {
            Schema::create('purchase_voucher_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_voucher_id')->constrained('purchase_vouchers')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained('ingredients');
                $table->decimal('quantity', 24, 8);
                $table->foreignId('unit_id')->constrained('units');
                $table->foreignId('package_conversion_id')->nullable()->constrained('ingredient_unit_conversions')->nullOnDelete();
                $table->decimal('conversion_factor_snapshot', 24, 8);
                $table->decimal('base_quantity', 24, 8);
                $table->decimal('unit_price', 18, 4);
                $table->decimal('line_total', 18, 4);
                $table->timestamps();
                $table->unique(['purchase_voucher_id', 'ingredient_id'], 'purchase_voucher_items_voucher_ingredient_unique');
            });
        }

        if (!Schema::hasTable('purchase_voucher_approvals')) {
            Schema::create('purchase_voucher_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_voucher_id')->constrained('purchase_vouchers')->cascadeOnDelete();
                $table->unsignedInteger('revision_no');
                $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('approver_name', 255)->nullable();
                $table->string('approver_email', 255)->nullable();
                $table->unsignedInteger('approval_order');
                $table->string('status', 20)->default('PENDING');
                $table->text('comment')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();
                $table->unique(['purchase_voucher_id', 'revision_no', 'approver_user_id'], 'purchase_voucher_approvals_revision_user_unique');
                $table->index(['approver_user_id', 'status'], 'purchase_voucher_approvals_user_status_idx');
                $table->index(['purchase_voucher_id', 'revision_no', 'approval_order'], 'purchase_voucher_approvals_order_idx');
            });
        }

        if (!Schema::hasTable('purchase_voucher_revisions')) {
            Schema::create('purchase_voucher_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_voucher_id')->constrained('purchase_vouchers')->cascadeOnDelete();
                $table->unsignedInteger('revision_no');
                $table->json('snapshot');
                $table->string('reason', 255)->nullable();
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['purchase_voucher_id', 'revision_no'], 'purchase_voucher_revisions_unique');
            });
        }

        if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'purchase_voucher_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreignId('purchase_voucher_id')->nullable()->after('id')->constrained('purchase_vouchers')->nullOnDelete();
                $table->unique('purchase_voucher_id', 'purchases_purchase_voucher_unique');
            });
        }

        if (Schema::hasTable('inventory_purchase_approval_settings') && DB::table('inventory_purchase_approval_settings')->count() === 0) {
            DB::table('inventory_purchase_approval_settings')->insert([
                'is_enabled' => 1,
                'sequential_approval' => 1,
                'minimum_approvers' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->seedPermissions();
        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'purchase_voucher_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                try { $table->dropForeign(['purchase_voucher_id']); } catch (\Throwable $e) {}
                try { $table->dropUnique('purchases_purchase_voucher_unique'); } catch (\Throwable $e) {}
                $table->dropColumn('purchase_voucher_id');
            });
        }

        Schema::dropIfExists('purchase_voucher_revisions');
        Schema::dropIfExists('purchase_voucher_approvals');
        Schema::dropIfExists('purchase_voucher_items');
        Schema::dropIfExists('purchase_vouchers');
        Schema::dropIfExists('inventory_purchase_approvers');
        Schema::dropIfExists('inventory_purchase_approval_settings');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $this->permissions)->pluck('id');
            if ($ids->isNotEmpty()) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
                }
                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
                }
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }

        $this->forgetPermissionCache();
    }

    private function seedPermissions(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        $hasGroup = Schema::hasColumn('permissions', 'group_name');
        foreach ($this->permissions as $name) {
            $existing = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            if (!$existing) {
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
            } elseif ($hasGroup && empty($existing->group_name)) {
                DB::table('permissions')->where('id', $existing->id)->update(['group_name' => 'Inventory', 'updated_at' => $now]);
            }
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id', 'name');

        $inventoryManager = DB::table('roles')->where('name', 'Inventory Manager')->where('guard_name', 'web')->first();
        if ($inventoryManager) {
            $manageId = $permissionIds['inventory-purchase-voucher-manage'] ?? null;
            if ($manageId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => (int) $manageId,
                    'role_id' => (int) $inventoryManager->id,
                ]);
            }
        }

        $superAdmin = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => (int) $permissionId,
                    'role_id' => (int) $superAdmin->id,
                ]);
            }
        }

        // Limited super admin can operate vouchers/approvals but cannot change the approval chain.
        $limited = DB::table('roles')->where('name', 'Super Admin Limited')->where('guard_name', 'web')->first();
        if ($limited) {
            foreach (['inventory-purchase-voucher-manage', 'inventory-purchase-approve'] as $name) {
                $permissionId = $permissionIds[$name] ?? null;
                if ($permissionId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => (int) $permissionId,
                        'role_id' => (int) $limited->id,
                    ]);
                }
            }
        }
    }

    private function forgetPermissionCache(): void
    {
        try {
            if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }
        } catch (\Throwable $e) {
            // Safe during CLI deploy/migrate when the permission registrar is unavailable.
        }
    }
};
