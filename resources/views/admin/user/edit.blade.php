@extends('admin.master.master')
@section('title', 'Edit User — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Edit User</h1>
        </div>
        <div>
            <a href="{{ route('user.index') }}" class="progga-btn progga-btn-outline progga-btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <form action="{{ route('user.update', $user->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row g-4">

            <div class="col-xl-4">
                <div class="progga-card" style="text-align:center;padding:32px 24px;">
                    <div class="progga-profile-avatar-wrapper" style="display:inline-block;margin-bottom:16px;">
                        <img src="{{ $user->image ? asset('public/'.$user->image) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=21352a&color=d5aa65&size=200' }}" class="progga-profile-avatar" id="profileAvatarImg" alt="Avatar">
                        <button class="progga-profile-avatar-edit" type="button" id="avatarEditBtn"><i class="bi bi-camera-fill"></i></button>
                        <input type="file" name="image" id="avatarFileInput" accept="image/jpeg, image/png, image/jpg, image/webp" style="display:none;">
                    </div>
                    <h3 style="font-size:16px;font-weight:700;color:var(--progga-primary);margin-bottom:4px;">Update Image</h3>
                    <div style="font-size: 12px; color: var(--progga-text-muted);">Max size: 300 KB. Formats: JPG, PNG, WEBP</div>
                    @error('image') <span class="text-danger d-block mt-2" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="col-xl-8">
                <div class="progga-card">
                    <div class="progga-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">First Name <span class="progga-required">*</span></label>
                                    <input type="text" name="first_name" class="progga-form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $user->first_name) }}" required>
                                    @error('first_name') <span class="text-danger" style="font-size:12px;">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Last Name</label>
                                    <input type="text" name="last_name" class="progga-form-control" value="{{ old('last_name', $user->last_name) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Email Address <span class="progga-required">*</span></label>
                                    <input type="email" name="email" class="progga-form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                                    @error('email') <span class="text-danger" style="font-size:12px;">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Phone Number</label>
                                    <input type="text" name="phone" class="progga-form-control" value="{{ old('phone', $user->phone) }}">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Role <span class="progga-required">*</span></label>
                                    <select name="role" class="progga-form-control progga-select" required>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}" {{ $userRole == $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-12"><hr class="my-2"></div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">New Password <span style="font-weight:400; font-size:11px; color:#888;">(Leave blank if unchanged)</span></label>
                                    <div class="progga-pwd-field">
                                        <input type="password" name="password" class="progga-form-control">
                                        <button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button>
                                    </div>
                                    @error('password') <span class="text-danger" style="font-size:12px;">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Confirm New Password</label>
                                    <div class="progga-pwd-field">
                                        <input type="password" name="password_confirmation" class="progga-form-control">
                                        <button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" class="progga-btn progga-btn-primary w-100 justify-content-center">
                                    <i class="bi bi-check2-circle"></i> Update User
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

        if(file.size > 300 * 1024) {
            window.showToast ? window.showToast('File too large!', 'Max size is 300 KB', 'warning') : alert('Max size is 300 KB');
            this.value = ''; return;
        }

        var reader = new FileReader();
        reader.onload = function(e){
            document.getElementById('profileAvatarImg').src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
</script>
@endsection
