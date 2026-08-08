<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:role-view', ['only' => ['index']]);
        $this->middleware('permission:role-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:role-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:role-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $roles = Role::orderBy('id', 'desc')->paginate(10);

            if ($request->ajax()) {
                return view('admin.role.table', compact('roles'))->render();
            }

            return view('admin.role.index', compact('roles'));
        } catch (Exception $e) {
            Log::error('Role Index Error: ' . $e->getMessage());
            return back()->with('error', 'Something went wrong while fetching roles!');
        }
    }

    public function create()
    {
        try {
            $permissions = Permission::all()->groupBy('group_name');
            return view('admin.role.create', compact('permissions'));
        } catch (Exception $e) {
            Log::error('Role Create Page Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load permissions!');
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'required|array'
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

            // Convert string IDs to integers and sync permissions
            $permissions = array_map('intval', $request->permissions);
            $role->syncPermissions($permissions);

            DB::commit();
            Log::info("Role '{$role->name}' created and permissions synced.", ['role_id' => $role->id, 'user_id' => auth()->id()]);

            return redirect()->route('role.index')->with('success', 'Role created successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Role Store Error: ' . $e->getMessage());

            return back()->with('error', 'Failed to create role. Please try again!');
        }
    }

    public function edit($id)
    {
        try {
            $role = Role::findOrFail($id);
            $permissions = Permission::all()->groupBy('group_name');

            // Get role's existing permission IDs
            $rolePermissions = DB::table("role_has_permissions")
                ->where("role_id", $id)
                ->pluck('permission_id', 'permission_id')
                ->all();

            return view('admin.role.edit', compact('role', 'permissions', 'rolePermissions'));
        } catch (Exception $e) {
            Log::error('Role Edit Page Error: ' . $e->getMessage());
            return redirect()->route('role.index')->with('error', 'Role not found!');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|unique:roles,name,' . $id,
            'permissions' => 'required|array'
        ]);

        DB::beginTransaction();
        try {
            $role = Role::findOrFail($id);
            $role->name = $request->name;
            $role->save();

            // Convert string IDs to integers and sync permissions
            $permissions = array_map('intval', $request->permissions);
            $role->syncPermissions($permissions);

            DB::commit();
            Log::info("Role '{$role->name}' updated successfully.", ['role_id' => $role->id, 'user_id' => auth()->id()]);

            return redirect()->route('role.index')->with('success', 'Role updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Role Update Error: ' . $e->getMessage());

            return back()->with('error', 'Failed to update role. Please check logs!');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $role = Role::findOrFail($id);

            // Super Admin ডিলেট করা আটকানোর জন্য একটি এক্সট্রা সিকিউরিটি
            if ($role->name === 'Super Admin') {
                throw new Exception("Super Admin role cannot be deleted.");
            }

            $roleName = $role->name;
            $role->delete();

            DB::commit();
            Log::info("Role '{$roleName}' deleted successfully by user ID: " . auth()->id());

            return redirect()->back()->with('success', 'Role deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Role Delete Error: ' . $e->getMessage());

            return redirect()->back()->with('error', $e->getMessage() == "Super Admin role cannot be deleted." ? $e->getMessage() : 'Failed to delete role!');
        }
    }
}
