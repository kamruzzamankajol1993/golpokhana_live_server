<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:sans-serif;color:#17201c;font-size:7px}
        .header{text-align:center;border-bottom:2px solid #21352a;padding-bottom:6px;margin-bottom:8px}
        .header h1{font-size:16px;color:#21352a;margin:0}.header p{margin:2px 0;color:#5f6f67}
        .report{width:100%;border-collapse:collapse;table-layout:fixed}
        .report th{background:#21352a;color:#fff;padding:3px 1px;border:1px solid #152219;font-size:6px}
        .report td{padding:3px 1px;border:1px solid #dfd0bc;font-size:6px;text-align:center}
        .report .employee{text-align:left;width:100px}.report .department{text-align:left;width:65px}
        .day{width:15px}.total{width:18px;font-weight:bold}.legend{margin-top:7px;font-size:7px;color:#4d5e56}
        .weekend{background:#f5ede2}.na{color:#9aada5}.present{color:#1e7a4a;font-weight:bold}
        .late{color:#c06820;font-weight:bold}.absent{color:#b33030;font-weight:bold}.leave{color:#663399;font-weight:bold}
        .notmarked{color:#a86418;font-weight:bold;background:#fff7e8}.offday{color:#6b7c74;background:#f7f7f7}
    </style>
</head>
<body>
<div class="header">
    <h1>{{ $restaurant->restaurant_name ?? $restaurant->name ?? 'Restaurant Management System' }}</h1>
    <p>All Employees Attendance — {{ $start->format('F Y') }}</p>
</div>

<table class="report">
    <thead>
        <tr>
            <th class="employee">Employee</th>
            <th class="department">Department</th>
            @foreach($days as $day)
                <th class="day">{{ $day }}</th>
            @endforeach
            <th class="total">P</th>
            <th class="total">L</th>
            <th class="total">A</th>
            <th class="total">HD</th>
            <th class="total">LV</th>
            <th class="total">O</th>
            <th class="total">NM</th>
        </tr>
    </thead>
    <tbody>
        @foreach($employees as $employee)
            <tr>
                <td class="employee"><strong>{{ $employee->employee_code }}</strong> — {{ $employee->name }}</td>
                <td class="department">{{ $employee->department->name ?? '—' }}</td>

                @foreach($days as $day)
                    @php
                        $date = $start->copy()->day($day);
                        $entry = $dayMap[$employee->id . '|' . $date->toDateString()] ?? null;
                        $status = $entry['status'] ?? 'not_marked';
                        $codes = [
                            'present' => 'P', 'late' => 'L', 'absent' => 'A', 'half_day' => 'HD',
                            'leave' => 'LV', 'off_day' => 'O', 'not_marked' => 'NM',
                            'not_applicable' => '—', 'future' => '—',
                        ];
                        $class = [
                            'present' => 'present', 'late' => 'late', 'absent' => 'absent',
                            'leave' => 'leave', 'not_marked' => 'notmarked', 'off_day' => 'offday',
                            'not_applicable' => 'na', 'future' => 'na',
                        ][$status] ?? '';
                    @endphp
                    <td class="day {{ ($entry['is_weekly_off'] ?? false) || ($entry['is_holiday'] ?? false) ? 'weekend' : '' }} {{ $class }}">{{ $codes[$status] ?? '—' }}</td>
                @endforeach

                @php $summary = $summaryMap[$employee->id]; @endphp
                <td class="total">{{ $summary['present'] }}</td>
                <td class="total">{{ $summary['late'] }}</td>
                <td class="total">{{ $summary['absent'] }}</td>
                <td class="total">{{ $summary['half_day'] }}</td>
                <td class="total">{{ $summary['leave'] }}</td>
                <td class="total">{{ $summary['off_day'] }}</td>
                <td class="total">{{ $summary['not_marked'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="legend">
    <strong>Legend:</strong> P = Present, L = Late, A = Absent, HD = Half Day, LV = Leave, O = Off Day, NM = Not Marked, — = Future or outside employment period.
</div>
</body>
</html>
