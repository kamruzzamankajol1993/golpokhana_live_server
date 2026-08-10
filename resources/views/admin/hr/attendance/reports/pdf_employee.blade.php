<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body{font-family:sans-serif;color:#17201c;font-size:10px}
        .header{text-align:center;border-bottom:2px solid #21352a;padding-bottom:8px;margin-bottom:10px}
        .header h1{font-size:18px;color:#21352a;margin:0}.header p{margin:3px 0;color:#5f6f67}
        .meta{width:100%;border-collapse:collapse;margin-bottom:10px}.meta td{padding:5px;border:1px solid #dfd0bc}
        .summary{width:100%;border-collapse:collapse;margin:8px 0 12px}.summary td{border:1px solid #dfd0bc;padding:6px;text-align:center}
        .summary strong{display:block;font-size:14px;color:#21352a}
        .report{width:100%;border-collapse:collapse}.report th{background:#21352a;color:#fff;padding:6px;border:1px solid #21352a;font-size:9px}
        .report td{padding:5px;border:1px solid #dfd0bc;font-size:9px}.center{text-align:center}.muted{color:#6b7c74}
    </style>
</head>
<body>
<div class="header">
    <h1>{{ $restaurant->restaurant_name ?? $restaurant->name ?? 'Restaurant Management System' }}</h1>
    <p>Employee Attendance Report — {{ $start->format('F Y') }}</p>
</div>

<table class="meta">
    <tr>
        <td><strong>Employee:</strong> {{ $employee->name }}</td>
        <td><strong>Employee Code:</strong> {{ $employee->employee_code }}</td>
        <td><strong>Department:</strong> {{ $employee->department->name ?? '—' }}</td>
        <td><strong>Designation:</strong> {{ $employee->designation->name ?? '—' }}</td>
    </tr>
</table>

@php
    $formatMinutes = function ($minutes) {
        $minutes = (int) $minutes;
        return floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm';
    };
@endphp

<table class="summary">
    <tr>
        <td><strong>{{ $summary['present'] }}</strong>Present</td>
        <td><strong>{{ $summary['late'] }}</strong>Late</td>
        <td><strong>{{ $summary['absent'] }}</strong>Absent</td>
        <td><strong>{{ $summary['half_day'] }}</strong>Half Day</td>
        <td><strong>{{ $summary['leave'] }}</strong>Leave</td>
        <td><strong>{{ $summary['off_day'] }}</strong>Off Day</td>
        <td><strong>{{ $summary['not_marked'] }}</strong>Not Marked</td>
        <td><strong>{{ $formatMinutes($summary['overtime_minutes']) }}</strong>Overtime</td>
    </tr>
</table>

<table class="report">
    <thead>
        <tr>
            <th>Date</th><th>Day</th><th>Status</th><th>Shift</th><th>Check In</th><th>Check Out</th><th>Worked</th><th>Late</th><th>OT</th><th>Note</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            @php
                $attendance = $row['attendance'];
                $shift = $row['shift'];
            @endphp
            <tr>
                <td>{{ $row['date']->format('d-m-Y') }}</td>
                <td>{{ $row['date']->format('D') }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $row['status'])) }}</td>
                <td>{{ $shift?->name ?? '—' }}</td>
                <td>{{ $attendance?->check_in?->format('h:i A') ?? '—' }}</td>
                <td>{{ $attendance?->check_out?->format('h:i A') ?? '—' }}</td>
                <td>{{ $formatMinutes($attendance?->worked_minutes ?? 0) }}</td>
                <td>{{ $formatMinutes($attendance?->late_minutes ?? 0) }}</td>
                <td>{{ $formatMinutes($attendance?->overtime_minutes ?? 0) }}</td>
                <td>{{ $attendance?->notes ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
