@extends('admin.master.master')
@section('title','Chart of Accounts')
@section('body')<main class="progga-content acc-shell">@include('admin.accounting._nav')
<div class="row g-3"><div class="col-lg-4">@can('accounting-coa')<div class="acc-panel"><h3>Create / Edit Account</h3><form method="post" action="{{ route('accounting.accounts.save') }}" id="accountForm">@csrf
<input type="hidden" name="id" id="a_id">
<div class="row g-2"><div class="col-4"><label>Code</label><input class="form-control" name="code" id="a_code" required></div><div class="col-8"><label>Name</label><input class="form-control" name="name" id="a_name" required></div></div>
<div class="mt-3"><label>Account Type</label><select class="form-select" name="type" id="a_type">@foreach(['asset','liability','equity','income','contra_revenue','cogs','expense'] as $type)<option value="{{ $type }}">{{ ucfirst(str_replace('_',' ',$type)) }}</option>@endforeach</select></div>
<div class="mt-3"><label>Normal Balance</label><select class="form-select" name="normal_balance" id="a_normal_balance"><option>debit</option><option>credit</option></select></div>
<div class="mt-3"><label>Parent Header Account</label><select class="form-select" name="parent_id" id="a_parent_id"><option value="">None</option>@foreach($accounts->where('is_header',1) as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select></div>
<div class="row mt-3 g-2"><div class="col-4"><label>Header</label><select name="is_header" id="a_is_header" class="form-select"><option value="0">No</option><option value="1">Yes</option></select></div><div class="col-4"><label>Active</label><select name="is_active" id="a_is_active" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div><div class="col-4"><label>Control</label><select name="is_control" id="a_is_control" class="form-select"><option value="0">No</option><option value="1">Yes</option></select></div></div>
<div class="mt-3"><label>Notes</label><textarea class="form-control" name="description" id="a_description" rows="2"></textarea></div>
<button class="btn btn-primary mt-3">Save Account</button> <button type="button" class="btn btn-outline-secondary mt-3" onclick="document.getElementById('accountForm').reset();document.getElementById('a_id').value=''">Reset</button>
</form></div>@endcan</div>
<div class="col-lg-8"><div class="acc-panel"><h3>Chart of Accounts (Company Level)</h3><input type="search" class="form-control mb-2" placeholder="Filter by code or name" id="accSearch"><div class="table-responsive" style="max-height:690px;overflow:auto"><table class="table table-hover" id="coaTable"><thead><tr><th>Code</th><th>Account</th><th>Type</th><th>Normal</th><th>Status</th><th></th></tr></thead><tbody>@foreach($accounts as $a)
<tr><td><b>{{ $a->code }}</b></td><td>{{ $a->name }} @if($a->is_header)<span class="badge bg-secondary">Header</span>@endif @if($a->is_control)<span class="badge bg-info text-dark">Control</span>@endif</td><td>{{ $a->type }}</td><td>{{ $a->normal_balance }}</td><td><span class="badge {{ $a->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span></td><td>@can('accounting-coa')<button class="btn btn-sm btn-outline-primary acc-edit" type="button" data-record="{{ json_encode($a,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_TAG) }}">Edit</button>@endcan</td></tr>
@endforeach</tbody></table></div></div></div></div>
</main>@endsection
@section('script')<script>
$('#accSearch').on('input',function(){let v=$(this).val().toLowerCase();$('#coaTable tbody tr').each(function(){$(this).toggle($(this).text().toLowerCase().includes(v));});});
$('.acc-edit').on('click',function(){let a=JSON.parse(this.dataset.record);['id','code','name','type','normal_balance','parent_id','is_header','is_active','is_control','description'].forEach(k=>{let el=document.getElementById('a_'+k);if(el)el.value=a[k]??'';});window.scrollTo({top:0,behavior:'smooth'});});
</script>@endsection
