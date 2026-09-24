@extends('admin.master.master')
@section('title','Edit Permission Group')
@section('body')
<main class="progga-content">
<div class="progga-page-header"><h1 class="progga-page-title">Edit Permission Group</h1></div>
<div class="progga-card"><div class="progga-card-body">
<form action="{{ route('permission.group.update', urlencode($groupName)) }}" method="POST">
@csrf @method('PUT')
<div class="progga-form-group">
<label class="progga-form-label">Group Name</label>
<input type="text" name="group_name" value="{{ $groupName }}" class="progga-form-control" required>
</div>
<label class="progga-form-label">Permissions</label>
@foreach($permissions as $permission)
<div class="progga-form-group">
<input type="text" name="permissions[{{ $permission->id }}][name]" value="{{ $permission->name }}" class="progga-form-control" required>
</div>
@endforeach
<button class="progga-btn progga-btn-primary">Update Group</button>
</form>
</div></div>
</main>
@endsection
