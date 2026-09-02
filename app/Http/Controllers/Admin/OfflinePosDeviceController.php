<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfflinePosDevice;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OfflinePosDeviceController extends Controller
{
    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->canManageOfflinePosDevices(), 403, 'Offline POS Devices are available only to the full Super Admin.');
    }

    public function index(Request $request, BranchContext $context)
    {
        $this->authorizeSuperAdmin($request);
        $branchId = $context->branchId();
        $devices = OfflinePosDevice::query()
            ->with('branch')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->latest('id')
            ->get();

        return view('admin.offline_pos_devices.index', compact('devices', 'branchId'));
    }

    public function store(Request $request, BranchContext $context)
    {
        $this->authorizeSuperAdmin($request);
        $branchId = $context->requireSpecificBranch();
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $device = OfflinePosDevice::query()->create([
            'device_uuid' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'name' => $data['name'],
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('offline-pos-devices.index')
            ->with('success', 'Offline POS device created. Device ID: ' . $device->device_uuid);
    }

    public function toggle(Request $request, BranchContext $context, OfflinePosDevice $device)
    {
        $this->authorizeSuperAdmin($request);
        $branchId = $context->requireSpecificBranch();
        abort_unless((int) $device->branch_id === $branchId, 404);

        $device->is_active = !$device->is_active;
        $device->save();

        return back()->with('success', 'Offline POS device status updated.');
    }

    public function destroy(Request $request, BranchContext $context, OfflinePosDevice $device)
    {
        $this->authorizeSuperAdmin($request);
        $branchId = $context->requireSpecificBranch();
        abort_unless((int) $device->branch_id === $branchId, 404);
        $device->delete();

        return back()->with('success', 'Offline POS device removed.');
    }
}
