@php
    $branchFieldName = $fieldName ?? 'branch_id';
    $locked = (bool) ($locked ?? false);
    $branchesForPicker = collect($availableBranches ?? [])->where('status', true)->values();
    $showPicker = auth()->check()
        && auth()->user()->isSuperAdmin()
        && ($branchMode ?? 'multiple') === 'multiple';

    $selectedBranchId = old($branchFieldName, $selectedBranchId ?? request($branchFieldName) ?? ($activeBranchId ?? null));
    if ($showPicker && empty($selectedBranchId)) {
        $selectedBranchId = optional($branchesForPicker->firstWhere('is_main', true) ?? $branchesForPicker->first())->id;
    }
    $selectId = 'progga_branch_' . \Illuminate\Support\Str::random(10);
@endphp

@if($showPicker)
<div class="progga-form-group progga-branch-select-wrap mb-3" data-branch-target data-locked="{{ $locked ? '1' : '0' }}">
    <label class="progga-form-label" for="{{ $selectId }}">
        <i class="bi bi-diagram-3 me-1"></i>{{ $label ?? 'Branch' }} <span class="progga-required">*</span>
    </label>

    <input type="hidden" name="{{ $branchFieldName }}" value="{{ $selectedBranchId }}" data-branch-target-input>

    <select
        id="{{ $selectId }}"
        class="progga-form-control progga-branch-select2 progga-branch-select2-control"
        data-branch-select
        data-no-select2="true"
        data-placeholder="Select Branch"
        data-default-value="{{ $selectedBranchId }}"
        {{ $locked ? 'disabled' : 'required' }}
    >
        @unless($locked)
            <option value="">Select Branch</option>
        @endunless
        @foreach($branchesForPicker as $branchOption)
            <option value="{{ $branchOption->id }}" {{ (string) $selectedBranchId === (string) $branchOption->id ? 'selected' : '' }}>
                {{ $branchOption->name }}{{ $branchOption->code ? ' — ' . $branchOption->code : '' }}{{ $branchOption->is_main ? ' (Main)' : '' }}
            </option>
        @endforeach
    </select>

    <div class="progga-form-hint mt-1">
        {{ $locked ? 'This record is already assigned to this branch.' : 'Choose the branch for this form. Header branch selection will not be changed.' }}
    </div>
    @error($branchFieldName)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
@elseif(!empty($activeBranchId))
    <input type="hidden" name="{{ $branchFieldName }}" value="{{ $selectedBranchId ?: $activeBranchId }}">
@endif
