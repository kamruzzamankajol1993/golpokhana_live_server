@extends('admin.master.master')
@section('title','Negative Stock Adjustment')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Negative Stock Adjustment</h1>
            <p class="text-muted mb-0">Controlled recovery of negative <strong>Kitchen Stock</strong>. This does not change Store Stock.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventory.ledger.index') }}" class="progga-btn progga-btn-secondary"><i class="bi bi-clipboard-data"></i> Inventory Audit</a>
            <a href="{{ route('inventory.ingredients.index') }}" class="progga-btn progga-btn-light"><i class="bi bi-arrow-left"></i> Ingredients</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Could not post adjustment.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="alert alert-warning">
        <strong>Who should use this?</strong> Inventory Manager. Super Admin is kept as an emergency fallback. Kitchen Manager cannot post this adjustment. Add only the quantity physically received/found; every adjustment is written to Inventory Audit with user, time and reason.
    </div>

    <div class="progga-card">
        <div class="progga-card-header"><div><strong>Current Negative Kitchen Stock</strong><div class="small text-muted">Partial adjustment is allowed. Adjustment quantity cannot exceed the current shortage.</div></div></div>
        <div class="progga-table-wrapper" style="border:none;border-radius:0;">
            <table class="progga-table">
                <thead><tr><th style="width:70px;">SL</th><th>Ingredient</th><th>Kitchen Stock</th><th>Shortage</th><th style="min-width:420px;">Adjustment</th></tr></thead>
                <tbody>
                @forelse($negativeStocks as $balance)
                    @php
                        $qty = (float) $balance->quantity_base;
                        $shortage = abs($qty);
                        $symbol = $balance->ingredient?->baseUnit?->symbol ?? '';
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $balance->ingredient?->name }}</strong><div class="small text-muted">{{ $balance->ingredient?->code }}</div></td>
                        <td><span class="progga-badge progga-badge-danger">{{ rtrim(rtrim(number_format($qty,8,'.',''),'0'),'.') }} {{ $symbol }}</span></td>
                        <td class="fw-semibold text-danger">{{ rtrim(rtrim(number_format($shortage,8,'.',''),'0'),'.') }} {{ $symbol }}</td>
                        <td>
                            <form method="POST" action="{{ route('inventory.negative-stock-adjustments.store') }}" class="row g-2 align-items-end negative-adjustment-form">
                                @csrf
                                <input type="hidden" name="ingredient_id" value="{{ $balance->ingredient_id }}">
                                <div class="col-md-3"><label class="progga-form-label">Qty</label><input type="number" step="0.00000001" min="0.00000001" max="{{ $shortage }}" name="adjust_quantity_base" value="{{ $shortage }}" class="progga-form-control" required></div>
                                <div class="col-md-6"><label class="progga-form-label">Reason</label><input type="text" name="reason" class="progga-form-control" value="Physical stock received to reconcile negative Kitchen Stock" maxlength="3000" required></div>
                                <div class="col-md-3"><button type="button" class="progga-btn progga-btn-primary w-100" onclick="confirmNegativeAdjustment(this)"><i class="bi bi-arrow-up-circle"></i> Adjust</button></div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-check-circle fs-3 d-block mb-2"></i>No negative Kitchen Stock remains.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
@section('script')
<script>
function confirmNegativeAdjustment(button){
    const form=button.closest('form');
    if(typeof Swal==='undefined'){ if(confirm('Post this Kitchen Stock adjustment?')) form.submit(); return; }
    Swal.fire({title:'Post negative stock adjustment?',text:'This creates a permanent Inventory Audit transaction and cannot be silently edited.',icon:'warning',showCancelButton:true,confirmButtonText:'Yes, post adjustment',cancelButtonText:'Cancel'}).then(r=>{if(r.isConfirmed)form.submit();});
}
</script>
@endsection
