@extends('admin.master.master')
@section('title','Accounting Journal Types')
@section('body')<main class="progga-content acc-shell">@include('admin.accounting._nav')
<div class="row g-3"><div class="col-lg-4">@can('accounting-settings')<div class="acc-panel"><h3>Create / Update Journal Series</h3><form method="post" action="{{ route('accounting.journal-types.save') }}" id="journalTypeForm">@csrf<input type="hidden" name="id" id="jt_id">
<div class="mb-3"><label>Unique Prefix</label><input class="form-control" name="code" id="jt_code" maxlength="12" required placeholder="ADJ"></div>
<div class="mb-3"><label>Journal Name</label><input class="form-control" name="name" id="jt_name" required></div>
<div class="mb-3"><label>Category</label><select class="form-select" name="type" id="jt_type">@foreach(['sales','purchase','cash','bank','miscellaneous','adjustment','depreciation'] as $type)<option>{{ $type }}</option>@endforeach</select></div>
<div class="mb-3"><label>Active</label><select name="is_active" id="jt_is_active" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
<button class="btn btn-primary">Save Journal Type</button></form></div>@endcan</div>
<div class="col-lg-8"><div class="acc-panel"><h3>Journal Types</h3><table class="table"><thead><tr><th>Prefix</th><th>Name</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>@foreach($journals as $j)<tr><td>{{ $j->code }}</td><td>{{ $j->name }}</td><td>{{ $j->type }}</td><td>{{ $j->is_active ? 'Active':'Inactive' }}</td><td>@can('accounting-settings')<button class="btn btn-sm btn-outline-primary jt-edit" data-record="{{ json_encode($j,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_HEX_TAG) }}">Edit</button>@endcan</td></tr>@endforeach</tbody></table></div></div></div></main>@endsection
@section('script')<script>$('.jt-edit').click(function(){let j=JSON.parse(this.dataset.record);['id','code','name','type','is_active'].forEach(k=>document.getElementById('jt_'+k).value=j[k]);window.scrollTo({top:0,behavior:'smooth'});});</script>@endsection
