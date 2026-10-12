@extends('admin.master.master')
@section('title','Accounting Trial Balance')
@section('body')<main class="progga-content acc-shell">@include('admin.accounting._nav')
<div class="acc-panel"><h3>Trial Balance · Posted Entries Only</h3><p class="acc-hint">Account balances come exclusively from POSTED journals. Pending drafts, unposted events, historical capture-OFF transactions and unverified inventory costs are excluded.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Account Code</th><th>Account</th><th class="text-end">Debit Balance</th><th class="text-end">Credit Balance</th></tr></thead><tbody>
@php $totalDr=0;$totalCr=0; @endphp
@foreach($rows as $row)
@php $net=\App\Services\Accounting\AccountingMoney::units((string)$row->debit_total)-\App\Services\Accounting\AccountingMoney::units((string)$row->credit_total);$dr=max(0,$net);$cr=max(0,-$net);$totalDr+=$dr;$totalCr+=$cr; @endphp
<tr><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($dr) }}</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($cr) }}</td></tr>
@endforeach
</tbody><tfoot><tr class="fw-bold"><td colspan="2">TOTAL</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($totalDr) }}</td><td class="text-end">{{ \App\Services\Accounting\AccountingMoney::format($totalCr) }}</td></tr></tfoot></table></div><div class="alert {{ $totalDr===$totalCr ? 'alert-success':'alert-danger' }}">{{ $totalDr===$totalCr ? 'Balanced Trial Balance' : 'Out of balance — investigate ledger entries' }}</div>
</div></main>@endsection
