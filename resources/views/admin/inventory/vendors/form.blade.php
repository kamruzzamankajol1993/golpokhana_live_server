@extends('admin.master.master')
@section('title', $vendor->exists ? 'Edit Vendor' : 'Add Vendor')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">{{ $vendor->exists ? 'Edit Vendor' : 'Add Vendor' }}</h1>
            <p class="text-muted mb-0">Maintain supplier contact, compliance and document details for inventory purchasing.</p>
        </div>
        <a href="{{ $vendor->exists ? route('inventory.vendors.show',$vendor) : route('inventory.vendors.index') }}" class="progga-btn progga-btn-outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="progga-card">
        <form method="POST" enctype="multipart/form-data" action="{{ $vendor->exists ? route('inventory.vendors.update',$vendor) : route('inventory.vendors.store') }}" class="p-4">
            @csrf @if($vendor->exists) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="progga-form-label">Vendor Name <span class="progga-required">*</span></label>
                    <input name="name" value="{{ old('name',$vendor->name) }}" class="progga-form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="progga-form-label">Phone</label>
                    <input name="phone" value="{{ old('phone',$vendor->phone) }}" class="progga-form-control">
                </div>
                <div class="col-md-3">
                    <label class="progga-form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email',$vendor->email) }}" class="progga-form-control">
                </div>
                <div class="col-12">
                    <label class="progga-form-label">Address</label>
                    <textarea name="address" rows="3" class="progga-form-control">{{ old('address',$vendor->address) }}</textarea>
                </div>
            </div>

            <div class="border-top my-4"></div>
            <div class="mb-3">
                <h5 class="mb-1">Compliance & Documents</h5>
                <div class="text-muted small">TIN No, BIN No and Others support both text/reference and document upload. TDS Percentage and VDS Percentage are text fields.</div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="progga-form-label">TIN No</label>
                    <input type="text" name="tin" value="{{ old('tin',$vendor->tin) }}" class="progga-form-control">
                </div>
                <div class="col-md-8">
                    <label class="progga-form-label">TIN File</label>
                    <input type="file" name="tin_file" class="progga-form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                    @if($vendor->exists && $vendor->tin_file_path)
                        <small class="d-block mt-1">Current: <a href="{{ route('inventory.vendors.document',[$vendor,'tin']) }}">{{ $vendor->tin_file_name ?: 'TIN document' }}</a></small>
                    @endif
                </div>

                <div class="col-md-4">
                    <label class="progga-form-label">BIN No</label>
                    <input type="text" name="bin" value="{{ old('bin',$vendor->bin) }}" class="progga-form-control">
                </div>
                <div class="col-md-8">
                    <label class="progga-form-label">BIN File</label>
                    <input type="file" name="bin_file" class="progga-form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                    @if($vendor->exists && $vendor->bin_file_path)
                        <small class="d-block mt-1">Current: <a href="{{ route('inventory.vendors.document',[$vendor,'bin']) }}">{{ $vendor->bin_file_name ?: 'BIN document' }}</a></small>
                    @endif
                </div>

                <div class="col-md-4">
                    <label class="progga-form-label">TDS Percentage</label>
                    <input type="text" name="tds" value="{{ old('tds',$vendor->tds) }}" class="progga-form-control">
                </div>
                <div class="col-md-4">
                    <label class="progga-form-label">VDS Percentage</label>
                    <input type="text" name="vds" value="{{ old('vds',$vendor->vds) }}" class="progga-form-control">
                </div>
                <div class="col-md-4">
                    <label class="progga-form-label">Others</label>
                    <input type="text" name="tax" value="{{ old('tax',$vendor->tax) }}" class="progga-form-control">
                </div>
                <div class="col-md-8 offset-md-4">
                    <label class="progga-form-label">Others Upload</label>
                    <input type="file" name="tax_file" class="progga-form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                    @if($vendor->exists && $vendor->tax_file_path)
                        <small class="d-block mt-1">Current: <a href="{{ route('inventory.vendors.document',[$vendor,'tax']) }}">{{ $vendor->tax_file_name ?: 'Others document' }}</a></small>
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <label class="d-flex align-items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active',$vendor->exists ? $vendor->is_active : true) ? 'checked' : '' }}>
                    <span>Active vendor</span>
                </label>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="progga-btn progga-btn-primary">{{ $vendor->exists ? 'Update Vendor' : 'Save Vendor' }}</button>
                <a href="{{ $vendor->exists ? route('inventory.vendors.show',$vendor) : route('inventory.vendors.index') }}" class="progga-btn progga-btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</main>
@endsection
