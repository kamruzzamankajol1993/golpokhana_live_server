{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Accounting Trial Balance')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Trial Balance · Posted Entries Only</h3><p class="acc-hint">Account balances come exclusively from POSTED journals. Pending drafts, unposted events, historical capture-OFF transactions and unverified inventory costs are excluded.</p>
<div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($rows, 'acc_trial_balance_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-trial-balance-1">
<table class="table"><thead><tr><th>Account Code</th><th>Account</th><th class="text-end">Debit Balance</th><th class="text-end">Credit Balance</th></tr></thead><tbody>
@php

$totalDr=0;$totalCr=0;
// Trial Balance totals must include ALL accounts, never only the current visible page.
foreach($rows as $balanceRow) {
    $balanceNet=\App\Services\Accounting\AccountingMoney::units((string)$balanceRow->debit_total)
        - \App\Services\Accounting\AccountingMoney::units((string)$balanceRow->credit_total);
    $totalDr+=max(0,$balanceNet);
    $totalCr+=max(0,-$balanceNet);
}
@endphp
@foreach($accPager1 as $row)
@php 
$net=\App\Services\Accounting\AccountingMoney::units((string)$row->debit_total)-\App\Services\Accounting\AccountingMoney::units((string)$row->credit_total);$dr=max(0,$net);$cr=max(0,-$net); 
@endphp
<tr><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($dr) }}</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($cr) }}</td></tr>
@endforeach
</tbody><tfoot><tr class="fw-bold"><td colspan="2">TOTAL</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($totalDr) }}</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($totalCr) }}</td></tr></tfoot></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'rows'])
</div></div><div class="alert {{ ($rows->count() === 0) ? 'alert-warning' : ($totalDr===$totalCr ? 'alert-success':'alert-danger') }}">{{ $rows->count() === 0 ? 'No posted journal balances to reconcile' : ($totalDr===$totalCr ? 'Balanced Trial Balance' : 'Out of balance — investigate ledger entries') }}</div>
</div></main>
@endsection
