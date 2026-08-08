@extends('admin.master.master')
@section('title', 'User Profile — Progga RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">View User Profile</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <a href="{{ route('user.index') }}" class="progga-breadcrumb-item">Users</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">{{ $user->name }}</span>
            </div>
        </div>
        <div>
            <a href="{{ route('user.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="progga-card" style="text-align:center; padding:32px 24px;">
                <div class="progga-profile-avatar-wrapper" style="display:inline-block; margin-bottom:16px;">
                    <img src="{{ $user->image ? asset('public/'.$user->image) : 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=21352a&color=d5aa65&size=200' }}"
                         class="progga-profile-avatar" style="width:160px; height:160px; object-fit:cover; border-radius:50%; border:3px solid var(--progga-border-light);" alt="Avatar">
                </div>
                <h3 style="font-size:18px; font-weight:800; color:var(--progga-primary); margin-bottom:4px;">{{ $user->name }}</h3>
                <div style="margin-bottom:16px;">
                    <span class="progga-badge progga-badge-secondary">
                        <i class="bi bi-shield-fill-check"></i> {{ $user->getRoleNames()->first() }}
                    </span>
                </div>

                <div class="progga-divider"></div>

                <div style="text-align:left; font-size:13px;">
                    <div style="display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--progga-border-light);">
                        <i class="bi bi-hash" style="color:var(--progga-secondary-dark); width:18px;"></i>
                        <span style="font-weight:600;">User ID:</span>
                        <span style="color:var(--progga-text-muted); margin-left:auto;">{{ $user->user_id }}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--progga-border-light);">
                        <i class="bi bi-envelope" style="color:var(--progga-secondary-dark); width:18px;"></i>
                        <span style="color:var(--progga-text-muted);">{{ $user->email }}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--progga-border-light);">
                        <i class="bi bi-telephone" style="color:var(--progga-secondary-dark); width:18px;"></i>
                        <span style="color:var(--progga-text-muted);">{{ $user->phone ?? 'N/A' }}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; padding:10px 0;">
                        <i class="bi bi-calendar3" style="color:var(--progga-secondary-dark); width:18px;"></i>
                        <span style="color:var(--progga-text-muted);">Joined: {{ $user->created_at->format('M d, Y') }}</span>
                    </div>
                </div>

                @can('user-edit')
                <div style="margin-top:20px;">
                    <a href="{{ route('user.edit', $user->id) }}" class="progga-btn progga-btn-primary w-100 justify-content-center">
                        <i class="bi bi-pencil-square"></i> Edit Profile
                    </a>
                </div>
                @endcan
            </div>
        </div>

        <div class="col-xl-8">
            <div class="progga-card mb-4">
                <div class="progga-card-header">
                    <div class="progga-card-title"><i class="bi bi-person-vcard me-2"></i>Account Details</div>
                </div>
                <div class="progga-card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="progga-info-label" style="font-size:12px; color:var(--progga-text-muted); text-uppercase:uppercase; letter-spacing:0.5px; margin-bottom:4px;">First Name</div>
                            <div class="progga-info-value" style="font-weight:700; color:var(--progga-primary);">{{ $user->first_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="progga-info-label" style="font-size:12px; color:var(--progga-text-muted); text-uppercase:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Last Name</div>
                            <div class="progga-info-value" style="font-weight:700; color:var(--progga-primary);">{{ $user->last_name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="progga-info-label" style="font-size:12px; color:var(--progga-text-muted); text-uppercase:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Email Status</div>
                            <div class="progga-info-value">
                                @if($user->email_verified_at)
                                    <span class="progga-badge progga-status-delivered">Verified</span>
                                @else
                                    <span class="progga-badge progga-status-pending">Unverified</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="progga-info-label" style="font-size:12px; color:var(--progga-text-muted); text-uppercase:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Last Activity</div>
                            <div class="progga-info-value" style="font-weight:600;">{{ $user->last_login ? $user->last_login->diffForHumans() : 'No login record' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="progga-card">
                <div class="progga-card-header">
                    <div class="progga-card-title"><i class="bi bi-shield-lock me-2"></i>Permissions & Access</div>
                </div>
                <div class="progga-card-body">
                    <div style="font-size:13px; color:var(--progga-text-muted); margin-bottom:12px;">This user has permissions through the <strong>{{ $user->getRoleNames()->first() }}</strong> role:</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($user->getPermissionsViaRoles() as $permission)
                            <span class="progga-badge progga-badge-secondary" style="font-size:11px;">{{ $permission->name }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
