{{-- Legacy Accounting template: use explicit Blade control-flow boundaries for safe compilation. --}}
@extends('admin.master.master')
@section('title','Journal Entry Details')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><div class="d-flex justify-content-between flex-wrap gap-2"><h3>{{ $entry->number ?? 'Draft #'.$entry->id }} · {{ $entry->journal_name }}</h3><span class="badge {{ $entry->status==='POSTED'?'bg-success':'bg-warning text-dark' }}">{{ $entry->status }}</span></div>
<div class="row small mb-3"><div class="col-md-4"><b>Entry Date</b>: {{ $entry->entry_date }}</div><div class="col-md-4"><b>Business Date</b>: {{ $entry->business_date ?? '—' }}</div><div class="col-md-4"><b>Reference</b>: {{ $entry->reference ?? '—' }}</div></div>
<p><b>Narration:</b> {{ $entry->narration }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>GL Account</th><th>Cost Center</th><th>Memo</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead><tbody>
@php 
$totalDr=0;$totalCr=0; 
@endphp
@foreach($lines as $l)

@php 
$totalDr+=\App\Services\Accounting\AccountingMoney::units((string)$l->debit);$totalCr+=\App\Services\Accounting\AccountingMoney::units((string)$l->credit); 
@endphp
<tr><td>{{ $l->code }} — {{ $l->account_name }}</td><td>{{ $l->cost_center_name ?? "—" }}</td><td>{{ $l->memo }}</td><td class="text-end">{{ $l->debit }}</td><td class="text-end">{{ $l->credit }}</td></tr>
@endforeach
</tbody><tfoot><tr class="fw-bold"><td colspan="3">Totals</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($totalDr) }}</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($totalCr) }}</td></tr></tfoot></table></div>
<p class="small {{ $totalDr===$totalCr ? 'text-success':'text-danger' }}">{{ $totalDr===$totalCr ? 'Balanced at fixed 4-decimal precision' : 'Debits and credits do not balance' }}</p>
<div class="d-flex gap-2 flex-wrap mb-3">
@if($entry->status==='DRAFT')

@can('accounting-journal-create')
<a href="{{ route('accounting.journals.edit',$entry->id) }}" class="btn btn-outline-primary">Edit Draft</a>
<form method="post" action="{{ route('accounting.journals.action',$entry->id) }}">@csrf<input type="hidden" name="action" value="SUBMIT"><button class="btn btn-primary">Submit for Approval</button></form>
@endcan

@endif
@if($entry->status==='SUBMITTED')

@can('accounting-journal-approve')
<form method="post" action="{{ route('accounting.journals.action',$entry->id) }}">@csrf<input type="hidden" name="action" value="APPROVE"><button class="btn btn-success" onclick="return confirm('Approve this balanced journal?')">Approve</button></form>
<form method="post" action="{{ route('accounting.journals.action',$entry->id) }}" class="d-flex gap-1">@csrf<input type="hidden" name="action" value="REJECT"><input name="reason" class="form-control" required minlength="8" placeholder="Reason for rejection"><button class="btn btn-outline-danger">Reject</button></form>
@endcan

@endif
@if($entry->status==='APPROVED')

@can('accounting-journal-post')
<form method="post" action="{{ route('accounting.journals.action',$entry->id) }}">@csrf<input type="hidden" name="action" value="POST"><button class="btn btn-primary" onclick="return confirm('Post journal permanently? Changes require a separate reversal.')">Post to General Ledger</button></form>
@endcan

@endif
@if($entry->status==='POSTED' && !$reverse && !$entry->reversal_of_id)

@can('accounting-journal-reverse')
<form method="post" class="d-flex gap-1 flex-wrap" action="{{ route('accounting.journals.action',$entry->id) }}" onsubmit="return confirm('Create a reversal DRAFT? Original posted entry stays immutable.')">@csrf<input type="hidden" name="action" value="REVERSE"><input type="date" class="form-control" style="width:170px" name="date" value="{{ now()->toDateString() }}" required><input name="reason" class="form-control" style="width:220px" placeholder="Reversal reason" required minlength="8"><button class="btn btn-outline-danger">Request Reversal</button></form>
@endcan

@endif
</div>
@if($reverse)
<p class="alert alert-info">Linked reversal draft/journal: <a href="{{ route('accounting.journals.show',$reverse->id) }}">#{{ $reverse->id }}</a></p>
@endif
</div>
<div class="row g-3"><div class="col-lg-6"><div class="acc-panel"><h3>Supporting Documents</h3>
@foreach($attachments as $attachment)
<p class="mb-1"><a href="{{ route('accounting.journals.attachment',$attachment->id) }}">{{ $attachment->filename }}</a></p>
@endforeach
@if($entry->status==='DRAFT')

@can('accounting-journal-create')
<form method="post" enctype="multipart/form-data" action="{{ route('accounting.journals.upload',$entry->id) }}">@csrf<input type="file" name="attachment" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png" required><button class="btn btn-sm btn-outline-primary">Upload (max 5 MB)</button></form>
@endcan

@endif
</div></div>
<div class="col-lg-6"><div class="acc-panel"><h3>Approval & Posting Audit</h3><div class="table-responsive"><table class="table"><thead><tr><th>Action</th><th>User</th><th>Time</th><th>Reason</th></tr></thead><tbody>
@forelse($approvals as $a)
<tr><td>{{ $a->action }}</td><td>#{{ $a->user_id }}</td><td>{{ $a->created_at }}</td><td>{{ $a->reason }}</td></tr>
@empty
<tr><td colspan="4">Not yet submitted.</td></tr>
@endforelse
</tbody></table></div></div></div></div>
</main>
@endsection
