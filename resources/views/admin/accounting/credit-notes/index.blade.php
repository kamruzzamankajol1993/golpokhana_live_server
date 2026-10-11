{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Credit Notes and Refunds')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<p class="alert alert-info">These are <strong>explicit, authorized financial corrections</strong>, not automatic edits to operational POS or supplier documents. They must reference verified original source documents and cash/bank evidence. Every correction remains DRAFT until separately approved and posted.</p>
<div class="row g-3">
<div class="col-lg-4"><div class="acc-panel"><h3>Sales Refund / Credit Note</h3><form method="post" action="{{ route('accounting.credits.sales') }}">@csrf
<div class="mb-2"><label>Completed Order ID</label><input type="number" name="order_id" class="form-control" min="1" required></div>
<div class="mb-2"><label>Refund reference</label><input name="refund_reference" class="form-control" required maxlength="150"></div>
<div class="mb-2"><label>Refund Date</label><input type="date" name="refund_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
<div class="mb-2"><label>Refund from</label><select name="payment_method" class="form-select"><option>Cash</option><option>Bank</option><option>MFS</option></select></div>
<div class="mb-2"><label>Total Refund (includes VAT)</label><input name="refund_amount" class="form-control" type="number" step="0.0001" min="0.0001" required></div>
<div class="mb-2"><label>Refunded VAT portion</label><input name="vat_refund" class="form-control" type="number" step="0.0001" min="0" value="0" required></div>
<div class="mb-3"><label>Refund evidence / reason</label><textarea name="supporting_evidence" class="form-control" minlength="15" maxlength="3000" required></textarea></div><button class="btn btn-primary">Prepare Refund Journal</button></form>
</div></div>
<div class="col-lg-4"><div class="acc-panel"><h3>Booking Deposit Refund</h3><form method="post" action="{{ route('accounting.credits.booking') }}">@csrf
<div class="mb-2"><label>Table Booking ID</label><input class="form-control" name="table_booking_id" type="number" min="1" required></div>
<div class="mb-2"><label>Refund reference</label><input class="form-control" name="refund_reference" maxlength="150" required></div>
<div class="mb-2"><label>Date</label><input class="form-control" name="refund_date" type="date" value="{{ date('Y-m-d') }}" required></div>
<div class="mb-2"><label>Payment method</label><select name="payment_method" class="form-select"><option>Cash</option><option>Bank</option><option>MFS</option></select></div>
<div class="mb-2"><label>Amount (BDT)</label><input name="amount" class="form-control" type="number" step="0.0001" min="0.0001" required></div>
<div class="mb-3"><label>Refund evidence / reason</label><textarea name="supporting_evidence" class="form-control" required minlength="15" maxlength="3000"></textarea></div><button class="btn btn-primary">Prepare Deposit Refund</button></form></div></div>
<div class="col-lg-4"><div class="acc-panel"><h3>Supplier Return Credit Note</h3><form method="post" action="{{ route('accounting.credits.supplier') }}">@csrf
<div class="mb-2"><label>Received Purchase ID</label><input name="purchase_id" class="form-control" type="number" min="1" required></div>
<div class="mb-2"><label>Posted Purchase Return Movement ID</label><input name="stock_movement_id" class="form-control" type="number" min="1" required></div>
<div class="mb-2"><label>Vendor credit-note reference</label><input name="credit_note_reference" class="form-control" maxlength="150" required></div>
<div class="mb-2"><label>Date</label><input class="form-control" name="credit_note_date" type="date" value="{{ date('Y-m-d') }}" required></div>
<div class="mb-2"><label>Credit Amount</label><input name="credit_amount" class="form-control" type="number" min="0.0001" step="0.0001" required></div>
<div class="mb-3"><label>Supplier evidence / reference</label><textarea name="supporting_evidence" class="form-control" required minlength="15" maxlength="3000"></textarea></div><button class="btn btn-primary">Prepare Supplier Credit</button></form></div></div>
</div>
<div class="acc-panel"><h3>Financial Corrections Register</h3>
@php 
$correctionPage=$corrections; $sourceLabels=['Sales Refund'=>'Order #','Deposit Refund'=>'Booking #','Supplier Credit'=>'Purchase #']; 
@endphp
<div class="acc-ajax-list" id="acc-list-credit-notes-1"><div class="table-responsive"><table class="table"><thead><tr><th>Type</th><th>Source</th><th>Reference</th><th>BDT</th><th>Journal</th></tr></thead><tbody>
@forelse($correctionPage as $row)
<tr><td>{{ $row->correction_type }}</td><td>{{ $sourceLabels[$row->correction_type] ?? 'Source #' }}{{ $row->source_id }}</td><td>{{ $row->reference }}</td><td>{{ number_format($row->amount,4) }}</td><td><a href="{{ route('accounting.journals.show',$row->journal_entry_id) }}">{{ $row->status }}</a></td></tr>
@empty
<tr><td colspan="5">No financial corrections yet.</td></tr>
@endforelse
</tbody></table></div>
@include('admin.accounting.shared._pagination',['paginator'=>$correctionPage,'label'=>'corrections'])
</div></div></main>
@endsection
