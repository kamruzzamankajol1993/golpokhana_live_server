<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Support\BranchContext;
use App\Services\AuditLogger;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());

        static::creating(function ($model) {
            $context = app(BranchContext::class);

            if ($context->isResolved() && $context->isAllBranches() && !($model instanceof \App\Models\User)) {
                if (!$context->user()?->isSuperAdmin()) {
                    throw new UnprocessableEntityHttpException('Select a specific branch before creating branch-owned data.');
                }

                // All Branches remains the persistent workspace. The selected form
                // branch is resolved only for this write and copied onto the model.
                $model->branch_id = $model->branch_id ?: $context->requireSpecificBranch();
            }

            if (!$model->branch_id) {
                if ($context->isResolved()) {
                    $model->branch_id = $context->requireSpecificBranch();
                } else {
                    $model->branch_id = app(\App\Services\BranchModeManager::class)->mainBranchId();
                }
            }

            if ($context->isResolved() && !$context->allowsBranch((int) $model->branch_id)) {
                throw new AccessDeniedHttpException('You cannot create data for another branch.');
            }
        });

        static::updating(function ($model) {
            $context = app(BranchContext::class);

            if ($context->isResolved() && $context->isAllBranches() && !($model instanceof \App\Models\User) && !$context->user()?->isSuperAdmin()) {
                throw new UnprocessableEntityHttpException('Select a specific branch before changing branch-owned data.');
            }

            if ($model->isDirty('branch_id')) {
                $original = $model->getOriginal('branch_id');
                $isUserReassignment = $model instanceof \App\Models\User
                    && $context->isResolved()
                    && $context->user()?->isSuperAdmin();

                if (!$isUserReassignment && $original !== null && (int) $original !== (int) $model->branch_id) {
                    throw new UnprocessableEntityHttpException('Branch cannot be changed after this record is created.');
                }
            }

            $branchToCheck = (int) ($model->getOriginal('branch_id') ?: $model->branch_id);
            if ($context->isResolved() && !$context->allowsBranch($branchToCheck)) {
                throw new AccessDeniedHttpException('You cannot modify data from another branch.');
            }
        });

        static::saved(function ($model) {
            if ($model->branch_id) {
                app(\App\Services\BranchModeManager::class)
                    ->lockForSecondaryBranch((int) $model->branch_id, $model->getTable());
            }
        });


        // Phase 9: keep an immutable, branch-aware audit trail for important model lifecycle changes.
        static::created(function ($model) {
            app(AuditLogger::class)->logModel('created', $model);
        });

        static::updated(function ($model) {
            app(AuditLogger::class)->logModel('updated', $model);
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->logModel('deleted', $model);
        });

        static::deleting(function ($model) {
            $context = app(BranchContext::class);
            if ($context->isResolved() && $context->isAllBranches() && !($model instanceof \App\Models\User) && !$context->user()?->isSuperAdmin()) {
                throw new UnprocessableEntityHttpException('Select a specific branch before deleting branch-owned data.');
            }
            if ($context->isResolved() && !$context->allowsBranch((int) $model->branch_id)) {
                throw new AccessDeniedHttpException('You cannot delete data from another branch.');
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->withoutGlobalScope(BranchScope::class)->where($this->qualifyColumn('branch_id'), $branchId);
    }

    public function scopeAllBranches($query)
    {
        return $query->withoutGlobalScope(BranchScope::class);
    }
}
