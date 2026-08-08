<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password OTP</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f5; margin: 0; padding: 0;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6f5; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;">

                    <tr>
                        <td align="center" style="background-color: #21352a; padding: 30px 20px;">
                            <h1 style="color: #d5aa65; margin: 0; font-size: 28px; letter-spacing: 1px;">Progga RMS</h1>
                            <p style="color: #a0aab2; margin: 5px 0 0; font-size: 14px;">Restaurant Management System</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px 30px; color: #333333;">
                            <h2 style="margin-top: 0; font-size: 20px; color: #21352a;">Password Reset Request</h2>
                            <p style="font-size: 15px; line-height: 1.6; color: #555555;">
                                Hello,<br><br>
                                We received a request to reset the password for your Progga RMS account. Please use the following 6-digit OTP to proceed with resetting your password.
                            </p>

                            <div style="text-align: center; margin: 30px 0;">
                                <span style="display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #21352a; background-color: #f8f9fa; padding: 15px 30px; border: 2px dashed #d5aa65; border-radius: 6px;">
                                    {{ $otp }}
                                </span>
                            </div>

                            <p style="font-size: 14px; line-height: 1.6; color: #777777;">
                                <strong>Note:</strong> This OTP is valid for the next 10 minutes. Please do not share this code with anyone.
                            </p>
                            <p style="font-size: 14px; line-height: 1.6; color: #777777; margin-bottom: 0;">
                                If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="background-color: #f8f9fa; padding: 20px; border-top: 1px solid #eeeeee;">
                            <p style="margin: 0; font-size: 12px; color: #888888;">
                                &copy; {{ date('Y') }} Progga RMS. All rights reserved.<br>
                                This is an automated message, please do not reply.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
