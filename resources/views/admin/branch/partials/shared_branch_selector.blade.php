@php
    $branchesForPicker = collect($availableBranches ?? [])->where('status', true)->values();
    $showPicker = auth()->check()
        && auth()->user()->isSuperAdmin()
        && ($branchMode ?? 'multiple') === 'multiple';

    $selectedBranchIds = collect(old('branch_ids', $selectedBranchIds ?? []))
        ->map(fn($id) => (int) $id)
        ->filter()
        ->unique()
        ->values();

    $defaultAll = (bool) ($defaultAll ?? true);
    if ($showPicker && $selectedBranchIds->isEmpty() && $defaultAll) {
        if ($allBranchesSelected ?? false) {
            $selectedBranchIds = $branchesForPicker->pluck('id')->map(fn($id) => (int) $id)->values();
        } elseif (!empty($activeBranchId)) {
            $selectedBranchIds = collect([(int) $activeBranchId]);
        } else {
            $mainBranch = $branchesForPicker->firstWhere('is_main', true) ?? $branchesForPicker->first();
            $selectedBranchIds = $mainBranch ? collect([(int) $mainBranch->id]) : collect();
        }
    }

    $selectId = 'progga_shared_branch_' . \Illuminate\Support\Str::random(10);
@endphp

@if($showPicker)
<div class="progga-form-group progga-branch-select-wrap mb-3" data-shared-branch-target>
    <label class="progga-form-label" for="{{ $selectId }}">
        <i class="bi bi-diagram-3-fill me-1"></i>{{ $label ?? 'Available Branches' }} <span class="progga-required">*</span>
    </label>
    <select
        id="{{ $selectId }}"
        name="branch_ids[]"
        class="progga-form-control progga-branch-select2 progga-branch-select2-control"
        data-shared-branch-select
        data-no-select2="true"
        data-placeholder="Select Branches"
        data-default-values="{{ $selectedBranchIds->implode(',') }}"
        multiple
        required
    >
        @foreach($branchesForPicker as $branchOption)
            <option value="{{ $branchOption->id }}" {{ $selectedBranchIds->contains((int) $branchOption->id) ? 'selected' : '' }}>
                {{ $branchOption->name }}{{ $branchOption->code ? ' — ' . $branchOption->code : '' }}{{ $branchOption->is_main ? ' (Main)' : '' }}
            </option>
        @endforeach
    </select>
    <div class="progga-form-hint mt-1">Select one or multiple branches. In All Branches workspace, new shared menu records default to all active branches.</div>
    @error('branch_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @error('branch_ids.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
@elseif(!empty($activeBranchId))
    <input type="hidden" name="branch_ids[]" value="{{ $activeBranchId }}">
@endif
