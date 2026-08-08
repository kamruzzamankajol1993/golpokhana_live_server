<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Waiter;
use App\Models\Zone;
use App\Models\Shift;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Exception;

class WaiterController extends Controller
{
    public function index(Request $request)
    {
        // Stats Calculation (Active এবং On Duty Now এর কাউন্ট একই রাখা হয়েছে)
        $totalWaiters = Waiter::count();
        $activeWaiters = Waiter::where('status', 1)->count();
        $inactiveWaiters = Waiter::where('status', 0)->count();

        // Ajax Request for Table (Search & Filter)
        if ($request->ajax()) {
            $query = Waiter::with(['zone', 'shift', 'user'])->orderBy('id', 'desc');

            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%')
                      ->orWhere('employee_id', 'like', '%'.$request->search.'%')
                      ->orWhere('phone', 'like', '%'.$request->search.'%');
                });
            }
            if ($request->zone_id) {
                $query->where('zone_id', $request->zone_id);
            }
            if ($request->shift_id) {
                $query->where('shift_id', $request->shift_id);
            }
            if ($request->status != '') {
                $query->where('status', $request->status === 'active' ? 1 : 0);
            }

            $waiters = $query->paginate(10);
            return view('admin.waiter.table', compact('waiters'))->render();
        }

        // Normal View Load
        $zones = Zone::where('status', 1)->get();
        $shifts = Shift::where('status', 1)->get();

        return view('admin.waiter.index', compact(
            'zones', 'shifts', 'totalWaiters', 'activeWaiters', 'inactiveWaiters'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|max:20',
            'zone_id'  => 'required',
            'shift_id' => 'required',
            'image'    => 'nullable|image|max:1024',
        ]);

        DB::beginTransaction();
        try {
            $userId = null;

            // অপশন: অ্যাকাউন্ট ক্রিয়েট করতে চাইলে
            if ($request->has('create_account')) {
                $request->validate([
                    'email' => 'required|email|unique:users,email',
                ]);

                // User Create
                $user = User::create([
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'phone'    => $request->phone,
                    // ডিফল্ট পাসওয়ার্ড হিসেবে ফোন নাম্বার দেওয়া হলো
                    'password' => Hash::make($request->phone),
                ]);

                // Role Assign
                $role = Role::firstOrCreate(['name' => 'waiter']);
                $user->assignRole($role);
                $userId = $user->id;
            }

            // Auto Generate Employee ID (EMP-001)
            $lastWaiter = Waiter::latest('id')->first();
            $nextId = $lastWaiter ? ($lastWaiter->id + 1) : 1;
            $employeeId = 'EMP-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

            // Image Upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imageName = 'waiter_' . time() . '.' . $request->image->extension();
                $request->image->move(public_path('uploads/waiters'), $imageName);
                $imagePath = 'uploads/waiters/' . $imageName;
            }

            // Create Waiter
            Waiter::create([
                'user_id'     => $userId,
                'zone_id'     => $request->zone_id,
                'shift_id'    => $request->shift_id,
                'employee_id' => $employeeId,
                'name'        => $request->name,
                'phone'       => $request->phone,
                'email'       => $request->email,
                'image'       => $imagePath,
                'join_date'   => $request->join_date,
                'notes'       => $request->notes,
                'status'      => $request->has('status') ? 1 : 0,
            ]);

            DB::commit();
            return back()->with('success', 'Waiter added successfully!');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to add waiter: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|max:20',
            'zone_id'  => 'required',
            'shift_id' => 'required',
            'image'    => 'nullable|image|max:1024',
        ]);

        try {
            $waiter = Waiter::findOrFail($id);

            // Image Upload Logic
            if ($request->hasFile('image')) {
                if ($waiter->image && File::exists(public_path($waiter->image))) {
                    File::delete(public_path($waiter->image));
                }
                $imageName = 'waiter_' . time() . '.' . $request->image->extension();
                $request->image->move(public_path('uploads/waiters'), $imageName);
                $waiter->image = 'uploads/waiters/' . $imageName;
            }

            $waiter->zone_id   = $request->zone_id;
            $waiter->shift_id  = $request->shift_id;
            $waiter->name      = $request->name;
            $waiter->phone     = $request->phone;
            $waiter->email     = $request->email;
            $waiter->join_date = $request->join_date;
            $waiter->notes     = $request->notes;
            $waiter->status    = $request->has('status') ? 1 : 0;
            $waiter->save();

            // সংযুক্ত ইউজারের তথ্য আপডেট
            if ($waiter->user_id) {
                User::where('id', $waiter->user_id)->update([
                    'name'  => $request->name,
                    'phone' => $request->phone,
                    'email' => $request->email,
                ]);
            }

            return back()->with('success', 'Waiter updated successfully!');

        } catch (Exception $e) {
            return back()->with('error', 'Failed to update waiter!');
        }
    }

    public function destroy($id)
    {
        try {
            $waiter = Waiter::findOrFail($id);
            if ($waiter->image && File::exists(public_path($waiter->image))) {
                File::delete(public_path($waiter->image));
            }
            // User delete হবে কিনা সেটা আপনার রিকোয়ারমেন্টের উপর নির্ভর করে। আপাতত শুধু ওয়েটার ডিলিট হচ্ছে।
            $waiter->delete();

            return back()->with('success', 'Waiter deleted successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to delete waiter!');
        }
    }

    // Ajax Status Update
    public function updateStatus(Request $request)
    {
        try {
            $waiter = Waiter::findOrFail($request->id);
            $waiter->status = $request->status;
            $waiter->save();

            return response()->json([
                'success' => true,
                'message' => 'Waiter status updated successfully!'
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update status.']);
        }
    }
}
