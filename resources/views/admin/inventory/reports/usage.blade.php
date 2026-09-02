@extends('admin.master.master')
@section('title','Inventory Usage Report')
@section('body')
<main class="progga-content">
<div class="progga-page-header"><div><h1 class="progga-page-title">Purchase & Ingredient Usage</h1><p class="text-muted mb-0">All quantities stay in each ingredient's base unit. Incompatible units are never added together.</p></div><a href="{{ route('reports.inventory.index', request()->only('branch_id')) }}" class="progga-btn progga-btn-light">Report Home</a></div>
<div class="progga-card mb-4"><div class="p-3"><form method="GET" class="row g-2 align-items-end">
@if($branches->count()>1 || $allBranches)<div class="col-md-3"><label class="progga-form-label">Branch</label><select name="branch_id" class="progga-form-control"><option value="all" @selected(request('branch_id')==='all' || ($allBranches && !request()->has('branch_id')))>All Branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@endif
<div class="col-md-3"><label class="progga-form-label">From</label><input type="text" name="date_from" value="{{ request('date_from',$start->toDateString()) }}" class="progga-form-control progga-datepicker"></div>
<div class="col-md-3"><label class="progga-form-label">To</label><input type="text" name="date_to" value="{{ request('date_to',$end->toDateString()) }}" class="progga-form-control progga-datepicker"></div>
<div class="col-md-3"><button class="progga-btn progga-btn-primary">Apply</button></div>
</form></div></div>

<div class="progga-card mb-4"><div class="progga-card-header"><div><strong>Ingredient-wise Purchase & Usage</strong><div class="text-muted small">{{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}</div></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr>@if($allBranches)<th>Branch</th>@endif<th>Ingredient</th><th class="text-end">Purchased</th><th class="text-end">Order Consumption</th><th class="text-end">Wastage</th></tr></thead><tbody>
@forelse($rows as $row)<tr>@if($allBranches)<td>{{ $row->branch_name }}</td>@endif<td><strong>{{ $row->ingredient_name }}</strong></td><td class="text-end">{{ rtrim(rtrim((string)$row->purchased_base,'0'),'.') }} {{ $row->base_unit_symbol }}</td><td class="text-end">{{ rtrim(rtrim((string)$row->consumed_base,'0'),'.') }} {{ $row->base_unit_symbol }}</td><td class="text-end">{{ rtrim(rtrim((string)$row->wastage_base,'0'),'.') }} {{ $row->base_unit_symbol }}</td></tr>@empty<tr><td colspan="{{ $allBranches?5:4 }}" class="text-center text-muted py-5">No posted purchase/consumption/wastage movement in this period.</td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $rows->links() }}</div></div>

<div class="progga-card"><div class="progga-card-header"><div><strong>Food-wise Ingredient Consumption</strong><div class="text-muted small">Historical recipe consumption grouped by menu item and ingredient.</div></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr>@if($allBranches)<th>Branch</th>@endif<th>Food</th><th>Ingredient</th><th class="text-end">Consumed</th></tr></thead><tbody>
@forelse($foodRows as $row)<tr>@if($allBranches)<td>{{ $row->branch_name }}</td>@endif<td>{{ $row->menu_item_name }}</td><td>{{ $row->ingredient_name }}</td><td class="text-end">{{ rtrim(rtrim((string)$row->consumed_base,'0'),'.') }} {{ $row->base_unit_symbol }}</td></tr>@empty<tr><td colspan="{{ $allBranches?4:3 }}" class="text-center text-muted py-5">No order ingredient consumption in this period.</td></tr>@endforelse
</tbody></table></div></div>
</main>
@endsection
