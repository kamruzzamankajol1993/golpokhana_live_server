@extends('admin.master.master')
@section('title','Purchase Details')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Purchase {{ $purchase->purchase_no }}</h1>
            <p class="text-muted mb-0">Supplier receipt, GRN, stock receiving, payment and voucher details.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('inventory.purchases.index') }}" class="progga-btn progga-btn-outline">Back</a>
            <a href="{{ route('inventory.vendors.show',$purchase->vendor) }}" class="progga-btn progga-btn-outline"><i class="bi bi-building"></i> Vendor</a>
            <a href="{{ route('inventory.purchases.invoice-pdf',$purchase) }}" class="progga-btn progga-btn-outline" title="Download Purchase Invoice PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            @if($purchase->original_invoice_path)
                <a href="{{ route('inventory.purchases.original-invoice',$purchase) }}" class="progga-btn progga-btn-outline" title="Download Original Supplier Invoice"><i class="bi bi-paperclip"></i> Original</a>
            @endif
            @if($purchase->isEditable() && (!$purchase->voucher || $purchase->voucher->canReceiveSupply()))
                @can('inventory-purchase-create')
                <a href="{{ route('inventory.purchases.edit',$purchase) }}" class="progga-btn progga-btn-outline progga-btn-icon" title="Edit Draft"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('inventory.purchases.destroy',$purchase) }}" class="d-inline">@csrf @method('DELETE')<button type="button" class="progga-btn progga-btn-danger progga-btn-icon" title="Delete Draft" onclick="confirmPurchaseDeleteShow(this)"><i class="bi bi-trash"></i></button></form>
                @endcan
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @php
        $paidAmount = $purchase->paidAmount();
        $dueAmount = $purchase->dueAmount();
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="progga-card h-100"><div class="p-4"><div class="row g-3">
                <div class="col-md-4"><small class="text-muted">Vendor</small><div><a href="{{ route('inventory.vendors.show',$purchase->vendor) }}"><strong>{{ $purchase->vendor?->name }}</strong></a></div></div>
                <div class="col-md-4"><small class="text-muted">Purchase Date</small><div>{{ optional($purchase->purchase_date)->format('d M Y') }}</div></div>
                <div class="col-md-4"><small class="text-muted">Status</small><div><span class="badge {{ $purchase->status==='RECEIVED'?'bg-success':'bg-warning text-dark' }}">{{ $purchase->status }}</span></div></div>
                <div class="col-md-4"><small class="text-muted">Invoice</small><div>{{ $purchase->invoice_no ?: '—' }}</div></div>
                <div class="col-md-4"><small class="text-muted">Reference</small><div>{{ $purchase->reference_no ?: '—' }}</div></div>
                <div class="col-md-4"><small class="text-muted">GRN Status</small><div><span class="badge {{ $purchase->grn_status==='CONFIRMED'?'bg-success':'bg-secondary' }}">{{ $purchase->grn_status ?: 'PENDING' }}</span></div></div>
                @if($purchase->voucher)<div class="col-md-4"><small class="text-muted">Approved Voucher</small><div><a href="{{ route('inventory.purchase-vouchers.show',$purchase->voucher) }}">{{ $purchase->voucher->voucher_no }} / R{{ $purchase->voucher->revision_no }}</a></div></div>@endif
                <div class="col-md-4"><small class="text-muted">Created By</small><div>{{ $purchase->creator?->name ?: '—' }}</div></div>
                <div class="col-md-4"><small class="text-muted">Received By</small><div>{{ $purchase->receiver?->name ?: '—' }}</div></div>
                <div class="col-12"><small class="text-muted">Notes</small><div>{{ $purchase->notes ?: '—' }}</div></div>
            </div></div></div>
        </div>
        <div class="col-lg-4">
            <div class="progga-card h-100"><div class="p-4">
                <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong>৳{{ number_format((float)$purchase->subtotal,2) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Discount</span><span>৳{{ number_format((float)$purchase->discount,2) }}</span></div>
                <div class="d-flex justify-content-between mb-3"><span>Tax</span><span>৳{{ number_format((float)$purchase->tax,2) }}</span></div>
                <div class="d-flex justify-content-between border-top pt-3"><strong>Total</strong><strong class="fs-5">৳{{ number_format((float)$purchase->total,2) }}</strong></div>
                <div class="d-flex justify-content-between mt-3"><span>Paid</span><strong class="text-success">৳{{ number_format($paidAmount,2) }}</strong></div>
                <div class="d-flex justify-content-between mt-2"><span>Due</span><strong class="{{ $dueAmount>0?'text-danger':'text-success' }}">৳{{ number_format($dueAmount,2) }}</strong></div>
            </div></div>
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>GRN / Goods Received Note</strong><div class="small text-muted">GRN confirmation is mandatory before stock receiving.</div></div></div>
        <div class="p-4">
            <div class="mb-3" style="white-space:pre-wrap;">{{ $purchase->grn ?: 'No GRN has been entered yet.' }}</div>
            @if($purchase->grn_status === 'CONFIRMED')
                <div class="small text-muted">Confirmed by <strong>{{ $purchase->grnConfirmer?->name ?: '—' }}</strong> on {{ optional($purchase->grn_confirmed_at)->format('d M Y h:i A') ?: '—' }}.</div>
            @endif
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <strong>Original Supplier Invoice / Receipt</strong>
                @if($purchase->original_invoice_path)
                    <div class="text-muted small">{{ $purchase->original_invoice_name ?: 'Original invoice file' }}@if($purchase->original_invoice_size) · {{ number_format($purchase->original_invoice_size / 1024, 1) }} KB @endif</div>
                @else
                    <div class="text-muted small">No original invoice file was uploaded with this purchase.</div>
                @endif
            </div>
            @if($purchase->original_invoice_path)
                <a href="{{ route('inventory.purchases.original-invoice',$purchase) }}" class="progga-btn progga-btn-outline"><i class="bi bi-download"></i> Download Original</a>
            @endif
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header"><strong>Purchased Ingredients</strong></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th>Input Qty</th><th>Conversion Snapshot</th><th>Base Qty</th><th>Purchase Price</th><th>Line Total</th></tr></thead><tbody>
        @foreach($purchase->items as $item)
            <tr><td><strong>{{ $item->ingredient?->name }}</strong></td><td>{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->packageConversion?->label ?: $item->unit?->symbol }}</td><td>× {{ rtrim(rtrim((string)$item->conversion_factor_snapshot,'0'),'.') }}</td><td class="fw-semibold">{{ rtrim(rtrim((string)$item->base_quantity,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td><td>@if($item->package_conversion_id || $item->unit?->dimension === 'PACKAGE') ৳{{ number_format((float)$item->unit_price,2) }}<div class="small text-muted">per {{ $item->packageConversion?->label ?: $item->unit?->name }}</div> @else ৳{{ number_format((float)$item->line_total,2) }}<div class="small text-muted">total for entered quantity</div> @endif</td><td>৳{{ number_format((float)$item->line_total,2) }}</td></tr>
        @endforeach
        </tbody></table></div>
    </div>

    @if($purchase->voucher)
    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>Approved Voucher vs Actual Supply</strong><div class="small text-muted">Short supply is accepted. Any overrun requires re-approval before stock receiving.</div></div></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th class="text-end">Approved Base Qty</th><th class="text-end">Actual Base Qty</th><th class="text-end">Variance</th><th class="text-end">Approved Value</th><th class="text-end">Actual Value</th></tr></thead><tbody>
            @foreach($purchase->voucher->items as $approved)
                @php
                    $actual = $purchase->items->firstWhere('ingredient_id',$approved->ingredient_id);
                    $actualBase = (float)($actual?->base_quantity ?? 0);
                    $approvedBase = (float)$approved->base_quantity;
                    $variance = $actualBase - $approvedBase;
                @endphp
                <tr><td>{{ $approved->ingredient?->name }}</td><td class="text-end">{{ rtrim(rtrim(number_format($approvedBase,8,'.',''),'0'),'.') }} {{ $approved->ingredient?->baseUnit?->symbol }}</td><td class="text-end">{{ rtrim(rtrim(number_format($actualBase,8,'.',''),'0'),'.') }} {{ $approved->ingredient?->baseUnit?->symbol }}</td><td class="text-end {{ $variance < 0 ? 'text-warning' : ($variance > 0 ? 'text-danger' : 'text-success') }}">{{ $variance > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($variance,8,'.',''),'0'),'.') }} {{ $approved->ingredient?->baseUnit?->symbol }}</td><td class="text-end">৳{{ number_format((float)$approved->line_total,2) }}</td><td class="text-end">৳{{ number_format((float)($actual?->line_total ?? 0),2) }}</td></tr>
            @endforeach
        </tbody></table></div>
    </div>
    @endif

    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>Vendor Payment History</strong><div class="small text-muted">Payments posted against this purchase.</div></div><a href="{{ route('inventory.vendors.show',$purchase->vendor) }}" class="progga-btn progga-btn-outline progga-btn-sm">Open Vendor Account</a></div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Payment</th><th>Date</th><th>Type</th><th>Reference</th><th class="text-end">Amount</th><th>By</th></tr></thead><tbody>
            @forelse($purchase->vendorPayments as $payment)
                <tr><td>{{ $payment->payment_no }}</td><td>{{ optional($payment->payment_date)->format('d M Y') }}</td><td>{{ $payment->payment_type }}</td><td class="small">@if($payment->card_reference)Card: {{ $payment->card_reference }}@endif @if($payment->mfs_reference)<div>MFS: {{ $payment->mfs_reference }}</div>@endif @if(!$payment->card_reference && !$payment->mfs_reference)—@endif</td><td class="text-end fw-semibold">৳{{ number_format((float)$payment->amount,2) }}</td><td>{{ $payment->creator?->name ?: '—' }}</td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">No payment posted for this purchase.</td></tr>@endforelse
        </tbody></table></div>
    </div>

    @if($purchase->isEditable())
        @if($purchase->voucher && !$purchase->voucher->canReceiveSupply())
            <div class="alert alert-warning"><strong>Waiting for voucher re-approval.</strong> Store Stock has not changed. Complete the current approval round before receiving this supplier delivery.</div>
        @else
            @can('inventory-purchase-receive')
            <div class="progga-card">
                <div class="p-4">
                    <div class="mb-3"><strong>Receive Supplier Delivery</strong><div class="text-muted small">Enter the GRN, confirm it, then the voucher limits are checked and Store Stock is increased.</div></div>
                    <form method="POST" action="{{ route('inventory.purchases.receive',$purchase) }}" id="receivePurchaseForm">@csrf
                        <div class="mb-3"><label class="progga-form-label">GRN <span class="progga-required">*</span></label><textarea name="grn" id="receiveGrn" class="progga-form-control" rows="4" required>{{ old('grn',$purchase->grn) }}</textarea></div>
                        <label class="d-flex align-items-start gap-2 mb-3"><input type="checkbox" name="grn_confirmed" id="receiveGrnConfirmed" value="1" required><span><strong>Confirm GRN</strong><span class="d-block small text-muted">I confirm the delivered goods match this GRN.</span></span></label>
                        <button type="button" class="progga-btn progga-btn-primary" onclick="confirmPurchaseReceiveShow(this)"><i class="bi bi-box-arrow-in-down"></i> Confirm GRN & Receive Supply</button>
                    </form>
                </div>
            </div>
            @endcan
        @endif
    @else
        <div class="alert alert-success">Received {{ optional($purchase->received_at)->format('d M Y h:i A') }}. Inventory Audit transaction: {{ $purchase->receivedMovement?->movement_no ?: '—' }}.</div>
    @endif
</main>
@endsection
@section('script')
<script>
function confirmPurchaseDeleteShow(button){const form=button.closest('form');if(typeof Swal==='undefined'){if(confirm('Delete this draft purchase?'))form.submit();return;}Swal.fire({title:'Delete draft purchase?',text:'This draft will be permanently deleted. No stock has been affected yet.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc3545',confirmButtonText:'Yes, delete it!',cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});}
function confirmPurchaseReceiveShow(button){const form=button.closest('form');const grn=(document.getElementById('receiveGrn')?.value||'').trim();const checked=document.getElementById('receiveGrnConfirmed')?.checked;if(!grn||!checked){if(typeof Swal==='undefined'){alert('Enter and confirm the GRN before receiving stock.');}else{Swal.fire({title:'GRN confirmation required',text:'Enter GRN details and tick Confirm GRN before receiving stock.',icon:'warning'});}return;}if(typeof Swal==='undefined'){if(confirm('Confirm GRN and receive this purchase?'))form.submit();return;}Swal.fire({title:'Confirm GRN & receive supply?',text:'The approved voucher will be checked again. If valid, Store Stock will increase and an Inventory Audit entry will be created.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, receive supply',cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});}
</script>
@endsection
