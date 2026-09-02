<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait ValidatesBranchRelations
{
    public static function bootValidatesBranchRelations(): void
    {
        static::creating(function (Model $model) {
            $model->validateBranchRelations(true);
        });

        static::updating(function (Model $model) {
            $model->validateBranchRelations(false);
        });
    }

    protected function validateBranchRelations(bool $creating): void
    {
        $rules = method_exists($this, 'branchRelationRules') ? $this->branchRelationRules() : [];
        if (!$rules) {
            return;
        }

        $context = app(BranchContext::class);
        $branchId = (int) ($this->branch_id
            ?: ($context->isResolved()
                ? $context->requireSpecificBranch()
                : app(\App\Services\BranchModeManager::class)->mainBranchId()));

        foreach ($rules as $foreignKey => $definition) {
            if (!$creating && !$this->isDirty('branch_id') && !$this->isDirty($foreignKey)) {
                // A transferred employee may legitimately remain referenced by a historical
                // source-branch Attendance/Payroll row. Revalidate only when ownership/FK changes.
                continue;
            }

            $modelClass = is_array($definition) ? ($definition['model'] ?? null) : $definition;
            $allowSuperAdmin = is_array($definition) && ($definition['allow_super_admin'] ?? false);
            $allowGlobal = is_array($definition) && ($definition['allow_global'] ?? false);
            $id = $this->{$foreignKey};

            if (!$id || !$modelClass) {
                continue;
            }

            /** @var Model|null $related */
            $related = $modelClass::query()->withoutGlobalScopes()->find($id);
            if (!$related) {
                throw ValidationException::withMessages([
                    $foreignKey => 'The selected related record does not exist.',
                ]);
            }

            if ($allowSuperAdmin && $related instanceof User && $related->isSuperAdmin()) {
                continue;
            }

            $relatedBranchId = $related->getAttribute('branch_id');
            if ($allowGlobal && $relatedBranchId === null) {
                continue;
            }

            if ($relatedBranchId === null || (int) $relatedBranchId !== $branchId) {
                throw ValidationException::withMessages([
                    $foreignKey => 'The selected related record belongs to another branch.',
                ]);
            }
        }
    }
}
