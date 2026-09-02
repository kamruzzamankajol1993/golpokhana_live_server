<?php

namespace App\Services\Menu;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SharedMenuBranchService
{
    public function __construct(private BranchContext $context)
    {
    }

    public function targetBranchIds(Request $request, ?Model $source = null): array
    {
        $isSuperAdmin = (bool) $this->context->user()?->isSuperAdmin();
        $isMultipleMode = app(\App\Services\BranchModeManager::class)->isMultipleMode();

        // In Multiple Branch mode, Super Admin roles may publish shared menu data to
        // one or many branches from any header workspace. The form selection is
        // request-scoped and does not change the header/session branch.
        if (!$isSuperAdmin || !$isMultipleMode) {
            return [$this->context->requireSpecificBranch()];
        }

        $activeIds = Branch::query()->active()->orderByDesc('is_main')->orderBy('name')->pluck('id')->map(fn ($id) => (int) $id);

        $allRequested = $request->boolean('all_branches');
        $requested = collect((array) $request->input('branch_ids', []))
            ->filter(fn ($id) => ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($allRequested) {
            $requested = $activeIds;
        }

        if ($requested->isEmpty() && $source) {
            $requested = collect($source->sharedBranchIds());
        }

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Select at least one branch or keep All Branches checked.',
            ]);
        }

        $invalid = $requested->diff($activeIds);
        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'branch_ids' => 'One or more selected branches are invalid or inactive.',
            ]);
        }

        return $requested->values()->all();
    }

    public function groupUuid(?Model $source = null): string
    {
        return (string) ($source?->shared_group_uuid ?: Str::uuid());
    }

    public function copies(string $modelClass, string $groupUuid): Collection
    {
        return $modelClass::query()
            ->withoutGlobalScopes()
            ->where('shared_group_uuid', $groupUuid)
            ->get()
            ->keyBy(fn ($model) => (int) $model->branch_id);
    }

    public function branchCopyFor(Model $source, int $branchId): ?Model
    {
        return $source::query()
            ->withoutGlobalScopes()
            ->where('shared_group_uuid', $source->shared_group_uuid)
            ->where('branch_id', $branchId)
            ->first();
    }

    public function relatedCopyForBranch(?Model $selected, int $branchId): ?Model
    {
        if (!$selected) {
            return null;
        }

        if (empty($selected->shared_group_uuid)) {
            return (int) $selected->branch_id === $branchId ? $selected : null;
        }

        return $selected::query()
            ->withoutGlobalScopes()
            ->where('shared_group_uuid', $selected->shared_group_uuid)
            ->where('branch_id', $branchId)
            ->first();
    }

    public function logicalPicker(string $modelClass, ?callable $queryCallback = null): Collection
    {
        $query = $modelClass::query()->with('branch');
        if ($this->context->isAllBranches()) {
            $query->withoutGlobalScopes();
        }
        if ($queryCallback) {
            $queryCallback($query);
        }

        $rows = $query->get();
        if (!$this->context->isAllBranches()) {
            return $rows;
        }

        return $rows
            ->groupBy(fn ($row) => $row->shared_group_uuid ?: ('legacy-' . $row->id))
            ->map(function ($group) {
                $first = $group->first();
                $first->setAttribute('shared_branch_names', $group->pluck('branch.name')->filter()->unique()->values()->implode(', '));
                $first->setAttribute('shared_branch_ids', $group->pluck('branch_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
                return $first;
            })
            ->values();
    }

    public function syncSimpleCopies(
        string $modelClass,
        ?Model $source,
        array $branchIds,
        array $attributes,
        ?callable $perBranchAttributes = null,
        ?callable $beforeRemove = null
    ): Collection {
        $groupUuid = $this->groupUuid($source);
        $existing = $this->copies($modelClass, $groupUuid);
        $saved = collect();

        foreach ($branchIds as $branchId) {
            $rowAttributes = $perBranchAttributes
                ? $perBranchAttributes($branchId, $attributes)
                : $attributes;

            $copy = $existing->get((int) $branchId);
            if ($copy) {
                $copy->fill($rowAttributes);
                $copy->save();
            } else {
                $copy = $modelClass::create(array_merge($rowAttributes, [
                    'branch_id' => (int) $branchId,
                    'shared_group_uuid' => $groupUuid,
                ]));
            }

            $saved->push($copy);
        }

        // Super Admin roles own the shared membership list from any header workspace.
        // Normal branch users can only edit their local copy and never remove sisters.
        if ($this->context->user()?->isSuperAdmin()) {
            $keep = collect($branchIds)->map(fn ($id) => (int) $id);
            foreach ($existing as $branchId => $copy) {
                if ($keep->contains((int) $branchId)) {
                    continue;
                }

                if ($beforeRemove) {
                    $beforeRemove($copy);
                }
                $copy->delete();
            }
        }

        Log::info('Shared menu branch sync completed.', [
            'model' => $modelClass,
            'shared_group_uuid' => $groupUuid,
            'branch_ids' => $branchIds,
            'actor_id' => auth()->id(),
        ]);

        return $saved;
    }
}
