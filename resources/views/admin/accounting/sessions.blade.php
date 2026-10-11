{{-- Legacy Accounting template: use explicit Blade control-flow boundaries for safe compilation. --}}
@extends('admin.master.master')
@section('title','POS Cash Reconciliation')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Closed POS Session Cash Checks</h3><p class="acc-hint">Expected = opening float + Cash portions of POSTED accounting events during session. Unposted source events, operator withdrawals and other unknown cash movements must be reviewed separately. A variance is not automatically an Income or Expense.</p><div class="table-responsive"><table class="table"><thead><tr><th>Session</th><th>Open / Close</th><th>Opening</th><th>Reconciled Amounts</th><th>Counted Drawer Cash</th></tr></thead><tbody>
@forelse($sessions as $s)

@php($rec=$reconciliations[$s->id]??null)
<tr><td>#{{ $s->id }}<div class="acc-hint">Employee #{{ $s->user_id }}</div></td><td>{{ $s->start_time }}<br>{{ $s->end_time }}</td><td>{{ number_format($s->opening_balance,2) }}</td><td>
@if($rec)

@if($rec->status==='REVIEW' && (float)$rec->variance==0 && (int)$rec->checked_by!==(int)auth()->id())

@can('accounting-reconcile')
<form method="post" action="{{ route('accounting.sessions.approve',$s->id) }}">@csrf<input type="text" name="reason" minlength="12" required placeholder="Review explanation" class="form-control form-control-sm mb-1"><button class="btn btn-sm btn-success acc-icon-action" title="Approve reconciliation" aria-label="Approve reconciliation"><i class="bi bi-patch-check" aria-hidden="true"></i></button></form>
@endcan

@endif
 Expected {{ $rec->expected_cash }}<br>Counted {{ $rec->counted_cash }}<br>Difference {{ $rec->variance }} ({{ $rec->status }})
@else
Not reconciled
@endif
</td><td>
@can('accounting-reconcile')
<form method="post" action="{{ route('accounting.sessions.reconcile',$s->id) }}">@csrf<div class="d-flex gap-1"><input name="counted_cash" type="number" class="form-control" min="0" step="0.0001" required placeholder="Actual cash"><button class="btn btn-sm btn-outline-primary acc-icon-action" title="Review cash reconciliation" aria-label="Review cash reconciliation"><i class="bi bi-clipboard-check" aria-hidden="true"></i></button></div><textarea class="form-control mt-1" name="reason" rows="2" minlength="10" maxlength="2000" required placeholder="Withdrawal and variance explanation"></textarea></form>
@endcan
</td></tr>
@empty
<tr><td colspan="5">No closed sessions.</td></tr>
@endforelse
</tbody></table></div></div></main>
@endsection
