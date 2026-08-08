<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;

class CustomResetPasswordController extends Controller
{
    // ১. Forgot Password পেজ দেখানো
    public function showForgotForm()
    {
        return view('admin.auth.forgotpassword');
    }

    // ২. ইমেইল চেক করা এবং OTP পাঠানো
    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        // ৬ ডিজিটের র‍্যান্ডম OTP জেনারেট করা
        $otp = rand(100000, 999999);

        // Cache-এ ১০ মিনিটের জন্য OTP সেভ রাখা
        Cache::put('otp_' . $request->email, $otp, now()->addMinutes(10));

        // ইউজারের ইমেইলটি Session এ রাখা (পরের পেজগুলোর জন্য)
        Session::put('reset_email', $request->email);

        // ইমেইল সেন্ড করা
        try {
            Mail::to($request->email)->send(new SendOtpMail($otp));
            Session::flash('success', 'OTP has been sent to your email successfully!');
        } catch (\Exception $e) {
            // যদি ইমেইল সেন্ড হতে কোনো এরর হয়
            return back()->with('error', 'Failed to send OTP. Please check your mail configuration. Error: ' . $e->getMessage());
        }

        return redirect()->route('password.otp');
    }

    // ৩. OTP ভেরিফিকেশন পেজ দেখানো
    public function showOtpForm()
    {
        if (!Session::has('reset_email')) {
            return redirect()->route('password.request')->with('error', 'Session expired. Try again.');
        }
        return view('admin.auth.otp');
    }

    // ৪. OTP ভেরিফাই করা
    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => 'required|numeric|digits:6']);

        $email = Session::get('reset_email');
        $cachedOtp = Cache::get('otp_' . $email);

        if ($cachedOtp && $cachedOtp == $request->otp) {
            Session::put('otp_verified', true);
            Cache::forget('otp_' . $email);
            return redirect()->route('password.resetForm');
        }

        return back()->with('error', 'Invalid or expired OTP!');
    }

    // ৫. রিসেট পাসওয়ার্ড পেজ দেখানো
    public function showResetForm()
    {
        if (!Session::has('otp_verified') || !Session::has('reset_email')) {
            return redirect()->route('password.request')->with('error', 'Unauthorized access.');
        }
        return view('admin.auth.resetpassword');
    }

    // ৬. নতুন পাসওয়ার্ড সেভ করা
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $email = Session::get('reset_email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();

            Session::forget(['reset_email', 'otp_verified']);

            return redirect()->route('admin.login')->with('success', 'Password reset successful! Please login with your new password.');
        }

        return redirect()->route('password.request')->with('error', 'Something went wrong.');
    }
}
