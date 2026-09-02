<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait SharedAcrossBranches
{
    public static function bootSharedAcrossBranches(): void
    {
        static::creating(function ($model) {
            if (empty($model->shared_group_uuid)) {
                $model->shared_group_uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeSharedGroup(Builder $query, ?string $groupUuid = null): Builder
    {
        return $query->withoutGlobalScopes()->where('shared_group_uuid', $groupUuid ?: $this->shared_group_uuid);
    }

    public function sharedCopies()
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('shared_group_uuid', $this->shared_group_uuid)
            ->with('branch')
            ->orderBy('branch_id')
            ->get();
    }

    public function sharedBranchIds(): array
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('shared_group_uuid', $this->shared_group_uuid)
            ->pluck('branch_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
