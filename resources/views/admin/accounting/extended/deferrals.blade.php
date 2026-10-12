@extends('admin.master.master')
@section('title','Accruals, Prepayments & Deferrals')
@section('body')<main class="progga-content acc-shell">@include('admin.accounting._nav')
<div class="row g-3"><div class="col-lg-5"><div class="acc-panel"><h3>New Deferral / Prepayment Schedule</h3>
<p class="acc-hint">Start with a POSTED funding/accrual journal, then recognize the benefit in periods. Each month creates a DRAFT journal that requires independent approval. Never book cash twice.</p>
@can('accounting-deferrals')<form action="{{ route('accounting.deferrals.save') }}" method="post">@csrf
<div class="mb-2"><label>Type</label><select class="form-select" name="type"><option value="PREPAYMENT">Prepaid Expense</option><option value="DEFERRED_REVENUE">Deferred Revenue</option></select></div>
<div class="mb-2"><label>Name</label><input name="name" class="form-control" required maxlength="160"></div>
<div class="mb-2"><label>Posted Source Journal ID</label><input name="source_journal_entry_id" type="number" min="1" class="form-control" required></div>
<div class="mb-2"><label>Balance Sheet Account</label><select name="balance_account_id" class="form-select">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
<div class="mb-2"><label>Recognition Account (Expense or Revenue)</label><select name="recognition_account_id" class="form-select">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
<div class="mb-2"><label>Amount BDT</label><input class="form-control" name="total_amount" type="number" step="0.0001" min="0.0001" required></div>
<div class="row g-2"><div class="col-7 mb-2"><label>First Month (1st date)</label><input class="form-control" type="date" name="start_month" required></div><div class="col-5 mb-2"><label>Months</label><input class="form-control" type="number" name="months" value="12" min="1" max="240" required></div></div>
<button class="btn btn-primary">Create Schedule</button></form>@endcan</div></div>
<div class="col-lg-7"><div class="acc-panel"><h3>Recognitions Due / Generated</h3><p class="acc-hint">Generate creates a reviewed DRAFT; it does not bypass period lock or maker-checker.</p>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Schedule</th><th>Month</th><th>BDT</th><th>Status / Journal</th></tr></thead><tbody>
@forelse($lines as $l)<tr><td>{{ $l->name }}</td><td>{{ $l->period_month }}</td><td>{{ number_format((float)$l->amount,4) }}</td><td>
@if($l->journal_entry_id)<a href="{{ route('accounting.journals.show',$l->journal_entry_id) }}">{{ $l->journal_status }} #{{ $l->journal_entry_id }}</a>
@else @can('accounting-deferrals')<form method="post" action="{{ route('accounting.deferrals.generate',$l->id) }}">@csrf<button class="btn btn-sm btn-outline-primary">Generate Draft</button></form>@endcan @endif</td></tr>
@empty<tr><td colspan="4">No scheduled recognitions.</td></tr>@endforelse</tbody></table></div></div></div></div>
<div class="acc-panel"><h3>Schedules</h3><table class="table table-sm"><thead><tr><th>Name</th><th>Type</th><th>Source Journal</th><th>Total</th><th>Months</th><th>Status</th></tr></thead><tbody>@foreach($schedules as $s)<tr><td>{{ $s->name }}</td><td>{{ $s->type }}</td><td><a href="{{ route('accounting.journals.show',$s->source_journal_entry_id) }}">#{{ $s->source_journal_entry_id }}</a></td><td>{{ number_format((float)$s->total_amount,2) }}</td><td>{{ $s->months }}</td><td>{{ $s->status }}</td></tr>@endforeach</tbody></table>{{ $schedules->links() }}</div></main>@endsection
