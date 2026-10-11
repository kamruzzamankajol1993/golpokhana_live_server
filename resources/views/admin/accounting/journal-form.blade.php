{{-- Legacy Accounting template: use explicit Blade control-flow boundaries for safe compilation. --}}
@extends('admin.master.master')
@section('title',$entry ? 'Edit Draft Journal' : 'Create Manual Journal')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>{{ $entry ? 'Edit Journal Draft #'.$entry->id : 'New Manual Journal Voucher' }}</h3>
<div class="alert alert-info small">Only DRAFT journals are editable. Posting requires an OPEN fiscal period, balanced lines and independent approval. A posted entry cannot be changed.</div>
<form method="post" action="{{ $entry ? route('accounting.journals.update',$entry->id) : route('accounting.journals.store') }}">@csrf
@if($entry)
@method('PUT')
@endif
<div class="row g-3 mb-3"><div class="col-md-3"><label>Journal Series</label><select class="form-select" name="journal_id" {{ $entry ? 'disabled':'' }}>
@foreach($journals as $j)
<option value="{{ $j->id }}" @selected(old('journal_id',$entry->journal_id ?? '')==$j->id)>{{ $j->code }} — {{ $j->name }}</option>
@endforeach
</select></div>
@if($entry)
<input type="hidden" name="journal_id" value="{{ $entry->journal_id }}">
@endif
<div class="col-md-3"><label>Posting Date</label><input name="entry_date" type="date" class="form-control" required value="{{ old('entry_date',$entry->entry_date ?? now()->toDateString()) }}"></div>
<div class="col-md-3"><label>Reference</label><input name="reference" class="form-control" value="{{ old('reference',$entry->reference ?? '') }}"></div>
<div class="col-md-12"><label>Narration</label><textarea name="narration" rows="2" class="form-control" required>{{ old('narration',$entry->narration ?? '') }}</textarea></div></div>
<div class="table-responsive"><table class="table" id="journal-lines"><thead><tr><th style="min-width:225px">Account</th><th style="min-width:130px">Debit (BDT)</th><th style="min-width:130px">Credit (BDT)</th><th>Memo</th><th>Cost Center</th><th></th></tr></thead><tbody>
@php 
$displayLines=old('lines') ?? ($lines->count() ? $lines->map(fn($line)=>(array)$line)->all() : [['account_id'=>'','debit'=>'0','credit'=>'0'],['account_id'=>'','debit'=>'0','credit'=>'0']]); 
@endphp
@foreach($displayLines as $i=>$line)
<tr><td><select name="lines[{{ $i }}][account_id]" class="form-select" required><option value="">Select posting account</option>
@foreach($accounts as $a)
<option value="{{ $a->id }}" @selected(($line['account_id'] ?? '')==$a->id)>{{ $a->code }} — {{ $a->name }}{{ $a->is_control ? ' [Control]':'' }}</option>
@endforeach
</select></td>
<td><input type="number" min="0" step="0.0001" class="form-control debit" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? '0' }}" required></td>
<td><input type="number" min="0" step="0.0001" class="form-control credit" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? '0' }}" required></td>
<td><input class="form-control" name="lines[{{ $i }}][memo]" value="{{ $line['memo'] ?? '' }}"></td>
<td><select class="form-select" name="lines[{{ $i }}][cost_center_id]"><option value="">Company Total</option>
@foreach($costCenters as $c)
<option value="{{ $c->id }}" @selected(($line['cost_center_id'] ?? '')==$c->id)>{{ $c->name }}</option>
@endforeach
</select></td><td><button type="button" class="btn btn-sm btn-outline-danger acc-icon-action" onclick="this.closest('tr').remove();recalculate()" title="Remove journal line" aria-label="Remove journal line"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td></tr>
@endforeach
</tbody><tfoot><tr><th>Totals</th><th id="totalDebit">0.0000</th><th id="totalCredit">0.0000</th><th id="balanceStatus" colspan="3">—</th></tr></tfoot></table></div>
<div class="d-flex justify-content-between"><button type="button" class="btn btn-outline-secondary" onclick="addLine()">+ Add Journal Line</button><div><a class="btn btn-light" href="{{ route('accounting.journals.index') }}">Cancel</a> <button class="btn btn-primary">Save Draft</button></div></div>
</form></div>
</main>
@endsection
@section('script')
<script>
let lineIndex=document.querySelectorAll('#journal-lines tbody tr').length;
function addLine(){let template=document.querySelector('#journal-lines tbody tr');let row=template.cloneNode(true),n=lineIndex++;
row.querySelectorAll('select,input').forEach(el=>{el.name=el.name.replace(/lines\[\d+\]/,'lines['+n+']');el.value=el.tagName==='SELECT'?'':(el.classList.contains('debit')||el.classList.contains('credit')?'0':'');});document.querySelector('#journal-lines tbody').appendChild(row);recalculate();}
function recalculate(){let dr=0,cr=0;document.querySelectorAll('#journal-lines tbody tr').forEach(tr=>{dr+=Number(tr.querySelector('.debit').value||0);cr+=Number(tr.querySelector('.credit').value||0);});document.getElementById('totalDebit').textContent=dr.toFixed(4);document.getElementById('totalCredit').textContent=cr.toFixed(4);document.getElementById('balanceStatus').textContent=Math.abs(dr-cr)<0.00001&&dr>0?'Balanced':'Not Balanced';}
document.addEventListener('input',e=>{if(e.target.matches('.debit,.credit'))recalculate();});recalculate();
</script>
@endsection
