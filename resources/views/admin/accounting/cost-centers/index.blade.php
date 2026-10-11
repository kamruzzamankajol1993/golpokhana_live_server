{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Analytic Cost Centers')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="row g-3"><div class="col-lg-5"><div class="acc-panel"><h3>Restaurant Cost Centers</h3><p class="acc-hint">Kitchen, Dining, Delivery etc. are analytic tags, not independent accounting ledgers.</p>
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($centers, 'acc_cost_centers_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-cost-centers-1">
<table class="table table-sm"><thead><tr><th>Code</th><th>Name</th><th>Active</th><th>Action</th></tr></thead><tbody>
@foreach($accPager1 as $c)
<tr><td>{{ $c->code }}</td><td>{{ $c->name }}</td><td>{{ $c->is_active?'Yes':'No' }}</td><td>
@can('accounting-cost-centers')
<form method="post" action="{{ route('accounting.cost-centers.delete',$c->id) }}" data-acc-confirm="Delete unused cost center? Journal and budget allocations must not reference it.">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger acc-icon-action" title="Delete" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
@endcan
</td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'centers'])
</div>
@can('accounting-cost-centers')
<form action="{{ route('accounting.cost-centers.save') }}" method="post">@csrf<div class="mb-2"><label>Code</label><input class="form-control" name="code" maxlength="32" required></div><div class="mb-2"><label>Name</label><input class="form-control" name="name" maxlength="120" required></div><div class="mb-2"><label>Status</label><select name="is_active" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div><button class="btn btn-primary">Save Cost Center</button></form>
@endcan
</div></div>
<div class="col-lg-7"><div class="acc-panel"><h3>Draft Journal Analytic Allocation</h3><p class="acc-hint">Assign a journal line to one or multiple centers, with percentages totalling exactly 100.0000%. Posted journals are immutable; allocations never change Debit or Credit totals.</p>
@can('accounting-cost-centers')
<form action="{{ route('accounting.cost-centers.allocate') }}" method="post">@csrf
<div class="mb-3"><label>Draft Journal Line ID</label><input class="form-control" type="number" min="1" name="journal_line_id" required></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Cost Center 1</label><select name="shares[0][cost_center_id]" class="form-select" required>
@foreach($centers->where('is_active',true) as $c)
<option value="{{ $c->id }}">{{ $c->name }}</option>
@endforeach
</select></div><div class="col-6 mb-2"><label>Percentage 1</label><input class="form-control" type="number" step="0.0001" name="shares[0][percentage]" value="100" required></div></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Cost Center 2 (optional)</label><select name="shares[1][cost_center_id]" class="form-select"><option value="">None</option>
@foreach($centers->where('is_active',true) as $c)
<option value="{{ $c->id }}">{{ $c->name }}</option>
@endforeach
</select></div><div class="col-6 mb-2"><label>Percentage 2</label><input class="form-control" type="number" step="0.0001" name="shares[1][percentage]" placeholder="0"></div></div>
<button class="btn btn-primary">Save Analytic Allocation</button></form>
@endcan
</div></div></div>
<div class="acc-panel"><h3>Recent Allocations</h3>
@php 
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($allocations, 'acc_cost_centers_2'); 
@endphp
<div class="acc-ajax-list" id="acc-list-cost-centers-2">
<table class="table table-sm"><thead><tr><th>Journal</th><th>Center</th><th>Share</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
@foreach($accPager2 as $a)
<tr><td>{{ $a->number ?? $a->status }}</td><td>{{ $a->center }}</td><td>{{ $a->percentage }}%</td><td>{{ $a->debit }}</td><td>{{ $a->credit }}</td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager2, 'label'=>'allocations'])
</div></div></main>
@endsection
