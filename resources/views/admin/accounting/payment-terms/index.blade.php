{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Payment Terms & Follow-up')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Receivables &amp; Payables – Terms / Follow-up</h3><p class="acc-hint">Payment terms are a follow-up dimension only. They never overwrite POS dues or supplier invoice amounts. Aged balance is not recalculated here.</p>
<a href="{{ route('accounting.due-terms',['type'=>'AR']) }}" class="btn btn-sm {{ $type==='AR'?'btn-primary':'btn-outline-secondary' }}">Customer AR</a>
<a href="{{ route('accounting.due-terms',['type'=>'AP']) }}" class="btn btn-sm {{ $type==='AP'?'btn-primary':'btn-outline-secondary' }}">Supplier AP</a>
<div class="table-responsive mt-3">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($receivables, 'acc_payment_terms_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-payment-terms-1">
<table class="table table-sm"><thead><tr><th>Record / Tracking ID</th><th>Original BDT</th><th>Due Date</th><th>Stage</th><th>Next Follow-up</th></tr></thead><tbody>
@forelse($accPager1 as $row)
<tr><td>{{ $type }} #{{ $row->id }}<div class="acc-hint">Tracking: {{ $row->tracking_id ?? "not assigned" }}</div></td><td>{{ number_format((float)($type==='AR'?$row->original_due:$row->original_amount),2) }}</td><td>{{ $row->tracked_due_date ?? $row->due_date ?? 'Not assigned' }}</td><td>{{ $row->followup_status ?? '—' }}</td><td>{{ $row->next_followup_on ?? '—' }}</td></tr>
@empty
<tr><td colspan="5">No {{ $type }} records.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'receivables'])
</div></div></div>
<div class="row g-3"><div class="col-lg-6"><div class="acc-panel"><h3>Set Due Term</h3>
@can('accounting-due-terms')
<form method="post" action="{{ route('accounting.due-terms.save') }}">@csrf<input type="hidden" name="source_type" value="{{ $type }}">
<div class="mb-2"><label>{{ $type }} Record ID</label><input class="form-control" type="number" name="source_id" min="1" required></div>
<div class="mb-2"><label>Payment Terms</label><select class="form-select" name="payment_term_id" required>
@foreach($terms as $t)
<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->days_due }} days)</option>
@endforeach
</select></div>
<div class="mb-2"><label>Original Invoice Date</label><input type="date" name="invoice_date" class="form-control" required></div><button class="btn btn-primary">Assign Follow-up Due Date</button></form>
@endcan
</div></div>
<div class="col-lg-6"><div class="acc-panel"><h3>Record Follow-up</h3>
@can('accounting-due-terms')
<form method="post" action="{{ route('accounting.due-terms.followup') }}">@csrf
<div class="mb-2"><label>Tracking ID (assign a term first)</label><input class="form-control" type="number" name="tracking_id" required min="1"></div>
<div class="mb-2"><label>Stage</label><select class="form-select" name="stage">
@foreach(['OPEN','CONTACTED','PROMISED','DISPUTED','RESOLVED'] as $stage)
<option>{{ $stage }}</option>
@endforeach
</select></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Promise to Pay</label><input type="date" name="promise_to_pay_on" class="form-control"></div><div class="col-6 mb-2"><label>Next Follow-up</label><input type="date" name="next_followup_on" class="form-control"></div></div>
<div class="mb-2"><label>Notes / Remarks</label><textarea class="form-control" name="note" required minlength="5" rows="2"></textarea></div><button class="btn btn-primary">Save Follow-up</button></form>
@endcan
</div></div></div>
<div class="acc-panel"><h3>Recent Follow-up History</h3>
@php 
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($followups, 'acc_payment_terms_2'); 
@endphp
<div class="acc-ajax-list" id="acc-list-payment-terms-2">
<table class="table table-sm"><thead><tr><th>Record</th><th>Stage</th><th>Promise Date</th><th>Next Action</th><th>Note</th></tr></thead><tbody>
@foreach($accPager2 as $f)
<tr><td>{{ $type }} #{{ $f->source_id }}</td><td>{{ $f->stage }}</td><td>{{ $f->promise_to_pay_on }}</td><td>{{ $f->next_followup_on }}</td><td>{{ $f->note }}</td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager2, 'label'=>'followups'])
</div></div></main>
@endsection
