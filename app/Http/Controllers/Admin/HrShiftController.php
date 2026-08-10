<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class HrShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:shift-view')->only(['index', 'shiftTable', 'rosterTable']);
        $this->middleware('permission:shift-create')->only('store');
        $this->middleware('permission:shift-edit')->only(['update', 'updateStatus']);
        $this->middleware('permission:shift-delete')->only('destroy');
        $this->middleware('permission:shift-roster-manage')->only(['saveRoster', 'copyPreviousWeek']);
    }

    public function index(Request $request)
    {
        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->week_start)->startOfWeek(Carbon::SATURDAY)
            : now()->startOfWeek(Carbon::SATURDAY);

        return view('admin.hr.shifts.index', [
            'activeShifts' => Shift::where('status', true)->count(),
            'totalShifts' => Shift::count(),
            'scheduledToday' => ShiftRoster::whereDate('roster_date', today())->where('status', 'scheduled')->count(),
            'offToday' => ShiftRoster::whereDate('roster_date', today())->where('status', 'off')->count(),
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shifts' => Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekStart->copy()->addDays(6)->toDateString(),
        ]);
    }

    public function shiftTable(Request $request)
    {
        $query = Shift::withCount(['employees', 'rosters'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status === 'active');
        }

        $shifts = $query->paginate(10)->withQueryString();
        return view('admin.hr.shifts.shift_table', compact('shifts'))->render();
    }

    public function store(Request $request)
    {
        $validated = $this->validateShift($request);
        $validated['is_overnight'] = $request->boolean('is_overnight') || $validated['end_time'] <= $validated['start_time'];
        $validated['status'] = $request->boolean('status');
        Shift::create($validated);
        return response()->json(['success' => true, 'message' => 'Shift created successfully.']);
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $this->validateShift($request, $shift);
        $validated['is_overnight'] = $request->boolean('is_overnight') || $validated['end_time'] <= $validated['start_time'];
        $validated['status'] = $request->boolean('status');
        $shift->update($validated);
        return response()->json(['success' => true, 'message' => 'Shift updated successfully.']);
    }

    public function updateStatus(Request $request, Shift $shift)
    {
        $request->validate(['status' => ['required', 'boolean']]);
        $shift->update(['status' => $request->boolean('status')]);
        return response()->json(['success' => true, 'message' => 'Shift status updated successfully.']);
    }

    public function destroy(Shift $shift)
    {
        if ($shift->employees()->exists() || $shift->waiters()->exists() || $shift->rosters()->exists() || $shift->attendances()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This shift is already assigned or has history. Set it inactive instead of deleting.',
            ], 422);
        }

        $shift->delete();
        return response()->json(['success' => true, 'message' => 'Shift deleted successfully.']);
    }

    public function rosterTable(Request $request)
    {
        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->week_start)->startOfWeek(Carbon::SATURDAY)
            : now()->startOfWeek(Carbon::SATURDAY);
        $dates = collect(CarbonPeriod::create($weekStart, $weekStart->copy()->addDays(6)))
            ->map(fn ($date) => Carbon::instance($date)->copy());

        $query = Employee::with(['department', 'designation', 'defaultShift'])
            ->where('employment_status', 'active')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $employees = $query->paginate(8)->withQueryString();
        $rosters = ShiftRoster::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('roster_date', [$dates->first()->toDateString(), $dates->last()->toDateString()])
            ->get()
            ->keyBy(fn ($roster) => $roster->employee_id . '|' . $roster->roster_date->toDateString());
        $shifts = Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.hr.shifts.roster_table', compact('employees', 'dates', 'rosters', 'shifts'))->render();
    }

    public function saveRoster(Request $request)
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'exists:employees,id'],
            'rows.*.assignments' => ['required', 'array'],
            'rows.*.assignments.*.date' => ['required', 'date'],
            'rows.*.assignments.*.value' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['rows'] as $row) {
                foreach ($row['assignments'] as $assignment) {
                    $value = $assignment['value'] ?? '';
                    if ($value === '') {
                        ShiftRoster::where('employee_id', $row['employee_id'])
                            ->whereDate('roster_date', $assignment['date'])
                            ->delete();
                        continue;
                    }

                    $status = $value === 'OFF' ? 'off' : 'scheduled';
                    $shiftId = $status === 'scheduled' ? (int) $value : null;

                    if ($shiftId && !Shift::whereKey($shiftId)->where('status', true)->exists()) {
                        throw new \RuntimeException('An invalid or inactive shift was selected.');
                    }

                    ShiftRoster::updateOrCreate(
                        ['employee_id' => $row['employee_id'], 'roster_date' => $assignment['date']],
                        ['shift_id' => $shiftId, 'status' => $status, 'assigned_by' => auth()->id()]
                    );
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Duty roster saved successfully.']);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to save roster. ' . $e->getMessage()], 500);
        }
    }

    public function copyPreviousWeek(Request $request)
    {
        $request->validate(['week_start' => ['required', 'date']]);
        $currentStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::SATURDAY);
        $previousStart = $currentStart->copy()->subWeek();
        $previousEnd = $previousStart->copy()->addDays(6);

        $previousRosters = ShiftRoster::whereBetween('roster_date', [$previousStart->toDateString(), $previousEnd->toDateString()])->get();
        if ($previousRosters->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No roster was found in the previous week.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($previousRosters as $roster) {
                $targetDate = $roster->roster_date->copy()->addWeek();
                ShiftRoster::updateOrCreate(
                    ['employee_id' => $roster->employee_id, 'roster_date' => $targetDate->toDateString()],
                    [
                        'shift_id' => $roster->shift_id,
                        'status' => $roster->status === 'leave' ? 'scheduled' : $roster->status,
                        'notes' => $roster->notes,
                        'assigned_by' => auth()->id(),
                    ]
                );
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Previous week roster copied successfully.']);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to copy roster.'], 500);
        }
    }

    private function validateShift(Request $request, ?Shift $shift = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('shifts', 'code')->ignore($shift?->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
