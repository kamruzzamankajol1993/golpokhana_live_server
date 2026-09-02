<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_NAME = 'Super Admin Limited';
    private const EMAIL = 'superadminlimited@restaurant.local';
    private const DEFAULT_PASSWORD = 'SALimited@2026!';
    private const USER_ID = 'SAL-1001';

    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('roles') || !Schema::hasTable('model_has_roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', self::ROLE_NAME)
            ->where('guard_name', 'web')
            ->value('id');

        if (!$roleId) {
            throw new RuntimeException('Super Admin Limited role is missing. Run migration 2026_08_15_000700 first.');
        }

        $mainBranchId = null;
        if (Schema::hasTable('branches')) {
            $mainBranchId = DB::table('branches')->where('is_main', 1)->value('id');
        }

        if (Schema::hasColumn('users', 'branch_id') && !$mainBranchId) {
            throw new RuntimeException('Main Branch is missing. Create/restore Main Branch before creating the limited Super Admin user.');
        }

        $existing = DB::table('users')->where('email', self::EMAIL)->first();
        if ($existing && Schema::hasColumn('users', 'user_id')) {
            $existingUserId = (string) ($existing->user_id ?? '');
            if ($existingUserId !== '' && $existingUserId !== self::USER_ID) {
                throw new RuntimeException('The default limited Super Admin email is already used by another user.');
            }
        }

        if (!$existing) {
            $values = [
                'name' => 'Super Admin Limited',
                'email' => self::EMAIL,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('users', 'user_id')) {
                $candidate = self::USER_ID;
                $suffix = 1;
                while (DB::table('users')->where('user_id', $candidate)->exists()) {
                    $suffix++;
                    $candidate = 'SAL-' . (1000 + $suffix);
                }
                $values['user_id'] = $candidate;
            }
            if (Schema::hasColumn('users', 'first_name')) {
                $values['first_name'] = 'Super Admin';
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $values['last_name'] = 'Limited';
            }
            if (Schema::hasColumn('users', 'branch_id')) {
                $values['branch_id'] = (int) $mainBranchId;
            }

            $userId = DB::table('users')->insertGetId($values);
        } else {
            $userId = (int) $existing->id;

            // Rerun-safe repair for this exact managed account. Never reset its password.
            $updates = ['updated_at' => now()];
            if (Schema::hasColumn('users', 'branch_id') && !$existing->branch_id) {
                $updates['branch_id'] = (int) $mainBranchId;
            }
            DB::table('users')->where('id', $userId)->update($updates);
        }

        $modelType = 'App\\Models\\User';
        DB::table('model_has_roles')
            ->where('model_type', $modelType)
            ->where('model_id', $userId)
            ->delete();

        DB::table('model_has_roles')->insert([
            'role_id' => (int) $roleId,
            'model_type' => $modelType,
            'model_id' => $userId,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('model_has_roles')) {
            return;
        }

        $userId = DB::table('users')->where('email', self::EMAIL)->value('id');
        if (!$userId) {
            return;
        }

        // Do not delete the user on rollback because it may already own audit/POS/history rows.
        DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', (int) $userId)
            ->delete();
    }
};
