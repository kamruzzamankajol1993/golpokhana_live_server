@extends('admin.master.master')
@section('title','Accounting Financial Dashboard')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting._nav')
@php $money=fn($v)=>'৳'.number_format($v/10000,4); @endphp
<div class="acc-panel"><h3>Financial Dashboard — {{ $from }} through {{ $to }}</h3>
<p class="acc-hint">Actuals are posted general-ledger transactions only; cash collection and POS sales are not substituted for recognized revenue.</p>
@if(!$settings?->capture_enabled || !$cutover)<div class="alert alert-warning">{{ !$settings?->capture_enabled?'Accounting Capture is OFF. ':'' }}{{ !$cutover?'Opening balance cutover not approved.':'' }} Published statements may be incomplete.</div>@endif
<div class="row g-3">@foreach(['Net Sales'=>$pnl['netSales'],'Gross Profit'=>$pnl['gross'],'Net Earnings'=>$pnl['profit'],'Receivables Outstanding'=>$aging['AR']['total'],'Payables Outstanding'=>$aging['AP']['total'],'Balance Sheet Difference'=>$bs['difference']] as $label=>$amount)<div class="col-md-4 col-xl-2"><div class="acc-panel border h-100"><div class="acc-hint">{{ $label }}</div><div class="acc-stat" style="font-size:19px">{{ $money($amount) }}</div></div></div>@endforeach</div>
<h3 class="mt-3">Close Readiness Exceptions</h3><div class="row g-2">@foreach(['pending'=>'Pending source events','draft'=>'Unposted journals','negative'=>'Pending inventory cost','bank'=>'Unmatched bank lines','cash'=>'Unapproved POS cash count'] as $key=>$label)<div class="col-md"><div class="p-3 border rounded"><div class="acc-hint">{{ $label }}</div><h4>{{ $counts[$key] }}</h4></div></div>@endforeach</div>
<p class="mt-3"><a class="btn btn-outline-primary" href="{{ route('accounting.reports') }}">Financial Statements</a> <a class="btn btn-outline-primary" href="{{ route('accounting.closing') }}">Month-End Checklist</a> <a class="btn btn-outline-primary" href="{{ route('accounting.budgets') }}">Budget vs Actual</a></p>
<div class="alert {{ $tb['debits']===$tb['credits'] && $bs['difference']===0?'alert-success':'alert-danger' }} mb-0">Trial Balance: Debit {{ $money($tb['debits']) }} / Credit {{ $money($tb['credits']) }} · Balance Sheet Difference: {{ $money($bs['difference']) }}. Pending costs and unposted events are excluded from reported profit.</div></div></main>
@endsection
