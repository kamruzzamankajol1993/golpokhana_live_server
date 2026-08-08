<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Login — {{ $restaurantSettingName ?? 'Progga RMS' }}</title>
  <meta name="title" content="Login — Progga RMS">
  <meta name="description" content="Sign in to Progga Restaurant Management System to manage your orders, kitchen, and analytics.">
  <meta name="keywords" content="restaurant management, pos, kitchen board, progga rms">
  <meta name="author" content="Progga RMS">

  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:title" content="Login — Progga RMS">
  <meta property="og:description" content="Sign in to Progga Restaurant Management System.">
  <meta property="og:image" content="{{ asset('public/'.$restaurantSettingLogo) }}"> <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="{{ url()->current() }}">
  <meta property="twitter:title" content="Login — Progga RMS">
  <meta property="twitter:description" content="Sign in to Progga Restaurant Management System.">
  <meta property="twitter:image" content="{{ asset('public/'.$restaurantSettingLogo) }}">
  <link rel="icon" type="image/x-icon" href="{{ asset('public/'.$restaurantSettingIconName) }}">
  <link rel="apple-touch-icon" href="{{ asset('public/'.$restaurantSettingIconName) }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="{{ asset('/') }}public/admin/assets/css/progga-style.css">
</head>
<body class="progga-auth-page">
  <div class="progga-auth-bg-pattern"></div>

  <div class="progga-auth-card progga-animate-fadein">

    <div class="progga-auth-logo" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; margin-bottom: 24px;">
  @if(!empty($restaurantSettingIconName))
      <img src="{{ asset('public/'.$restaurantSettingIconName) }}" alt="Icon" style="max-width: 120px; max-height: 80px; object-fit: contain; margin-bottom: 12px;">
  @else
      <div class="progga-auth-logo-mark" style="margin-bottom: 12px;">
          {{ strtoupper(substr($restaurantSettingName ?? 'P', 0, 1)) }}
      </div>
  @endif

  <div class="progga-auth-brand" style="margin-bottom: 4px;">{{ $restaurantSettingName ?? 'Progga RMS' }}</div>
  <div class="progga-auth-tagline">Restaurant Management System</div>
</div>
    <div class="progga-auth-title">Welcome back</div>
    <div class="progga-auth-subtitle">Sign in to your account to continue</div>

    <form method="POST" action="{{ route('login') }}">
      @csrf

      <div class="progga-form-group">
        <label class="progga-form-label" for="loginEmail">
          Email Address <span class="progga-required">*</span>
        </label>
        <div class="progga-input-group">
          <input type="email" id="loginEmail" name="email" class="progga-form-control @error('email') is-invalid @enderror"
                 value="{{ old('email') }}" placeholder="admin@progga.com" required autocomplete="email" autofocus>
          <span class="progga-input-addon"><i class="bi bi-envelope"></i></span>
        </div>
        @error('email')
            <span class="text-danger" style="font-size: 12px;">{{ $message }}</span>
        @enderror
      </div>

      <div class="progga-form-group">
        <label class="progga-form-label" for="loginPassword">
          Password <span class="progga-required">*</span>
        </label>
        <div class="progga-pwd-field">
          <input type="password" id="loginPassword" name="password" class="progga-form-control @error('password') is-invalid @enderror"
                 placeholder="Enter your password" required autocomplete="current-password">
          <button class="progga-pwd-toggle" type="button" aria-label="Toggle password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        @error('password')
            <span class="text-danger" style="font-size: 12px;">{{ $message }}</span>
        @enderror
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <label class="progga-toggle" style="gap:8px; display:flex; align-items:center;">
          {{-- <input type="checkbox" name="remember" id="rememberMe" {{ old('remember') ? 'checked' : '' }}>
          <span style="font-size: 14px; color: var(--progga-text-muted);">Remember me</span> --}}
        </label>

        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}" style="font-size:13px;color:var(--progga-secondary-dark);font-weight:600;">Forgot password?</a>
        @endif
      </div>

      <button type="submit" class="progga-btn progga-btn-primary" style="width:100%;justify-content:center;" id="loginBtn">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </button>

    </form>
  </div>

  <div class="progga-toast-container"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  {{-- <script src="{{ asset('/') }}public/admin/assets/js/progga-app.js"></script> --}}
 <script>
  // DOM পুরোপুরি লোড হওয়ার পর স্ক্রিপ্ট রান করবে
  document.addEventListener("DOMContentLoaded", function() {
      const toggleButtons = document.querySelectorAll('.progga-pwd-toggle');

      toggleButtons.forEach(button => {
          button.addEventListener('click', function() {
              // বাটনের আগের এলিমেন্ট অর্থাৎ ইনপুট ফিল্ডটিকে ধরবে
              const pwdInput = this.previousElementSibling;
              const icon = this.querySelector('i');

              // টাইপ চেক করে পাসওয়ার্ড শো অথবা হাইড করবে
              if (pwdInput.type === 'password') {
                  pwdInput.type = 'text';
                  icon.classList.remove('bi-eye');
                  icon.classList.add('bi-eye-slash');
              } else {
                  pwdInput.type = 'password';
                  icon.classList.remove('bi-eye-slash');
                  icon.classList.add('bi-eye');
              }
          });
      });
  });
</script>
</body>
</html>
