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
<div class="acc-panel"><h3>Record Loan Repayment as Draft</h3>
@can('accounting-company-loans')
<form method="post" action="{{ route('accounting.loans.repay', $loans->first()->id ?? 0) }}" onsubmit="this.action=this.action.replace(/\d+\/repay$/,document.getElementById('repay-loan-id').value+'/repay')">@csrf
<div class="row g-2"><div class="col-6 mb-2"><label>Loan</label><select class="form-select" id="repay-loan-id" required>
@foreach($loans as $loan)
<option value="{{ $loan->id }}">{{ $loan->reference }}</option>
@endforeach
</select></div><div class="col-6 mb-2"><label>Payment Date</label><input class="form-control" type="date" name="paid_on" required></div></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Principal</label><input type="number" step="0.0001" min="0.0001" name="principal" class="form-control" required></div><div class="col-6 mb-2"><label>Interest</label><input type="number" step="0.0001" min="0" name="interest" value="0" class="form-control" required></div></div>
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
