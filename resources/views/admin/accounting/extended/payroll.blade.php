@extends('admin.master.master')
@section('title','Payroll & Employee Ledger')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting._nav')
<div class="row g-3"><div class="col-lg-7"><div class="acc-panel"><h3>Approved Employee Payroll / Accounting Status</h3>
<p class="acc-hint">Payroll accrual: Debit Gross Salary Expense; Credit Net Salary Payable and separately classified deductions/recoveries. Salary payment clears payable (never credits Sales).</p>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Employee</th><th>Gross</th><th>Deduction</th><th>Net</th><th>HR Status</th><th>Accounting</th></tr></thead><tbody>
@forelse($items as $i)<tr><td>{{ $i->employee_name }}<div class="acc-hint">Payroll item #{{ $i->id }}</div></td>
<td>{{ number_format((float)$i->gross_salary,2) }}</td><td>{{ number_format((float)$i->total_deduction,2) }}</td><td>{{ number_format((float)$i->net_salary,2) }}</td>
<td>{{ ucfirst($i->status) }}</td><td>@if($i->event_id)<a href="{{ route('accounting.events') }}">{{ $i->accounting_status }} #{{ $i->event_id }}</a>@else <span class="text-muted">Not captured</span>@endif</td></tr>
@empty<tr><td colspan="6">No approved employee payroll.</td></tr>@endforelse</tbody></table></div>{{ $items->links() }}
</div></div><div class="col-lg-5"><div class="acc-panel"><h3>Confirm Employee Advance / Loan Funding</h3>
<p class="acc-hint">HR loan/advance creation alone does not prove that cash was actually disbursed. Accountant confirms payment evidence here. Capture must be ON; past OFF-window funding is not backfilled. A review event is created, not a posted GL entry.</p>
@can('accounting-payroll')<form method="post" action="{{ route('accounting.payroll.funding') }}">@csrf
<div class="mb-2"><label>Source Type</label><select class="form-select" name="source_type" required><option value="salary_advances">Salary Advance</option><option value="employee_loans">Employee Loan</option></select></div>
<div class="mb-2"><label>Source Record ID</label><input class="form-control" type="number" name="source_id" min="1" required placeholder="HR record ID"></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Paid On</label><input type="date" class="form-control" name="paid_on" value="{{ date('Y-m-d') }}" required></div>
<div class="col-6 mb-2"><label>Payment Method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mfs">MFS</option></select></div></div>
<div class="mb-2"><label>Payment Reference</label><input class="form-control" name="reference" maxlength="150" required></div>
<div class="mb-2"><label>Document / Proof Details</label><textarea name="evidence" rows="2" class="form-control" minlength="10" required></textarea></div>
<button class="btn btn-primary" type="submit">Verify &amp; Create Pending Accounting Event</button></form>@endcan
</div></div></div>
<div class="row g-3"><div class="col-lg-6"><div class="acc-panel"><h3>Existing HR Salary Advances</h3>
<table class="table table-sm"><thead><tr><th>HR ID</th><th>Employee ID</th><th>Amount</th><th>Status</th></tr></thead><tbody>
@foreach($advances as $a)<tr><td>{{ $a->id }}</td><td>{{ $a->employee_id }}</td><td>{{ number_format((float)$a->amount,2) }}</td><td>{{ $a->status }}</td></tr>@endforeach</tbody></table></div></div>
<div class="col-lg-6"><div class="acc-panel"><h3>Existing HR Employee Loans</h3><table class="table table-sm"><thead><tr><th>HR ID</th><th>Employee ID</th><th>Principal</th><th>Status</th></tr></thead><tbody>
@foreach($loans as $l)<tr><td>{{ $l->id }}</td><td>{{ $l->employee_id }}</td><td>{{ number_format((float)$l->principal_amount,2) }}</td><td>{{ $l->status }}</td></tr>@endforeach</tbody></table></div></div></div>
<div class="acc-panel"><h3>Manual HR Repayment Verification (Not Payroll Recovery)</h3>
<p class="acc-hint">Only manually collected HR repayments belong here; payroll-deducted recovery is recognized with the approved payroll event. For interest-bearing employee loans, separate the amount into loan principal and interest income. Do not backfill Capture-OFF receipts.</p>
<div class="row g-3"><div class="col-lg-6"><h6>Recent Salary Advance Repayments</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Repayment ID</th><th>Date</th><th>Amount</th></tr></thead><tbody>@foreach($manualRepayments as $p)<tr><td>{{ $p->id }} (Advance #{{ $p->salary_advance_id }})</td><td>{{ $p->payment_date }}</td><td>{{ $p->amount }}</td></tr>@endforeach</tbody></table></div></div>
<div class="col-lg-6"><h6>Recent Employee Loan Repayments</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Repayment ID</th><th>Date</th><th>Amount</th></tr></thead><tbody>@foreach($manualLoanRepayments as $p)<tr><td>{{ $p->id }} (Loan #{{ $p->employee_loan_id }})</td><td>{{ $p->payment_date }}</td><td>{{ $p->amount }}</td></tr>@endforeach</tbody></table></div></div></div>
@can('accounting-payroll')<form class="row g-2 mt-2" method="post" action="{{ route('accounting.payroll.repayment') }}">@csrf
<div class="col-md-4"><label>HR Repayment Type</label><select name="source_type" class="form-select"><option value="salary_advance_repayments">Salary Advance</option><option value="loan_repayments">Employee Loan</option></select></div>
<div class="col-md-4"><label>Repayment ID</label><input type="number" name="source_id" min="1" required class="form-control"></div>
<div class="col-md-4"><label>HR Payment Date</label><input type="date" name="received_on" required class="form-control"></div>
<div class="col-md-4"><label>Received Via</label><select name="method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mfs">MFS</option></select></div>
<div class="col-md-4"><label>Principal BDT</label><input type="number" min="0" step="0.0001" name="principal" required class="form-control"></div>
<div class="col-md-4"><label>Interest BDT</label><input type="number" min="0" step="0.0001" name="interest" value="0" required class="form-control"></div>
<div class="col-md-6"><label>Receipt Reference</label><input name="reference" required class="form-control"></div>
<div class="col-md-6"><label>Evidence / Receipt Proof</label><input name="evidence" minlength="10" required class="form-control"></div>
<div class="col-12"><button class="btn btn-primary">Verify Repayment &amp; Create Pending Journal Event</button></div>
</form>@endcan</div>
<div class="acc-panel"><h3>Verified Disbursements</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Type / ID</th><th>Amount</th><th>Date</th><th>Method / Reference</th><th>Event</th></tr></thead><tbody>
@foreach($fundings as $f)<tr><td>{{ $f->source_type }} #{{ $f->source_id }}</td><td>{{ number_format((float)$f->amount,2) }}</td><td>{{ $f->paid_on }}</td><td>{{ $f->method }} / {{ $f->reference }}</td><td>#{{ $f->accounting_event_id }}</td></tr>@endforeach</tbody></table></div></div>
</main>@endsection
