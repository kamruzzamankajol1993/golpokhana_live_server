<?php

namespace App\Services;

use App\Models\User;

class PosSessionManagerResolver
{
    /**
     * Manager is the shared POS work-period owner.
     * The role match is case-insensitive so both Manager / manager work.
     */
    public function resolve(?User $actor = null): ?User
    {
        if ($actor && $this->isManager($actor)) {
            return $actor;
        }

        return User::query()
            ->whereHas('roles', function ($query) {
                $query->whereRaw('LOWER(TRIM(name)) = ?', ['manager']);
            })
            ->orderBy('id')
            ->first();
    }

    public function resolveId(?User $actor = null): ?int
    {
        return $this->resolve($actor)?->id;
    }

    private function isManager(User $user): bool
    {
        return $user->getRoleNames()->contains(
            fn ($role) => strcasecmp(trim((string) $role), 'manager') === 0
        );
    }
}
