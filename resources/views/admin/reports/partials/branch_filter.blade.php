@php
    $reportBranchSelectId = $branchSelectId ?? 'reportBranchFilter';
    $reportBranchWrapperClass = $wrapperClass ?? 'progga-form-group';
    $reportBranchLabelClass = $labelClass ?? 'progga-form-label';
    $reportBranchSelectClass = $selectClass ?? 'progga-form-control';
    $reportBranches = collect($availableBranches ?? [])->where('status', true)->values();
    $reportCanPickBranch = auth()->check()
        && auth()->user()->isSuperAdmin()
        && ($branchMode ?? 'multiple') === 'multiple';
    $reportCanSelectAll = $reportCanPickBranch;

    $requestedReportBranch = request()->query('branch_id');
    if ($requestedReportBranch === null || $requestedReportBranch === '') {
        $selectedReportBranch = ($reportScope['is_all'] ?? false)
            ? 'all'
            : (string) ($reportScope['branch_id'] ?? ($activeBranchId ?? ''));
    } else {
        $selectedReportBranch = (string) $requestedReportBranch;
    }
@endphp

<div class="{{ $reportBranchWrapperClass }} progga-report-branch-filter">
    <label class="{{ $reportBranchLabelClass }}" for="{{ $reportBranchSelectId }}">Branch</label>
    <select
        name="branch_id"
        id="{{ $reportBranchSelectId }}"
        class="{{ $reportBranchSelectClass }} progga-branch-select2 progga-report-branch-select2"
        data-no-select2="true"
        data-placeholder="Select Branch"
        data-allow-clear="false"
        @unless($reportCanPickBranch) disabled @endunless
    >
        @if($reportCanSelectAll)
            <option value="all" {{ $selectedReportBranch === 'all' ? 'selected' : '' }}>All Branches</option>
        @endif

        @foreach($reportBranches as $branchOption)
            @if($reportCanPickBranch || (int) $branchOption->id === (int) ($activeBranchId ?? 0))
                <option value="{{ $branchOption->id }}" {{ $selectedReportBranch === (string) $branchOption->id ? 'selected' : '' }}>
                    {{ $branchOption->name }}{{ $branchOption->is_main ? ' (Main)' : '' }}
                </option>
            @endif
        @endforeach
    </select>
</div>
