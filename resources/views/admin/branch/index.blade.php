@extends('admin.master.master')
@section('title', 'Branch & Mode Management — ' . ($restaurantSettingName ?? 'Restaurant'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Branch & Mode Management</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Branches</span>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if(auth()->user()->canManageBranchMode())
    <div class="progga-card" style="padding:22px;margin-bottom:18px;">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="text-muted" style="font-size:12px;font-weight:700;text-transform:uppercase;">Current Mode</div>
                <h3 class="mb-1">{{ $modeSetting->isMultiple() ? 'Multiple Branch Mode' : 'Single Branch Mode' }}</h3>
                @if($modeSetting->isSingle())
                    <p class="text-muted mb-0">Only Main Branch is active internally. Branch selector and branch CRUD remain hidden from normal operations.</p>
                @else
                    <p class="text-muted mb-0">Super Admin and Super Admin Limited default to Main Branch after login. All Branches remains available for combined viewing.</p>
                @endif
            </div>
            <div class="text-end">
                @if($modeSetting->isSingle())
                    <form method="POST" action="{{ route('branches.mode.update') }}" data-swal-title="Activate Multiple Branch Mode?" data-swal-confirm="You can return to Single Mode only until secondary-branch business data is created." data-swal-confirm-text="Yes, activate">
                        @csrf @method('PUT')
                        <input type="hidden" name="mode" value="multiple">
                        <button class="progga-btn progga-btn-primary"><i class="bi bi-diagram-3"></i> Activate Multiple Branch</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('branches.mode.update') }}" data-swal-title="Switch to Single Branch Mode?" data-swal-confirm="This is allowed only while no secondary-branch business data exists." data-swal-confirm-text="Yes, switch">
                        @csrf @method('PUT')
                        <input type="hidden" name="mode" value="single">
                        <button class="progga-btn progga-btn-outline" {{ $modeSetting->multi_branch_locked_at || $hasSecondaryData ? 'disabled' : '' }}>
                            <i class="bi bi-arrow-counterclockwise"></i> Return to Single Mode
                        </button>
                    </form>
                @endif
            </div>
        </div>
        @if($modeSetting->isMultiple())
            <div class="mt-3 p-3 rounded" style="background:{{ ($modeSetting->multi_branch_locked_at || $hasSecondaryData) ? '#fff3f3' : '#f5f8f6' }};border:1px solid var(--progga-border);">
                @if($modeSetting->multi_branch_locked_at || $hasSecondaryData)
                    <strong><i class="bi bi-lock-fill"></i> Multiple mode is locked.</strong>
                    Secondary-branch business data exists, so Multiple → Single is no longer allowed.
                @else
                    <strong><i class="bi bi-unlock"></i> Rollback is still available.</strong>
                    No meaningful secondary-branch business data has been created yet.
                @endif
            </div>
        @endif
    </div>
    @endif

    @if($modeSetting->isMultiple())
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4 class="mb-0">Branches</h4><small class="text-muted">Main Branch is permanent. Branches with business history should be deactivated, not deleted.</small></div>
        <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal"><i class="bi bi-plus-lg"></i> Add Branch</button>
    </div>

    <div class="progga-card" style="overflow-x:auto;">
        <table class="table align-middle mb-0">
            <thead><tr><th>Branch</th><th>Code</th><th>Contact</th><th>Users</th><th>Status</th><th style="width:220px;">Actions</th></tr></thead>
            <tbody>
            @foreach($branches as $branch)
                <tr>
                    <td><strong>{{ $branch->name }}</strong>@if($branch->is_main) <span class="badge bg-primary ms-1">Main</span>@endif<br><small class="text-muted">{{ $branch->address }}</small></td>
                    <td>{{ $branch->code }}<br><small class="text-muted">{{ $branch->slug }}</small></td>
                    <td>{{ $branch->phone ?: '—' }}<br><small>{{ $branch->email }}</small></td>
                    <td>{{ $branch->users_count }}</td>
                    <td><span class="badge {{ $branch->status ? 'bg-success' : 'bg-secondary' }}">{{ $branch->status ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        <button class="progga-btn progga-btn-outline progga-btn-sm" data-bs-toggle="modal" data-bs-target="#editBranch{{ $branch->id }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('branches.status', $branch) }}" class="d-inline">@csrf @method('PATCH')<button class="progga-btn progga-btn-outline progga-btn-sm" {{ $branch->is_main && $branch->status ? 'disabled' : '' }}>{{ $branch->status ? 'Deactivate' : 'Activate' }}</button></form>
                        @if(!$branch->is_main)
                        <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="d-inline" data-swal-title="Delete branch?" data-swal-confirm="This branch can be deleted only when it has no business history." data-swal-confirm-text="Yes, delete">@csrf @method('DELETE')<button class="progga-btn progga-btn-outline progga-btn-sm text-danger"><i class="bi bi-trash"></i></button></form>
                        @endif
                    </td>
                </tr>

                <div class="modal fade" id="editBranch{{ $branch->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('branches.update', $branch) }}">@csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">Edit {{ $branch->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ $branch->name }}" required></div>
                        <div class="row"><div class="col-md-5 mb-3"><label class="form-label">Code</label><input class="form-control" name="code" value="{{ $branch->code }}" required></div><div class="col-md-7 mb-3"><label class="form-label">Slug</label><input class="form-control" name="slug" value="{{ $branch->slug }}" required></div></div>
                        <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ $branch->phone }}"></div><div class="col-md-6 mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ $branch->email }}"></div></div>
                        <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2">{{ $branch->address }}</textarea></div>
                    </div><div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="progga-btn progga-btn-primary">Save</button></div>
                </form></div></div></div>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="addBranchModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('branches.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Branch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Branch Name</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
            <div class="row"><div class="col-md-5 mb-3"><label class="form-label">Code</label><input class="form-control" name="code" value="{{ old('code') }}" placeholder="BR2" required></div><div class="col-md-7 mb-3"><label class="form-label">Slug (optional)</label><input class="form-control" name="slug" value="{{ old('slug') }}" placeholder="branch-two"></div></div>
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone') }}"></div><div class="col-md-6 mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email') }}"></div></div>
            <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2">{{ old('address') }}</textarea></div>
        </div><div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="progga-btn progga-btn-primary">Create Branch</button></div>
    </form></div></div></div>
    @else
        <div class="progga-card" style="padding:20px;"><strong>Main Branch is already active internally.</strong> Existing users, food, tables, orders and HR data remain assigned to Main Branch. Activate Multiple Branch Mode only when this client opens or already has another branch.</div>
    @endif
</main>
@endsection
