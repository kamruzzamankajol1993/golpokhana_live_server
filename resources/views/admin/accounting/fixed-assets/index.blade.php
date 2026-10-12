{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Fixed Assets & Depreciation')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Fixed Asset Accounting Policy</h3>
@if($policy && $policy->is_approved)
<span class="badge bg-success">Accountant Policy Approved</span><p class="acc-hint mt-2">{{ $policy->approval_reference }}</p>
@else
 <span class="badge bg-warning text-dark">Depreciation Disabled – Approval Required</span><p class="acc-hint mt-2">Do not capitalize or depreciate without documented client-accountant approval of asset values, residual values, useful life, and capitalization threshold.</p>
@endif
@can('accounting-settings')
<form action="{{ route('accounting.assets.policy') }}" method="post" class="row g-2">@csrf
<div class="col-md-3"><label>Policy</label><select name="is_approved" class="form-select"><option value="0">Not Approved</option><option value="1">Approved</option></select></div>
<div class="col-md-7"><label>Approval Reference / Signed Policy</label><input name="approval_reference" minlength="10" class="form-control" required placeholder="Name, signature/reference, approval reason"></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Record Decision</button></div></form>
@endcan
</div>
<div class="row g-3"><div class="col-lg-5"><div class="acc-panel"><h3>Register Fixed Asset</h3>
<p class="acc-hint">Acquisition must have a POSTED source journal that debits the selected asset account. The register does not create another expense.</p>
@can('accounting-assets')
<form method="post" action="{{ route('accounting.assets.save') }}">@csrf
<div class="row g-2"><div class="col-5 mb-2"><label>Asset Code</label><input name="code" class="form-control" required></div><div class="col-7 mb-2"><label>Asset Name</label><input name="name" class="form-control" required></div></div>
<div class="mb-2"><label>Category</label><select class="form-select" name="asset_category_id">
@foreach($categories as $c)
<option value="{{ $c->id }}">{{ $c->name }}</option>
@endforeach
</select></div>
<div class="mb-2"><label>Posted Acquisition Journal ID</label><input type="number" class="form-control" name="source_journal_entry_id" required></div>
@foreach(['asset_account_id'=>'Asset GL Account','accumulated_depreciation_account_id'=>'Accumulated Depreciation (Contra Asset)','depreciation_expense_account_id'=>'Depreciation Expense'] as $name=>$caption)
<div class="mb-2"><label>{{ $caption }}</label><select class="form-select" name="{{ $name }}">
@foreach($accounts as $a)
<option value="{{ $a->id }}">{{ $a->code }} – {{ $a->name }}</option>
@endforeach
</select></div>
@endforeach
<div class="row g-2"><div class="col-6 mb-2"><label>Cost BDT</label><input class="form-control" name="cost" type="number" min="0.0001" step="0.0001" required></div>
<div class="col-6 mb-2"><label>Residual BDT</label><input class="form-control" name="residual_value" type="number" min="0" step="0.0001" value="0" required></div></div>
<div class="row g-2"><div class="col-6 mb-2"><label>Useful Life (Months)</label><input class="form-control" name="useful_life_months" type="number" min="1" max="600" required></div><div class="col-6 mb-2"><label>In Service Date</label><input class="form-control" name="in_service_on" type="date" required></div></div>
<button class="btn btn-primary">Register Asset</button></form>
@endcan
</div></div>
<div class="col-lg-7"><div class="acc-panel"><h3>Asset Register & Depreciation</h3>
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($assets, 'acc_fixed_assets_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-fixed-assets-1">
<table class="table table-sm"><thead><tr><th>Asset</th><th>Cost</th><th>Life</th><th>Depreciate</th></tr></thead><tbody>
@forelse($accPager1 as $a)
<tr><td>{{ $a->code }} – {{ $a->name }}<div class="acc-hint">{{ $a->category }}</div></td><td>{{ number_format((float)$a->cost,2) }}</td><td>{{ $a->useful_life_months }} months</td><td>
@can('accounting-assets')
<form action="{{ route('accounting.assets.depreciate',$a->id) }}" method="post" class="d-flex gap-1">@csrf<input class="form-control form-control-sm" name="period_month" type="date" required title="First day of month"><button class="btn btn-sm btn-outline-primary acc-icon-action" title="Create depreciation draft" aria-label="Create depreciation draft" {{ !$policy || !$policy->is_approved?'disabled':'' }}><i class="bi bi-file-earmark-plus" aria-hidden="true"></i></button></form>
@endcan
</td></tr>
@empty
<tr><td colspan="4">No registered assets.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'assets'])
</div></div>
<div class="acc-panel"><h3>Asset Disposal / Sale (Approval Required)</h3>
<p class="acc-hint">A disposal produces a journal DRAFT. The asset remains ACTIVE until that journal is independently approved and POSTED. Write-off has zero sale proceeds. Gain or loss account must match income/expense.</p>
@can('accounting-assets')
<form method="post" action="{{ route('accounting.assets.dispose',$assets->first()->id??0) }}" id="acc-dispose-form" class="row g-2" data-acc-confirm="Create asset disposal journal DRAFT?" onsubmit="this.action=this.action.replace(/\d+\/dispose$/,document.getElementById('acc-dispose-id').value+'/dispose')">@csrf
<div class="col-md-4"><label>Active Asset</label><select id="acc-dispose-id" class="form-select" required>
@foreach($assets as $a)@if($a->status==='ACTIVE' && !$disposals->where('fixed_asset_id',$a->id)->whereIn('status',['PENDING','POSTED'])->count())
<option value="{{ $a->id }}">{{ $a->code }} – {{ $a->name }}</option>@endif @endforeach</select></div>
<div class="col-md-2"><label>Type</label><select name="disposal_type" class="form-select"><option value="SALE">Sale</option><option value="WRITE_OFF">Write-off</option></select></div>
<div class="col-md-3"><label>Disposal Date</label><input name="disposed_on" type="date" class="form-control" required></div>
<div class="col-md-3"><label>Sale Proceeds</label><input name="sale_proceeds" type="number" min="0" step="0.0001" value="0" class="form-control" required></div>
<div class="col-md-6"><label>Receiving Bank/Cash Account (Sale)</label><select name="cash_account_id" class="form-select"><option value="">None / write-off</option>
@foreach($accounts->whereIn('code',['1100','1110','1120','1150']) as $a)<option value="{{ $a->id }}">{{ $a->code }} – {{ $a->name }}</option>@endforeach</select></div>
<div class="col-md-6"><label>Gain (Income) or Loss (Expense) GL Account</label><select name="gain_loss_account_id" class="form-select"><option value="">No gain or loss</option>
@foreach($accounts->whereIn('type',['income','expense']) as $a)<option value="{{ $a->id }}">{{ $a->code }} – {{ $a->name }} ({{ $a->type }})</option>@endforeach</select></div>
<div class="col-md-3"><label>Unique Reference</label><input name="reference" class="form-control" required maxlength="100"></div>
<div class="col-md-7"><label>Reason / Evidence</label><input name="reason" minlength="10" maxlength="2000" class="form-control" required></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary" {{ $assets->isEmpty()?'disabled':'' }}>Draft Disposal</button></div></form>
@endcan
<div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Asset</th><th>Date / Type</th><th>Proceeds</th><th>Book Value</th><th>Gain/Loss</th><th>Status</th><th>Journal</th><th>Action</th></tr></thead><tbody>
@forelse($disposals as $d)
<tr><td>{{ $d->asset_code }}</td><td>{{ $d->disposed_on }} / {{ $d->disposal_type }}</td><td>{{ $d->sale_proceeds }}</td><td>{{ $d->book_value }}</td><td>{{ $d->gain_loss }}</td><td>{{ $d->status }}</td><td><a href="{{ route('accounting.journals.show',$d->journal_entry_id) }}">#{{ $d->journal_entry_id }}</a></td><td>
@can('accounting-assets') @if($d->status==='PENDING')<form action="{{ route('accounting.assets.dispose-cancel',$d->id) }}" method="post" data-acc-confirm="Cancel only unused disposal draft?">@csrf<button class="btn btn-sm btn-outline-danger">Cancel Draft</button></form>@endif @endcan</td></tr>
@empty<tr><td colspan="8">No disposals recorded.</td></tr>@endforelse</tbody></table></div></div>
<div class="acc-panel"><h3>Depreciation Audit</h3>
@php 
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($depreciations, 'acc_fixed_assets_2'); 
@endphp
<div class="acc-ajax-list" id="acc-list-fixed-assets-2">
<table class="table table-sm"><thead><tr><th>Asset ID</th><th>Month</th><th>Amount</th><th>Journal</th></tr></thead><tbody>
@foreach($accPager2 as $dep)
<tr><td>{{ $dep->fixed_asset_id }}</td><td>{{ $dep->period_month }}</td><td>{{ $dep->amount }}</td><td><a href="{{ route('accounting.journals.show',$dep->journal_entry_id) }}">#{{ $dep->journal_entry_id }}</a></td></tr>
@endforeach
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager2, 'label'=>'depreciations'])
</div></div></div></div></main>
@endsection
