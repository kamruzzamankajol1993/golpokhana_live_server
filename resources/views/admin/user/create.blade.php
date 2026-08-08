@extends('admin.master.master')
@section('title', 'Create User — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Create User</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <a href="{{ route('user.index') }}" class="progga-breadcrumb-item">Users</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Add New</span>
            </div>
        </div>
        <div>
            <a href="{{ route('user.index') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <form action="{{ route('user.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row g-4">

            <div class="col-xl-4">
                <div class="progga-card" style="text-align:center;padding:32px 24px;">
                    <div class="progga-profile-avatar-wrapper" style="display:inline-block;margin-bottom:16px;">
                        <img src="https://ui-avatars.com/api/?name=New+User&background=21352a&color=d5aa65&size=200" class="progga-profile-avatar" id="profileAvatarImg" alt="Avatar">
                        <button class="progga-profile-avatar-edit" type="button" id="avatarEditBtn"><i class="bi bi-camera-fill"></i></button>
                        <input type="file" name="image" id="avatarFileInput" accept="image/jpeg, image/png, image/jpg, image/webp" style="display:none;">
                    </div>
                    <h3 style="font-size:16px;font-weight:700;color:var(--progga-primary);margin-bottom:4px;">Profile Image</h3>
                    <div style="font-size: 12px; color: var(--progga-text-muted);">Max size: 300 KB. Formats: JPG, PNG, WEBP</div>
                    @error('image') <span class="text-danger d-block mt-2" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="col-xl-8">
                <div class="progga-card">
                    <div class="progga-card-header"><div class="progga-card-title"><i class="bi bi-person me-2"></i>Personal Information</div></div>
                    <div class="progga-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">First Name <span class="progga-required">*</span></label>
                                    <input type="text" name="first_name" class="progga-form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                                    @error('first_name') <span class="text-danger" style="font-size:12px;">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Last Name</label>
                                    <input type="text" name="last_name" class="progga-form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Email Address <span class="progga-required">*</span></label>
                                    <input type="email" name="email" class="progga-form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                                    @error('email') <span class="text-danger" style="font-size:12px;">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Phone Number</label>
                                    <input type="text" name="phone" class="progga-form-control" value="{{ old('phone') }}">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Assign Role <span class="progga-required">*</span></label>
                                    <select name="role" class="progga-form-control progga-select" required>
                                        <option value="">Select a role</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('role') <span class="text-danger" style="font-size:12px;">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Password <span class="progga-required">*</span></label>
                                    <div class="progga-pwd-field">
                                        <input type="password" name="password" class="progga-form-control" required>
                                        <button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button>
                                    </div>
                                    @error('password') <span class="text-danger" style="font-size:12px;">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Confirm Password <span class="progga-required">*</span></label>
                                    <div class="progga-pwd-field">
                                        <input type="password" name="password_confirmation" class="progga-form-control" required>
                                        <button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="progga-btn progga-btn-primary w-100 justify-content-center">
                                    <i class="bi bi-save"></i> Save User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</main>
@endsection

@section('script')
<script>
    document.getElementById('avatarEditBtn').addEventListener('click', function(){
        document.getElementById('avatarFileInput').click();
    });

    document.getElementById('avatarFileInput').addEventListener('change', function(){
        var file = this.files[0];
        if (!file) return;

        // 300 KB Size Validation
        if(file.size > 300 * 1024) {
            if(typeof window.showToast === 'function') {
                window.showToast('File too large!', 'Image size must be less than 300 KB', 'warning');
            } else {
                alert('Image size must be less than 300 KB');
            }
            this.value = ''; // Input clear
            return;
        }

        var reader = new FileReader();
        reader.onload = function(e){
            document.getElementById('profileAvatarImg').src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
</script>
@endsection
