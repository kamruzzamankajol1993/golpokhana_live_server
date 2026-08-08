<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\File;
use Exception;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:user-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:user-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:user-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:user-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $users = User::with('roles')->orderBy('id', 'desc')->paginate(10);
            if ($request->ajax()) {
                return view('admin.user.table', compact('users'))->render();
            }
            return view('admin.user.index', compact('users'));
        } catch (Exception $e) {
            Log::error('User Index Error: ' . $e->getMessage());
            return back()->with('error', 'Something went wrong!');
        }
    }

    public function create()
    {
        $roles = Role::all();
        return view('admin.user.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8|confirmed',
            'role'       => 'required',
            'image'      => 'nullable|image|max:300',
        ]);

        DB::beginTransaction();
        try {
            // ১. অটো ইউজার আইডি জেনারেট করা (Format: PR-1001)
            $lastUser = User::latest('id')->first();
            $nextId = $lastUser ? ($lastUser->id + 1) : 1;
            $generatedUserId = 'PR-' . (1000 + $nextId);

            $imagePath = $request->hasFile('image') ? $this->uploadImage($request->file('image')) : null;

            $user = User::create([
                'user_id'    => $generatedUserId, // অটো জেনারেটেড আইডি
                'name'       => $request->first_name . ' ' . $request->last_name,
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => Hash::make($request->password),
                'image'      => $imagePath,
            ]);

            $user->assignRole($request->role);

            DB::commit();
            return redirect()->route('user.index')->with('success', 'User created successfully with ID: ' . $generatedUserId);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('User Store Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to create user!')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            return view('admin.user.show', compact('user'));
        } catch (Exception $e) {
            return redirect()->route('user.index')->with('error', 'User not found!');
        }
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::all();
        $userRole = $user->roles->pluck('name')->first();
        return view('admin.user.edit', compact('user', 'roles', 'userRole'));
    }

   public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'first_name' => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $id,
            'role'       => 'required',
            'image'      => 'nullable|image|max:300',
        ]);

        DB::beginTransaction();
        try {
            // ২. যদি user_id না থাকে (null হয়), তবে সেটি জেনারেট করা হচ্ছে
            if (empty($user->user_id)) {
                $user->user_id = 'PR-' . (1000 + $user->id);
                Log::info("Generated missing user_id for User: {$user->id}");
            }

            $user->first_name = $request->first_name;
            $user->last_name  = $request->last_name;
            $user->name       = $request->first_name . ' ' . $request->last_name;
            $user->email      = $request->email;
            $user->phone      = $request->phone;

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            if ($request->hasFile('image')) {
                if ($user->image && File::exists(public_path($user->image))) {
                    File::delete(public_path($user->image));
                }
                $user->image = $this->uploadImage($request->file('image'));
            }

            $user->save();
            $user->syncRoles([$request->role]);

            DB::commit();
            return redirect()->route('user.index')->with('success', 'User updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('User Update Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to update user!');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);
            if ($user->id === auth()->id()) {
                throw new Exception("You cannot delete your own account.");
            }
            if ($user->image && File::exists(public_path($user->image))) {
                File::delete(public_path($user->image));
            }
            $user->delete();
            DB::commit();
            return redirect()->back()->with('success', 'User deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    private function uploadImage($file)
    {
        $imageName = 'user_' . time() . '.' . $file->getClientOriginalExtension();
        $directory = public_path('uploads/users');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        $path = $directory . '/' . $imageName;

        // Image::read ব্যবহার করা হলো
        $image = Image::read($file);
        $image->scale(width: 500);
        $image->save($path, quality: 80);

        return 'uploads/users/' . $imageName;
    }
}
