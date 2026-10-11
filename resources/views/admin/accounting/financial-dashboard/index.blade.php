{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title', 'Financial Dashboard')
@section('body')
<main class="progga-content acc-shell acc-dashboard">
@include('admin.accounting.shared._common')
@php
  
$money = fn($value) => '৳'.number_format($value / 10000, 2);
  $exact = fn($value) => \App\Services\Accounting\AccountingMoney::format($value);
  $unresolved = array_sum($counts);
@endphp
<style>
.acc-dashboard{--db-navy:var(--progga-primary,#21352a);--db-teal:var(--progga-primary-light,#2e4a3c);--db-bg:var(--progga-bg-card,#faf5ee);--db-muted:var(--progga-text-muted,#6b7c74)}
.acc-dashboard .db-heading{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin:0 0 19px}
.acc-dashboard .db-heading h1{font-size:25px;font-weight:760;letter-spacing:-.55px;color:var(--db-navy);margin:0 0 5px}
.acc-dashboard .db-subtitle{font-size:13px;color:var(--db-muted);margin:0}
.acc-dashboard .db-kicker{text-transform:uppercase;letter-spacing:1.3px;font-size:10px;font-weight:800;color:var(--db-teal);margin:0 0 7px}
.acc-dashboard .db-period{display:flex;align-items:flex-end;gap:9px;flex-wrap:wrap;background:white;border:1px solid var(--progga-border-light,#ede4d8);border-radius:12px;padding:10px 13px}
.acc-dashboard .db-period label{display:block;color:#65798c;font-size:10px;text-transform:uppercase;letter-spacing:.7px;margin:0 0 4px}
.acc-dashboard .db-period input{border:1px solid var(--progga-border-light,#ede4d8);border-radius:7px;padding:5px 8px;color:var(--db-navy);background:#fafcfd;min-height:33px;font-size:12px}
.acc-dashboard .db-period button{min-height:33px;border:0;border-radius:7px;background:var(--progga-primary,#21352a);color:#fff;font-weight:700;padding:6px 15px}
.acc-dashboard .db-stat{height:100%;background:#fff;border:1px solid var(--progga-border-light,#ede4d8);border-radius:13px;padding:19px 18px;box-shadow:0 2px 8px rgba(30,60,85,.025);border-top:3px solid #d5e4eb}
.acc-dashboard .db-stat.primary{border-top-color:var(--progga-primary,#21352a)}.acc-dashboard .db-stat.profit{border-top-color:#2e906e}
.acc-dashboard .db-stat.negative{border-top-color:#c97457}
.acc-dashboard .db-stat-name{font-size:12px;font-weight:650;color:#5c7085;margin-bottom:10px}
.acc-dashboard .db-stat-value{font-size:clamp(19px,1.8vw,27px);font-weight:800;color:var(--progga-primary,#21352a);line-height:1.2;word-break:break-word;font-variant-numeric:tabular-nums}
.acc-dashboard .db-stat-foot{color:#7b8a9a;font-size:11px;margin-top:10px}
.acc-dashboard .db-card{background:#fff;border:1px solid var(--progga-border-light,#ede4d8);border-radius:13px;overflow:hidden;height:100%;box-shadow:0 3px 13px rgba(20,49,70,.03)}
.acc-dashboard .db-card-head{padding:19px 21px 10px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.acc-dashboard .db-card h2{font-size:16px;font-weight:760;color:#213b54;margin:0 0 4px}
.acc-dashboard .db-small{font-size:11px;color:#8191a1;margin:0}
.acc-dashboard .db-card-body{padding:14px 21px 20px}
.acc-dashboard .db-link{font-size:12px;font-weight:700;color:var(--progga-primary,#21352a);text-decoration:none}
.acc-dashboard .db-link:hover{text-decoration:underline}
.acc-dashboard .db-status{display:inline-flex;gap:6px;align-items:center;font-size:11px;font-weight:750;border-radius:99px;padding:6px 10px}
.acc-dashboard .db-status.ok{background:#e5f5ec;color:#237850}.acc-dashboard .db-status.warn{background:#fff3dd;color:#a06813}
.acc-dashboard .db-status.neutral{background:#edf3f7;color:#4d667e}
.acc-dashboard .db-section-label{font-size:12px;font-weight:750;color:#34536d;margin:18px 0 12px}
.acc-dashboard .db-chart{display:flex;height:196px;align-items:stretch;gap:10px;border-bottom:1px solid #e3eaf0;padding-top:10px}
.acc-dashboard .db-chart-month{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:9px}
.acc-dashboard .db-chart-bars{height:158px;width:100%;display:flex;align-items:end;justify-content:center;gap:5px;border-bottom:1px solid #eef1f4}
.acc-dashboard .db-bar{width:min(27px,37%);border-radius:5px 5px 0 0;min-height:0;transition:height .15s}
.acc-dashboard .db-bar.revenue{background:var(--progga-primary,#21352a)}.acc-dashboard .db-bar.earnings{background:var(--progga-secondary,#d5aa65)}
.acc-dashboard .db-month-label{font-size:10px;color:#6c7e91;font-weight:650;white-space:nowrap}
.acc-dashboard .db-legend{display:flex;gap:19px;align-items:center;font-size:11px;color:#607488}
.acc-dashboard .db-dot{display:inline-block;width:9px;height:9px;border-radius:3px;margin-right:5px}
.acc-dashboard .db-table{width:100%;font-size:12px;margin:0}
.acc-dashboard .db-table th{font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:#7f90a0;padding:10px 9px;border-bottom:1px solid #e3e9ef}
.acc-dashboard .db-table td{padding:11px 9px;border-bottom:1px solid #eff3f6;color:#40536b;vertical-align:middle}
.acc-dashboard .db-table tr:last-child td{border-bottom:0}
.acc-dashboard .db-action{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid #e7edf2;border-radius:9px;padding:11px 13px;margin-bottom:8px;text-decoration:none;color:#34536d;font-size:12px;font-weight:650}
.acc-dashboard .db-action:hover{background:#f5fafb;border-color:#afcbd5;color:var(--progga-primary,#21352a)}
.acc-dashboard .db-empty{text-align:center;color:#72869a;font-size:12px;padding:35px 16px;line-height:1.8}
.acc-dashboard .db-check{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f0f2f5;font-size:12px}
.acc-dashboard .db-check:last-child{border-bottom:0}
.acc-dashboard .db-exception{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid #eef2f5;font-size:12px}
.acc-dashboard .db-exception:last-child{border-bottom:0}
.acc-dashboard .db-exception strong{font-variant-numeric:tabular-nums;color:#233d56;font-size:16px}
@media(max-width:768px){.acc-dashboard .db-heading h1{font-size:22px}.acc-dashboard .db-period{width:100%}.acc-dashboard .db-period>div{flex:1}.acc-dashboard .db-period input{width:100%}.acc-dashboard .db-card-head,.acc-dashboard .db-card-body{padding-left:15px;padding-right:15px}}
@media print{.acc-dashboard .db-period,.acc-dashboard .db-action,.acc-dashboard .db-link{display:none!important}}
</style>

<div class="db-heading">
  <div>
    <div class="db-kicker">Accounting / Financial management</div>
    <h1>Financial Dashboard</h1>
    <p class="db-subtitle">Company-wide financial position &middot; BDT &middot; Based on posted general ledger only</p>
  </div>
  <form method="GET" action="{{ route('accounting.dashboard') }}" class="db-period" aria-label="Select financial reporting period">
    <div><label for="acc-dash-from">From</label><input id="acc-dash-from" name="from" type="date" value="{{ $from }}" required></div>
    <div><label for="acc-dash-to">Through</label><input id="acc-dash-to" name="to" type="date" value="{{ $to }}" required></div>
    <button type="submit"><i class="bi bi-funnel me-1"></i> Apply</button>
  </form>
</div>

<div class="d-flex align-items-center gap-2 flex-wrap mb-3">
  
@if(!$postedCount)
    <span class="db-status warn"><i class="bi bi-exclamation-circle"></i> No posted accounting entries</span>
  
@elseif($ledgerBalanced)
    <span class="db-status ok"><i class="bi bi-check-circle"></i> GL debits and credits balanced</span>
  
@else
    <span class="db-status warn"><i class="bi bi-exclamation-triangle"></i> General ledger needs review</span>
  
@endif
  <span class="db-status {{ $settings?->capture_enabled ? 'ok' : 'neutral' }}">Capture: {{ $settings?->capture_enabled ? 'ON' : 'OFF' }}</span>
  <span class="db-status neutral">{{ number_format($periodPosted) }} journal(s) posted in selected period</span>
</div>

@if(!$postedCount || !$settings?->capture_enabled || !$cutover)
  <div class="alert alert-warning small mb-3" role="status">
    <i class="bi bi-info-circle me-1"></i>
    
@if(!$postedCount)
 No journals have been posted through the selected end date. Zero balances do not mean reconciliation is complete. 
@endif
    
@if(!$settings?->capture_enabled)
 Capture is OFF, so operational transactions are not automatically recorded in Accounting. 
@endif
    
@if(!$cutover)
 Opening balances have not been approved for cutover. 
@endif
  </div>
@endif

<div class="row g-3 mb-3">
  
@foreach([
    ['title'=>'Net Revenue','value'=>$pnl['netSales'],'detail'=>'Selected reporting period','class'=>'primary','icon'=>'bi-graph-up-arrow'],
    ['title'=>'Gross Profit','value'=>$pnl['gross'],'detail'=>'Revenue less verified COGS','class'=>'profit','icon'=>'bi-bar-chart-line'],
    ['title'=>'Net Profit / Loss','value'=>$pnl['profit'],'detail'=>'After operating expenses','class'=>$pnl['profit']<0?'negative':'profit','icon'=>'bi-wallet2'],
    ['title'=>'Accounts Receivable','value'=>$aging['AR']['total'],'detail'=>'Posted outstanding customer due','class'=>'','icon'=>'bi-people'],
    ['title'=>'Accounts Payable','value'=>$aging['AP']['total'],'detail'=>'Posted outstanding supplier due','class'=>'','icon'=>'bi-receipt'],
    ['title'=>'Balance Sheet Difference','value'=>$bs['difference'],'detail'=>'Assets vs liabilities and equity','class'=>$bs['difference']===0?'':'negative','icon'=>'bi-shield-check']
  ] as $stat)
  <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
    <section class="db-stat {{ $stat['class'] }}">
      <div class="d-flex justify-content-between align-items-center mb-2"><span class="db-stat-name mb-0">{{ $stat['title'] }}</span><i class="bi {{ $stat['icon'] }} text-secondary"></i></div>
      <div class="db-stat-value" title="{{ $exact($stat['value']) }} BDT">{{ $money($stat['value']) }}</div>
      <div class="db-stat-foot">{{ $stat['detail'] }}</div>
    </section>
  </div>
  
@endforeach
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <section class="db-card">
      <div class="db-card-head">
        <div><h2>Financial Performance</h2><p class="db-small">Last six calendar months through {{ \Carbon\Carbon::parse($to)->format('M Y') }}; POSTED journal figures</p></div>
        <div class="db-legend"><span><span class="db-dot" style="background:var(--progga-primary,#21352a)"></span> Revenue</span><span><span class="db-dot" style="background:var(--progga-secondary,#d5aa65)"></span> Net Profit</span></div>
      </div>
      <div class="db-card-body">
        
@if($hasTrend)
        <div class="db-chart" role="img" aria-label="Six month posted revenue and net profit comparison">
          
@foreach($trend as $month)
          <div class="db-chart-month">
            <div class="db-chart-bars">
              <div class="db-bar revenue" style="height:{{ max(0, round(100*max(0,$month['revenue'])/$trendScale)) }}%" title="{{ $month['label'] }} revenue: {{ $money($month['revenue']) }}"></div>
              <div class="db-bar earnings" style="height:{{ max(0, round(100*max(0,$month['profit'])/$trendScale)) }}%" title="{{ $month['label'] }} profit: {{ $money($month['profit']) }}"></div>
            </div>
            <span class="db-month-label">{{ $month['label'] }}</span>
          </div>
          
@endforeach
        </div>
        <p class="db-small mt-2 mb-0">Negative profit is not drawn below zero; use Profit & Loss for exact figures. Hover bars for monthly values.</p>
        
@else
        <div class="db-empty"><i class="bi bi-bar-chart fs-2 d-block mb-2"></i>No posted revenue or expense movement for this six-month period.<br>Figures will appear after approved journals are posted.</div>
        
@endif
        
@can('accounting-financial-reports')
<div class="mt-3"><a class="db-link" href="{{ route('accounting.reports.profit-loss', ['from'=>$from,'to'=>$to]) }}">Open Profit & Loss <i class="bi bi-arrow-up-right"></i></a></div>
@endcan
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="db-card">
      <div class="db-card-head"><div><h2>Exceptions Requiring Review</h2><p class="db-small">Unresolved items across Accounting modules</p></div><span class="db-status {{ $unresolved ? 'warn' : 'neutral' }}">{{ number_format($unresolved) }} open</span></div>
      <div class="db-card-body pt-1">
      
@foreach([
        ['title'=>'Pending source events','key'=>'pending','route'=>'accounting.events','permission'=>'accounting-event-view'],
        ['title'=>'Unposted journals','key'=>'draft','route'=>'accounting.journals.index','permission'=>'accounting-view'],
        ['title'=>'Inventory cost pending','key'=>'negative','route'=>'accounting.costs','permission'=>'accounting-view'],
        ['title'=>'Unmatched bank lines','key'=>'bank','route'=>'accounting.bank','permission'=>'accounting-view'],
        ['title'=>'Unapproved POS cash counts','key'=>'cash','route'=>'accounting.sessions','permission'=>'accounting-view'],
      ] as $issue)
        <div class="db-exception">
          <span>{{ $issue['title'] }}</span>
          <div class="d-flex align-items-center gap-3"><strong>{{ number_format($counts[$issue['key']]) }}</strong>
@can($issue['permission'])
<a href="{{ route($issue['route']) }}" class="text-secondary" aria-label="Review {{ $issue['title'] }}"><i class="bi bi-arrow-right"></i></a>
@endcan
</div>
        </div>
      
@endforeach
      <p class="db-small mt-3 mb-0">Only POSTED entries affect official financial statements.</p>
      </div>
    </section>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <section class="db-card">
      <div class="db-card-head"><div><h2>Recent Posted Journals</h2><p class="db-small">Most recent entries within selected period</p></div>
@can('accounting-view')
<a class="db-link" href="{{ route('accounting.journals.index') }}">View Journal Entries <i class="bi bi-arrow-up-right"></i></a>
@endcan
</div>
      <div class="db-card-body pt-1">
      <div class="table-responsive"><table class="db-table"><thead><tr><th>Date</th><th>Journal No.</th><th>Type</th><th>Details</th><th class="text-end">Amount</th></tr></thead><tbody>
      
@forelse($recentJournals as $entry)
      <tr>
        <td class="text-nowrap">{{ $entry->entry_date }}</td>
        <td>
@can('accounting-view')
<a class="db-link" href="{{ route('accounting.journals.show', $entry->id) }}">{{ $entry->number ?: '#'.$entry->id }}</a>
@else
{{ $entry->number ?: '#'.$entry->id }}
@endcan
</td>
        <td>{{ $entry->journal_name }}</td>
        <td>{{ \Illuminate\Support\Str::limit($entry->narration, 65) }}</td>
        <td class="text-end text-nowrap fw-semibold">{{ $money(\App\Services\Accounting\AccountingMoney::units((string)$entry->debit_total)) }}</td>
      </tr>
      
@empty
      <tr><td colspan="5"><div class="db-empty">No posted journals found for the selected period.</div></td></tr>
      
@endforelse
      </tbody></table></div>
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="db-card">
      <div class="db-card-head"><div><h2>Accounting Controls</h2><p class="db-small">Current configuration and GL checks</p></div><span class="db-status {{ $checksReady===count($checks) ? 'ok' : 'warn' }}">{{ $checksReady }}/{{ count($checks) }} ready</span></div>
      <div class="db-card-body pt-1">
        
@foreach($checks as $check)
        <div class="db-check">
          <div><div class="fw-semibold">{{ $check['label'] }}</div>
@unless($check['ready'])
<div class="db-small">{{ $check['message'] }}</div>
@endunless
</div>
          <div class="text-nowrap"><span class="db-status {{ $check['ready'] ? 'ok' : 'warn' }}">{{ $check['ready'] ? 'Ready' : 'Pending' }}</span>
@can($check['permission'])
<a href="{{ route($check['route']) }}" class="text-secondary ms-2" title="Open {{ $check['label'] }}"><i class="bi bi-arrow-up-right"></i></a>
@endcan
</div>
        </div>
        
@endforeach
        <div class="pt-3"><strong class="small d-block mb-2">Posted GL Balance Check</strong>
          
@if(!$postedCount)
<span class="db-status warn">Not verified — no posted entries</span>
          
@elseif($ledgerBalanced)
<span class="db-status ok">Debit = Credit; source reconciliation still required</span>
          
@else
<span class="db-status warn">Difference detected — review statements</span>
@endif
          <div class="db-small mt-2">Trial Balance: {{ $money($tb['debits']) }} debit / {{ $money($tb['credits']) }} credit</div>
        </div>
        
@can('accounting-financial-reports')
<a class="db-link d-inline-block mt-3" href="{{ route('accounting.reports.trial-balance', ['from'=>$from,'to'=>$to]) }}">Open Trial Balance <i class="bi bi-arrow-up-right"></i></a>
@endcan
      </div>
    </section>
  </div>
</div>
</main>
@endsection
