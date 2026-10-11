{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Inventory Cost & Exceptions')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Verified Inventory Cost Balances</h3><p class="acc-hint">This is the captured verified-cost subledger, not the operational stock quantity ledger. Capture OFF periods are not valued or backfilled automatically.</p><div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($balances, 'acc_inventory_cost_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-inventory-cost-1">
<table class="table"><thead><tr><th>Location</th><th>Ingredient</th><th>Verified Quantity</th><th>Verified Value (BDT)</th></tr></thead><tbody>
@forelse($accPager1 as $b)
<tr><td>{{ $b->location }}</td><td>{{ $b->ingredient }}</td><td>{{ $b->verified_qty }}</td><td>{{ number_format($b->verified_value,4) }}</td></tr>
@empty
<tr><td colspan="4">No verified cost balances.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'balances'])
</div></div></div>
<div class="acc-panel"><h3>Unverified / Negative Cost Pending</h3><p class="acc-hint">Physical stock is NOT blocked. Do not treat an unverified portion as posted COGS. Receipt/transfer exceptions require matching before accountant adjustment.</p><div class="table-responsive">
@php 
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($pending, 'acc_inventory_cost_2'); 
@endphp
<div class="acc-ajax-list" id="acc-list-inventory-cost-2">
<table class="table"><thead><tr><th>Movement</th><th>Ingredient</th><th>Qty Pending</th><th>Reason / Status</th><th>Cost Review</th></tr></thead><tbody>
@forelse($accPager2 as $p)
<tr><td>{{ $p->movement_no }}<div class="acc-hint">{{ $p->movement_type }}</div></td><td>#{{ $p->ingredient_id }}<div class="acc-hint">Order #{{ $p->order_id }}</div></td><td>{{ $p->quantity_base }}</td><td><span class="badge bg-warning text-dark">{{ $p->status }}</span><div class="acc-hint">{{ $p->reason }}</div></td><td>
@if($p->status==='COST_PENDING' && $p->movement_type==='ORDER_CONSUMPTION')

@can('accounting-cost-review')
<form data-acc-confirm="Are the cost and supporting evidence verified? GL does not change until approved posting." method="post" action="{{ route('accounting.costs.verify',$p->id) }}">@csrf<div class="d-flex gap-1"><input class="form-control" type="number" step="0.0001" min="0.0001" name="verified_cost" placeholder="BDT cost" required><button class="btn btn-outline-primary btn-sm acc-icon-action" title="Submit cost review" aria-label="Submit cost review"><i class="bi bi-check-circle" aria-hidden="true"></i></button></div><textarea name="reason" class="form-control mt-1" minlength="10" maxlength="3000" rows="2" required placeholder="GRN reference / documented cost proof"></textarea></form>
@endcan

@else
<small class="text-muted">Financial review required</small>
@endif
</td></tr>
@empty
<tr><td colspan="5">No pending cost exceptions.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager2, 'label'=>'pending'])
</div></div></div></main>
@endsection
