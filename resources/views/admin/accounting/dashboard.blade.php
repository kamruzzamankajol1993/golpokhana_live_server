@extends('admin.master.master')
@section('title','Accounting Foundation')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting._nav')
 <div class="row g-3 mb-3">
  @foreach(['Accounts'=>$metrics['accounts'],'Unposted Journals'=>$metrics['drafts'],'Posted Journals'=>$metrics['posted'],'Pending / Exceptions'=>$metrics['pending']] as $caption=>$val)
  <div class="col-md-3 col-sm-6"><div class="acc-panel h-100"><div class="acc-hint">{{ $caption }}</div><div class="acc-stat">{{ $val }}</div></div></div>
  @endforeach
 </div>
 <div class="row g-3"><div class="col-lg-7"><div class="acc-panel"><h3>Accounting Control</h3>
  <table class="table"><tr><th>Capture</th><td><span class="badge {{ $settings->capture_enabled ? 'bg-success' : 'bg-secondary' }}">{{ $settings->capture_enabled ? 'ON' : 'OFF' }}</span></td></tr>
   <tr><th>Posting mode</th><td>{{ $settings->capture_enabled ? ($settings->posting_mode==='auto' ? 'Automatic Journal Posting' : 'Manual Review & Post') : 'Inactive (Capture OFF)' }}</td></tr>
   <tr><th>Capture activated</th><td>{{ $settings->activated_at ?? 'Not activated' }}</td></tr>
   <tr><th>Accounting lock date</th><td>{{ $settings->locked_until ?? 'None' }}</td></tr>
  </table><p class="acc-hint">Capture OFF: operational transactions continue normally; no automatic accounting rows. Manual journals remain permissioned.</p>
  @can('accounting-settings')<a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.settings') }}">Manage Accounting Settings</a>@endcan
  </div></div><div class="col-lg-5"><div class="acc-panel"><h3>Posted GL Integrity</h3><p class="acc-hint">Only POSTED journal lines count.</p>
    <div class="d-flex justify-content-between"><span>Total Debits</span><b>৳{{ number_format((float)$balance->dr,2) }}</b></div><hr>
    <div class="d-flex justify-content-between"><span>Total Credits</span><b>৳{{ number_format((float)$balance->cr,2) }}</b></div><hr>
    <div class="alert {{ \App\Services\Accounting\AccountingMoney::units((string)$balance->dr) === \App\Services\Accounting\AccountingMoney::units((string)$balance->cr) ? 'alert-success' : 'alert-danger' }} mb-0">{{ \App\Services\Accounting\AccountingMoney::units((string)$balance->dr) === \App\Services\Accounting\AccountingMoney::units((string)$balance->cr) ? 'Debits and credits balance' : 'Posted ledger mismatch: review required' }}</div>
    <a class="small d-block mt-2" href="{{ route('accounting.trial-balance') }}">See Trial Balance</a>
  </div></div></div>
 <div class="acc-panel"><h3>Accounting Implementation — Phases 1–5</h3><p class="mb-0">Foundation, revenue/cash, purchase/inventory, extended ledger, and reporting/closing screens are available. All financial statements are POSTED-GL only. Complete opening cutover sign-off, accountant mapping, tax checks and staging UAT before enabling automatic capture/posting.</p></div>
</main>@endsection
