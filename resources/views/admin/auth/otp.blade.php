<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP — {{ $restaurantSettingName ?? 'Progga RMS' }}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="{{ asset('/') }}public/admin/assets/css/progga-style.css">
</head>
<body class="progga-auth-page">
  <div class="progga-auth-bg-pattern"></div>

  <div class="progga-auth-card progga-animate-fadein">

    <div class="progga-auth-logo">
      <div class="progga-auth-logo-mark" style="background:var(--progga-secondary);color:var(--progga-primary);">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
      <div class="progga-auth-brand">OTP Verification</div>
    </div>

    <div class="progga-auth-title">Enter Verification Code</div>
    <div class="progga-auth-subtitle">A 6-digit OTP has been sent to your email address. It expires in 10 minutes.</div>

    @if(session('success'))
      <div class="alert alert-success" style="font-size:13px; padding:10px; margin-bottom: 15px; border-radius: var(--progga-radius);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger" style="font-size:13px; padding:10px; margin-bottom: 15px; border-radius: var(--progga-radius);">{{ session('error') }}</div>
    @endif

    <form id="otpForm" action="{{ route('password.otp.verify') }}" method="POST">
      @csrf
      <input type="hidden" name="otp" id="finalOtp">

      <div class="progga-otp-grid" style="margin:28px 0 12px;">
        <input class="progga-otp-input" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input class="progga-otp-input" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input class="progga-otp-input" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input class="progga-otp-input" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input class="progga-otp-input" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
        <input class="progga-otp-input" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
      </div>

      <div style="text-align:center;font-size:12.5px;color:var(--progga-text-muted);margin-bottom:20px;">
        Didn't receive the code?
        <button id="proggaResendBtn" type="button" class="progga-btn progga-btn-outline progga-btn-sm" style="margin-left:8px;" onclick="window.location.href='{{ route('password.request') }}'">
          Resend OTP
        </button>
      </div>

      <button type="submit" class="progga-btn progga-btn-primary" style="width:100%;justify-content:center;" id="verifyBtn">
        <i class="bi bi-check-circle"></i> Verify OTP
      </button>

    </form>

    <div style="text-align:center;margin-top:16px;">
      <a href="{{ route('password.request') }}" class="progga-btn progga-btn-outline progga-btn-sm">
        <i class="bi bi-arrow-left"></i> Back
      </a>
    </div>

  </div>

  <div class="progga-toast-container"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="{{ asset('/') }}public/admin/assets/js/progga-app.js"></script>
  <script>
    const inputs = document.querySelectorAll('.progga-otp-input');

    // Auto focus to next input on typing
    inputs.forEach((input, index) => {
        input.addEventListener('keyup', (e) => {
            if (e.key >= 0 && e.key <= 9) {
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            } else if (e.key === 'Backspace') {
                if (index > 0) {
                    inputs[index - 1].focus();
                }
            }
        });
    });

    // Handle Form Submit
    document.getElementById('otpForm').addEventListener('submit', function (e) {
      const otp = Array.from(inputs).map(function (i) { return i.value; }).join('');

      if (otp.length < 6) {
          e.preventDefault();
          alert('Please enter the complete 6-digit OTP');
          return;
      }
      // Assign value to hidden input
      document.getElementById('finalOtp').value = otp;

      // Update button text while loading
      const btn = document.getElementById('verifyBtn');
      btn.innerHTML = '<i class="bi bi-hourglass-split progga-animate-spin"></i> Verifying...';
    });
  </script>
</body>
</html>
