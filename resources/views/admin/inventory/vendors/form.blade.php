@extends('admin.master.master')
@section('title', $vendor->exists ? 'Edit Vendor' : 'Add Vendor')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $vendor->exists ? 'Edit Vendor' : 'Add Vendor' }}</h1><p class="text-muted mb-0">Maintain supplier contact details for inventory purchases.</p></div>
        <a href="{{ route('inventory.vendors.index') }}" class="progga-btn progga-btn-outline">Back</a>
    </div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="progga-card">
        <form method="POST" action="{{ $vendor->exists ? route('inventory.vendors.update',$vendor) : route('inventory.vendors.store') }}" class="p-4">
            @csrf @if($vendor->exists) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6"><label class="progga-form-label">Vendor Name <span class="progga-required">*</span></label><input name="name" value="{{ old('name',$vendor->name) }}" class="progga-form-control" required></div>
                <div class="col-md-3"><label class="progga-form-label">Phone</label><input name="phone" value="{{ old('phone',$vendor->phone) }}" class="progga-form-control"></div>
                <div class="col-md-3"><label class="progga-form-label">Email</label><input type="email" name="email" value="{{ old('email',$vendor->email) }}" class="progga-form-control"></div>
                <div class="col-12"><label class="progga-form-label">Address</label><textarea name="address" rows="3" class="progga-form-control">{{ old('address',$vendor->address) }}</textarea></div>
                <div class="col-12"><label class="d-flex align-items-center gap-2"><input type="checkbox" name="is_active" value="1" {{ old('is_active',$vendor->exists ? $vendor->is_active : true) ? 'checked' : '' }}> <span>Active vendor</span></label></div>
            </div>
            <div class="mt-4 d-flex gap-2"><button class="progga-btn progga-btn-primary">{{ $vendor->exists ? 'Update Vendor' : 'Save Vendor' }}</button><a href="{{ route('inventory.vendors.index') }}" class="progga-btn progga-btn-outline">Cancel</a></div>
        </form>
    </div>
</main>
@endsection
