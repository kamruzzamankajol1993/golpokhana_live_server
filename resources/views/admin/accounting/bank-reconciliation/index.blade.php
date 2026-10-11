{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Bank Statement Matching')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="row g-3"><div class="col-lg-5"><div class="acc-panel"><h3>Enter Bank Statement Line</h3>
<p class="acc-hint">Bank statement reference is not a journal. Only existing POSTED Bank GL entries can be matched. No GL value changes during matching.</p>
@can('accounting-bank-match')
<form method="post" action="{{ route('accounting.bank.save') }}">@csrf
<div class="mb-2"><label>Bank Payment Account</label><select class="form-select" name="payment_account_id" required>
@foreach($accounts as $a)
<option value="{{ $a->id }}">{{ $a->name }}</option>
@endforeach
</select></div>
<div class="mb-2"><label>Statement Date</label><input class="form-control" name="statement_date" type="date" required value="{{ date('Y-m-d') }}"></div>
<div class="mb-2"><label>Statement Reference</label><input class="form-control" name="statement_reference" required maxlength="180"></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Direction</label><select name="direction" class="form-select"><option value="IN">Money In</option><option value="OUT">Money Out</option></select></div>
<div class="col-6 mb-2"><label>BDT Amount</label><input type="number" step="0.0001" min="0.0001" name="amount" class="form-control" required></div></div>
<div class="mb-2"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div><button class="btn btn-primary">Save Unmatched Statement Line</button></form>
@endcan
</div></div>
<div class="col-lg-7"><div class="acc-panel"><h3>Statement Matching</h3><div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($lines, 'acc_bank_reconciliation_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-bank-reconciliation-1">
<table class="table table-sm"><thead><tr><th>Bank / Reference</th><th>BDT</th><th>Status</th><th>Matched GL Journal</th></tr></thead><tbody>
@forelse($accPager1 as $l)
<tr><td>{{ $l->account_name }}<div class="acc-hint">{{ $l->statement_reference }} · {{ $l->statement_date }}</div></td><td>{{ $l->direction }} {{ number_format((float)$l->amount,2) }}</td><td>{{ $l->status }}</td><td>
@if($l->matched_journal_entry_id)
<a href="{{ route('accounting.journals.show',$l->matched_journal_entry_id) }}">Journal #{{ $l->matched_journal_entry_id }}</a>
@can('accounting-bank-match')
<form class="mt-1 d-flex gap-1" method="post" action="{{ route('accounting.bank.unmatch',$l->id) }}">@csrf<input class="form-control form-control-sm" name="reason" required minlength="8" placeholder="Reason to unmatch"><button class="btn btn-sm btn-outline-danger acc-icon-action" title="Unmatch journal" aria-label="Unmatch journal"><i class="bi bi-link-45deg" aria-hidden="true"></i></button></form>
@endcan
@else
 
@can('accounting-bank-match')
<form method="post" action="{{ route('accounting.bank.match',$l->id) }}" class="d-flex gap-1">@csrf<input class="form-control form-control-sm" type="number" name="journal_entry_id" required placeholder="Posted Journal ID"><button class="btn btn-sm btn-outline-primary acc-icon-action" title="Match journal" aria-label="Match journal"><i class="bi bi-link-45deg" aria-hidden="true"></i></button></form>
<form method="post" class="mt-1" action="{{ route('accounting.bank.delete',$l->id) }}" data-acc-confirm="Delete this unmatched statement line? Matched statement lines must be unlinked first.">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger acc-icon-action" title="Delete" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
@endcan
 
@endif
</td></tr>
@empty
<tr><td colspan="4">No bank statement lines.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'lines'])
</div></div></div></div></div>
<div class="acc-panel"><h3>Recent Unmatched Posted Bank GL Lines</h3>
@php 
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($journals, 'acc_bank_reconciliation_2'); 
@endphp
<div class="acc-ajax-list" id="acc-list-bank-reconciliation-2">
<table class="table table-sm"><thead><tr><th>ID</th><th>Journal No</th><th>Bank</th><th>Date</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
@foreach($accPager2 as $j)
<tr><td>{{ $j->id }}</td><td>{{ $j->number }}</td><td>{{ $j->name }}</td><td>{{ $j->entry_date }}</td><td>{{ $j->debit }}</td><td>{{ $j->credit }}</td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager2, 'label'=>'journals'])
</div></div></main>
@endsection
