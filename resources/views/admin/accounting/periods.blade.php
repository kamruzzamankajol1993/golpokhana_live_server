@extends('admin.master.master')
@section('title','Fiscal Years & Periods')
@section('body')<main class="progga-content acc-shell">@include('admin.accounting._nav')
<div class="row g-3"><div class="col-lg-4">@can('accounting-period')<div class="acc-panel"><h3>New Fiscal Year</h3>
<p class="acc-hint">Monthly periods will be generated automatically. Choose accountant-approved start/end dates.</p>
<form method="post" action="{{ route('accounting.periods.year') }}">@csrf
<div class="mb-3"><label>Year Name</label><input class="form-control" name="name" placeholder="FY 2026-27" required></div>
<div class="mb-3"><label>Starts</label><input type="date" class="form-control" name="starts_on" required></div>
<div class="mb-3"><label>Ends</label><input type="date" class="form-control" name="ends_on" required></div>
<button class="btn btn-primary">Create Fiscal Year</button></form></div>@endcan
<div class="acc-panel"><h3>Fiscal Years</h3>@forelse($years as $y)<p class="mb-2"><b>{{ $y->name }}</b> <span class="small">({{ $y->starts_on }} — {{ $y->ends_on }})</span></p>@empty<p>No fiscal year created.</p>@endforelse</div></div>
<div class="col-lg-8"><div class="acc-panel"><h3>Accounting Periods</h3><div class="table-responsive"><table class="table"><thead><tr><th>Period</th><th>Dates</th><th>Status</th><th>Change</th></tr></thead><tbody>
@forelse($periods as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->starts_on }} → {{ $p->ends_on }}</td><td><span class="badge {{ $p->status==='OPEN' ? 'bg-success' : ($p->status==='CLOSED'?'bg-dark':'bg-warning text-dark') }}">{{ $p->status }}</span></td><td>@can('accounting-period')<form method="post" action="{{ route('accounting.periods.change',$p->id) }}" class="d-flex flex-wrap gap-1 align-items-center" onsubmit="return confirm('Change period status? Posting in closed periods is blocked.')">@csrf
<select name="status" class="form-select" style="min-width:125px;width:130px"><option @selected($p->status==='OPEN')>OPEN</option><option @selected($p->status==='SOFT_CLOSED')>SOFT_CLOSED</option><option @selected($p->status==='CLOSED')>CLOSED</option></select><input name="reason" required minlength="8" placeholder="Audit reason" class="form-control" style="max-width:160px"><button class="btn btn-outline-primary btn-sm">Update</button></form>@endcan</td></tr>@empty<tr><td colspan="4">Add a fiscal year to begin posting.</td></tr>@endforelse</tbody></table></div></div></div></div>
</main>@endsection
