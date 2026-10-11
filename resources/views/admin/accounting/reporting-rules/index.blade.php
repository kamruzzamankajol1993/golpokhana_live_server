{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Accounting Report Rules')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Cash Flow & Tax Reporting Classifications</h3><div class="alert alert-info">Choose which accounts represent actual cash/bank, and classify counterpart accounts by activity. Missing or mixed classifications display as UNCLASSIFIED. Tax mapping does not set any statutory tax rate or create journals.</div>
@can('accounting-report-mapping')
<form method="post" action="{{ route('accounting.report-rules.save') }}" class="row g-2 align-items-end mb-4">@csrf
<div class="col-lg-4"><label>Posting Account</label><select name="account_id" required class="form-select">
@foreach($accounts as $a)
<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
@endforeach
</select></div>
<div class="col-lg-3"><label>Mapping Type</label><select class="form-select" name="rule_type"><option value="CASH_FLOW">Cash Flow</option><option value="TAX">Tax</option></select></div>
<div class="col-lg-3"><label>Classification</label><select class="form-select" name="classification"><option>CASH</option><option>OPERATING</option><option>INVESTING</option><option>FINANCING</option><option>VAT</option><option>TDS</option><option>VDS</option><option>NONE</option></select></div><div class="col-lg-2"><button class="btn btn-primary w-100">Save Mapping</button></div></form>
@endcan
@php 
$accListRows = collect($accounts)->filter(fn ($a) => $cashRules->has($a->id) || $taxRules->has($a->id))->values();$accPager1 = \App\Support\Accounting\TablePaginator::paginate($accListRows, 'acc_reporting_rules_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-reporting-rules-1">
<table class="table table-sm"><thead><tr><th>Code</th><th>Account</th><th>Cash Flow</th><th>Tax</th></tr></thead><tbody>
@foreach($accPager1 as $a)

@if($cashRules->has($a->id) || $taxRules->has($a->id))
<tr><td>{{ $a->code }}</td><td>{{ $a->name }}</td><td>{{ $cashRules[$a->id]??'-' }}</td><td>{{ $taxRules[$a->id]??'-' }}</td></tr>
@endif
 
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'accounts'])
</div></div></main>
@endsection
