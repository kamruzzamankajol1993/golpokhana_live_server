@extends('admin.master.master')
@section('title','Purchase Voucher '.$voucher->voucher_no)
@section('body')
@php
    $currentApprovals = $voucher->currentApprovals->where('revision_no',$voucher->revision_no)->sortBy('approval_order');
    $badge = match($voucher->status){'APPROVED','COMPLETED'=>'success','REJECTED'=>'danger','SENT_TO_VENDOR'=>'primary','PENDING_APPROVAL'=>'warning',default=>'secondary'};
@endphp
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $voucher->voucher_no }} <small class="text-muted">R{{ $voucher->revision_no }}</small></h1><p class="text-muted mb-0">Purchase Voucher / Approval document</p></div>
        <div class="d-flex flex-wrap gap-2">
            @can('inventory-purchase-voucher-manage')<a href="{{ route('inventory.purchase-vouchers.index') }}" class="progga-btn progga-btn-outline">Back</a>@endcan
            <a href="{{ route('inventory.purchase-vouchers.pdf',$voucher) }}" class="progga-btn progga-btn-outline"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            @if($voucher->isEditable()) @can('inventory-purchase-voucher-manage')<a href="{{ route('inventory.purchase-vouchers.edit',$voucher) }}" class="progga-btn progga-btn-secondary"><i class="bi bi-pencil"></i> Edit</a>@endcan @endif
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if($voucher->reapproval_reason)<div class="alert alert-warning"><strong>Re-approval reason:</strong> {{ $voucher->reapproval_reason }}<br><small>Stock has not been increased while this approval round is pending.</small></div>@endif

    <div class="row g-4 mb-4">
        <div class="col-lg-8"><div class="progga-card h-100"><div class="p-4"><div class="row g-3">
            <div class="col-md-4"><small class="text-muted d-block">Vendor</small><strong>{{ $voucher->vendor?->name }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Voucher Date</small><strong>{{ optional($voucher->voucher_date)->format('d M Y') }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Status</small><span class="badge bg-{{ $badge }} {{ $badge==='warning'?'text-dark':'' }}">{{ str_replace('_',' ',$voucher->status) }}</span></div>
            <div class="col-md-4"><small class="text-muted d-block">Created By</small>{{ $voucher->creator?->name ?: '—' }}</div>
            <div class="col-md-4"><small class="text-muted d-block">Submitted</small>{{ $voucher->submitted_at?->format('d M Y h:i A') ?: '—' }}</div>
            <div class="col-md-4"><small class="text-muted d-block">Approved</small>{{ $voucher->approved_at?->format('d M Y h:i A') ?: '—' }}</div>
            <div class="col-md-4"><small class="text-muted d-block">Sent to Vendor</small>{{ $voucher->sent_to_vendor_at?->format('d M Y h:i A') ?: '—' }}</div>
            <div class="col-md-4"><small class="text-muted d-block">Sent By</small>{{ $voucher->sentToVendorBy?->name ?: '—' }}</div>
            <div class="col-12"><small class="text-muted d-block">Notes</small>{{ $voucher->notes ?: '—' }}</div>
        </div></div></div></div>
        <div class="col-lg-4"><div class="progga-card h-100"><div class="p-4">
            <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong>৳{{ number_format((float)$voucher->subtotal,2) }}</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>Discount</span><span>৳{{ number_format((float)$voucher->discount,2) }}</span></div>
            <div class="d-flex justify-content-between mb-3"><span>Tax</span><span>৳{{ number_format((float)$voucher->tax,2) }}</span></div>
            <div class="d-flex justify-content-between border-top pt-3"><strong>Approved / Requested Total</strong><strong class="fs-5">৳{{ number_format((float)$voucher->total,2) }}</strong></div>
        </div></div></div>
    </div>

    <div class="progga-card mb-4"><div class="progga-card-header"><strong>Voucher Items</strong></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th class="text-end">Quantity</th><th>Unit</th><th class="text-end">Price</th><th class="text-end">Line Total</th></tr></thead><tbody>
        @foreach($voucher->items as $item)<tr><td>{{ $item->ingredient?->name }}</td><td class="text-end">{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }}</td><td>{{ $item->packageConversion?->label ?: ($item->unit?->name.' ('.$item->unit?->symbol.')') }}</td><td class="text-end">৳{{ number_format((float)$item->unit_price,2) }}</td><td class="text-end fw-semibold">৳{{ number_format((float)$item->line_total,2) }}</td></tr>@endforeach
    </tbody></table></div></div>

    <div class="progga-card mb-4"><div class="progga-card-header"><strong>Approval Chain — Revision {{ $voucher->revision_no }}</strong></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Level</th><th>Officer</th><th>Status</th><th>Action Time</th><th>Comment</th></tr></thead><tbody>
        @forelse($currentApprovals as $approval)<tr><td>Level {{ $approval->approval_order }}</td><td>{{ $approval->approver?->name ?: $approval->approver_name ?: 'Former / unavailable user' }}<br><small class="text-muted">{{ $approval->approver?->email ?: $approval->approver_email }}</small></td><td><span class="badge {{ $approval->status==='APPROVED'?'bg-success':($approval->status==='REJECTED'?'bg-danger':'bg-warning text-dark') }}">{{ $approval->status }}</span></td><td>{{ $approval->acted_at?->format('d M Y h:i A') ?: 'Waiting' }}</td><td>{{ $approval->comment ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Approval round has not been created yet.</td></tr>@endforelse
    </tbody></table></div></div>

    @if($canAct)
    <div class="progga-card mb-4"><div class="p-4"><h5 class="mb-3">Your Approval Action</h5><div class="row g-3"><div class="col-lg-8"><textarea id="approvalComment" class="progga-form-control" rows="3" placeholder="Optional approval comment; rejection requires a reason."></textarea></div><div class="col-lg-4 d-grid gap-2"><form method="POST" action="{{ route('inventory.purchase-vouchers.approve',$voucher) }}" id="approveVoucherForm">@csrf<input type="hidden" name="comment" id="approveComment"><button type="button" class="progga-btn progga-btn-primary w-100" onclick="actOnVoucher('approve')"><i class="bi bi-check2-circle"></i> Approve</button></form><form method="POST" action="{{ route('inventory.purchase-vouchers.reject',$voucher) }}" id="rejectVoucherForm">@csrf<input type="hidden" name="comment" id="rejectComment"><button type="button" class="progga-btn progga-btn-danger w-100" onclick="actOnVoucher('reject')"><i class="bi bi-x-circle"></i> Reject</button></form></div></div></div></div>
    @endif

    @can('inventory-purchase-voucher-manage')
        @if($voucher->canSubmit())
        <div class="progga-card mb-4"><div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><strong>Send for Approval</strong><div class="text-muted small">Creates the configured officer approval sequence. The voucher will be locked until approved or rejected.</div></div><form method="POST" action="{{ route('inventory.purchase-vouchers.submit',$voucher) }}" id="submitVoucherForm">@csrf<button type="button" class="progga-btn progga-btn-primary" onclick="confirmVoucherAction('submit')"><i class="bi bi-send-check"></i> Send for Approval</button></form></div></div>
        @endif
        @if($voucher->canSendToVendor())
        <div class="progga-card mb-4"><div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><strong>All approvals complete</strong><div class="text-muted small">Mark this approved voucher as sent to the vendor. No stock is changed yet.</div></div><form method="POST" action="{{ route('inventory.purchase-vouchers.send-to-vendor',$voucher) }}" id="sendVendorForm">@csrf<button type="button" class="progga-btn progga-btn-primary" onclick="confirmVoucherAction('vendor')"><i class="bi bi-send"></i> Send to Vendor</button></form></div></div>
        @endif
        @if($voucher->canReceiveSupply())
        <div class="progga-card mb-4"><div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><strong>Supplier delivery / invoice received?</strong><div class="text-muted small">Enter actual supplied quantity and supplier invoice. If actual quantity/value exceeds this approved revision, re-approval starts automatically and stock remains unchanged.</div></div><a href="{{ route('inventory.purchase-vouchers.receive-supply',$voucher) }}" class="progga-btn progga-btn-primary"><i class="bi bi-box-arrow-in-down"></i> Receive Supply</a></div></div>
        @endif
    @endcan

    @if($voucher->convertedPurchase || $voucher->purchase?->status === 'RECEIVED')
    <div class="alert alert-success"><strong>Completed:</strong> this voucher has been converted to Purchase <a href="{{ route('inventory.purchases.show',$voucher->convertedPurchase ?: $voucher->purchase) }}">{{ ($voucher->convertedPurchase ?: $voucher->purchase)?->purchase_no }}</a> and stock has been received.</div>
    @endif

    @if($voucher->revisions->isNotEmpty())
    <div class="progga-card"><div class="progga-card-header"><strong>Previous Revisions / Audit History</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Revision</th><th>Reason</th><th>Archived</th><th>Archived By</th></tr></thead><tbody>@foreach($voucher->revisions as $revision)<tr><td>R{{ $revision->revision_no }}</td><td>{{ $revision->reason ?: '—' }}</td><td>{{ $revision->created_at?->format('d M Y h:i A') }}</td><td>{{ $revision->archivedBy?->name ?: 'System' }}</td></tr>@endforeach</tbody></table></div></div>
    @endif
</main>
@endsection
@section('script')
<script>
function actOnVoucher(action){const comment=document.getElementById('approvalComment').value.trim();if(action==='reject'&&!comment){if(typeof Swal!=='undefined')Swal.fire('Reason required','Please enter a rejection reason.','warning');else alert('Please enter a rejection reason.');return;}document.getElementById(action==='approve'?'approveComment':'rejectComment').value=comment;const form=document.getElementById(action==='approve'?'approveVoucherForm':'rejectVoucherForm');const title=action==='approve'?'Approve this voucher?':'Reject this voucher?';if(typeof Swal==='undefined'){if(confirm(title))form.submit();return;}Swal.fire({title,icon:action==='approve'?'question':'warning',showCancelButton:true,confirmButtonText:action==='approve'?'Approve':'Reject'}).then(r=>{if(r.isConfirmed)form.submit();});}
function confirmVoucherAction(action){const form=document.getElementById(action==='submit'?'submitVoucherForm':'sendVendorForm');const title=action==='submit'?'Send for approval?':'Mark as sent to vendor?';if(typeof Swal==='undefined'){if(confirm(title))form.submit();return;}Swal.fire({title,icon:'question',showCancelButton:true,confirmButtonText:'Yes'}).then(r=>{if(r.isConfirmed)form.submit();});}
</script>
@endsection
