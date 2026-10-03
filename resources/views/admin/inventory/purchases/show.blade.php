@extends('admin.master.master')
@section('title','Purchase '.$purchase->purchase_no)
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div><h1 class="progga-page-title">{{ $purchase->purchase_no }}</h1><p class="text-muted mb-0">{{ $purchase->vendor?->name }} · {{ optional($purchase->purchase_date)->format('d M Y') }} @if($purchase->voucher) · Voucher <a href="{{ route('inventory.purchase-vouchers.show',$purchase->voucher) }}">{{ $purchase->voucher->voucher_no }}</a> @endif</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('inventory.purchases.index') }}" class="progga-btn progga-btn-outline">Back</a>
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

    <div class="row g-4 mb-4"><div class="col-lg-8"><div class="progga-card h-100"><div class="p-4"><div class="row g-3"><div class="col-md-4"><small class="text-muted">Status</small><div><span class="badge {{ $purchase->status==='RECEIVED'?'bg-success':'bg-warning text-dark' }}">{{ $purchase->status }}</span></div></div><div class="col-md-4"><small class="text-muted">Invoice</small><div>{{ $purchase->invoice_no ?: '—' }}</div></div><div class="col-md-4"><small class="text-muted">Reference</small><div>{{ $purchase->reference_no ?: '—' }}</div></div>@if($purchase->voucher)<div class="col-md-4"><small class="text-muted">Approved Voucher</small><div><a href="{{ route('inventory.purchase-vouchers.show',$purchase->voucher) }}">{{ $purchase->voucher->voucher_no }} / R{{ $purchase->voucher->revision_no }}</a></div></div>@endif<div class="col-12"><small class="text-muted">Notes</small><div>{{ $purchase->notes ?: '—' }}</div></div></div></div></div></div>
    <div class="col-lg-4"><div class="progga-card h-100"><div class="p-4"><div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong>৳{{ number_format((float)$purchase->subtotal,2) }}</strong></div><div class="d-flex justify-content-between mb-2"><span>Discount</span><span>৳{{ number_format((float)$purchase->discount,2) }}</span></div><div class="d-flex justify-content-between mb-3"><span>Tax</span><span>৳{{ number_format((float)$purchase->tax,2) }}</span></div><div class="d-flex justify-content-between border-top pt-3"><strong>Total</strong><strong class="fs-5">৳{{ number_format((float)$purchase->total,2) }}</strong></div></div></div></div></div>

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

    <div class="progga-card mb-4"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ingredient</th><th>Input Qty</th><th>Conversion Snapshot</th><th>Base Qty</th><th>Purchase Price</th><th>Line Total</th></tr></thead><tbody>@foreach($purchase->items as $item)<tr><td><strong>{{ $item->ingredient?->name }}</strong></td><td>{{ rtrim(rtrim((string)$item->quantity,'0'),'.') }} {{ $item->packageConversion?->label ?: $item->unit?->symbol }}</td><td>× {{ rtrim(rtrim((string)$item->conversion_factor_snapshot,'0'),'.') }}</td><td class="fw-semibold">{{ rtrim(rtrim((string)$item->base_quantity,'0'),'.') }} {{ $item->ingredient?->baseUnit?->symbol }}</td><td>@if($item->package_conversion_id || $item->unit?->dimension === 'PACKAGE') ৳{{ number_format((float)$item->unit_price,2) }}<div class="small text-muted">per {{ $item->packageConversion?->label ?: $item->unit?->name }}</div> @else ৳{{ number_format((float)$item->line_total,2) }}<div class="small text-muted">total for entered quantity</div> @endif</td><td>৳{{ number_format((float)$item->line_total,2) }}</td></tr>@endforeach</tbody></table></div></div>

    @if($purchase->voucher)
    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>Approved Voucher vs Actual Supply</strong><div class="small text-muted">Short supply is accepted and remains visible here. Any overrun must be re-approved before stock receiving.</div></div></div>
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

    @if($purchase->isEditable())
        @if($purchase->voucher && !$purchase->voucher->canReceiveSupply())
            <div class="alert alert-warning"><strong>Waiting for voucher re-approval.</strong> Store Stock has not changed. Complete the current approval round before receiving this supplier delivery.</div>
        @else
            @can('inventory-purchase-receive')
            <div class="progga-card"><div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><strong>Receive Supplier Delivery</strong><div class="text-muted small">The approved voucher limit will be verified again. If valid, Store Stock increases and a PURCHASE_RECEIVE transaction is created.</div></div><form method="POST" action="{{ route('inventory.purchases.receive',$purchase) }}">@csrf<button type="button" class="progga-btn progga-btn-primary" onclick="confirmPurchaseReceiveShow(this)"><i class="bi bi-box-arrow-in-down"></i> Receive Supply</button></form></div></div>
            @endcan
        @endif
    @else
        <div class="alert alert-success">Received {{ optional($purchase->received_at)->format('d M Y h:i A') }}. Transaction: {{ $purchase->receivedMovement?->movement_no ?: '—' }}.</div>
    @endif
</main>
@endsection
@section('script')
<script>
function confirmPurchaseDeleteShow(button){const form=button.closest('form');if(typeof Swal==='undefined'){if(confirm('Delete this draft purchase?'))form.submit();return;}Swal.fire({title:'Delete draft purchase?',text:'This draft will be permanently deleted. No stock has been affected yet.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc3545',confirmButtonText:'Yes, delete it!',cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});}
function confirmPurchaseReceiveShow(button){const form=button.closest('form');if(typeof Swal==='undefined'){if(confirm('Receive this purchase and increase stock?'))form.submit();return;}Swal.fire({title:'Receive supplier delivery?',text:'The approved voucher will be checked again before Store Stock is increased.',icon:'question',showCancelButton:true,confirmButtonText:'Yes, receive supply',cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});}
</script>
@endsection
