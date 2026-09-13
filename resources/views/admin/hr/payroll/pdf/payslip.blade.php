<!doctype html>
<html>
<head><meta charset="utf-8"><style>
@page{margin:8mm 8mm 9mm 8mm}body{font-family:dejavusans,sans-serif;color:#111;font-size:10.5px;margin:0}.sheet{border:1px solid #3447ad;min-height:270mm;padding:8px;box-sizing:border-box}.company{text-align:center}.company-name{font-size:20px;font-weight:700}.concern{font-size:10px;font-weight:400}.address{font-size:10px;margin-top:5px}.title{font-size:19px;font-weight:700;margin-top:8px}.date-table{border-collapse:collapse;width:34%;margin-top:24px}.date-table td{border:1px solid #111;padding:4px 5px;font-size:10px}.date-label{font-weight:700;text-align:center;width:58%}.meta{width:100%;border-collapse:collapse;margin-top:20px}.meta td{border:1px solid #111;padding:4px 5px}.meta .label{font-weight:700;width:20%}.meta .value{text-align:center;font-size:11px}.spacer{height:35px}.columns{width:100%;border-collapse:collapse;table-layout:fixed}.columns>tbody>tr>td{vertical-align:top;padding:0;width:33.333%}.pay-table{width:100%;border-collapse:collapse;table-layout:fixed}.pay-table th,.pay-table td{border:1px solid #111;padding:4px 5px;vertical-align:middle}.pay-table th{background:#d9d9d9;font-size:10px;text-align:center;font-weight:700}.pay-table td:first-child{text-align:center}.pay-table .amt{text-align:center;width:31%}.pay-table .total td{background:#d9d9d9;font-weight:700}.bottom-wrap{margin-top:30px;width:54%}.bottom{width:100%;border-collapse:collapse}.bottom td{border:1px solid #111;padding:5px 7px;text-align:center;font-size:10.5px}.bottom .net td{background:#c6e0b4;font-weight:700;font-style:italic}.note{text-align:center;font-weight:700;font-style:italic;margin-top:48px;font-size:10px}.sign{width:100%;margin-top:28px}.sign td{width:70%;border:0}.sign .sigcell{width:30%;text-align:center;vertical-align:bottom}.sigline{border-top:1px solid #111;padding-top:3px;font-weight:700;font-size:12px}.sigspace{height:42px}.muted{color:#555}
</style></head>
<body>
@php
    $salaryRows = $item->components->where('component_group','salary')->values();
    $allowanceRows = $item->components->where('component_group','allowance')->values();
    $deductionRows = $item->components->where('component_group','deduction')->values();
    $companyName = $restaurant->restaurant_name ?? $restaurant->name ?? 'JK Food Arena Limited';
    $companyAddress = $restaurant->address ?? 'Noor Tower, Block -D, HOME- 29/31, Road NO- 01, Aftab Nagar Main Road, Dhaka 1212';
    $concern = 'a concern of JK Lifestyle Limited';
    $docDate = $item->payment?->payment_date ?? now();
@endphp
<div class="sheet">
    <div class="company">
        <div class="company-name">{{ $companyName }} <span class="concern">{{ $concern }}</span></div>
        <div class="address">{{ $companyAddress }}</div>
        <div class="title">Salary Pay Slip</div>
    </div>

    <table class="date-table"><tr><td class="date-label">Date:</td><td>{{ \Carbon\Carbon::parse($docDate)->format('d-M-y') }}</td></tr></table>

    <table class="meta">
        <tr><td class="label">Employee Name:</td><td class="value">{{ $item->employee_name }}</td></tr>
        <tr><td class="label">Employee ID:</td><td class="value">{{ $item->employee_code }}</td></tr>
        <tr><td class="label">Designation:</td><td class="value">{{ $item->designation_name ?: 'N/A' }}</td></tr>
        <tr><td class="label">Department:</td><td class="value">{{ $item->department_name ?: 'N/A' }}</td></tr>
        <tr><td class="label">Month &amp; Year:</td><td class="value">For the Month of {{ $run->payroll_month->format('F Y') }}</td></tr>
    </table>

    <div class="spacer"></div>
    <table class="columns"><tr>
        <td><table class="pay-table"><thead><tr><th>Salary</th><th class="amt">Amount</th></tr></thead><tbody>
            @foreach($salaryRows as $row)<tr><td>{{ $row->component_name }}</td><td class="amt">{{ number_format((float)$row->amount,0) }}</td></tr>@endforeach
            <tr class="total"><td>Total</td><td class="amt">{{ number_format((float)$item->salary_total,0) }}</td></tr>
        </tbody></table></td>
        <td><table class="pay-table"><thead><tr><th>Allowance</th><th class="amt">Amount</th></tr></thead><tbody>
            @foreach($allowanceRows as $row)<tr><td>{{ $row->component_name }}</td><td class="amt">{{ number_format((float)$row->amount,0) }}</td></tr>@endforeach
            <tr class="total"><td>Total</td><td class="amt">{{ number_format((float)$item->allowance_total,0) }}</td></tr>
        </tbody></table></td>
        <td><table class="pay-table"><thead><tr><th>Deductions</th><th class="amt">Amount</th></tr></thead><tbody>
            @foreach($deductionRows as $row)<tr><td>{{ $row->component_name }}</td><td class="amt">{{ number_format((float)$row->amount,0) }}</td></tr>@endforeach
            <tr class="total"><td>Total</td><td class="amt">{{ number_format((float)$item->total_deduction,0) }}</td></tr>
        </tbody></table></td>
    </tr></table>

    <div class="bottom-wrap"><table class="bottom">
        <tr><td>Gross Salary</td><td>{{ number_format((float)$item->salary_total,0) }}</td></tr>
        <tr class="net"><td>Net Salary/ Bank Salary</td><td>{{ number_format((float)$item->net_salary,0) }}</td></tr>
    </table></div>

    <div class="note">Note: All the amount disbursed into respective bank account.</div>
    <table class="sign"><tr><td></td><td class="sigcell"><div class="sigspace"></div><div class="sigline">HOD</div></td></tr></table>
</div>
</body></html>
