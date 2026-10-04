@extends('admin.master.master')
@section('title','Vendor Details')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">{{ $vendor->name }}</h1>
            <p class="text-muted mb-0">Vendor profile, purchase history, due balance and payment history.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('inventory.vendors.index') }}" class="progga-btn progga-btn-outline">Back</a>
            <a href="{{ route('inventory.vendors.edit',$vendor) }}" class="progga-btn progga-btn-primary"><i class="bi bi-pencil"></i> Edit Vendor</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="progga-card h-100"><div class="p-4"><small class="text-muted">Received Purchase Total</small><div class="fs-3 fw-bold mt-1">৳{{ number_format($totalPurchase,2) }}</div></div></div></div>
        <div class="col-md-4"><div class="progga-card h-100"><div class="p-4"><small class="text-muted">Total Paid</small><div class="fs-3 fw-bold text-success mt-1">৳{{ number_format($totalPaid,2) }}</div></div></div></div>
        <div class="col-md-4"><div class="progga-card h-100"><div class="p-4"><small class="text-muted">Current Due</small><div class="fs-3 fw-bold {{ $totalDue > 0 ? 'text-danger' : 'text-success' }} mt-1">৳{{ number_format($totalDue,2) }}</div></div></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="progga-card h-100">
                <div class="progga-card-header"><strong>Vendor Information</strong></div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-6"><small class="text-muted">Phone</small><div>{{ $vendor->phone ?: '—' }}</div></div>
                        <div class="col-md-6"><small class="text-muted">Email</small><div>{{ $vendor->email ?: '—' }}</div></div>
                        <div class="col-12"><small class="text-muted">Address</small><div>{{ $vendor->address ?: '—' }}</div></div>
                        <div class="col-md-4"><small class="text-muted">TIN</small><div>{{ $vendor->tin ?: '—' }}</div>@if($vendor->tin_file_path)<a class="small" href="{{ route('inventory.vendors.document',[$vendor,'tin']) }}"><i class="bi bi-paperclip"></i> {{ $vendor->tin_file_name ?: 'TIN file' }}</a>@endif</div>
                        <div class="col-md-4"><small class="text-muted">BIN</small><div>{{ $vendor->bin ?: '—' }}</div>@if($vendor->bin_file_path)<a class="small" href="{{ route('inventory.vendors.document',[$vendor,'bin']) }}"><i class="bi bi-paperclip"></i> {{ $vendor->bin_file_name ?: 'BIN file' }}</a>@endif</div>
                        <div class="col-md-4"><small class="text-muted">Tax</small><div>{{ $vendor->tax ?: '—' }}</div>@if($vendor->tax_file_path)<a class="small" href="{{ route('inventory.vendors.document',[$vendor,'tax']) }}"><i class="bi bi-paperclip"></i> {{ $vendor->tax_file_name ?: 'Tax file' }}</a>@endif</div>
                        <div class="col-md-4"><small class="text-muted">TDS</small><div>{{ $vendor->tds ?: '—' }}</div></div>
                        <div class="col-md-4"><small class="text-muted">VDS</small><div>{{ $vendor->vds ?: '—' }}</div></div>
                        <div class="col-md-4"><small class="text-muted">Status</small><div><span class="progga-badge progga-badge-{{ $vendor->is_active?'success':'neutral' }}">{{ $vendor->is_active?'Active':'Inactive' }}</span></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="progga-card h-100">
                <div class="progga-card-header"><div><strong>Pay Vendor</strong><div class="small text-muted">Payment is posted against a received purchase.</div></div></div>
                <div class="p-4">
                    @if($outstandingPurchases->isEmpty())
                        <div class="alert alert-success mb-0">No outstanding received purchase is available for payment.</div>
                    @else
                    <form method="POST" action="{{ route('inventory.vendors.payments.store',$vendor) }}" id="vendorPaymentForm">
                        @csrf
                        <div class="mb-3">
                            <label class="progga-form-label">Purchase <span class="progga-required">*</span></label>
                            <select name="purchase_id" id="vendorPaymentPurchase" class="progga-form-control" required>
                                <option value="">Select purchase</option>
                                @foreach($outstandingPurchases as $purchase)
                                    <option value="{{ $purchase->id }}" data-due="{{ number_format($purchase->dueAmount(),4,'.','') }}" @selected((string)old('purchase_id')===(string)$purchase->id)>{{ $purchase->purchase_no }} · Due ৳{{ number_format($purchase->dueAmount(),2) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6"><label class="progga-form-label">Payment Date</label><input type="text" name="payment_date" value="{{ old('payment_date',now()->format('Y-m-d')) }}" class="progga-form-control progga-datepicker" required></div>
                            <div class="col-md-6"><label class="progga-form-label">Payment Type</label><select name="payment_type" id="vendorPaymentType" class="progga-form-control" required><option value="Cash" @selected(old('payment_type','Cash')==='Cash')>Cash</option><option value="Card" @selected(old('payment_type')==='Card')>Card</option><option value="MFS" @selected(old('payment_type')==='MFS')>MFS</option><option value="Split" @selected(old('payment_type')==='Split')>Split</option></select></div>
                        </div>
                        <div class="mb-3"><label class="progga-form-label">Amount <span class="progga-required">*</span></label><input type="number" step="0.01" min="0.01" name="amount" id="vendorPaymentAmount" value="{{ old('amount') }}" class="progga-form-control" required><small class="text-muted" id="vendorPaymentDueText"></small></div>

                        <div id="singleCardFields" class="payment-extra d-none">
                            <div class="row g-2 mb-3"><div class="col-md-6"><label class="progga-form-label">Card Type</label><select name="card_type" class="progga-form-control"><option value="">Select card</option>@foreach($cardTypes as $type)<option value="{{ $type }}" @selected(old('card_type')===$type)>{{ $type }}</option>@endforeach</select></div><div class="col-md-6"><label class="progga-form-label">Bank / Card Reference</label><input name="card_reference" value="{{ old('card_reference') }}" class="progga-form-control"></div></div>
                        </div>
                        <div id="singleMfsFields" class="payment-extra d-none">
                            <div class="row g-2 mb-3"><div class="col-md-6"><label class="progga-form-label">MFS Provider</label><select name="mfs_provider" class="progga-form-control"><option value="">Select MFS</option>@foreach($mfsProviders as $provider)<option value="{{ $provider }}" @selected(old('mfs_provider')===$provider)>{{ $provider }}</option>@endforeach</select></div><div class="col-md-6"><label class="progga-form-label">MFS Reference</label><input name="mfs_reference" value="{{ old('mfs_reference') }}" class="progga-form-control"></div></div>
                        </div>
                        <div id="splitFields" class="payment-extra d-none">
                            <div class="row g-2 mb-2"><div class="col-md-4"><label class="progga-form-label">Cash</label><input type="number" step="0.01" min="0" name="paid_in_cash" id="vendorSplitCash" value="{{ old('paid_in_cash',0) }}" class="progga-form-control"></div><div class="col-md-4"><label class="progga-form-label">Card</label><input type="number" step="0.01" min="0" name="paid_in_card" id="vendorSplitCard" value="{{ old('paid_in_card',0) }}" class="progga-form-control"></div><div class="col-md-4"><label class="progga-form-label">MFS</label><input type="number" step="0.01" min="0" name="paid_in_mfs" id="vendorSplitMfs" value="{{ old('paid_in_mfs',0) }}" class="progga-form-control"></div></div>
                            <div class="small text-muted mb-3">For Split, Cash + Card + MFS must equal Amount.</div>
                        </div>

                        <div class="mb-3"><label class="progga-form-label">Note</label><textarea name="note" rows="2" class="progga-form-control">{{ old('note') }}</textarea></div>
                        <button class="progga-btn progga-btn-primary w-100"><i class="bi bi-cash-coin"></i> Save Payment</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="progga-card mb-4">
        <div class="progga-card-header"><div><strong>Purchase History</strong><div class="small text-muted">Complete purchase history. Paid and due are calculated from vendor payment history.</div></div></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Purchase</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Due</th><th style="width:80px">View</th></tr></thead>
                <tbody>
                @forelse($purchases as $purchase)
                    @php($paid = $purchase->paidAmount())
                    @php($due = $purchase->dueAmount())
                    <tr>
                        <td><strong>{{ $purchase->purchase_no }}</strong><div class="small text-muted">Invoice: {{ $purchase->invoice_no ?: '—' }}</div></td>
                        <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                        <td><span class="progga-badge progga-badge-{{ $purchase->status==='RECEIVED'?'success':'warning' }}">{{ $purchase->status }}</span></td>
                        <td class="text-end">৳{{ number_format((float)$purchase->total,2) }}</td>
                        <td class="text-end text-success">৳{{ number_format($paid,2) }}</td>
                        <td class="text-end {{ $due>0?'text-danger':'text-success' }}">৳{{ number_format($due,2) }}</td>
                        <td><a href="{{ route('inventory.purchases.show',$purchase) }}" class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="View Purchase"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No purchase history found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="progga-card">
        <div class="progga-card-header"><div><strong>Payment History</strong><div class="small text-muted">Complete vendor payment history.</div></div></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Payment</th><th>Date</th><th>Purchase</th><th>Method</th><th>Breakdown / Reference</th><th class="text-end">Amount</th><th>By</th></tr></thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td><strong>{{ $payment->payment_no }}</strong>@if($payment->note)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($payment->note,80) }}</div>@endif</td>
                        <td>{{ optional($payment->payment_date)->format('d M Y') }}</td>
                        <td><a href="{{ route('inventory.purchases.show',$payment->purchase_id) }}">{{ $payment->purchase?->purchase_no }}</a></td>
                        <td>{{ $payment->payment_type }}</td>
                        <td class="small">
                            @if((float)$payment->paid_in_cash > 0)<div>Cash: ৳{{ number_format((float)$payment->paid_in_cash,2) }}</div>@endif
                            @if((float)$payment->paid_in_card > 0)<div>Card: ৳{{ number_format((float)$payment->paid_in_card,2) }} · {{ $payment->card_type }} · {{ $payment->card_reference }}</div>@endif
                            @if((float)$payment->paid_in_mfs > 0)<div>MFS: ৳{{ number_format((float)$payment->paid_in_mfs,2) }} · {{ $payment->mfs_provider }} · {{ $payment->mfs_reference }}</div>@endif
                        </td>
                        <td class="text-end fw-semibold">৳{{ number_format((float)$payment->amount,2) }}</td>
                        <td>{{ $payment->creator?->name ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No vendor payment has been posted yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
@section('script')
<script>
(function(){
    const type=document.getElementById('vendorPaymentType');
    const purchase=document.getElementById('vendorPaymentPurchase');
    const amount=document.getElementById('vendorPaymentAmount');
    const dueText=document.getElementById('vendorPaymentDueText');
    if(!type)return;

    const cardBox=document.getElementById('singleCardFields');
    const mfsBox=document.getElementById('singleMfsFields');
    const splitBox=document.getElementById('splitFields');
    function togglePaymentFields(){
        cardBox.classList.toggle('d-none',type.value!=='Card' && type.value!=='Split');
        mfsBox.classList.toggle('d-none',type.value!=='MFS' && type.value!=='Split');
        splitBox.classList.toggle('d-none',type.value!=='Split');
    }
    function updateDue(){
        if(!purchase)return;
        const opt=purchase.options[purchase.selectedIndex];
        const due=parseFloat(opt?.dataset?.due||0);
        if(dueText)dueText.textContent=due>0?'Available due: ৳'+due.toFixed(2):'';
        if(amount && due>0)amount.max=due.toFixed(2); else if(amount)amount.removeAttribute('max');
    }
    type.addEventListener('change',togglePaymentFields);
    purchase?.addEventListener('change',updateDue);
    togglePaymentFields(); updateDue();
})();
</script>
@endsection
