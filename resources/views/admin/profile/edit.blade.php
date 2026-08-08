@extends('admin.master.master')
@section('title', 'My Profile — ' . ($restaurantSettingName ?? 'TableTrack RMS'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">My Profile</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Profile Settings</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="font-size:13px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="font-size:13px;">{{ session('error') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="progga-card" style="text-align:center; padding:32px 24px;">
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" id="profileImageForm">
                    @csrf
                    @method('PUT')
                    <div class="progga-profile-avatar-wrapper" style="display:inline-block; margin-bottom:16px;">
                        <img src="{{ $user->image ? asset('public/'.$user->image) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=21352a&color=d5aa65&size=200' }}"
                             class="progga-profile-avatar" id="profileAvatarImg" alt="Avatar">
                        <button class="progga-profile-avatar-edit" type="button" id="avatarEditBtn"><i class="bi bi-camera-fill"></i></button>
                        <input type="file" name="image" id="avatarFileInput" accept="image/*" style="display:none;">
                    </div>
                    <h3 style="font-size:18px; font-weight:800; color:var(--progga-primary); margin-bottom:4px;">{{ $user->name }}</h3>
                    <div style="margin-bottom:16px;">
                        <span class="progga-badge progga-badge-secondary">
                            <i class="bi bi-shield-fill-check"></i> {{ $user->getRoleNames()->first() }}
                        </span>
                    </div>
                    @error('image') <div class="text-danger mb-2" style="font-size:12px;">{{ $message }}</div> @enderror
                </form>

                <div class="progga-divider"></div>
                <div style="text-align:left; font-size:13px;">
                    <div style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--progga-border-light);">
                        <i class="bi bi-hash" style="color:var(--progga-secondary-dark); width:18px;"></i>
                        <span style="color:var(--progga-text-muted);">ID: {{ $user->user_id }}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--progga-border-light);">
                        <i class="bi bi-envelope" style="color:var(--progga-secondary-dark); width:18px;"></i>
                        <span style="color:var(--progga-text-muted);">{{ $user->email }}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:8px 0;">
                        <i class="bi bi-calendar3" style="color:var(--progga-secondary-dark); width:18px;"></i>
                        <span style="color:var(--progga-text-muted);">Joined {{ $user->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="progga-card" style="margin-bottom:20px;">
                <div class="progga-card-header"><div class="progga-card-title"><i class="bi bi-person me-2"></i>Personal Information</div></div>
                <div class="progga-card-body">
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">First Name <span class="progga-required">*</span></label>
                                    <input type="text" name="first_name" class="progga-form-control" value="{{ old('first_name', $user->first_name) }}" required>
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
                                    <input type="email" name="email" class="progga-form-control" value="{{ old('email', $user->email) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Phone Number</label>
                                    <input type="text" name="phone" class="progga-form-control" value="{{ old('phone', $user->phone) }}">
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="progga-btn progga-btn-primary">
                                    <i class="bi bi-check-lg"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="progga-card">
                <div class="progga-card-header"><div class="progga-card-title"><i class="bi bi-lock me-2"></i>Change Password</div></div>
                <div class="progga-card-body">
                    <form action="{{ route('profile.password.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Current Password <span class="progga-required">*</span></label>
                                    <div class="progga-pwd-field"><input type="password" name="current_password" class="progga-form-control" required><button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">New Password <span class="progga-required">*</span></label>
                                    <div class="progga-pwd-field"><input type="password" name="password" class="progga-form-control" required><button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="progga-form-group">
                                    <label class="progga-form-label">Confirm New Password <span class="progga-required">*</span></label>
                                    <div class="progga-pwd-field"><input type="password" name="password_confirmation" class="progga-form-control" required><button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button></div>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="progga-btn progga-btn-primary">
                                    <i class="bi bi-lock-fill"></i> Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
    // ইমেজ প্রিভিউ এবং ৩০০০ কেবি ভ্যালিডেশন
    document.getElementById('avatarEditBtn').addEventListener('click', function(){
        document.getElementById('avatarFileInput').click();
    });

    document.getElementById('avatarFileInput').addEventListener('change', function(){
        var file = this.files[0];
        if (!file) return;

        if(file.size > 300 * 1024) {
            window.showToast ? window.showToast('File too large!', 'Max size is 300 KB', 'warning') : alert('Max size 300 KB');
            this.value = '';
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
