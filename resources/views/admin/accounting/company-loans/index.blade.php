{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Company Loans')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="row g-3"><div class="col-lg-5"><div class="acc-panel"><h3>Register Company Loan</h3>
<p class="acc-hint">The loan must be linked to an existing POSTED funding journal. Principal is a liability; repayments split principal (not expense) and separately documented interest.</p>
@can('accounting-company-loans')
<form method="post" action="{{ route('accounting.loans.save') }}">@csrf
<div class="mb-2"><label>Loan Reference</label><input class="form-control" name="reference" required></div>
<div class="mb-2"><label>Lender / Bank</label><input class="form-control" name="lender" required></div>
<div class="mb-2"><label>Posted Funding Journal ID</label><input type="number" name="funding_journal_entry_id" class="form-control" required></div>
<div class="mb-2"><label>Loan Liability Account</label><select class="form-select" name="liability_account_id">
@foreach($accounts->where('type','liability') as $a)
<option value="{{ $a->id }}">{{ $a->code }} – {{ $a->name }}</option>
@endforeach
</select></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Principal</label><input class="form-control" type="number" step="0.0001" min="0.0001" name="principal" required></div><div class="col-6 mb-2"><label>Annual Interest %</label><input class="form-control" type="number" step="0.0001" min="0" max="100" name="annual_interest_rate" value="0" required></div></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Received On</label><input type="date" class="form-control" name="received_on" required></div><div class="col-6 mb-2"><label>Maturity (Optional)</label><input type="date" class="form-control" name="maturity_on"></div></div>
<div class="mb-2"><label>Agreement / Notes</label><textarea class="form-control" name="notes"></textarea></div><button class="btn btn-primary">Register Linked Loan</button></form>
@endcan
</div></div>
<div class="col-lg-7"><div class="acc-panel"><h3>Company Loans & Controlled Repayments</h3>
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($loans, 'acc_company_loans_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-company-loans-1">
<table class="table table-sm"><thead><tr><th>Ref / Lender</th><th>Principal</th><th>Linked Journal</th></tr></thead><tbody>
@foreach($accPager1 as $loan)
<tr><td>{{ $loan->reference }}<div class="acc-hint">{{ $loan->lender }}</div></td><td>{{ number_format((float)$loan->principal,2) }}</td><td><a href="{{ route('accounting.journals.show',$loan->funding_journal_entry_id) }}">#{{ $loan->funding_journal_entry_id }}</a></td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'loans'])
</div></div>
<div class="acc-panel"><h3>Generate Installment Schedule</h3>
<p class="acc-hint">One-time monthly, equal-principal plan. Interest is an estimate calculated on outstanding principal. Schedule alone does not create a payment or GL entry. Existing repayments must be reconciled first.</p>
@can('accounting-company-loans')
@foreach($loans as $loan)
@if(!$installments->contains('company_loan_id',$loan->id))
<form method="post" action="{{ route('accounting.loans.schedule',$loan->id) }}" class="row g-2 mb-3" data-acc-confirm="Generate monthly schedule for {{ $loan->reference }}?">@csrf
<div class="col-12"><strong>{{ $loan->reference }} – {{ $loan->lender }}</strong></div>
<div class="col-5"><label>Installments</label><input name="installment_count" type="number" min="1" max="360" value="12" class="form-control" required></div>
<div class="col-5"><label>First Due Date</label><input name="first_due_date" type="date" class="form-control" required></div>
<div class="col-2 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm">Create</button></div></form>
@endif
@endforeach
@endcan</div>
<div class="acc-panel"><h3>Installment Schedule (POSTED repayments only)</h3>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Loan</th><th># / Due</th><th>Principal</th><th>Interest estimate</th><th>Paid (principal / interest)</th><th>Status</th></tr></thead><tbody>
@forelse($installments as $i)
@php $pa=$installmentAllocations->get($i->id,collect()); $pp=$pa->sum('principal_amount'); $pi=$pa->sum('interest_amount'); $dueP=round((float)$i->principal_due-$pp,4); $dueI=round((float)$i->interest_due-$pi,4); @endphp
<tr><td>{{ $i->loan_reference }}</td><td>{{ $i->installment_no }} / {{ $i->due_date }}</td><td>{{ number_format((float)$i->principal_due,2) }}</td><td>{{ number_format((float)$i->interest_due,2) }}</td><td>{{ number_format($pp,2) }} / {{ number_format($pi,2) }}</td>
<td>@if($dueP<=0.00001 && $dueI<=0.00001)<span class="badge bg-success">Paid</span>@elseif($pp+$pi>0)<span class="badge bg-info text-dark">Partial</span>@elseif($i->due_date<today()->toDateString())<span class="badge bg-danger">Overdue</span>@else<span class="badge bg-secondary">Upcoming</span>@endif</td></tr>
@can('accounting-company-loans') @if($pa->isEmpty())
<tr><td colspan="6"><form class="d-flex flex-wrap gap-2 align-items-center" method="post" action="{{ route('accounting.loans.installment-adjust',$i->id) }}" data-acc-confirm="Adjust forecast due date/interest without posting?">@csrf
<span class="acc-hint">Update #{{ $i->installment_no }} (unsigned bank schedule)</span>
<input type="date" name="due_date" value="{{ $i->due_date }}" class="form-control form-control-sm" style="width:150px" required>
<input type="number" name="interest_due" value="{{ $i->interest_due }}" min="0" step="0.0001" class="form-control form-control-sm" style="width:130px" required title="Scheduled interest">
<input name="reason" minlength="8" class="form-control form-control-sm" style="width:220px" required placeholder="Signed agreement / reason"><button class="btn btn-sm btn-outline-primary">Update Forecast</button></form></td></tr>
@endif @endcan
@empty<tr><td colspan="6">No installment schedules yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="acc-panel"><h3>Record Loan Repayment as Draft</h3>
@can('accounting-company-loans')
<form method="post" action="{{ route('accounting.loans.repay', $loans->first()->id ?? 0) }}" onsubmit="this.action=this.action.replace(/\d+\/repay$/,document.getElementById('repay-loan-id').value+'/repay')">@csrf
<div class="row g-2"><div class="col-6 mb-2"><label>Loan</label><select class="form-select" id="repay-loan-id" required>
@foreach($loans as $loan)
<option value="{{ $loan->id }}">{{ $loan->reference }}</option>
@endforeach
</select></div><div class="col-6 mb-2"><label>Payment Date</label><input class="form-control" type="date" name="paid_on" required></div></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Principal</label><input type="number" step="0.0001" min="0.0001" name="principal" class="form-control" required></div><div class="col-6 mb-2"><label>Interest</label><input type="number" step="0.0001" min="0" name="interest" value="0" class="form-control" required></div></div>
<div class="mb-2"><label>Installment (required for scheduled loans)</label><select name="installment_id" class="form-select"><option value="">No schedule / legacy repayment</option>
@foreach($installments as $installment)<option value="{{ $installment->id }}">{{ $installment->loan_reference }} — #{{ $installment->installment_no }} due {{ $installment->due_date }}</option>@endforeach
</select><div class="acc-hint">Select an installment belonging to the loan; posted payments alone update schedule progress.</div></div>
<div class="mb-2"><label>Payment from Cash / Bank Asset Account</label><select class="form-select" name="payment_account_id">
@foreach($accounts->where('type','asset') as $a)
<option value="{{ $a->id }}">{{ $a->code }} – {{ $a->name }}</option>
@endforeach
</select></div>
<div class="mb-2"><label>Unique Payment Reference</label><input name="reference" required class="form-control"></div><button class="btn btn-primary" {{ $loans->isEmpty()?'disabled':'' }}>Create Repayment Draft</button></form>
@endcan
</div></div></div>
<div class="acc-panel"><h3>Repayment Register</h3>
@php 
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($payments, 'acc_company_loans_2'); 
@endphp
<div class="acc-ajax-list" id="acc-list-company-loans-2">
<table class="table table-sm"><thead><tr><th>Loan</th><th>Paid On</th><th>Principal</th><th>Interest</th><th>Journal</th></tr></thead><tbody>
@foreach($accPager2 as $p)
<tr><td>{{ $p->company_loan_id }}</td><td>{{ $p->paid_on }}</td><td>{{ $p->principal }}</td><td>{{ $p->interest }}</td><td><a href="{{ route('accounting.journals.show',$p->journal_entry_id) }}">#{{ $p->journal_entry_id }}</a></td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager2, 'label'=>'payments'])
</div></div></main>
@endsection
