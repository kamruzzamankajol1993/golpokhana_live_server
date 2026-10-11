{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Accounting Budget vs Actual')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
@php 
$money=fn($v)=>'৳'.number_format($v/10000,4); 
@endphp
<div class="row g-3"><div class="col-lg-4"><div class="acc-panel"><h3>Create Fiscal Budget Draft</h3>
@can('accounting-budget')
<form method="post" action="{{ route('accounting.budgets.create') }}">@csrf<div class="mb-2"><label>Fiscal Year</label><select class="form-select" name="fiscal_year_id" required>
@foreach($years as $y)
<option value="{{ $y->id }}">{{ $y->name }}</option>
@endforeach
</select></div><div class="mb-2"><label>Budget Name</label><input name="name" class="form-control" placeholder="Restaurant Operating Budget" required></div><button class="btn btn-primary">Create Draft</button></form>
@endcan
</div></div><div class="col-lg-8"><div class="acc-panel"><h3>Budget Versions</h3><div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($budgets, 'acc_budgets_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-budgets-1">
<table class="table table-sm"><thead><tr><th>Fiscal Year</th><th>Budget</th><th>Status</th><th>Open</th></tr></thead><tbody>
@foreach($accPager1 as $b)
<tr><td>{{ $b->year_name }}</td><td>{{ $b->name }}</td><td>{{ $b->status }}</td><td><a href="{{ route('accounting.budgets',['budget_id'=>$b->id]) }}" class="btn btn-sm btn-outline-primary acc-icon-action" title="View budget" aria-label="View budget"><i class="bi bi-eye" aria-hidden="true"></i></a>
{{-- Only unapproved drafts expose Delete; approved budgets are immutable. --}}
@if($b->status==='DRAFT')

@can('accounting-budget')
<form method="post" class="d-inline-block ms-2" action="{{ route('accounting.budgets.delete',$b->id) }}" data-acc-confirm="Delete this DRAFT budget and its planned lines? Approved budgets cannot be deleted.">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger acc-icon-action" title="Delete" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
@endcan

@endif
</td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'budgets'])
</div></div></div></div></div>
@if($budget)
<div class="acc-panel"><h3>{{ $budget->name }} — {{ $budget->status }}</h3><p class="acc-hint">Actuals are posted revenue/expense movements, excluding year-end closing journals; departments are optional analytic dimensions only. Approved budgets are immutable.</p>
@if($budget->status==='DRAFT')

@can('accounting-budget')
<h5 class="mt-3">Add Planned Amount (BDT)</h5><form class="row g-2 align-items-end mb-3" method="post" action="{{ route('accounting.budgets.lines',$budget->id) }}">@csrf<div class="col-lg-2"><label>Period</label><select name="period_id" class="form-select">
@foreach($periods as $p)
<option value="{{ $p->id }}">{{ $p->name }}</option>
@endforeach
</select></div><div class="col-lg-3"><label>Income / Expense Account</label><select class="form-select" name="account_id">
@foreach($accounts as $a)
<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>
@endforeach
</select></div><div class="col-lg-2"><label>Cost Center</label><select name="cost_center_id" class="form-select"><option value="">Company Total</option>
@foreach($centers as $c)
<option value="{{ $c->id }}">{{ $c->name }}</option>
@endforeach
</select></div><div class="col-lg-2"><label>Amount</label><input name="amount" type="number" min="0" step="0.0001" class="form-control" required></div><div class="col-lg-2"><label>Notes</label><input name="notes" class="form-control"></div><div class="col-lg-1"><button class="btn btn-primary">Add</button></div></form>
@endcan
@can('accounting-budget-approve')
<form method="post" action="{{ route('accounting.budgets.approve',$budget->id) }}" class="d-flex gap-2 mb-3">@csrf<input name="reason" class="form-control" required minlength="8" placeholder="Independent approval reason"><button class="btn btn-outline-success">Approve & Lock</button></form>
@endcan
 
@endif
<div class="table-responsive">
@php 
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($rows, 'acc_budgets_2'); 
@endphp
<div class="acc-ajax-list" id="acc-list-budgets-2">
<table class="table table-sm"><thead><tr><th>Month</th><th>GL Account</th><th>Cost Center</th><th class="text-end">Budget</th><th class="text-end">Posted Actual</th><th class="text-end">Actual - Budget</th></tr></thead><tbody>
@foreach($accPager2 as $r)
<tr><td>{{ $r['line']->period_name }}</td><td>{{ $r['line']->code }} {{ $r['line']->account_name }}</td><td>{{ $r['line']->center_name??'Company Total' }}</td><td class="text-end">{{ $money($r['target']) }}</td><td class="text-end">{{ $money($r['actual']) }}</td><td class="text-end">{{ $money($r['variance']) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th colspan="3">Total (lines may mix company totals and cost-centre budgets)</th><th class="text-end">{{ $money($budgetTotal) }}</th><th class="text-end">{{ $money($actualTotal) }}</th><th class="text-end">{{ $money($actualTotal-$budgetTotal) }}</th></tr></tfoot></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager2, 'label'=>'rows'])
</div></div></div>
@endif
</main>
@endsection
