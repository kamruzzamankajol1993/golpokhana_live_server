<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\TipsoiAttendanceLog;
use App\Models\TipsoiDevice;
use App\Models\TipsoiRemotePerson;
use App\Models\TipsoiSyncHistory;
use App\Services\Hr\TipsoiService;
use App\Services\Hr\TipsoiAttendanceAutoSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class TipsoiController extends Controller
{
    public function __construct(
        private readonly TipsoiService $tipsoiService,
        private readonly TipsoiAttendanceAutoSyncService $attendanceAutoSyncService,
    )
    {
        $this->middleware('permission:hr-setting-update')->only('testConnection');
        $this->middleware('permission:employee-view')->only(['people']);
        $this->middleware('permission:employee-create|employee-edit')->only([
            'pullEmployees', 'pushEmployees', 'pushEmployee', 'refreshPeople',
            'fingerprint', 'startEnrollment', 'stopEnrollment', 'enrollmentStatus',
            'allocations', 'allocate', 'batchAllocation',
        ]);
        $this->middleware('permission:attendance-view')->only(['devices', 'rawLogs', 'syncHistory']);
        $this->middleware('permission:attendance-create|attendance-edit|hr-setting-update')->only([
            'pullAttendance', 'pushAttendance', 'refreshDevices', 'refreshRawLogs',
        ]);
    }

    public function people(Request $request)
    {
        $employees = Employee::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->input('search')) . '%';
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', $search)
                        ->orWhere('employee_code', 'like', $search)
                        ->orWhere('tipsoi_identifier', 'like', $search)
                        ->orWhere('tipsoi_rfid', 'like', $search);
                });
            })
            ->when($request->filled('sync_status'), fn ($query) => $query->where('tipsoi_sync_status', $request->input('sync_status')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $remotePeople = TipsoiRemotePerson::query()
            ->whereNull('employee_id')
            ->when($request->filled('remote_search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->input('remote_search')) . '%';
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', $search)
                        ->orWhere('identifier', 'like', $search)
                        ->orWhere('rfid', 'like', $search)
                        ->orWhere('nid', 'like', $search)
                        ->orWhere('id_in_device', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate(20, ['*'], 'remote_page')
            ->withQueryString();

        if ($request->ajax()) {
            if ($request->input('table') === 'remote') {
                return view('admin.hr.tipsoi.people-remote-table', compact('remotePeople'));
            }
            return view('admin.hr.tipsoi.people-local-table', compact('employees'));
        }

        return view('admin.hr.tipsoi.people', [
            'employees' => $employees,
            'remotePeople' => $remotePeople,
            'remoteTotal' => TipsoiRemotePerson::query()->count(),
            'remoteUnmatchedTotal' => TipsoiRemotePerson::query()->whereNull('employee_id')->count(),
            'attendanceSetting' => AttendanceSetting::first(),
        ]);
    }

    public function refreshPeople(Request $request)
    {
        try {
            $result = $this->tipsoiService->refreshPeopleMapping();
            return $request->expectsJson()
                ? response()->json(['success' => true] + $result)
                : back()->with('success', $result['message']);
        } catch (Throwable $e) {
            report($e);
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage()], 422)
                : back()->with('error', 'Tipsoi people refresh failed. ' . $e->getMessage());
        }
    }

    public function testConnection()
    {
        try {
            return response()->json(['success' => true] + $this->tipsoiService->testConnection());
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function pullEmployees()
    {
        try {
            return response()->json(['success' => true] + $this->tipsoiService->pullEmployees());
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Employee pull failed. ' . $e->getMessage()], 422);
        }
    }

    public function pushEmployees(Request $request)
    {
        try {
            $ids = collect($request->input('employee_ids', []))->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $employees = $ids->isNotEmpty() ? Employee::query()->whereIn('id', $ids)->orderBy('id')->get() : null;
            $result = $this->tipsoiService->pushEmployees($employees);

            return $request->expectsJson()
                ? response()->json(['success' => true] + $result)
                : back()->with(($result['failed'] ?? 0) ? 'warning' : 'success', $result['message']);
        } catch (Throwable $e) {
            report($e);
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Employee push failed. ' . $e->getMessage()], 422)
                : back()->with('error', 'Employee push failed. ' . $e->getMessage());
        }
    }

    public function pushEmployee(Request $request, Employee $employee)
    {
        try {
            $result = $this->tipsoiService->pushEmployee($employee);
            return $request->expectsJson()
                ? response()->json(['success' => true] + $result)
                : back()->with('success', $result['message']);
        } catch (Throwable $e) {
            report($e);
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Employee push failed. ' . $e->getMessage()], 422)
                : back()->with('error', 'Employee push failed. ' . $e->getMessage());
        }
    }

    public function devices(Request $request)
    {
        $devices = TipsoiDevice::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim((string) $request->input('search')) . '%';
                $query->where(function ($sub) use ($search) {
                    $sub->where('identifier', 'like', $search)
                        ->orWhere('location', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('vendor_id', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('connection'), function ($query) use ($request) {
                $connection = (string) $request->input('connection');
                if (in_array($connection, ['online', 'offline'], true)) {
                    $query->where('connected', $connection === 'online');
                }
            })
            ->orderByDesc('synced_at')->orderBy('identifier')->paginate(20)->withQueryString();

        if ($request->ajax()) {
            return view('admin.hr.tipsoi.devices-table', compact('devices'));
        }

        return view('admin.hr.tipsoi.devices', [
            'devices' => $devices,
            'attendanceSetting' => AttendanceSetting::first(),
            'deviceSummary' => [
                'total' => TipsoiDevice::query()->count(),
                'online' => TipsoiDevice::query()->where('connected', true)->count(),
                'offline' => TipsoiDevice::query()->where('connected', false)->count(),
                'enrollment' => TipsoiDevice::query()->where('has_enrollment_feature', true)->count(),
            ],
        ]);
    }

    public function refreshDevices(Request $request)
    {
        try {
            $result = $this->tipsoiService->syncDevices();
            return $request->expectsJson()
                ? response()->json(['success' => true] + $result)
                : back()->with('success', $result['message']);
        } catch (Throwable $e) {
            report($e);
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Device refresh failed. ' . $e->getMessage()], 422)
                : back()->with('error', 'Device refresh failed. ' . $e->getMessage());
        }
    }

    public function fingerprint()
    {
        return view('admin.hr.tipsoi.fingerprint', [
            'employees' => Employee::query()->where('employment_status', 'active')->orderBy('name')->get(),
            'devices' => TipsoiDevice::query()->orderBy('identifier')->get(),
            'attendanceSetting' => AttendanceSetting::first(),
        ]);
    }

    public function startEnrollment(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'device_identifier' => ['required', 'exists:tipsoi_devices,identifier'],
            'hand' => ['required', Rule::in(['left', 'right'])],
            'finger' => ['required', Rule::in(['thumb', 'index', 'middle', 'ring', 'pinky'])],
        ]);

        try {
            $employee = Employee::findOrFail($data['employee_id']);
            if (!$employee->tipsoi_identifier || !$employee->tipsoi_person_id) {
                $this->tipsoiService->pushEmployee($employee);
                $employee->refresh();
            }

            $result = $this->tipsoiService->startEnrollment(
                $data['device_identifier'],
                $employee->tipsoi_identifier ?: $employee->employee_code,
                $data['hand'],
                $data['finger']
            );

            return back()->with('success', $result['message'] ?? 'Fingerprint enrollment started successfully.');
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Unable to start enrollment. ' . $e->getMessage());
        }
    }

    public function stopEnrollment(Request $request)
    {
        $data = $request->validate(['device_identifier' => ['required', 'exists:tipsoi_devices,identifier']]);
        try {
            $result = $this->tipsoiService->stopEnrollment($data['device_identifier']);
            return back()->with('success', $result['message'] ?? 'Fingerprint enrollment stopped.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'Unable to stop enrollment. ' . $e->getMessage());
        }
    }

    public function enrollmentStatus(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'device_identifier' => ['required', 'exists:tipsoi_devices,identifier'],
        ]);

        try {
            $employee = Employee::findOrFail($data['employee_id']);
            $device = TipsoiDevice::where('identifier', $data['device_identifier'])->firstOrFail();
            if (!$employee->tipsoi_person_id || !$device->tipsoi_id) {
                return response()->json(['success' => false, 'message' => 'Sync employee and refresh devices first.'], 422);
            }
            return response()->json([
                'success' => true,
                'data' => $this->tipsoiService->enrollmentStatus($device->tipsoi_id, $employee->tipsoi_person_id),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function allocations()
    {
        return view('admin.hr.tipsoi.allocations', [
            'employees' => Employee::query()->where('employment_status', 'active')->orderBy('name')->get(),
            'devices' => TipsoiDevice::query()->orderBy('identifier')->get(),
            'attendanceSetting' => AttendanceSetting::first(),
        ]);
    }

    public function allocate(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'device_identifier' => ['required', 'exists:tipsoi_devices,identifier'],
            'action' => ['required', Rule::in(['allocate', 'revoke'])],
        ]);

        try {
            $employee = Employee::findOrFail($data['employee_id']);
            if (!$employee->tipsoi_identifier) {
                $this->tipsoiService->pushEmployee($employee);
                $employee->refresh();
            }
            $this->tipsoiService->allocatePerson($data['device_identifier'], $employee->tipsoi_identifier ?: $employee->employee_code, $data['action']);
            return back()->with('success', ucfirst($data['action']) . ' request sent to Tipsoi successfully.');
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Device assignment failed. ' . $e->getMessage());
        }
    }

    public function batchAllocation(Request $request)
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'device_identifiers' => ['required', 'array', 'min:1'],
            'device_identifiers.*' => ['string', 'exists:tipsoi_devices,identifier'],
            'action' => ['required', Rule::in(['allocate', 'revoke'])],
        ]);

        try {
            $employees = Employee::whereIn('id', $data['employee_ids'])->get();
            foreach ($employees as $employee) {
                if (!$employee->tipsoi_identifier) {
                    $this->tipsoiService->pushEmployee($employee);
                    $employee->refresh();
                }
            }
            $this->tipsoiService->batchAllocation(
                $employees->map(fn (Employee $employee) => $employee->tipsoi_identifier ?: $employee->employee_code)->values()->all(),
                $data['device_identifiers'],
                $data['action']
            );
            return back()->with('success', 'Batch ' . $data['action'] . ' request sent to Tipsoi successfully.');
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'Batch device assignment failed. ' . $e->getMessage());
        }
    }

    public function rawLogs(Request $request)
    {
        $logs = TipsoiAttendanceLog::query()
            ->with('employee')
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('logged_time', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('logged_time', '<=', $request->input('to_date')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . trim((string) $request->input('search')) . '%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('person_identifier', 'like', $search)
                        ->orWhere('device_identifier', 'like', $search)
                        ->orWhere('rfid', 'like', $search)
                        ->orWhereHas('employee', fn ($employee) => $employee->where('name', 'like', $search)->orWhere('employee_code', 'like', $search));
                });
            })
            ->latest('logged_time')->paginate(30)->withQueryString();

        if ($request->ajax()) {
            return view('admin.hr.tipsoi.raw-logs-table', compact('logs'));
        }
        return view('admin.hr.tipsoi.raw-logs', compact('logs'));
    }

    public function refreshRawLogs(Request $request)
    {
        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);
        try {
            $result = $this->tipsoiService->syncRawLogs($data['from_date'], $data['to_date']);
            return back()->with('success', $result['message']);
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'Raw punch sync failed. ' . $e->getMessage());
        }
    }

    public function syncHistory(Request $request)
    {
        $histories = TipsoiSyncHistory::query()->with('creator')->latest()->paginate(30)->withQueryString();
        if ($request->ajax()) {
            return view('admin.hr.tipsoi.sync-history-table', compact('histories'));
        }
        return view('admin.hr.tipsoi.sync-history', compact('histories'));
    }

    public function autoSyncAttendance(Request $request)
    {
        try {
            $isLoginSync = $request->boolean('login_sync');
            $isFirstAttendanceVisit = $request->boolean('attendance_page_first_visit');
            $isBackground = $request->boolean('background');

            if ($isLoginSync) {
                abort_unless((bool) session()->pull('tipsoi_login_attendance_sync_pending', false), 403);
            }

            if ($isFirstAttendanceVisit) {
                abort_unless($request->user()?->can('attendance-view'), 403);
                abort_unless((bool) session()->pull('attendance_page_auto_sync_pending', false), 403);
            }

            $result = $this->attendanceAutoSyncService->syncThroughToday(
                forceCurrentMonth: $isLoginSync || $isFirstAttendanceVisit || $request->boolean('force_month'),
                minimumIntervalSeconds: $isBackground ? 25 : 0,
            );

            return response()->json(['success' => true] + $result);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Attendance auto sync failed. ' . $e->getMessage(),
            ], 422);
        }
    }

    public function pullAttendance(Request $request)
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        try {
            return response()->json(['success' => true] + $this->tipsoiService->pullAttendance($validated['from_date'], $validated['to_date']));
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Attendance pull failed. ' . $e->getMessage()], 422);
        }
    }

    public function pushAttendance()
    {
        return response()->json([
            'success' => false,
            'message' => 'Tipsoi Device API V2.4 does not document an attendance write endpoint. Use Attendance Pull instead.',
        ], 422);
    }
}
