{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Accounting Journal Entries')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><div class="d-flex justify-content-between flex-wrap gap-2"><h3>Journal Entries</h3>
@can('accounting-journal-create')
<a class="btn btn-primary btn-sm" href="{{ route('accounting.journals.create') }}">+ New Manual Journal</a>
@endcan
</div>
<form method="get" class="mb-3 d-flex gap-2"><select name="status" class="form-select" style="max-width:190px"><option value="">All Statuses</option>
@foreach(['DRAFT','SUBMITTED','APPROVED','POSTED'] as $s)
<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
@endforeach
</select><button class="btn btn-outline-primary">Filter</button></form>
<div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($entries, 'acc_journals_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-journals-1">
<table class="table table-hover"><thead><tr><th>ID / Voucher</th><th>Date</th><th>Journal</th><th>Narration</th><th>Origin</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($accPager1 as $e)
<tr><td>#{{ $e->id }}<div><b>{{ $e->number ?? 'Unposted' }}</b></div></td><td>{{ $e->entry_date }}</td><td>{{ $e->journal_code }}</td><td>{{ \Illuminate\Support\Str::limit($e->narration,85) }}</td><td>{{ $e->origin }}</td><td><span class="badge {{ $e->status==='POSTED'?'bg-success':($e->status==='DRAFT'?'bg-secondary':'bg-warning text-dark') }}">{{ $e->status }}</span></td><td><a class="btn btn-sm btn-outline-primary acc-icon-action" href="{{ route('accounting.journals.show',$e->id) }}" title="Details" aria-label="Details"><i class="bi bi-eye" aria-hidden="true"></i></a>
@if($e->status==='DRAFT' && $e->origin==='MANUAL' && !$e->accounting_event_id)

@can('accounting-journal-create')
<form method="post" class="d-inline-block ms-1" action="{{ route('accounting.journals.delete',$e->id) }}" data-acc-confirm="Delete this standalone manual DRAFT? Posted, submitted and source-linked journals must be retained.">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger acc-icon-action" title="Delete" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
@endcan

@endif
</td></tr>
@empty
<tr><td colspan="7">No journal entries yet.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'entries'])
</div></div></div>
</main>
@endsection
