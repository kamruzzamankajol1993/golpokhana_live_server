{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Pending Accounting Events')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><div class="d-flex justify-content-between flex-wrap gap-2"><h3>Pending Accounting Event Queue</h3><span class="acc-hint">Only ON-window source events appear</span></div>
<form method="get" class="d-flex gap-2 mb-3 flex-wrap"><input name="source_type" class="form-control" placeholder="Source type" style="max-width:180px" value="{{ request('source_type') }}"><select name="status" class="form-select" style="max-width:200px"><option value="">All statuses</option>
@foreach(['PENDING','MAPPING_ERROR','DRAFT','APPROVED','POSTED','SUPERSEDED'] as $status)
<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>
@endforeach
</select><button class="btn btn-primary">Filter</button></form>
@if(session('bulk_generate_errors'))
<div class="alert alert-warning" role="alert"><strong>Bulk generate exceptions:</strong><ul class="mb-0">@foreach(session('bulk_generate_errors') as $failure)<li>{{ $failure }}</li>@endforeach</ul></div>
@endif
@can('accounting-event-process')
<form id="accounting-bulk-draft-form" method="post" action="{{ route('accounting.events.bulk-generate') }}" data-acc-confirm="Generate separate draft journals for selected pending events?">
@csrf
<input type="hidden" name="status" value="{{ request('status') }}">
<input type="hidden" name="source_type" value="{{ request('source_type') }}">
<div class="d-flex gap-2 align-items-center flex-wrap mb-2">
<label class="d-flex gap-2 align-items-center mb-0"><input id="accounting-select-visible" type="checkbox" aria-label="Select all eligible events on this page"> Select eligible on page</label>
<button type="submit" class="btn btn-primary btn-sm" id="accounting-bulk-generate-btn" disabled>Bulk Generate Draft (<span id="accounting-selected-count">0</span>)</button>
<span class="acc-hint">Maximum 100 events per batch. Each creates its own draft for review; nothing is posted automatically.</span>
</div>
</form>
@endcan
<div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($events, 'acc_pending_events_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-pending-events-1">
<table class="table table-hover"><thead><tr><th>Select</th><th>ID</th><th>Source / Version</th><th>Occurred / Business Date</th><th>Amount</th><th>Status / Errors</th><th>Action</th></tr></thead><tbody>
@forelse($accPager1 as $event)
<tr><td>
@if(auth()->user()->can('accounting-event-process') && !$event->journal_entry_id && in_array($event->status,['PENDING','MAPPING_ERROR'],true))
<input type="checkbox" form="accounting-bulk-draft-form" class="accounting-event-select" name="event_ids[]" value="{{ $event->id }}" aria-label="Select event {{ $event->id }}">
@endif
</td><td>#{{ $event->id }}</td><td><b>{{ $event->source_type }} / {{ $event->source_id }}</b><div class="acc-hint">{{ $event->event_type }} · v{{ $event->source_version }}</div></td><td>{{ $event->occurred_at }}<div class="acc-hint">{{ $event->business_date }}</div></td><td>৳{{ number_format((float)$event->amount,2) }}</td><td><span class="badge {{ $event->status==='POSTED'?'bg-success':'bg-warning text-dark' }}">{{ $event->status }}</span>
@if($event->last_error)
<div class="text-danger small">{{ $event->last_error }}</div>
@endif
</td>
<td>
@if($event->journal_entry_id)
<a class="btn btn-sm btn-outline-primary acc-icon-action" href="{{ route('accounting.journals.show',$event->journal_entry_id) }}" title="View journal" aria-label="View journal"><i class="bi bi-journal-text" aria-hidden="true"></i></a>
@elseif(auth()->user()->can('accounting-event-process') && in_array($event->status,['PENDING','MAPPING_ERROR'],true))
<form data-acc-confirm="Generate journal draft from saved source snapshot?" method="post" action="{{ route('accounting.events.generate',$event->id) }}">@csrf<button class="btn btn-sm btn-primary acc-icon-action" title="Generate draft or retry" aria-label="Generate draft or retry"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button></form>
@if(auth()->user()->can('accounting-journal-post'))
<form data-acc-confirm="Generate and POST only if approval policy permits?" method="post" class="d-inline-block mt-1" action="{{ route('accounting.events.generate-post',$event->id) }}">@csrf<button class="btn btn-sm btn-outline-success" title="Permission and approval policy checked server-side">Generate &amp; Post</button></form>
@endif
@endif
</td></tr>
@empty
<tr><td colspan="7">No accounting events yet. Capture OFF or no qualifying source transactions yet.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'events'])
@can('accounting-event-process')
<script>
(function(){
    const form=document.getElementById('accounting-bulk-draft-form');
    if(!form) return;
    const selected=()=>Array.from(document.querySelectorAll('#acc-list-pending-events-1 .accounting-event-select'));
    const all=document.getElementById('accounting-select-visible');
    const button=document.getElementById('accounting-bulk-generate-btn');
    const count=document.getElementById('accounting-selected-count');
    function refresh(){ const boxes=selected();const chosen=boxes.filter(el=>el.checked).length;
        count.textContent=chosen;button.disabled=!chosen;
        all.checked=boxes.length>0 && boxes.every(el=>el.checked);
        all.indeterminate=chosen>0 && chosen<boxes.length;
    }
    all.addEventListener('change',()=>{selected().forEach(el=>el.checked=all.checked);refresh();});
    document.addEventListener('change',e=>{if(e.target.matches('#acc-list-pending-events-1 .accounting-event-select'))refresh();});
    document.addEventListener('accounting:pageLoaded',e=>{if(e.detail.target?.id==='acc-list-pending-events-1')refresh();});
    form.addEventListener('submit',e=>{if(!selected().some(el=>el.checked))e.preventDefault();});
    refresh();
})();
</script>
@endcan
</div></div></div>
</main>
@endsection
