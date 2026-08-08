<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — {{ $restaurantSettingName ?? 'Progga RMS' }}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="{{ asset('/') }}public/admin/assets/css/progga-style.css">
</head>
<body class="progga-auth-page">
  <div class="progga-auth-bg-pattern"></div>

  <div class="progga-auth-card progga-animate-fadein">

    <div class="progga-auth-logo">
      <div class="progga-auth-logo-mark"><i class="bi bi-key-fill"></i></div>
      <div class="progga-auth-brand">Reset Password</div>
    </div>

    <div class="progga-auth-title">Create New Password</div>
    <div class="progga-auth-subtitle">Your new password must be different from previously used passwords.</div>

    @if(session('error'))
      <div class="alert alert-danger" style="font-size:13px; padding:10px; margin-bottom: 15px; border-radius: var(--progga-radius);">{{ session('error') }}</div>
    @endif

    <form action="{{ route('password.update') }}" method="POST">
      @csrf

      <div class="progga-form-group">
        <label class="progga-form-label" for="newPwd">
          New Password <span class="progga-required">*</span>
        </label>
        <div class="progga-pwd-field">
          <input type="password" id="newPwd" name="password" class="progga-form-control @error('password') is-invalid @enderror"
                 placeholder="Minimum 8 characters" required>
          <button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button>
        </div>
        @error('password')
            <span class="text-danger" style="font-size: 13px; display: block; margin-top: 5px;">{{ $message }}</span>
        @enderror
        <div class="progga-form-hint">Use 8+ characters with uppercase, numbers and symbols for a strong password.</div>
      </div>

      <div class="progga-form-group">
        <label class="progga-form-label" for="confirmPwd">
          Confirm Password <span class="progga-required">*</span>
        </label>
        <div class="progga-pwd-field">
          <input type="password" id="confirmPwd" name="password_confirmation" class="progga-form-control"
                 placeholder="Re-enter your password" required>
          <button class="progga-pwd-toggle" type="button"><i class="bi bi-eye"></i></button>
        </div>
      </div>

      <button type="submit" class="progga-btn progga-btn-primary" style="width:100%;justify-content:center;margin-top:8px;" id="resetBtn">
        <i class="bi bi-lock-fill"></i> Reset Password
      </button>

    </form>

  </div>

  <div class="progga-toast-container"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="{{ asset('/') }}public/admin/assets/js/progga-app.js"></script>
  <script>
    // Password visibility toggle logic
    document.addEventListener("DOMContentLoaded", function() {
        const toggleButtons = document.querySelectorAll('.progga-pwd-toggle');

        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const pwdInput = this.previousElementSibling;
                const icon = this.querySelector('i');

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
