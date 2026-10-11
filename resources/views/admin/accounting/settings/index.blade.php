{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Accounting Settings')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="row g-3"><div class="col-lg-7"><div class="acc-panel"><h3>Master Accounting Settings</h3>
<form data-acc-confirm="Save Accounting Settings? Capture changes affect future events." method="post" action="{{ route('accounting.settings.save') }}">@csrf
 <div class="alert alert-warning small">Capture OFF is the safe default. When OFF, no automatic event, queue, journal or negative-cost record will be created. Turning ON does not backfill historical transactions.</div>
 <div class="mb-3"><label>Accounting Transaction Capture</label><select name="capture_enabled" id="capture_enabled" class="form-select" onchange="document.getElementById('posting_mode').disabled=this.value==='0'"><option value="0" @selected(!$settings->capture_enabled)>OFF · Do not capture</option><option value="1" @selected($settings->capture_enabled)>ON · Capture qualifying events</option></select></div>
 <div class="mb-3"><label>Journal Posting Mode</label><select name="posting_mode" id="posting_mode" class="form-select" {{ !$settings->capture_enabled ? 'disabled' : '' }}><option value="manual" @selected($settings->posting_mode==='manual')>Manual Review &amp; Post (Recommended)</option><option value="auto" @selected($settings->posting_mode==='auto')>Automatic Journal Posting</option></select><div class="acc-hint">Save mode even while OFF: when disabling capture, field remains preserved via hidden fallback.</div><input type="hidden" name="posting_mode" value="{{ $settings->posting_mode }}" id="posting_fallback" {{ $settings->capture_enabled ? 'disabled' : '' }}></div>
 <div class="row"><div class="col-sm-6 mb-3"><label>Posting locked through</label><input class="form-control" type="date" name="locked_until" value="{{ $settings->locked_until }}"></div>
 <div class="col-sm-6 mb-3"><label>Inventory valuation policy</label><select class="form-select" name="valuation_method"><option value="weighted_average" @selected($settings->valuation_method==='weighted_average')>Weighted Average</option></select></div></div>
 <div class="mb-3"><label>Rounding adjustment account</label><select class="form-select" name="rounding_account_id"><option value="">Not approved</option>
@foreach($accounts as $a)
<option value="{{ $a->id }}" @selected($settings->rounding_account_id==$a->id)>{{ $a->code }} — {{ $a->name }}</option>
@endforeach
</select></div>
 <div class="mb-3"><label>Reason for change (audit)</label><textarea name="reason" class="form-control" required minlength="8" rows="2" placeholder="State the approval reason"></textarea></div>
 <button class="btn btn-primary">Save Settings</button>
</form></div></div><div class="col-lg-5"><div class="acc-panel"><h3>Settings Change History</h3><div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($history, 'acc_settings_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-settings-1">
<table class="table"><thead><tr><th>Field</th><th>Before → After</th><th>When</th></tr></thead><tbody>
@forelse($accPager1 as $h)
<tr><td>{{ $h->field }}<div class="acc-hint">User #{{ $h->user_id }} · {{ $h->reason }}</div></td><td>{{ $h->old_value ?? '—' }} → {{ $h->new_value ?? '—' }}</td><td>{{ $h->changed_at }}</td></tr>
@empty
<tr><td colspan="3">No changes yet.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'history'])
</div></div></div></div></div>
</main>
@endsection
@section('script')
<script>document.getElementById('capture_enabled').addEventListener('change',function(){document.getElementById('posting_fallback').disabled=this.value==='1';});</script>
@endsection
