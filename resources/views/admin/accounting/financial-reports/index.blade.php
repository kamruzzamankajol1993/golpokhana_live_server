{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title',ucwords(str_replace('-', ' ', $type)))
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
@php 
$money=fn($v)=>'৳'.number_format($v/10000,4); $format=fn($v)=>\App\Services\Accounting\AccountingMoney::format(abs($v)); 
@endphp
<div class="acc-panel"><div class="d-flex justify-content-between flex-wrap gap-2 align-items-center"><div><h3 class="mb-1">{{ ucwords(str_replace('-', ' ', $type)) }}</h3><p class="acc-hint mb-1">Company ledger · POSTED journals only · BDT · period filter</p></div><div><a class="btn btn-sm btn-outline-secondary" href="{{ route('accounting.reports.export', array_merge(request()->except(['page','print_all']), ['type'=>$type, 'from'=>$from,'to'=>$to,'account_id'=>$account])) }}"><i class="bi bi-download"></i> Download Excel (.xlsx)</a> <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.reports.pdf', array_merge(request()->except(['page','print_all']), ['type'=>$type,'from'=>$from,'to'=>$to,'account_id'=>$account])) }}"><i class="bi bi-file-earmark-pdf"></i> Download PDF (mPDF)</a></div></div>
<form method="get" action="{{ request()->routeIs('accounting.reports.*') ? url()->current() : route('accounting.reports') }}" class="row g-2 align-items-end mt-2 acc-print-hide">
@if(request()->routeIs('accounting.reports.*'))
<input type="hidden" name="type" value="{{ $type }}"><div class="col-lg-3"><label>Report</label><input type="text" class="form-control" value="{{ ucwords(str_replace('-', ' ', $type)) }}" readonly></div>
@else
<div class="col-lg-3"><label>Report</label><select name="type" class="form-select">
@foreach(['profit-loss'=>'Profit & Loss','balance-sheet'=>'Balance Sheet','trial-balance'=>'Trial Balance','cash-flow'=>'Cash Flow / Cash Book','tax'=>'VAT / TDS / VDS','aging'=>'Receivable / Payable Aging','general-ledger'=>'General Ledger'] as $key=>$name)
<option value="{{ $key }}" @selected($type===$key)>{{ $name }}</option>
@endforeach
</select></div>
@endif
<div class="col-lg-2"><label>From</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div><div class="col-lg-2"><label>Through</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div><div class="col-lg-3"><label>Account (General Ledger)</label><select name="account_id" class="form-select"><option value="">Select</option>
@foreach($accounts as $a)
<option value="{{ $a->id }}" @selected($account==$a->id)>{{ $a->code }} – {{ $a->name }}</option>
@endforeach
</select></div><div class="col-lg-2"><button class="btn btn-primary w-100">Apply</button></div></form></div>
@if(!$cutover || !$settings?->capture_enabled || $exceptions)
<div class="alert alert-warning">Accounting completeness warning: {{ $cutover?'':'Opening balance cutover not signed. ' }}{{ !$settings?->capture_enabled?'Capture is OFF; operational records will not appear automatically. ':'' }}{{ $exceptions?'Unposted or exception source events: '.$exceptions.'.':'' }} Reports show only POSTED entries.</div>
@endif
<div class="acc-panel"><h3>{{ ['profit-loss'=>'Profit & Loss','balance-sheet'=>'Balance Sheet','trial-balance'=>'Trial Balance','cash-flow'=>'Cash Flow','tax'=>'Tax Account Activity','aging'=>'Aged Receivable / Payable','general-ledger'=>'General Ledger'][$type] }}</h3>
@if($type==='profit-loss')
@foreach(['income'=>'Revenue','contra_revenue'=>'Contra Revenue / Discounts','cogs'=>'Cost of Goods Sold','expense'=>'Operating & Other Expenses'] as $key=>$caption)
<h5 class="mt-4">{{ $caption }}</h5>
@php 
$pnlAccountsPage=\App\Support\Accounting\TablePaginator::paginate($report['data'][$key], 'acc_pnl_'.$key); 
@endphp
<div class="acc-ajax-list" id="acc-list-pnl-{{ $key }}"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Code</th><th>Account</th><th class="text-end">Amount (BDT)</th></tr></thead><tbody>
@forelse($pnlAccountsPage as $row)
<tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="text-end">{{ $money($row['amount']) }}</td></tr>
@empty
<tr><td colspan="3" class="text-muted">No posted activity</td></tr>
@endforelse
</tbody><tfoot><tr><th colspan="2">{{ $caption }} Total</th><th class="text-end">{{ $money($report['sums'][$key]) }}</th></tr></tfoot></table></div>@include('admin.accounting.shared._pagination',['paginator'=>$pnlAccountsPage,'label'=>'accounts'])</div>
@endforeach
<div class="row g-3 mt-2">
@foreach(['netSales'=>'Net Sales','gross'=>'Gross Profit','profit'=>'Net Profit / (Loss)'] as $key=>$caption)
<div class="col-md-4"><div class="acc-panel border"><div class="acc-hint">{{ $caption }}</div><div class="acc-stat">{{ $money($report[$key]) }}</div></div></div>
@endforeach
</div>
@elseif($type==='balance-sheet')
@foreach(['asset'=>'Assets','liability'=>'Liabilities','equity'=>'Equity'] as $key=>$caption)
<h5 class="mt-4">{{ $caption }}</h5>
@php 
$bsGroupPage=\App\Support\Accounting\TablePaginator::paginate($report['groups'][$key], 'acc_bs_'.$key); 
@endphp
<div class="acc-ajax-list" id="acc-list-bs-{{ $key }}"><table class="table table-sm"><thead><tr><th>Account</th><th class="text-end">Signed Balance</th></tr></thead><tbody>
@foreach($bsGroupPage as $row)
<tr><td>{{ $row['code'] }} – {{ $row['name'] }}</td><td class="text-end">{{ $money($row['amount']) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th>{{ $caption }} Total</th><th class="text-end">{{ $money($report['sum'][$key]) }}</th></tr></tfoot></table>@include('admin.accounting.shared._pagination',['paginator'=>$bsGroupPage,'label'=>'accounts'])</div>
@endforeach
<table class="table"><tr><th>Untransferred Current / Prior Earnings</th><td class="text-end">{{ $money($report['earnings']) }}</td></tr><tr><th>Liabilities + Equity + Earnings</th><td class="text-end">{{ $money($report['liabilitiesAndEquity']) }}</td></tr><tr class="{{ $report['difference']===0?'table-success':'table-danger' }}"><th>Balance Sheet Difference</th><th class="text-end">{{ $money($report['difference']) }}</th></tr></table>
@elseif($type==='trial-balance')
<div class="table-responsive">
@php 
$accReportPage=\App\Support\Accounting\TablePaginator::paginate($report['rows'], 'acc_report_trial_balance'); 
@endphp
<div class="acc-ajax-list" id="acc-report-trial_balance"><table class="table table-sm"><thead><tr><th>Account</th><th class="text-end">Debit Balance</th><th class="text-end">Credit Balance</th></tr></thead><tbody>
@foreach($accReportPage as $row)
<tr><td>{{ $row['code'] }} – {{ $row['name'] }}</td><td class="text-end">{{ $money($row['debit_balance']) }}</td><td class="text-end">{{ $money($row['credit_balance']) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th>TOTAL</th><th class="text-end">{{ $money($report['debits']) }}</th><th class="text-end">{{ $money($report['credits']) }}</th></tr></tfoot></table>@include('admin.accounting.shared._pagination',['paginator'=>$accReportPage,'label'=>'report rows'])</div></div><div class="alert {{ count($report['rows']) === 0?'alert-warning':($report['debits']===$report['credits']?'alert-success':'alert-danger') }}">{{ count($report['rows']) === 0?'No posted journal balances available yet.':($report['debits']===$report['credits']?'Posted GL is balanced.':'Ledger imbalance: financial close must be blocked.') }}</div>
@elseif($type==='cash-flow')
<div class="alert alert-warning">{{ $report['warning'] }}</div><div class="row g-2">
@foreach(['opening'=>'Opening Cash/Bank','movement'=>'Net Cash Movement','closing'=>'Closing Cash/Bank'] as $key=>$caption)
<div class="col-md-4"><div class="acc-panel border"><div class="acc-hint">{{ $caption }}</div><div class="acc-stat">{{ $money($report[$key]) }}</div></div></div>
@endforeach
</div><table class="table table-sm"><thead><tr><th>Category</th><th class="text-end">Net Movement</th></tr></thead><tbody>
@foreach($report['groups'] as $key=>$sum)
<tr><td>{{ $key }}</td><td class="text-end">{{ $money($sum) }}</td></tr>
@endforeach
</tbody></table><div class="table-responsive">
@php 
$accReportPage=\App\Support\Accounting\TablePaginator::paginate($report['rows'], 'acc_report_cash_flow'); 
@endphp
<div class="acc-ajax-list" id="acc-report-cash_flow"><table class="table table-sm"><thead><tr><th>Date / Journal</th><th>Cash Flow Activity</th><th>Memo</th><th class="text-end">Net Cash</th></tr></thead><tbody>
@foreach($accReportPage as $row)
<tr><td><a href="{{ route('accounting.journals.show',$row['id']) }}">{{ $row['number'] }}</a><small class="d-block">{{ $row['date'] }}</small></td><td>{{ $row['activity'] }}</td><td>{{ $row['narration'] }}</td><td class="text-end">{{ $money($row['amount']) }}</td></tr>
@endforeach
</tbody></table>@include('admin.accounting.shared._pagination',['paginator'=>$accReportPage,'label'=>'report rows'])</div></div>
@elseif($type==='tax')
<div class="alert alert-info">VAT, TDS and VDS movements come from accountant-mapped, POSTED tax accounts. Tax rates and legal filing amounts are not inferred. Verify inclusive/exclusive POS VAT and statutory settlement externally.</div>
@php 
$accReportPage=\App\Support\Accounting\TablePaginator::paginate($report['rows'], 'acc_report_tax'); 
@endphp
<div class="acc-ajax-list" id="acc-report-tax"><table class="table table-sm"><thead><tr><th>Tax</th><th>GL Account</th><th>Debit</th><th>Credit</th><th>Net Liability Movement</th></tr></thead><tbody>
@forelse($accReportPage as $row)
<tr><td>{{ $row['type'] }}</td><td>{{ $row['code'] }} – {{ $row['name'] }}</td><td>{{ $money($row['debit']) }}</td><td>{{ $money($row['credit']) }}</td><td>{{ $money($row['movement']) }}</td></tr>
@empty
<tr><td colspan="5">No GL tax mapping configured.</td></tr>
@endforelse
</tbody></table>@include('admin.accounting.shared._pagination',['paginator'=>$accReportPage,'label'=>'report rows'])</div><h5 class="mt-3">Posted Supplier Withholding References</h5><table class="table table-sm"><thead><tr><th>Tax Type</th><th>Transactions</th><th>Approved Source Amount</th></tr></thead><tbody>
@foreach($report['withholdings'] as $w)
<tr><td>{{ $w->tax_type }}</td><td>{{ $w->count }}</td><td>{{ $w->amount }}</td></tr>
@endforeach
</tbody></table>
@elseif($type==='aging')
@foreach(['AR'=>'Customer Receivable','AP'=>'Vendor Payable'] as $key=>$label)
<h5 class="mt-3">{{ $label }}: {{ $money($report[$key]['total']) }}</h5><table class="table table-sm"><thead><tr><th>Current</th><th>1–30</th><th>31–60</th><th>61–90</th><th>90+ days</th></tr></thead><tbody><tr>
@foreach($report[$key]['buckets'] as $v)
<td>{{ $money($v) }}</td>
@endforeach
</tr></tbody></table>
@php 
$agingPage=\App\Support\Accounting\TablePaginator::paginate($report[$key]['rows'], 'acc_aging_'.$key); 
@endphp
<div class="acc-ajax-list" id="acc-list-aging-{{ $key }}"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Source ID</th><th>Party ID</th><th>Due</th><th>Original</th><th>Posted Paid</th><th>Outstanding</th></tr></thead><tbody>
@forelse($agingPage as $row)
<tr><td>{{ $row['source_id'] }}</td><td>{{ $row['party_id'] }}</td><td>{{ $row['due'] }}</td><td>{{ $money($row['original']) }}</td><td>{{ $money($row['paid']) }}</td><td>{{ $money($row['outstanding']) }}</td></tr>
@empty
<tr><td colspan="6">No posted outstanding items</td></tr>
@endforelse
</tbody></table></div>@include('admin.accounting.shared._pagination',['paginator'=>$agingPage,'label'=>'invoices'])</div>
@endforeach
@elseif($type==='general-ledger')
@if(!$report['account'])
<div class="alert alert-info">Select a posting account above to view its complete posted ledger.</div>
@else
<h5>{{ $report['account']->code }} – {{ $report['account']->name }}</h5><p>Opening (Debit − Credit): <b>{{ $money($report['opening']) }}</b></p><div class="table-responsive">
@php 
$accReportPage=\App\Support\Accounting\TablePaginator::paginate($report['rows'], 'acc_report_general_ledger'); 
@endphp
<div class="acc-ajax-list" id="acc-report-general_ledger"><table class="table table-sm"><thead><tr><th>Date / Journal</th><th>Memo</th><th>Debit</th><th>Credit</th><th>Running D−C</th></tr></thead><tbody>
@foreach($accReportPage as $row)
<tr><td>{{ $row['date'] }} <a href="{{ route('accounting.journals.show',$row['id']) }}">{{ $row['number'] }}</a></td><td>{{ $row['narration'] }}</td><td>{{ $money($row['dr']) }}</td><td>{{ $money($row['cr']) }}</td><td>{{ $money($row['running']) }}</td></tr>
@endforeach
</tbody></table>@include('admin.accounting.shared._pagination',['paginator'=>$accReportPage,'label'=>'report rows'])</div></div><p><b>Ending D−C Balance:</b> {{ $money($report['running']) }}</p>
@endif
@endif
</div></main>
<style>@media print {.acc-print-hide,.acc-tabs,.progga-sidebar,.sidebar,.btn,header,footer{display:none!important}.progga-content{margin:0!important;padding:0!important}.acc-panel{box-shadow:none!important;border:0!important}.table{font-size:10px!important}} </style>
@endsection
