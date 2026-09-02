<?php

namespace App\Services;

use App\Models\AttendanceSetting;
use App\Models\HrSetting;
use App\Models\InvoiceSetting;
use App\Models\PayrollSetting;
use App\Models\PosSetting;
use App\Models\RestaurantSetting;
use App\Models\RewardPointSetting;
use App\Models\TaxSetting;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class BranchSettingResolver
{
    public const SETTING_MODELS = [
        RestaurantSetting::class,
        TaxSetting::class,
        InvoiceSetting::class,
        PosSetting::class,
        RewardPointSetting::class,
        HrSetting::class,
        AttendanceSetting::class,
        PayrollSetting::class,
    ];

    public function __construct(
        private BranchContext $context,
        private BranchModeManager $mode
    ) {
    }

    /**
     * Read a branch setting. In All-Branches/unresolved contexts this intentionally
     * uses Main Branch as a display fallback; operational write screens should call editable().
     */
    public function get(string $modelClass, ?int $branchId = null, bool $fallbackToMain = true): ?Model
    {
        $this->assertSupported($modelClass);
        $model = new $modelClass();
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            return null;
        }

        if (!Schema::hasColumn($table, 'branch_id')) {
            return $modelClass::query()->withoutGlobalScopes()->first();
        }

        $branchId = $branchId ?: $this->context->branchId() ?: $this->mode->mainBranchId();
        $setting = $modelClass::query()->withoutGlobalScopes()->where('branch_id', $branchId)->first();

        if (!$setting && $fallbackToMain && $branchId !== $this->mode->mainBranchId()) {
            $setting = $modelClass::query()->withoutGlobalScopes()
                ->where('branch_id', $this->mode->mainBranchId())
                ->first();
        }

        return $setting;
    }

    /**
     * Return the settings row that may be edited for the active branch. This never
     * writes Main Branch accidentally from an All-Branches context.
     */
    public function editable(string $modelClass, ?int $branchId = null): Model
    {
        $this->assertSupported($modelClass);
        $branchId = $branchId ?: $this->context->requireSpecificBranch();

        return $this->ensureForBranch($modelClass, $branchId);
    }

    public function ensureForBranch(string $modelClass, int $branchId): Model
    {
        $this->assertSupported($modelClass);
        $model = new $modelClass();
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            return $model;
        }

        if (!Schema::hasColumn($table, 'branch_id')) {
            return $modelClass::query()->withoutGlobalScopes()->first() ?: $model;
        }

        $existing = $modelClass::query()->withoutGlobalScopes()->where('branch_id', $branchId)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($modelClass, $branchId) {
            $existing = $modelClass::query()->withoutGlobalScopes()->where('branch_id', $branchId)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $main = $modelClass::query()->withoutGlobalScopes()
                ->where('branch_id', $this->mode->mainBranchId())
                ->first();

            $copy = new $modelClass();
            if ($main) {
                $attributes = $main->getAttributes();
                unset($attributes[$main->getKeyName()], $attributes['created_at'], $attributes['updated_at']);
                $copy->forceFill($attributes);
            }
            $copy->branch_id = $branchId;

            // Saving through normal model events is safe for an explicitly selected branch;
            // branch creation/bootstrap may call this before BranchContext is resolved.
            $copy->saveQuietly();

            return $copy;
        });
    }

    public function seedBranch(int $branchId): void
    {
        foreach (self::SETTING_MODELS as $modelClass) {
            $model = new $modelClass();
            if (Schema::hasTable($model->getTable())) {
                $this->ensureForBranch($modelClass, $branchId);
            }
        }
    }

    private function assertSupported(string $modelClass): void
    {
        if (!in_array($modelClass, self::SETTING_MODELS, true)) {
            throw new InvalidArgumentException("{$modelClass} is not registered as a branch setting model.");
        }
    }
}
