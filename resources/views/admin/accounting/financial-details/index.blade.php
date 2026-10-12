@extends('admin.master.master')
@section('title', ['customer-ledger' => 'Customer Ledger', 'vendor-ledger' => 'Vendor Ledger', 'invoice-analysis' => 'Invoice Analysis'][$type] ?? 'Financial Details')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
@php
$reportLabels = ['customer-ledger' => 'Customer Ledger', 'vendor-ledger' => 'Vendor Ledger', 'invoice-analysis' => 'Invoice Analysis'];
$filterParams = request()->only(['type', 'party_id', 'from', 'to', 'search']);
@endphp
<div class="acc-panel">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-2">
        <div>
            <h3 class="mb-1">{{ $reportLabels[$type] }}</h3>
            <p class="acc-hint mb-0">Company-level accounting · POSTED source-linked journals only · BDT</p>
        </div>
        @if($type === 'invoice-analysis' || $partyId)
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.financial-details.pdf', $filterParams) }}"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
            <a class="btn btn-sm btn-outline-success" href="{{ route('accounting.financial-details.excel', $filterParams) }}"><i class="bi bi-file-earmark-excel"></i> Download Excel</a>
        </div>
        @endif
    </div>
    <form class="row g-2 align-items-end" method="get" action="{{ route('accounting.financial-details') }}">
        <input type="hidden" name="type" value="{{ $type }}">
        @if($type !== 'invoice-analysis')
            <div class="col-lg-3 col-md-6">
                <label for="accountingReportParty">{{ $isVendor ? 'Vendor' : 'Customer' }}</label>
                <select id="accountingReportParty" name="party_id" class="form-select" required>
                    <option value="">Select {{ $isVendor ? 'vendor' : 'customer' }}</option>
                    @foreach($parties as $p)
                    <option value="{{ $p->id }}" @selected($partyId === (int)$p->id)>{{ $p->name }} (#{{ $p->id }})</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-lg-2 col-md-6"><label for="accountingReportFrom">From</label><input id="accountingReportFrom" type="date" name="from" class="form-control" value="{{ $from }}"></div>
        <div class="col-lg-2 col-md-6"><label for="accountingReportTo">Through</label><input id="accountingReportTo" type="date" name="to" class="form-control" value="{{ $to }}"></div>
        <div class="col-lg-3 col-md-6"><label for="accountingReportSearch">Search</label><input id="accountingReportSearch" type="search" name="search" class="form-control" maxlength="100" value="{{ $search }}" placeholder="Journal number / source ID / event"></div>
        <div class="col-lg-2 col-md-6"><button class="btn btn-primary w-100">Apply Filter</button></div>
    </form>
</div>
<div class="acc-panel">
@if($type === 'invoice-analysis')
    <h3>Posted POS Invoice Transactions</h3>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Date</th><th>Order</th><th>Event / Version</th><th>Sales</th><th>VAT</th><th>Discount</th><th>AR Due</th><th>Posted Journal</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r->entry_date }}</td><td>{{ $r->order_number }}</td>
                    <td>{{ $r->event_type }} v{{ $r->source_version }} @if($r->event_type === 'SALES_CORRECTION')<div class="acc-hint">Correction delta posted; displayed amounts are the saved source snapshot.</div>@endif</td>
                    @foreach(['sales_gross', 'vat', 'discount_total', 'ar_due'] as $field)
                    <td>{{ number_format((float)($r->amounts[$field] ?? 0), 2) }}</td>
                    @endforeach
                    <td><a href="{{ route('accounting.journals.show', $r->journal_id) }}">{{ $r->number ?: '#'.$r->journal_id }}</a></td>
                </tr>
            @empty
                <tr><td colspan="8">No posted invoice records match this filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $rows->links() }}
@elseif($partyId)
    <h3>{{ $isVendor ? 'Vendor Payable' : 'Customer Receivable' }} Ledger — {{ $selectedParty->name ?? '' }}</h3>
    <p class="acc-hint">Control account {{ $isVendor ? '2100' : '1200' }}. Balance uses all posted source-linked rows in the period; text search only filters the displayed transactions. Opening-balance or standalone manual journal adjustments should be reviewed in the General Ledger.</p>
    <div class="d-flex flex-wrap gap-4 mb-3">
        <strong>Opening: ৳{{ number_format($opening / 10000, 4) }}</strong>
        <strong>Closing: ৳{{ number_format($ending / 10000, 4) }}</strong>
    </div>
    <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Date</th><th>Source</th><th>Transaction</th><th>Debit</th><th>Credit</th><th>Posted Journal</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr><td>{{ $r->entry_date }}</td><td>{{ $r->source_type }} #{{ $r->source_id }}</td><td>{{ $r->event_type }}</td>
            <td>{{ number_format((float)$r->debit, 4) }}</td><td>{{ number_format((float)$r->credit, 4) }}</td>
            <td><a href="{{ route('accounting.journals.show', $r->journal_id) }}">{{ $r->number ?: '#'.$r->journal_id }}</a></td></tr>
        @empty
            <tr><td colspan="6">No posted transactions match this filter.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $rows->links() }}
@else
    <p class="acc-hint">Select a {{ $isVendor ? 'vendor' : 'customer' }} above to see the posted accounting statement.</p>
@endif
</div>
</main>
@endsection
