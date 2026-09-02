<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceTemplateExport;
use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Imports\AttendanceRowsImport;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RestaurantSetting;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:attendance-view')->only([
            'index', 'reports', 'reportTable', 'reportPdf', 'reportExcel',
        ]);
        $this->middleware('permission:attendance-create|attendance-edit')->only('downloadTemplate');
        $this->middleware('permission:attendance-create|attendance-edit')->only(['storeBulk', 'importExcel']);
        $this->middleware('permission:attendance-edit')->only('update');
        $this->middleware('permission:attendance-delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->date)->toDateString() : now()->toDateString();

        if ($request->ajax()) {
            $query = Employee::with(['department', 'designation', 'defaultShift'])
                ->where('employment_status', 'active')
                ->orderBy('name');

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->filled('shift_id')) {
                $shiftId = $request->shift_id;
                $query->where(function ($q) use ($date, $shiftId) {
                    $q->where('default_shift_id', $shiftId)
                        ->orWhereHas('shiftRosters', fn ($roster) => $roster->whereDate('roster_date', $date)->where('shift_id', $shiftId));
                });
            }

            if ($request->filled('status')) {
                $status = $request->status;
                if ($status === 'not_marked') {
                    $query->whereDoesntHave('attendances', fn ($q) => $q->whereDate('attendance_date', $date));
                } else {
                    $query->whereHas('attendances', fn ($q) => $q->whereDate('attendance_date', $date)->where('status', $status));
                }
            }

            $employees = $query->paginate(10)->withQueryString();
            $employeeIds = $employees->pluck('id');
            $attendanceMap = Attendance::whereIn('employee_id', $employeeIds)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('employee_id');
            $rosterMap = ShiftRoster::whereIn('employee_id', $employeeIds)
                ->whereDate('roster_date', $date)
                ->get()
                ->keyBy('employee_id');
            $shifts = Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get();
            $weeklyOffDays = collect(AttendanceSetting::first()?->weekly_off_days ?? [])->map(fn ($day) => strtolower($day));
            $isGlobalOffDay = $weeklyOffDays->contains(strtolower(Carbon::parse($date)->format('l')))
                || Holiday::where('status', true)->whereDate('holiday_date', $date)->exists();

            $summary = $this->summaryForDate($date);
            return view('admin.hr.attendance.table', compact('employees', 'attendanceMap', 'rosterMap', 'shifts', 'date', 'isGlobalOffDay', 'summary'))->render();
        }

        $summary = $this->summaryForDate($date);

        return view('admin.hr.attendance.index', [
            'date' => $date,
            'summary' => $summary,
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'shifts' => Shift::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'exists:employees,id'],
            'rows.*.shift_id' => ['nullable', 'exists:shifts,id'],
            'rows.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'leave', 'off_day'])],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();
        try {
            $saved = 0;
            foreach ($validated['rows'] as $row) {
                $existingAttendance = Attendance::where('employee_id', $row['employee_id'])
                    ->whereDate('attendance_date', $validated['attendance_date'])
                    ->first();

                if ($existingAttendance?->source === 'system' && $existingAttendance?->status === 'leave') {
                    continue;
                }

                $shift = !empty($row['shift_id']) ? Shift::find($row['shift_id']) : null;
                $metrics = $this->calculateMetrics(
                    $validated['attendance_date'],
                    $shift,
                    $row['status'],
                    $row['check_in'] ?? null,
                    $row['check_out'] ?? null
                );

                $finalStatus = $row['status'];
                if ($finalStatus === 'present' && $metrics['late_minutes'] > 0 && (AttendanceSetting::first()?->auto_calculate_late ?? true)) {
                    $finalStatus = 'late';
                }

                Attendance::updateOrCreate(
                    [
                        'employee_id' => $row['employee_id'],
                        'attendance_date' => $validated['attendance_date'],
                    ],
                    array_merge($metrics, [
                        'shift_id' => $row['shift_id'] ?? null,
                        'status' => $finalStatus,
                        'source' => 'manual',
                        'notes' => $row['notes'] ?? null,
                        'marked_by' => auth()->id(),
                    ])
                );
                $saved++;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $saved . ' attendance record(s) saved successfully.',
                'summary' => $this->summaryForDate($validated['attendance_date']),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to save attendance. ' . $e->getMessage()], 500);
        }
    }

    public function downloadTemplate(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $start = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::with('defaultShift')
            ->whereDate('join_date', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('exit_date')->orWhereDate('exit_date', '>=', $start);
            })
            ->when($validated['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy('employee_code')
            ->get();

        $employeeIds = $employees->pluck('id');
        $attendances = Attendance::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$start, $end])
            ->get()
            ->keyBy(fn ($attendance) => $attendance->employee_id . '|' . $attendance->attendance_date->toDateString());
        $rosters = ShiftRoster::whereIn('employee_id', $employeeIds)
            ->whereBetween('roster_date', [$start, $end])
            ->get()
            ->keyBy(fn ($roster) => $roster->employee_id . '|' . Carbon::parse($roster->roster_date)->toDateString());
        $shifts = Shift::orderBy('sort_order')->orderBy('name')->get()->keyBy('id');
        $weeklyOffDays = collect(AttendanceSetting::first()?->weekly_off_days ?? [])->map(fn ($day) => strtolower($day));
        $holidayDates = Holiday::where('status', true)
            ->whereBetween('holiday_date', [$start, $end])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $rows = [];
        foreach ($employees as $employee) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($date->lt($employee->join_date) || ($employee->exit_date && $date->gt($employee->exit_date))) {
                    continue;
                }

                $key = $employee->id . '|' . $date->toDateString();
                $attendance = $attendances->get($key);
                $roster = $rosters->get($key);
                $shiftId = $attendance?->shift_id ?: ($roster?->shift_id ?: $employee->default_shift_id);
                $shift = $shiftId ? $shifts->get($shiftId) : null;
                $isOff = $roster?->status === 'off'
                    || $weeklyOffDays->contains(strtolower($date->format('l')))
                    || $holidayDates->has($date->toDateString());

                $rows[] = [
                    $employee->employee_code,
                    $employee->name,
                    $date->format('d-m-Y'),
                    $shift?->code ?: $shift?->name,
                    $attendance?->status ?: (!$date->isFuture() && $isOff ? 'off_day' : ''),
                    $attendance?->check_in?->format('H:i'),
                    $attendance?->check_out?->format('H:i'),
                    $attendance?->notes,
                ];
            }
        }

        $fileName = 'attendance_template_' . $validated['month'] . '.xlsx';
        return Excel::download(new AttendanceTemplateExport($rows, $validated['month']), $fileName);
    }

    public function importExcel(Request $request)
    {
        $validated = $request->validate([
            'attendance_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'duplicate_action' => ['required', Rule::in(['update', 'skip'])],
        ]);

        $import = new AttendanceRowsImport();

        try {
            Excel::import($import, $validated['attendance_file']);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'The Excel file could not be read. Please use the downloaded attendance template.',
            ], 422);
        }

        $rows = $import->rows();
        if ($rows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'The uploaded sheet has no attendance rows.'], 422);
        }

        $saved = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $employeeCache = [];
        $shiftCache = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $excelRow = $index + 2;
                $employeeCode = trim((string) ($row['employee_code'] ?? ''));
                $statusInput = trim((string) ($row['status'] ?? ''));

                // Blank status rows in the monthly template are intentionally ignored.
                if ($employeeCode === '' && $statusInput === '') {
                    continue;
                }
                if ($statusInput === '') {
                    $skipped++;
                    continue;
                }
                if ($employeeCode === '') {
                    $errors[] = "Row {$excelRow}: Employee Code is required.";
                    continue;
                }

                if (!array_key_exists(strtolower($employeeCode), $employeeCache)) {
                    $employeeCache[strtolower($employeeCode)] = Employee::where('employee_code', $employeeCode)->first();
                }
                $employee = $employeeCache[strtolower($employeeCode)];
                if (!$employee) {
                    $errors[] = "Row {$excelRow}: Employee Code {$employeeCode} was not found.";
                    continue;
                }

                $date = $this->parseExcelDate($row['attendance_date'] ?? null);
                if (!$date) {
                    $errors[] = "Row {$excelRow}: Attendance Date is invalid.";
                    continue;
                }
                if ($date->isFuture()) {
                    $errors[] = "Row {$excelRow}: Future attendance date {$date->format('d-m-Y')} is not allowed.";
                    continue;
                }
                if ($employee->join_date && $date->lt($employee->join_date)) {
                    $errors[] = "Row {$excelRow}: Date is before {$employeeCode}'s joining date.";
                    continue;
                }
                if ($employee->exit_date && $date->gt($employee->exit_date)) {
                    $errors[] = "Row {$excelRow}: Date is after {$employeeCode}'s exit date.";
                    continue;
                }

                $status = $this->normalizeImportedStatus($statusInput);
                if (!$status) {
                    $errors[] = "Row {$excelRow}: Status '{$statusInput}' is not valid.";
                    continue;
                }

                $shiftText = trim((string) ($row['shift_code'] ?? ''));
                $shift = null;
                if ($shiftText !== '') {
                    $shiftKey = strtolower($shiftText);
                    if (!array_key_exists($shiftKey, $shiftCache)) {
                        $shiftCache[$shiftKey] = Shift::where('code', $shiftText)->orWhere('name', $shiftText)->first();
                    }
                    $shift = $shiftCache[$shiftKey];
                    if (!$shift) {
                        $errors[] = "Row {$excelRow}: Shift '{$shiftText}' was not found.";
                        continue;
                    }
                } else {
                    $roster = ShiftRoster::where('employee_id', $employee->id)->whereDate('roster_date', $date)->first();
                    $shift = $roster?->shift ?: $employee->defaultShift;
                }

                $checkIn = $this->parseExcelTime($row['check_in'] ?? null);
                $checkOut = $this->parseExcelTime($row['check_out'] ?? null);
                if (($row['check_in'] ?? null) !== null && trim((string) ($row['check_in'] ?? '')) !== '' && !$checkIn) {
                    $errors[] = "Row {$excelRow}: Check In must be HH:MM.";
                    continue;
                }
                if (($row['check_out'] ?? null) !== null && trim((string) ($row['check_out'] ?? '')) !== '' && !$checkOut) {
                    $errors[] = "Row {$excelRow}: Check Out must be HH:MM.";
                    continue;
                }

                $existing = Attendance::where('employee_id', $employee->id)
                    ->whereDate('attendance_date', $date)
                    ->first();

                if ($existing?->source === 'system' && $existing?->status === 'leave') {
                    $errors[] = "Row {$excelRow}: Approved leave for {$employeeCode} is locked and was skipped.";
                    continue;
                }
                if ($existing && $validated['duplicate_action'] === 'skip') {
                    $skipped++;
                    continue;
                }

                $metrics = $this->calculateMetrics($date->toDateString(), $shift, $status, $checkIn, $checkOut);
                $finalStatus = $status;
                if ($finalStatus === 'present' && $metrics['late_minutes'] > 0 && (AttendanceSetting::first()?->auto_calculate_late ?? true)) {
                    $finalStatus = 'late';
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $employee->id, 'attendance_date' => $date->toDateString()],
                    array_merge($metrics, [
                        'shift_id' => $shift?->id,
                        'status' => $finalStatus,
                        'source' => 'manual',
                        'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                        'marked_by' => auth()->id(),
                    ])
                );

                $existing ? $updated++ : $saved++;
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Attendance import failed. ' . $e->getMessage(),
            ], 500);
        }

        $processed = $saved + $updated;
        if ($processed === 0 && count($errors) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No attendance records were imported.',
                'errors' => array_slice($errors, 0, 30),
                'skipped' => $skipped,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Import complete: {$saved} created, {$updated} updated and {$skipped} skipped.",
            'created' => $saved,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 30),
            'error_count' => count($errors),
        ]);
    }

    public function reports(Request $request)
    {
        return view('admin.hr.attendance.reports.index', [
            'month' => $request->get('month', now()->format('Y-m')),
            'employees' => Employee::orderBy('employee_code')->get(['id', 'employee_code', 'name']),
            'departments' => Department::where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function reportTable(Request $request)
    {
        $validated = $request->validate([
            'report_type' => ['required', Rule::in(['monthly_summary', 'employee_detail'])],
            'month' => ['required', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'required_if:report_type,employee_detail', 'exists:employees,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        [$start, $end] = $this->monthRange($validated['month']);

        if ($validated['report_type'] === 'employee_detail') {
            $employee = Employee::with(['department', 'designation', 'defaultShift'])->findOrFail($validated['employee_id']);
            $allRows = $this->employeeMonthlyRows($employee, $start, $end);
            $rows = $this->paginateCollection($allRows, 15, $request);
            $summary = $this->summarizeAttendanceRows($allRows);

            return view('admin.hr.attendance.reports.table', [
                'reportType' => 'employee_detail',
                'employee' => $employee,
                'rows' => $rows,
                'summary' => $summary,
                'monthLabel' => $start->format('F Y'),
            ])->render();
        }

        $query = $this->employeesForMonthQuery($start, $end)
            ->with(['department', 'designation', 'defaultShift']);

        if (!empty($validated['department_id'])) {
            $query->where('department_id', $validated['department_id']);
        }
        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('employee_code')->paginate(15)->withQueryString();
        $reportData = $this->buildMonthlyReportData($employees->getCollection(), $start, $end);

        return view('admin.hr.attendance.reports.table', [
            'reportType' => 'monthly_summary',
            'employees' => $employees,
            'dayMap' => $reportData['dayMap'],
            'summaryMap' => $reportData['summaryMap'],
            'days' => $reportData['days'],
            'start' => $start,
            'monthLabel' => $start->format('F Y'),
        ])->render();
    }

    public function reportPdf(Request $request)
    {
        $validated = $request->validate([
            'report_type' => ['required', Rule::in(['monthly_summary', 'employee_detail'])],
            'month' => ['required', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'required_if:report_type,employee_detail', 'exists:employees,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        [$start, $end] = $this->monthRange($validated['month']);
        $restaurant = RestaurantSetting::first();

        if ($validated['report_type'] === 'employee_detail') {
            $employee = Employee::with(['department', 'designation', 'defaultShift'])->findOrFail($validated['employee_id']);
            $rows = $this->employeeMonthlyRows($employee, $start, $end);
            $summary = $this->summarizeAttendanceRows($rows);
            $html = view('admin.hr.attendance.reports.pdf_employee', compact('employee', 'rows', 'summary', 'start', 'end', 'restaurant'))->render();
            $fileName = 'attendance_' . $employee->employee_code . '_' . $validated['month'] . '.pdf';
            $format = 'A4';
            $orientation = 'L';
        } else {
            $query = $this->employeesForMonthQuery($start, $end)->with(['department', 'designation', 'defaultShift']);
            if (!empty($validated['department_id'])) {
                $query->where('department_id', $validated['department_id']);
            }
            if (!empty($validated['search'])) {
                $search = trim($validated['search']);
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            }

            $employees = $query->orderBy('employee_code')->get();
            $reportData = $this->buildMonthlyReportData($employees, $start, $end);
            $dayMap = $reportData['dayMap'];
            $summaryMap = $reportData['summaryMap'];
            $days = $reportData['days'];

            $html = view('admin.hr.attendance.reports.pdf_monthly', compact('employees', 'dayMap', 'summaryMap', 'days', 'start', 'end', 'restaurant'))->render();
            $fileName = 'attendance_all_employees_' . $validated['month'] . '.pdf';
            $format = 'A3';
            $orientation = 'L';
        }

        @ini_set('pcre.backtrack_limit', '50000000');
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $tempDir = storage_path('app/mpdf-attendance');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 9,
            'margin_bottom' => 10,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetTitle($fileName);
        $mpdf->SetFooter('Generated: ' . now()->format('d M Y, h:i A') . '||Page {PAGENO} of {nbpg}');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($fileName, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }



    public function reportExcel(Request $request)
    {
        $validated = $request->validate([
            'report_type' => ['required', Rule::in(['monthly_summary', 'employee_detail'])],
            'month' => ['required', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'required_if:report_type,employee_detail', 'exists:employees,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        [$start, $end] = $this->monthRange($validated['month']);

        if ($validated['report_type'] === 'employee_detail') {
            $employee = Employee::with(['department', 'designation', 'defaultShift'])->findOrFail($validated['employee_id']);
            $detailRows = $this->employeeMonthlyRows($employee, $start, $end);
            $headings = ['Date', 'Day', 'Status', 'Shift', 'Check In', 'Check Out', 'Worked Minutes', 'Late Minutes', 'OT Minutes', 'Note'];
            $rows = $detailRows->map(function ($row) {
                $attendance = $row['attendance'];
                $shift = $row['shift'];
                return [
                    $row['date']->format('d-m-Y'), $row['date']->format('D'), ucfirst(str_replace('_', ' ', (string) $row['status'])),
                    $shift?->name ?? 'N/A', $attendance?->check_in?->format('h:i A') ?? 'N/A',
                    $attendance?->check_out?->format('h:i A') ?? 'N/A', (int) ($attendance?->worked_minutes ?? 0),
                    (int) ($attendance?->late_minutes ?? 0), (int) ($attendance?->overtime_minutes ?? 0), $attendance?->notes ?? '',
                ];
            })->all();
            return Excel::download(new ArrayReportExport($headings, $rows, 'Attendance Detail'), 'attendance-' . $employee->employee_code . '-' . $validated['month'] . '.xlsx');
        }

        $query = $this->employeesForMonthQuery($start, $end)->with(['department', 'designation', 'defaultShift']);
        if (!empty($validated['department_id'])) $query->where('department_id', $validated['department_id']);
        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
            });
        }
        $employees = $query->orderBy('employee_code')->get();
        $reportData = $this->buildMonthlyReportData($employees, $start, $end);
        $statusCodes = [
            'present' => 'P', 'late' => 'L', 'absent' => 'A', 'half_day' => 'HD', 'leave' => 'LV',
            'off_day' => 'O', 'not_marked' => 'NM', 'not_applicable' => '-', 'future' => '-',
        ];
        $dayHeadings = array_map(fn ($day) => str_pad((string) $day, 2, '0', STR_PAD_LEFT), $reportData['days']);
        $headings = array_merge(['Employee', 'Code', 'Department'], $dayHeadings, ['Present', 'Late', 'Absent', 'Half Day', 'Leave', 'Off Day', 'Not Marked', 'OT Minutes']);
        $rows = $employees->map(function ($employee) use ($reportData, $start, $statusCodes) {
            $row = [$employee->name, $employee->employee_code, $employee->department->name ?? 'N/A'];
            foreach ($reportData['days'] as $day) {
                $date = $start->copy()->day($day)->toDateString();
                $entry = $reportData['dayMap'][$employee->id . '|' . $date] ?? null;
                $status = $entry['status'] ?? 'not_marked';
                $row[] = $statusCodes[$status] ?? strtoupper(substr($status, 0, 2));
            }
            $summary = $reportData['summaryMap'][$employee->id];
            return array_merge($row, [
                $summary['present'], $summary['late'], $summary['absent'], $summary['half_day'],
                $summary['leave'], $summary['off_day'], $summary['not_marked'], $summary['overtime_minutes'],
            ]);
        })->all();
        return Excel::download(new ArrayReportExport($headings, $rows, 'Monthly Attendance'), 'attendance-all-employees-' . $validated['month'] . '.xlsx');
    }


    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'leave', 'off_day'])],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = !empty($validated['shift_id']) ? Shift::find($validated['shift_id']) : null;
        $metrics = $this->calculateMetrics(
            $attendance->attendance_date->toDateString(),
            $shift,
            $validated['status'],
            $validated['check_in'] ?? null,
            $validated['check_out'] ?? null
        );

        if ($validated['status'] === 'present' && $metrics['late_minutes'] > 0 && (AttendanceSetting::first()?->auto_calculate_late ?? true)) {
            $validated['status'] = 'late';
        }
        $attendance->update(array_merge($validated, $metrics, ['marked_by' => auth()->id()]));
        return response()->json(['success' => true, 'message' => 'Attendance updated successfully.']);
    }

    public function destroy(Attendance $attendance)
    {
        if ($attendance->source === 'system' && $attendance->status === 'leave') {
            return response()->json(['success' => false, 'message' => 'Approved leave attendance cannot be deleted from this page.'], 422);
        }

        $date = $attendance->attendance_date->toDateString();
        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance record removed successfully.',
            'summary' => $this->summaryForDate($date),
        ]);
    }

    private function calculateMetrics(string $date, ?Shift $shift, string $status, ?string $checkIn, ?string $checkOut): array
    {
        if (in_array($status, ['absent', 'leave', 'off_day'], true)) {
            return [
                'check_in' => null,
                'check_out' => null,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
                'worked_minutes' => 0,
            ];
        }

        $checkInAt = $checkIn ? Carbon::parse("{$date} {$checkIn}") : null;
        $checkOutAt = $checkOut ? Carbon::parse("{$date} {$checkOut}") : null;

        if ($checkOutAt && $checkInAt && $checkOutAt->lessThanOrEqualTo($checkInAt)) {
            $checkOutAt->addDay();
        }

        $workedMinutes = ($checkInAt && $checkOutAt) ? max(0, $checkInAt->diffInMinutes($checkOutAt)) : 0;
        if ($workedMinutes > 0 && $shift) {
            $workedMinutes = max(0, $workedMinutes - (int) $shift->break_minutes);
        }
        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        if ($shift && $shift->start_time && $shift->end_time) {
            $scheduledStart = Carbon::parse("{$date} {$shift->start_time}");
            $scheduledEnd = Carbon::parse("{$date} {$shift->end_time}");
            if ($shift->is_overnight || $scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                $scheduledEnd->addDay();
            }

            $setting = AttendanceSetting::first();
            $grace = (int) ($shift->grace_minutes ?: ($setting->grace_minutes ?? 0));

            if ($checkInAt && $checkInAt->greaterThan($scheduledStart->copy()->addMinutes($grace))) {
                $lateMinutes = $scheduledStart->diffInMinutes($checkInAt);
            }
            if ($checkOutAt && $checkOutAt->lessThan($scheduledEnd)) {
                $earlyLeaveMinutes = $checkOutAt->diffInMinutes($scheduledEnd);
            }
            if ($checkOutAt && $checkOutAt->greaterThan($scheduledEnd) && ($setting?->auto_calculate_overtime ?? true)) {
                $rawOvertime = $scheduledEnd->diffInMinutes($checkOutAt);
                $minimum = (int) ($setting->minimum_overtime_minutes ?? 0);
                $overtimeMinutes = $rawOvertime >= $minimum ? $rawOvertime : 0;
            }
        }

        return [
            'check_in' => $checkInAt,
            'check_out' => $checkOutAt,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'worked_minutes' => $workedMinutes,
        ];
    }

    private function summaryForDate(string $date): array
    {
        $active = Employee::where('employment_status', 'active')->count();
        $counts = Attendance::whereDate('attendance_date', $date)
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => $active,
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'leave' => (int) ($counts['leave'] ?? 0),
            'half_day' => (int) ($counts['half_day'] ?? 0),
            'off_day' => (int) ($counts['off_day'] ?? 0),
            'not_marked' => max(0, $active - (int) $counts->sum()),
        ];
    }

    private function parseExcelDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->startOfDay();
                }
            } catch (Throwable) {
                // Try next supported format.
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseExcelTime(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('H:i');
            } catch (Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        foreach (['H:i', 'H:i:s', 'h:i A', 'h:i a'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('H:i');
            } catch (Throwable) {
                // Try next supported format.
            }
        }
        return null;
    }

    private function normalizeImportedStatus(string $status): ?string
    {
        $key = strtolower(trim(str_replace(['-', ' '], '_', $status)));
        $map = [
            'p' => 'present', 'present' => 'present',
            'l' => 'late', 'late' => 'late',
            'a' => 'absent', 'absent' => 'absent',
            'hd' => 'half_day', 'halfday' => 'half_day', 'half_day' => 'half_day',
            'lv' => 'leave', 'leave' => 'leave',
            'o' => 'off_day', 'off' => 'off_day', 'offday' => 'off_day', 'off_day' => 'off_day',
        ];
        return $map[$key] ?? null;
    }

    private function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        return [$start, $start->copy()->endOfMonth()];
    }

    private function employeesForMonthQuery(Carbon $start, Carbon $end)
    {
        return Employee::query()
            ->whereDate('join_date', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('exit_date')->orWhereDate('exit_date', '>=', $start);
            });
    }

    private function buildMonthlyReportData($employees, Carbon $start, Carbon $end): array
    {
        $employees = collect($employees)->values();
        $employeeIds = $employees->pluck('id');

        $attendances = Attendance::with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$start, $end])
            ->get()
            ->keyBy(fn ($attendance) => $attendance->employee_id . '|' . $attendance->attendance_date->toDateString());

        $rosters = ShiftRoster::with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('roster_date', [$start, $end])
            ->get()
            ->keyBy(fn ($roster) => $roster->employee_id . '|' . $roster->roster_date->toDateString());

        $weeklyOffDays = collect(AttendanceSetting::first()?->weekly_off_days ?? [])
            ->map(fn ($day) => strtolower($day));

        $holidayDates = Holiday::where('status', true)
            ->whereBetween('holiday_date', [$start, $end])
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $dayMap = [];
        $summaryMap = [];

        foreach ($employees as $employee) {
            $summary = $this->emptyMonthlySummary();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $key = $employee->id . '|' . $date->toDateString();
                $attendance = $attendances->get($key);
                $roster = $rosters->get($key);

                $outsideEmployment = ($employee->join_date && $date->lt($employee->join_date))
                    || ($employee->exit_date && $date->gt($employee->exit_date));

                if ($outsideEmployment) {
                    $status = 'not_applicable';
                } elseif ($date->isFuture()) {
                    $status = 'future';
                } elseif ($attendance) {
                    $status = $attendance->status;
                } elseif (
                    $roster?->status === 'off'
                    || $weeklyOffDays->contains(strtolower($date->format('l')))
                    || $holidayDates->has($date->toDateString())
                ) {
                    $status = 'off_day';
                } else {
                    $status = 'not_marked';
                }

                $shift = $attendance?->shift
                    ?: ($roster?->shift ?: $employee->defaultShift);

                $dayMap[$key] = [
                    'date' => $date->copy(),
                    'status' => $status,
                    'attendance' => $attendance,
                    'shift' => $shift,
                    'is_holiday' => $holidayDates->has($date->toDateString()),
                    'is_weekly_off' => $weeklyOffDays->contains(strtolower($date->format('l'))),
                ];

                if (array_key_exists($status, $summary)) {
                    $summary[$status]++;
                }

                if ($attendance) {
                    $summary['worked_minutes'] += (int) $attendance->worked_minutes;
                    $summary['late_minutes'] += (int) $attendance->late_minutes;
                    $summary['overtime_minutes'] += (int) $attendance->overtime_minutes;
                }
            }

            $summaryMap[$employee->id] = $summary;
        }

        return [
            'dayMap' => $dayMap,
            'summaryMap' => $summaryMap,
            'days' => collect(range(1, $end->day)),
        ];
    }

    private function employeeMonthlyRows(Employee $employee, Carbon $start, Carbon $end)
    {
        $employee->loadMissing('defaultShift');
        $reportData = $this->buildMonthlyReportData(collect([$employee]), $start, $end);

        return collect($reportData['dayMap'])
            ->filter(fn ($row, $key) => str_starts_with((string) $key, $employee->id . '|'))
            ->sortBy(fn ($row) => $row['date']->toDateString())
            ->values();
    }

    private function paginateCollection($items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    private function summarizeAttendanceRows($rows): array
    {
        $summary = $this->emptyMonthlySummary();

        foreach (collect($rows) as $row) {
            $status = $row['status'] ?? null;
            if ($status !== null && array_key_exists($status, $summary)) {
                $summary[$status]++;
            }

            $attendance = $row['attendance'] ?? null;
            if ($attendance) {
                $summary['worked_minutes'] += (int) $attendance->worked_minutes;
                $summary['late_minutes'] += (int) $attendance->late_minutes;
                $summary['overtime_minutes'] += (int) $attendance->overtime_minutes;
            }
        }

        return $summary;
    }

    private function emptyMonthlySummary(): array
    {
        return [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'half_day' => 0,
            'leave' => 0,
            'off_day' => 0,
            'not_marked' => 0,
            'not_applicable' => 0,
            'future' => 0,
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
        ];
    }
}
