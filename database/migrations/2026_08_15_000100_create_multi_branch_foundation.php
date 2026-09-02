<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Phase 1 - Multi-branch foundation.
     *
     * This is intentionally an expand/backfill migration: branch_id is nullable at
     * database level so the older QR/offline code can continue working while the
     * remaining phases are rolled out. Existing rows are immediately backfilled to
     * Main Branch. Application-level branch guards prevent new null/wrong-branch
     * records in the Restaurant Management application.
     */
    public function up(): void
    {
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('code', 30)->unique();
                $table->string('slug', 180)->unique();
                $table->string('phone', 40)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_main')->default(false)->index();
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('branch_mode_settings')) {
            Schema::create('branch_mode_settings', function (Blueprint $table) {
                $table->id();
                $table->string('mode', 20)->default('multiple');
                $table->timestamp('multi_branch_activated_at')->nullable();
                $table->timestamp('multi_branch_locked_at')->nullable();
                $table->timestamps();
            });
        }

        $mainBranchId = DB::table('branches')->where('is_main', 1)->value('id');
        if (!$mainBranchId) {
            $mainBranchId = DB::table('branches')->insertGetId([
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'slug' => 'main-branch',
                'is_main' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('branch_mode_settings')->updateOrInsert(
            ['id' => 1],
            ['mode' => 'multiple', 'multi_branch_activated_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );

        foreach ($this->branchOwnedTables() as $table) {
            $this->addBranchColumn($table, (int) $mainBranchId);
        }

        // Shared DB tables created by the separate QR project.
        foreach (['waiter_calls', 'reviews'] as $table) {
            $this->addBranchColumn($table, (int) $mainBranchId);
        }

        $this->convertGlobalUniquesToBranchUniques();
    }

    public function down(): void
    {
        $this->restoreOriginalUniques();

        foreach (array_reverse(array_merge($this->branchOwnedTables(), ['waiter_calls', 'reviews'])) as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign($table . '_branch_id_foreign');
                $blueprint->dropColumn('branch_id');
            });
        }

        Schema::dropIfExists('branch_mode_settings');
        Schema::dropIfExists('branches');
    }

    private function addBranchColumn(string $table, int $mainBranchId): void
    {
        if (!Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->unsignedBigInteger('branch_id')->nullable()->after('id');
            $blueprint->index('branch_id', $this->safeIndexName($table, 'branch_idx'));
            $blueprint->foreign('branch_id', $table . '_branch_id_foreign')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();
        });

        DB::table($table)->whereNull('branch_id')->update(['branch_id' => $mainBranchId]);
    }

    private function branchOwnedTables(): array
    {
        return [
            // Access + branch-specific settings
            'users',
            'restaurant_settings', 'tax_settings', 'invoice_settings', 'pos_settings', 'reward_point_settings',

            // Floor, service, menu and ordering
            'zones', 'shifts', 'waiters', 'tables', 'occasions', 'table_bookings',
            'food_categories', 'cuisine_types', 'allergens', 'course_types', 'food_items', 'food_addons',
            'orders', 'order_kots', 'order_due_payments',
            'pos_sessions', 'pos_deleted_item_histories',

            // HR
            'hr_settings', 'departments', 'designations', 'employment_types', 'leave_types',
            'salary_components', 'holidays', 'attendance_settings', 'payroll_settings',
            'employees', 'shift_rosters', 'attendances', 'leave_requests', 'employee_leave_balances',
            'employee_salary_structures', 'employee_salary_components', 'payroll_runs', 'payroll_items',
            'payroll_item_components', 'payroll_payments',
        ];
    }

    private function convertGlobalUniquesToBranchUniques(): void
    {
        $this->replaceSingleUnique('food_categories', 'slug', 'food_categories_branch_slug_unique');
        $this->replaceSingleUnique('food_items', 'slug', 'food_items_branch_slug_unique');
        $this->replaceSingleUnique('cuisine_types', 'slug', 'cuisine_types_branch_slug_unique');
        $this->replaceSingleUnique('waiters', 'employee_id', 'waiters_branch_employee_unique');
        $this->replaceSingleUnique('employees', 'employee_code', 'employees_branch_code_unique');

        $this->replaceSingleUnique('departments', 'name', 'departments_branch_name_unique');
        $this->replaceSingleUnique('departments', 'code', 'departments_branch_code_unique');
        $this->replaceSingleUnique('designations', 'code', 'designations_branch_code_unique');
        $this->replaceSingleUnique('employment_types', 'name', 'employment_types_branch_name_unique');
        $this->replaceSingleUnique('employment_types', 'code', 'employment_types_branch_code_unique');
        $this->replaceSingleUnique('leave_types', 'name', 'leave_types_branch_name_unique');
        $this->replaceSingleUnique('leave_types', 'code', 'leave_types_branch_code_unique');
        $this->replaceSingleUnique('salary_components', 'name', 'salary_components_branch_name_unique');
        $this->replaceSingleUnique('salary_components', 'code', 'salary_components_branch_code_unique');
        $this->replaceSingleUnique('shifts', 'code', 'shifts_branch_code_unique');

        $this->replaceSingleUnique('payroll_runs', 'payroll_code', 'payroll_runs_branch_code_unique');
        $this->replaceSingleUnique('payroll_runs', 'payroll_month', 'payroll_runs_branch_month_unique');

        if (Schema::hasTable('holidays') && Schema::hasColumn('holidays', 'branch_id')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropUnique(['holiday_date', 'name']);
                $table->unique(['branch_id', 'holiday_date', 'name'], 'holidays_branch_date_name_unique');
            });
        }

        // order_number remains globally unique in Phase 1/2. It will be converted
        // together with the branch-wise numbering/locking strategy in the numbering phase.
    }

    private function replaceSingleUnique(string $table, string $column, string $newIndex): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id') || !Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $newIndex) {
            $blueprint->dropUnique([$column]);
            $blueprint->unique(['branch_id', $column], $newIndex);
        });
    }

    private function restoreOriginalUniques(): void
    {
        $pairs = [
            ['food_categories', 'slug', 'food_categories_branch_slug_unique'],
            ['food_items', 'slug', 'food_items_branch_slug_unique'],
            ['cuisine_types', 'slug', 'cuisine_types_branch_slug_unique'],
            ['waiters', 'employee_id', 'waiters_branch_employee_unique'],
            ['employees', 'employee_code', 'employees_branch_code_unique'],
            ['departments', 'name', 'departments_branch_name_unique'],
            ['departments', 'code', 'departments_branch_code_unique'],
            ['designations', 'code', 'designations_branch_code_unique'],
            ['employment_types', 'name', 'employment_types_branch_name_unique'],
            ['employment_types', 'code', 'employment_types_branch_code_unique'],
            ['leave_types', 'name', 'leave_types_branch_name_unique'],
            ['leave_types', 'code', 'leave_types_branch_code_unique'],
            ['salary_components', 'name', 'salary_components_branch_name_unique'],
            ['salary_components', 'code', 'salary_components_branch_code_unique'],
            ['shifts', 'code', 'shifts_branch_code_unique'],
            ['payroll_runs', 'payroll_code', 'payroll_runs_branch_code_unique'],
            ['payroll_runs', 'payroll_month', 'payroll_runs_branch_month_unique'],
        ];

        foreach ($pairs as [$table, $column, $index]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $index) {
                $blueprint->dropUnique($index);
                $blueprint->unique($column);
            });
        }

        if (Schema::hasTable('holidays') && Schema::hasColumn('holidays', 'branch_id')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropUnique('holidays_branch_date_name_unique');
                $table->unique(['holiday_date', 'name']);
            });
        }
    }

    private function safeIndexName(string $table, string $suffix): string
    {
        return Str::limit($table . '_' . $suffix, 60, '');
    }
};
