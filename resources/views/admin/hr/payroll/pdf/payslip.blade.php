<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body{font-family:dejavusans,sans-serif;color:#1e2924;font-size:11px}.header{border-bottom:3px solid #21352a;padding-bottom:10px;margin-bottom:14px}.brand{font-size:20px;font-weight:bold;color:#21352a}.muted{color:#6b7c74}.title{text-align:right;font-size:20px;font-weight:bold;color:#21352a}.meta{width:100%;border-collapse:collapse;margin-bottom:14px}.meta td{padding:5px 7px;border:1px solid #dfe5e1}.summary{width:100%;border-collapse:collapse;margin:12px 0}.summary td{padding:9px;border:1px solid #dfe5e1;text-align:center}.summary strong{font-size:15px;color:#21352a}.table{width:100%;border-collapse:collapse;margin-top:10px}.table th{background:#21352a;color:#fff;padding:7px;text-align:left}.table td{border:1px solid #dfe5e1;padding:7px}.amount{text-align:right}.total{font-weight:bold;background:#f2f5f3}.net{font-size:16px;background:#e9f5ee}.status{display:inline-block;padding:4px 8px;border-radius:10px;background:#edf5fd;color:#1466a0;font-weight:bold}.footer-note{margin-top:20px;border-top:1px solid #dfe5e1;padding-top:8px;color:#6b7c74;font-size:9px}.signatures{width:100%;margin-top:42px}.signatures td{text-align:center;width:33%}.line{border-top:1px solid #777;padding-top:4px;margin:0 20px}
</style>
</head>
<body>
<table class="header" width="100%"><tr><td><div class="brand">{{ $restaurant->restaurant_name ?? $restaurant->name ?? config('app.name') }}</div><div class="muted">{{ $restaurant->address ?? '' }}</div><div class="muted">{{ $restaurant->phone ?? '' }}</div></td><td class="title">PAYSLIP<div class="muted" style="font-size:11px;font-weight:normal">{{ $run->month_label }}</div></td></tr></table>

<table class="meta">
<tr><td><strong>Employee</strong></td><td>{{ $item->employee_name }}</td><td><strong>Employee ID</strong></td><td>{{ $item->employee_code }}</td></tr>
<tr><td><strong>Department</strong></td><td>{{ $item->department_name ?: 'N/A' }}</td><td><strong>Designation</strong></td><td>{{ $item->designation_name ?: 'N/A' }}</td></tr>
<tr><td><strong>Payroll Code</strong></td><td>{{ $run->payroll_code }}</td><td><strong>Workflow Status</strong></td><td><span class="status">{{ strtoupper($item->status) }}</span></td></tr>
<tr><td><strong>Payment Status</strong></td><td>{{ strtoupper($item->payment_status) }}</td><td><strong>Approved By</strong></td><td>{{ $item->approvedBy->name ?? 'N/A' }}</td></tr>
<tr><td><strong>Period</strong></td><td>{{ $run->period_start->format('d-m-Y') }} to {{ $run->period_end->format('d-m-Y') }}</td><td><strong>Payment Method</strong></td><td>{{ ucwords(str_replace('_', ' ', $item->payment?->payment_method ?: $item->payment_method)) }}</td></tr>
</table>

<table class="summary"><tr><td><span class="muted">Present</span><br><strong>{{ (float) $item->present_days }}</strong></td><td><span class="muted">Late</span><br><strong>{{ (float) $item->late_days }}</strong></td><td><span class="muted">Absent</span><br><strong>{{ (float) $item->absent_days }}</strong></td><td><span class="muted">Leave</span><br><strong>{{ (float) $item->paid_leave_days + (float) $item->unpaid_leave_days }}</strong></td><td><span class="muted">Overtime</span><br><strong>{{ intdiv($item->overtime_minutes, 60) }}h {{ $item->overtime_minutes % 60 }}m</strong></td></tr></table>

<table width="100%"><tr><td width="49%" valign="top">
<table class="table"><thead><tr><th>Earnings</th><th class="amount">Amount</th></tr></thead><tbody>
@foreach($item->components->where('component_type', 'earning') as $component)
<tr>
    <td>
        {{ $component->component_name }}
        @if($component->is_overridden)
            <small>*</small>
        @endif
    </td>
    <td class="amount">৳{{ number_format((float) $component->amount, 2) }}</td>
</tr>
@endforeach
<tr class="total"><td>Gross Salary</td><td class="amount">৳{{ number_format((float) $item->gross_salary, 2) }}</td></tr>
</tbody></table>
</td><td width="2%"></td><td width="49%" valign="top">
<table class="table"><thead><tr><th>Deductions</th><th class="amount">Amount</th></tr></thead><tbody>
@foreach($item->components->where('component_type', 'deduction') as $component)
<tr>
    <td>
        {{ $component->component_name }}
        @if($component->is_overridden)
            <small>*</small>
        @endif
    </td>
    <td class="amount">৳{{ number_format((float) $component->amount, 2) }}</td>
</tr>
@endforeach
<tr class="total"><td>Total Deduction</td><td class="amount">৳{{ number_format((float) $item->total_deduction, 2) }}</td></tr>
</tbody></table>
</td></tr></table>

<table class="table"><tr class="net"><td><strong>NET SALARY</strong></td><td class="amount"><strong>৳{{ number_format((float) $item->net_salary, 2) }}</strong></td></tr></table>

@if($item->payment)
<div style="margin-top:12px">
    <strong>Payment:</strong> {{ $item->payment->payment_date->format('d-m-Y') }} · {{ ucwords(str_replace('_', ' ', $item->payment->payment_method)) }}
    @if($item->payment->reference_number)
        · Ref: {{ $item->payment->reference_number }}
    @endif
</div>
@endif
@if($item->notes)
    <div style="margin-top:10px"><strong>Note:</strong> {{ $item->notes }}</div>
@endif

<table class="signatures"><tr><td><div class="line">Employee Signature</div></td><td><div class="line">Prepared By</div></td><td><div class="line">Approved By</div></td></tr></table>
<div class="footer-note">This payslip is generated from the payroll snapshot. Later changes to attendance or salary setup do not alter approved payroll records.</div>
</body>
</html>
