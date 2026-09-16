<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FloorZone;
use App\Models\Shift;
use App\Models\User;
use App\Models\Waiter;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class WaiterController extends Controller
{
    public function index(Request $request)
    {
        $totalWaiters = Waiter::count();
        $activeWaiters = Waiter::where('status', 1)->count();
        $inactiveWaiters = Waiter::where('status', 0)->count();

        // Ajax Request for Table (Search & Filter)
        if ($request->ajax()) {
            $query = Waiter::with(['zone', 'shift', 'user'])->orderBy('id', 'desc');

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('employee_id', 'like', '%' . $request->search . '%')
                        ->orWhere('phone', 'like', '%' . $request->search . '%');
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

        // Table Management's Floor / Zone is now the only zone source used by waiters.
        $zones = FloorZone::where('status', 1)->orderBy('name')->get();
        $shifts = Shift::where('status', 1)->orderBy('name')->get();

        // Existing users created directly from User Management can be linked to an
        // unlinked waiter. Only Waiter-role users that are not already linked are shown.
        $linkedUserIds = Waiter::whereNotNull('user_id')->pluck('user_id');
        $waiterUsers = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['waiter']);
            })
            ->when($linkedUserIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedUserIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        return view('admin.waiter.index', compact(
            'zones',
            'shifts',
            'waiterUsers',
            'totalWaiters',
            'activeWaiters',
            'inactiveWaiters'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'zone_id' => 'required|exists:floor_zones,id',
            'shift_id' => 'required|exists:shifts,id',
            'image' => 'nullable|image|max:1024',
        ]);

        DB::beginTransaction();
        try {
            $userId = null;

            // Optional login account. When enabled, email is required and the
            // initial password remains the waiter's phone number as requested.
            if ($request->boolean('create_account')) {
                $request->validate([
                    'email' => 'required|email|unique:users,email',
                ]);

                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->phone),
                ]);

                // Reuse an existing Waiter/waiter role regardless of letter case.
                $role = Role::where('guard_name', 'web')
                    ->whereRaw('LOWER(name) = ?', ['waiter'])
                    ->first();

                if (!$role) {
                    $role = Role::create(['name' => 'Waiter', 'guard_name' => 'web']);
                }

                $user->assignRole($role);
                $userId = $user->id;
            }

            // Auto Generate Employee ID (EMP-001)
            $lastWaiter = Waiter::latest('id')->first();
            $nextId = $lastWaiter ? ($lastWaiter->id + 1) : 1;
            $employeeId = 'EMP-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imageName = 'waiter_' . time() . '.' . $request->image->extension();
                $request->image->move(public_path('uploads/waiters'), $imageName);
                $imagePath = 'uploads/waiters/' . $imageName;
            }

            Waiter::create([
                'user_id' => $userId,
                'zone_id' => $request->zone_id,
                'shift_id' => $request->shift_id,
                'employee_id' => $employeeId,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'image' => $imagePath,
                'join_date' => $request->join_date,
                'notes' => $request->notes,
                'status' => $request->has('status') ? 1 : 0,
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
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'zone_id' => 'required|exists:floor_zones,id',
            'shift_id' => 'required|exists:shifts,id',
            'image' => 'nullable|image|max:1024',
        ]);

        try {
            $waiter = Waiter::findOrFail($id);

            if ($request->hasFile('image')) {
                if ($waiter->image && File::exists(public_path($waiter->image))) {
                    File::delete(public_path($waiter->image));
                }
                $imageName = 'waiter_' . time() . '.' . $request->image->extension();
                $request->image->move(public_path('uploads/waiters'), $imageName);
                $waiter->image = 'uploads/waiters/' . $imageName;
            }

            $waiter->zone_id = $request->zone_id;
            $waiter->shift_id = $request->shift_id;
            $waiter->name = $request->name;
            $waiter->phone = $request->phone;
            $waiter->email = $request->email;
            $waiter->join_date = $request->join_date;
            $waiter->notes = $request->notes;
            $waiter->status = $request->has('status') ? 1 : 0;
            $waiter->save();

            // Keep an already-linked login account in sync with the waiter profile.
            if ($waiter->user_id) {
                User::where('id', $waiter->user_id)->update([
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'email' => $request->email,
                ]);
            }

            return back()->with('success', 'Waiter updated successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to update waiter: ' . $e->getMessage());
        }
    }

    /**
     * Repair old data where a Waiter-role user and a waiter record were created
     * separately. This sets waiters.user_id to the selected users.id.
     */
    public function linkUser(Request $request)
    {
        $request->validate([
            'waiter_id' => 'required|exists:waiters,id',
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $waiter = Waiter::lockForUpdate()->findOrFail($request->waiter_id);
                $user = User::findOrFail($request->user_id);

                $isWaiterUser = $user->roles()
                    ->whereRaw('LOWER(name) = ?', ['waiter'])
                    ->exists();

                if (!$isWaiterUser) {
                    throw new Exception('Selected user does not have the Waiter role.');
                }

                $usedByAnotherWaiter = Waiter::where('user_id', $user->id)
                    ->where('id', '<>', $waiter->id)
                    ->exists();

                if ($usedByAnotherWaiter) {
                    throw new Exception('This user is already linked with another waiter.');
                }

                // Prevent accidental cross-branch links when both records carry branch IDs.
                if ($waiter->branch_id !== null && $user->branch_id !== null
                    && (int) $waiter->branch_id !== (int) $user->branch_id) {
                    throw new Exception('Waiter and user belong to different branches.');
                }

                $waiter->user_id = $user->id;
                $waiter->save();

                // If this waiter is synchronized from HR, persist the same link
                // on the employee too so a later HR edit cannot clear it again.
                if ($waiter->hr_employee_id) {
                    Employee::where('id', $waiter->hr_employee_id)->update([
                        'user_id' => $user->id,
                        'can_login' => true,
                    ]);
                }
            });

            return back()->with('success', 'Waiter login user linked successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Unable to link waiter user: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $waiter = Waiter::findOrFail($id);
            if ($waiter->image && File::exists(public_path($waiter->image))) {
                File::delete(public_path($waiter->image));
            }
            // Delete only the waiter record. A linked user account remains intact.
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
                'message' => 'Waiter status updated successfully!',
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update status.']);
        }
    }
}
