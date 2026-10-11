{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Bank / Card / MFS Clearing')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Provider Clearing Register</h3><p class="acc-hint">Only posted sales/due/booking journals create clearing receivables. Settlements use Dr Bank (net) + Dr Provider Fees / Cr Clearing (gross). Each provider transaction can be settled once; posting is approval-controlled.</p>
<div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($clearing, 'acc_payment_clearing_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-payment-clearing-1">
<table class="table"><thead><tr><th>Provider</th><th>Source</th><th>Gross (BDT)</th><th>Status</th><th>Prepare Settlement</th></tr></thead><tbody>
@forelse($accPager1 as $t)
<tr><td>{{ $t->account_name }}</td><td>{{ $t->reference }}</td><td>{{ number_format($t->amount,4) }}</td><td>{{ $t->status }}</td><td>
@if($t->status==='UNSETTLED')

@can('accounting-reconcile')
<form data-acc-confirm="Prepare settlement JV for separate approval?" method="post" action="{{ route('accounting.clearing.settle',$t->id) }}">@csrf<div class="d-flex flex-wrap gap-1"><input name="entry_date" class="form-control" type="date" value="{{ date('Y-m-d') }}" required style="width:135px"><input name="fee" class="form-control" type="number" value="0" min="0" step="0.0001" required style="width:105px" title="Provider fee"><input name="reference" class="form-control" maxlength="200" required placeholder="Bank reference" style="width:170px"><button class="btn btn-outline-primary btn-sm acc-icon-action" title="Create settlement journal draft" aria-label="Create settlement journal draft"><i class="bi bi-file-earmark-plus" aria-hidden="true"></i></button></div></form>
@endcan

@endif
</td></tr>
@empty
<tr><td colspan="5">No provider clearing records yet.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'clearing'])
</div></div></div></main>
@endsection
