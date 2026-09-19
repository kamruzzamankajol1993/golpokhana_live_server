<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfflinePosDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OfflinePosDeviceController extends Controller
{
    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->canManageOfflinePosDevices(), 403, 'Offline POS Devices are available only to the full Super Admin.');
    }

    public function index(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $devices = OfflinePosDevice::query()->latest('id')->get();

        return view('admin.offline_pos_devices.index', compact('devices'));
    }

    public function store(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate(['device_name' => ['required', 'string', 'max:255']]);

        $device = OfflinePosDevice::query()->create([
            'device_uuid' => (string) Str::uuid(),
            'device_key' => 'TT-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
            'device_name' => $data['device_name'],
            'status' => true,
        ]);

        return redirect()->route('offline-pos-devices.index')
            ->with('success', 'Offline POS device created. Device ID: ' . $device->device_uuid);
    }

    public function toggle(Request $request, OfflinePosDevice $device)
    {
        $this->authorizeSuperAdmin($request);
        $device->status = !$device->status;
        $device->save();

        return back()->with('success', 'Offline POS device status updated.');
    }

    public function destroy(Request $request, OfflinePosDevice $device)
    {
        $this->authorizeSuperAdmin($request);
        $device->delete();

        return back()->with('success', 'Offline POS device removed.');
    }
}
