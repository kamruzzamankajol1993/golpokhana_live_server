@extends('admin.master.master')

@section('title', 'Transfer Employee')

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Transfer Employee</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('hr.employees.index') }}" class="progga-breadcrumb-item">Employees</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.employees.show', $employee) }}" class="progga-breadcrumb-item">{{ $employee->employee_code }}</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Transfer</span>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="hr-card">
                    <div class="hr-card-header">
                        <div>
                            <div class="hr-card-title">{{ $employee->name }}</div>
                            <div class="hr-card-subtitle">Current branch: {{ $employee->branch->name ?? 'Unknown' }}</div>
                        </div>
                    </div>
                    <div class="hr-card-body">
                        <div class="alert alert-warning">
                            Historical attendance, leave, salary and payroll records stay in the original branch. Future source-branch rosters are removed. Target-branch Department, Designation, Employment Type, Shift and Zone are cleared and must be assigned after transfer. A new target-branch salary structure is also required before payroll.
                        </div>

                        <form method="POST" action="{{ route('hr.employees.transfer.store', $employee) }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="progga-form-label">Target Branch <span class="progga-required">*</span></label>
                                    <select name="to_branch_id" id="employeeTransferBranch" class="form-select progga-branch-select2" data-no-select2="true" data-placeholder="Select Branch" data-allow-clear="false" required>
                                        <option value="">Select branch</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected(old('to_branch_id') == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>
                                        @endforeach
                                    </select>
                                    @error('to_branch_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-5">
                                    <label class="progga-form-label">Effective Date <span class="progga-required">*</span></label>
                                    <input type="date" name="effective_date" class="form-control" max="{{ now()->toDateString() }}" value="{{ old('effective_date', now()->toDateString()) }}" required>
                                    @error('effective_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="progga-form-label">Transfer Note</label>
                                    <textarea name="note" rows="4" class="form-control" maxlength="2000">{{ old('note') }}</textarea>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-4">
                                <button class="progga-btn progga-btn-primary" type="submit"><i class="bi bi-arrow-left-right"></i> Transfer Employee</button>
                                <a class="progga-btn progga-btn-secondary" href="{{ route('hr.employees.show', $employee) }}">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="hr-card">
                    <div class="hr-card-header"><div class="hr-card-title">Transfer History</div></div>
                    <div class="hr-card-body">
                        @forelse($employee->branchTransfers as $transfer)
                            <div class="border-bottom pb-2 mb-2">
                                <strong>{{ $transfer->fromBranch->name ?? $transfer->from_branch_id }} → {{ $transfer->toBranch->name ?? $transfer->to_branch_id }}</strong>
                                <div class="small text-muted">{{ optional($transfer->effective_date)->format('d-m-Y') }} · {{ $transfer->approvedBy->name ?? 'System' }}</div>
                                @if($transfer->note)<div class="small mt-1">{{ $transfer->note }}</div>@endif
                            </div>
                        @empty
                            <div class="text-muted">No previous transfers.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
