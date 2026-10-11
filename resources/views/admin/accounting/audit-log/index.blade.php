{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Accounting Audit Log')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="acc-panel"><h3>Foundation Master Data Audit</h3><div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($audits, 'acc_audit_log_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-audit-log-1">
<table class="table"><thead><tr><th>Time</th><th>Area</th><th>Action</th><th>Changed By</th><th>Before</th><th>After</th></tr></thead><tbody>
@forelse($accPager1 as $a)
<tr><td>{{ $a->created_at }}</td><td>{{ $a->subject_type }} #{{ $a->subject_id }}</td><td>{{ $a->action }}</td><td>#{{ $a->actor_id }}</td><td><details><summary>View</summary><pre class="small">{{ json_encode(json_decode($a->before_data,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></td><td><details><summary>View</summary><pre class="small">{{ json_encode(json_decode($a->after_data,true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></td></tr>
@empty
<tr><td colspan="6">No changes recorded yet.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'audits'])
</div></div></div></main>
@endsection
