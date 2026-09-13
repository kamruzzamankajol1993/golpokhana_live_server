<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchModeSetting;
use App\Services\Inventory\StockLocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BranchModeManager
{
    private array $meaningfulTables = [
        'users',
        'zones', 'shifts', 'waiters', 'tables', 'occasions', 'table_bookings',
        'food_categories', 'cuisine_types', 'allergens', 'course_types', 'food_items', 'food_addons',
        'orders', 'order_kots', 'order_due_payments', 'pos_sessions', 'pos_deleted_item_histories', 'offline_pos_devices',
        'departments', 'designations', 'employment_types', 'leave_types', 'salary_components', 'holidays',
        'employees', 'shift_rosters', 'attendances', 'leave_requests', 'employee_leave_balances',
        'employee_salary_structures', 'employee_salary_components', 'payroll_runs', 'payroll_items',
        'payroll_item_components', 'payroll_payments',
        'salary_advances', 'salary_advance_repayments', 'employee_loans', 'loan_repayments', 'payroll_recovery_allocations',
        // Inventory stock is branch-owned business data. stock_locations are deliberately
        // excluded because MAIN/KITCHEN rows are default branch configuration.
        'inventory_balances', 'stock_movements',
    ];

    public function setting(): BranchModeSetting
    {
        return BranchModeSetting::current();
    }

    public function isSingleMode(): bool
    {
        return $this->setting()->isSingle();
    }

    public function isMultipleMode(): bool
    {
        return $this->setting()->isMultiple();
    }

    public function mainBranchId(): int
    {
        $id = Branch::query()->main()->value('id') ?: Branch::query()->oldest('id')->value('id');

        if (!$id) {
            $branch = Branch::query()->create([
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'slug' => 'main-branch',
                'is_main' => true,
                'status' => true,
            ]);
            $id = $branch->id;

            if (Schema::hasTable('stock_locations')) {
                app(StockLocationService::class)->ensureDefaultLocations((int) $id);
            }
        }

        return (int) $id;
    }

    public function activateMultipleMode(): BranchModeSetting
    {
        $setting = $this->setting();

        if ($setting->isMultiple()) {
            return $setting;
        }

        $setting->forceFill([
            'mode' => BranchModeSetting::MODE_MULTIPLE,
            'multi_branch_activated_at' => now(),
        ])->save();

        if ($this->hasSecondaryBranchBusinessData()) {
            $this->lockMultipleMode();
        }

        return $setting->fresh();
    }

    public function switchToSingleMode(): BranchModeSetting
    {
        $setting = $this->setting();

        if ($setting->isSingle()) {
            return $setting;
        }

        if ($setting->multi_branch_locked_at || $this->hasSecondaryBranchBusinessData()) {
            $this->lockMultipleMode();

            throw ValidationException::withMessages([
                'branch_mode' => 'Multiple branch mode cannot be changed back to single because secondary-branch business data exists.',
            ]);
        }

        $setting->forceFill([
            'mode' => BranchModeSetting::MODE_SINGLE,
            'multi_branch_activated_at' => null,
        ])->save();

        return $setting->fresh();
    }

    public function lockForSecondaryBranch(?int $branchId, ?string $table = null): void
    {
        if (!$branchId || !$this->isMultipleMode() || $branchId === $this->mainBranchId()) {
            return;
        }

        if ($table !== null && !in_array($table, $this->meaningfulTables, true)) {
            return;
        }

        $this->lockMultipleMode();
    }

    public function lockMultipleMode(): void
    {
        $setting = $this->setting();
        if ($setting->isMultiple() && !$setting->multi_branch_locked_at) {
            $setting->forceFill(['multi_branch_locked_at' => now()])->save();
        }
    }

    public function hasSecondaryBranchBusinessData(): bool
    {
        $main = $this->mainBranchId();

        foreach ($this->meaningfulTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            if (DB::table($table)->whereNotNull('branch_id')->where('branch_id', '<>', $main)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function branchHasBusinessData(int $branchId): bool
    {
        foreach ($this->meaningfulTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            if (DB::table($table)->where('branch_id', $branchId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
