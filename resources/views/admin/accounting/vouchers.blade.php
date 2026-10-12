@extends('admin.master.master')
@section('title','Accounting Vouchers')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting._nav')
<div class="row g-3"><div class="col-xl-5">@can('accounting-voucher')<div class="acc-panel">
<h3>New Receipt / Payment / Contra Voucher</h3>
<p class="acc-hint">A voucher is a controlled double-entry DRAFT, not another payment source. Existing POS due and supplier payments must use their original modules.</p>
<form method="POST" action="{{ route('accounting.vouchers.store') }}">@csrf
<div class="row g-2 mb-3"><div class="col-md-6"><label>Voucher type</label><select name="voucher_type" class="form-select" required><option value="RV">RV — Receipt</option><option value="PV">PV — Payment</option><option value="CV">CV — Cash / Bank Transfer</option></select></div><div class="col-md-6"><label>Date</label><input class="form-control" type="date" name="entry_date" value="{{ date('Y-m-d') }}" required></div></div>
<div class="mb-3"><label>Party / Reference</label><input name="party_name" class="form-control" placeholder="Customer / Vendor / Office"><input name="reference" class="form-control mt-2" placeholder="Document number"><input name="payment_reference" class="form-control mt-2" placeholder="Bank / MFS transfer reference"></div>
<div class="mb-3"><label>Purpose</label><textarea class="form-control" name="narration" rows="2" required placeholder="Explain the business purpose"></textarea></div>
<label>Journal lines — Debit equals Credit</label><div id="voucher-lines">
@for($i=0;$i<2;$i++)<div class="row g-1 mb-2 voucher-line"><div class="col-6"><select class="form-select" name="lines[{{ $i }}][account_id]" required><option value="">Account</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select></div><div class="col-3"><input name="lines[{{ $i }}][debit]" type="number" step="0.0001" min="0" value="{{ $i===0 ? '' : 0 }}" class="form-control" placeholder="Debit" required></div><div class="col-3"><input name="lines[{{ $i }}][credit]" type="number" step="0.0001" min="0" value="{{ $i===1 ? '' : 0 }}" class="form-control" placeholder="Credit" required></div></div>@endfor
</div><button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addVoucherLine()">+ Another line</button><br><button class="btn btn-primary">Save Voucher Draft</button>
</form></div>@endcan</div><div class="col-xl-7"><div class="acc-panel"><h3>Voucher Register</h3><div class="table-responsive"><table class="table"><thead><tr><th>Type</th><th>Date</th><th>Number / Reference</th><th>Party</th><th>Status</th></tr></thead><tbody>@forelse($vouchers as $v)<tr><td><span class="badge bg-secondary">{{ $v->voucher_type }}</span></td><td>{{ $v->entry_date }}</td><td><a href="{{ route('accounting.journals.show',$v->journal_entry_id) }}">{{ $v->number ?: 'DRAFT #'.$v->journal_entry_id }}</a><div class="acc-hint">{{ $v->reference }}</div></td><td>{{ $v->party_name }}</td><td>{{ $v->status }}</td></tr>@empty<tr><td colspan="5">No vouchers yet.</td></tr>@endforelse</tbody></table></div>{{ $vouchers->links() }}</div></div></div>
</main>
<script>
function addVoucherLine(){const root=document.getElementById('voucher-lines'),i=root.children.length;if(i>=30)return;let node=root.children[0].cloneNode(true);node.querySelectorAll('input,select').forEach(el=>{el.name=el.name.replace(/lines\[\d+\]/,'lines['+i+']');el.value=el.tagName==='SELECT'?'':0});root.appendChild(node)}
</script>
@endsection
