<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:hr-dashboard-view');
    }

    public function index()
    {
        $today = now()->toDateString();
        $activeEmployees = Employee::where('employment_status', 'active')->count();
        $attendanceCounts = Attendance::whereDate('attendance_date', $today)
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $todayRoster = ShiftRoster::with(['employee.department', 'employee.designation', 'shift'])
            ->whereDate('roster_date', $today)
            ->orderByRaw("FIELD(status, 'scheduled', 'leave', 'off')")
            ->limit(10)
            ->get();


        $recentLeaves = LeaveRequest::with(['employee.department', 'leaveType'])
            ->latest('id')
            ->limit(8)
            ->get();

        $chartLabels = [];
        $chartPresent = [];
        $chartAbsent = [];
        $chartLate = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $counts = Attendance::whereDate('attendance_date', $date)
                ->select('status', DB::raw('COUNT(*) AS total'))
                ->groupBy('status')
                ->pluck('total', 'status');
            $chartLabels[] = $date->format('D');
            $chartPresent[] = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);
            $chartAbsent[] = (int) ($counts['absent'] ?? 0);
            $chartLate[] = (int) ($counts['late'] ?? 0);
        }

        return view('admin.hr.dashboard.index', [
            'activeEmployees' => $activeEmployees,
            'presentToday' => (int) ($attendanceCounts['present'] ?? 0) + (int) ($attendanceCounts['late'] ?? 0),
            'absentToday' => (int) ($attendanceCounts['absent'] ?? 0),
            'lateToday' => (int) ($attendanceCounts['late'] ?? 0),
            'onLeaveToday' => LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $today)->whereDate('to_date', '>=', $today)->count(),
            'pendingLeaves' => LeaveRequest::where('status', 'pending')->count(),
            'scheduledToday' => ShiftRoster::whereDate('roster_date', $today)->where('status', 'scheduled')->count(),
            'notMarkedToday' => max(0, $activeEmployees - (int) $attendanceCounts->sum()),
            'todayRoster' => $todayRoster,
            'recentLeaves' => $recentLeaves,
            'chartLabels' => $chartLabels,
            'chartPresent' => $chartPresent,
            'chartAbsent' => $chartAbsent,
            'chartLate' => $chartLate,
        ]);
    }
}
