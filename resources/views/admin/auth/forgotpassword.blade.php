<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — {{ $restaurantSettingName ?? 'Progga RMS' }}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="{{ asset('/') }}public/admin/assets/css/progga-style.css">
</head>
<body class="progga-auth-page">
  <div class="progga-auth-bg-pattern"></div>

  <div class="progga-auth-card progga-animate-fadein">

    <div class="progga-auth-logo">
      <div class="progga-auth-logo-mark">P</div>
      <div class="progga-auth-brand">{{ $restaurantSettingName ?? 'Progga RMS' }}</div>
    </div>

    <div class="progga-auth-title">Forgot Password?</div>
    <div class="progga-auth-subtitle">Enter your email and we'll send a 6-digit OTP to reset your password.</div>

    @if(session('success'))
      <div class="alert alert-success" style="font-size:13px; padding:10px; margin-bottom: 15px; border-radius: var(--progga-radius);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger" style="font-size:13px; padding:10px; margin-bottom: 15px; border-radius: var(--progga-radius);">{{ session('error') }}</div>
    @endif

    <form action="{{ route('password.email') }}" method="POST">
      @csrf
      <div class="progga-form-group">
        <label class="progga-form-label" for="forgotEmail">
          Email Address <span class="progga-required">*</span>
        </label>
        <div class="progga-input-group">
          <input type="email" id="forgotEmail" name="email" class="progga-form-control @error('email') is-invalid @enderror"
                 placeholder="Enter your registered email" value="{{ old('email') }}" required>
          <span class="progga-input-addon"><i class="bi bi-envelope"></i></span>
        </div>
        @error('email')
            <span class="text-danger" style="font-size: 13px;">{{ $message }}</span>
        @enderror
      </div>

      <button type="submit" class="progga-btn progga-btn-primary" style="width:100%;justify-content:center;" id="sendOtpBtn">
        <i class="bi bi-send"></i> Send OTP
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;">
      <a href="{{ route('admin.login') }}" class="progga-btn progga-btn-outline progga-btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Login
      </a>
    </div>

  </div>

  <div class="progga-toast-container"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="{{ asset('/') }}public/admin/assets/js/progga-app.js"></script>
</body>
</html>
