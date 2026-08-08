<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image;
use Exception;

class ProfileController extends Controller
{
    /**
     * প্রোফাইল এডিট পেজ দেখানো
     */
    public function edit()
    {
        $user = Auth::user();
        return view('admin.profile.edit', compact('user'));
    }

    /**
     * প্রোফাইল তথ্য আপডেট করা
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'first_name' => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:300', // ৩০০ KB লিমিট
        ]);

        DB::beginTransaction();
        try {
            $user->first_name = $request->first_name;
            $user->last_name  = $request->last_name;
            $user->name       = $request->first_name . ' ' . $request->last_name;
            $user->email      = $request->email;
            $user->phone      = $request->phone;

            if ($request->hasFile('image')) {
                // পুরোনো ইমেজ ডিলিট করা
                if ($user->image && File::exists(public_path($user->image))) {
                    File::delete(public_path($user->image));
                }
                $user->image = $this->uploadImage($request->file('image'));
            }

            $user->save();
            DB::commit();
            Log::info("User ID {$user->id} updated their profile info.");

            return back()->with('success', 'Profile information updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Profile Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update profile.');
        }
    }

    /**
     * পাসওয়ার্ড পরিবর্তন করা
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'Current password does not match!');
        }

        try {
            $user->password = Hash::make($request->password);
            $user->save();

            Log::info("User ID {$user->id} changed their password.");
            return back()->with('success', 'Password changed successfully!');
        } catch (Exception $e) {
            Log::error('Password Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update password.');
        }
    }

    /**
     * ইমেজ আপলোড লজিক (Intervention Image)
     */
    private function uploadImage($file)
    {
        $imageName = 'profile_' . time() . '.' . $file->getClientOriginalExtension();
        $directory = public_path('uploads/profile');

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory . '/' . $imageName;
        $image = Image::read($file);
        $image->scale(width: 400);
        $image->save($path, quality: 80);

        return 'uploads/profile/' . $imageName;
    }
}
