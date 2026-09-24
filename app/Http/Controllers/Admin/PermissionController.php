<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:permission-view', ['only' => ['index']]);
        $this->middleware('permission:permission-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:permission-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:permission-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        try {
            $query = Permission::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('group_name', 'like', '%' . $search . '%');
                });
            }

            $permissions = $query->orderBy('group_name')->orderBy('id', 'desc')->paginate(10)->withQueryString();

            if ($request->ajax()) {
                return view('admin.permission.table', compact('permissions'))->render();
            }

            return view('admin.permission.index', compact('permissions'));
        } catch (Exception $e) {
            Log::error('Permission Index Error: ' . $e->getMessage());
            return back()->with('error', 'Something went wrong while fetching permissions!');
        }
    }

    public function create()
    {
        return view('admin.permission.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'group_name' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*' => 'required|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->permissions as $permissionName) {
                $permissionName = trim($permissionName);

                if (!empty($permissionName)) {
                    Permission::firstOrCreate(
                        ['name' => $permissionName, 'guard_name' => 'web'],
                        ['group_name' => $request->group_name]
                    );
                }
            }

            DB::commit();
            Log::info('Permissions created successfully by user ID: ' . auth()->id(), ['group' => $request->group_name]);

            return redirect()->route('permission.index')->with('success', 'Permissions added successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Permission Store Error: ' . $e->getMessage());

            return back()->with('error', 'Failed to create permissions. Please check logs for details.');
        }
    }

    public function edit($id)
    {
        try {
            $permission = Permission::findOrFail($id);
            return view('admin.permission.edit', compact('permission'));
        } catch (Exception $e) {
            Log::error('Permission Edit Error: ' . $e->getMessage());
            return redirect()->route('permission.index')->with('error', 'Permission not found!');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,' . $id,
            'group_name' => 'required|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            $permission = Permission::findOrFail($id);
            $permission->update([
                'name' => $request->name,
                'group_name' => $request->group_name
            ]);

            DB::commit();
            Log::info('Permission updated successfully.', ['permission_id' => $id, 'new_name' => $request->name]);

            return redirect()->route('permission.index')->with('success', 'Permission updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Permission Update Error: ' . $e->getMessage());

            return back()->with('error', 'Failed to update permission. Try again!');
        }
    }

    public function editGroup($groupName)
    {
        $permissions = Permission::where('group_name', $groupName)->get();
        return view('admin.permission.edit_group', compact('permissions', 'groupName'));
    }

    public function updateGroup(Request $request, $groupName)
    {
        $request->validate([
            'group_name' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*.name' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->permissions as $id => $data) {
                Permission::where('id', $id)->update([
                    'name' => $data['name'],
                    'group_name' => $request->group_name,
                ]);
            }
            DB::commit();
            return redirect()->route('permission.index')->with('success', 'Permission group updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update permission group!');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $permission = Permission::findOrFail($id);
            $permissionName = $permission->name;
            $permission->delete();

            DB::commit();
            Log::info("Permission '{$permissionName}' deleted successfully by user ID: " . auth()->id());

            return redirect()->back()->with('success', 'Permission deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Permission Delete Error: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Failed to delete permission! It might be in use.');
        }
    }
}
