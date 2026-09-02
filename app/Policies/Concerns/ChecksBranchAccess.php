<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksBranchAccess
{
    protected function userCanAccessBranchModel(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->branch_id !== null
            && (int) $user->branch_id === (int) $model->getAttribute('branch_id');
    }
}
