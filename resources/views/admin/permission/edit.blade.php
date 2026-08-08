@extends('admin.master.master')

@section('title', 'Edit Permission — ' . ($restaurantSettingName ?? 'Progga RMS'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">Edit Permission</h1></div>
        <div>
            <a href="{{ route('permission.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="progga-card" style="max-width: 600px;">
        <div class="progga-card-body">
            <form action="{{ route('permission.update', $permission->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="progga-form-group">
                    <label class="progga-form-label">Group Name</label>
                    <input type="text" name="group_name" class="progga-form-control" value="{{ $permission->group_name }}" required>
                </div>
                <div class="progga-form-group">
                    <label class="progga-form-label">Permission Name</label>
                    <input type="text" name="name" class="progga-form-control" value="{{ $permission->name }}" required>
                </div>
                <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Update</button>
            </form>
        </div>
    </div>
</main>
@endsection
