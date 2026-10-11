{{-- Legacy Accounting template: use explicit Blade control-flow boundaries for safe compilation. --}}
@extends('admin.master.master')
@section('title','Account Mapping Rules')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Source Event → Multi-line Debit/Credit Mapping</h3><p class="acc-hint">Phase 1: prepare approved mappings; source-specific POS and Purchase capture hooks are introduced in later phases. An inactive mapping never generates a journal. The saved source snapshot determines amounts; a mapping may select their accounts, not modify source totals.</p>
@can('accounting-mapping')
@php 
$editId=request('edit');$edit=$editId ? $mappings->firstWhere('id',(int)$editId) : null; $editRules=$edit ? ($rules[$edit->id] ?? collect()) : collect(); 
@endphp
<form method="post" action="{{ route('accounting.mappings.save') }}">@csrf
@if($edit)
<input type="hidden" name="id" value="{{ $edit->id }}">
@endif
<div class="row g-2"><div class="col-lg-3"><label>Mapping Name</label><input class="form-control" name="name" value="{{ old('name',$edit->name ?? '') }}" required></div>
<div class="col-lg-2"><label>Source Type</label><input class="form-control" name="source_type" value="{{ old('source_type',$edit->source_type ?? '') }}" placeholder="orders" required></div>
<div class="col-lg-3"><label>Event Type</label><input class="form-control" name="event_type" value="{{ old('event_type',$edit->event_type ?? '') }}" placeholder="SALES_COMPLETED" required></div>
<div class="col-lg-2"><label>Journal Series</label><select name="journal_id" class="form-select">
@foreach($journals as $j)
<option value="{{ $j->id }}" @selected(old('journal_id',$edit->journal_id ?? '')==$j->id)>{{ $j->code }}</option>
@endforeach
</select></div>
<div class="col-lg-2"><label>State</label><select name="is_active" class="form-select"><option value="0" @selected(!old('is_active',$edit->is_active ?? false))>Inactive</option><option value="1" @selected(old('is_active',$edit->is_active ?? false))>Active</option></select></div></div>
<div class="row mt-2"><div class="col-lg-4"><label>Posting Approval Policy</label><select name="requires_approval" class="form-select"><option value="1" @selected(old('requires_approval',$edit->requires_approval ?? true))>Manual approval required (safe)</option><option value="0" @selected(!old('requires_approval',$edit->requires_approval ?? true))>Eligible for automatic posting</option></select><small class="acc-hint">Automatic posting is permitted only for approved policies and fully validated events.</small></div></div>
<div class="table-responsive mt-3"><table class="table" id="mappingRuleTable"><thead><tr><th>Accounting Role</th><th>Side</th><th>Account</th><th>Snapshot amount key</th><th></th></tr></thead><tbody>
@foreach($editRules->isNotEmpty() ? $editRules : [null,null] as $i=>$rule)
<tr><td><input class="form-control" name="rules[{{ $i }}][role]" value="{{ $rule->role ?? '' }}" placeholder="CASH_RECEIVED" required></td>
<td><select name="rules[{{ $i }}][direction]" class="form-select"><option value="debit" @selected(($rule->direction ?? '')==='debit')>Debit</option><option value="credit" @selected(($rule->direction ?? '')==='credit')>Credit</option></select></td>
<td><select name="rules[{{ $i }}][account_id]" class="form-select">
@foreach($accounts as $a)
<option value="{{ $a->id }}" @selected(($rule->account_id ?? '')==$a->id)>{{ $a->code }} — {{ $a->name }}</option>
@endforeach
</select></td>
<td><input name="rules[{{ $i }}][amount_key]" class="form-control" value="{{ $rule->amount_key ?? '' }}" placeholder="cash_paid" required></td>
<td><button type="button" onclick="this.closest('tr').remove()" class="btn btn-sm btn-outline-danger acc-icon-action" title="Remove journal line" aria-label="Remove journal line"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td></tr>
@endforeach
</tbody></table></div>
<button type="button" class="btn btn-outline-secondary btn-sm" onclick="addMappingRow()">+ Add Mapping Line</button>
<button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Save mapping? Live approved events may use it.')">{{ $edit ? 'Update Mapping' : 'Create Mapping' }}</button>
@if($edit)
<a href="{{ route('accounting.mappings') }}" class="btn btn-sm btn-outline-secondary">Cancel Edit</a>
@endif
</form>
@endcan
</div>
<div class="acc-panel"><h3>Existing Mappings</h3><div class="table-responsive"><table class="table"><thead><tr><th>Name / Source</th><th>Journal</th><th>Rule Lines</th><th>State</th><th></th></tr></thead><tbody>
@forelse($mappings as $m)
<tr><td><b>{{ $m->name }}</b><div class="acc-hint">{{ $m->source_type }} / {{ $m->event_type }}</div></td><td>{{ $m->journal_code }}</td><td>
@foreach(($rules[$m->id] ?? collect()) as $rule)
<div>{{ $rule->role }}: {{ ucfirst($rule->direction) }} · {{ $rule->amount_key }}</div>
@endforeach
</td><td><span class="badge {{ $m->is_active ? 'bg-success':'bg-secondary' }}">{{ $m->is_active ? 'Active':'Inactive' }}</span></td><td>
@can('accounting-mapping')
<a href="{{ route('accounting.mappings',['edit'=>$m->id]) }}" class="btn btn-sm btn-outline-primary acc-icon-action" title="Edit" aria-label="Edit"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
@endcan
</td></tr>
@empty
<tr><td colspan="5">No mappings yet. Configure and obtain accounting approval before enabling posting.</td></tr>
@endforelse
</tbody></table></div></div>
</main>
@endsection
@section('script')
<script>
let mapIndex=document.querySelectorAll('#mappingRuleTable tbody tr').length;
function addMappingRow(){let sample=document.querySelector('#mappingRuleTable tbody tr');if(!sample)return;let tr=sample.cloneNode(true);let i=mapIndex++;tr.querySelectorAll('input, select').forEach(x=>{x.name=x.name.replace(/rules\[\d+\]/,'rules['+i+']');if(x.tagName==='INPUT')x.value='';});document.querySelector('#mappingRuleTable tbody').appendChild(tr);}
</script>
@endsection
