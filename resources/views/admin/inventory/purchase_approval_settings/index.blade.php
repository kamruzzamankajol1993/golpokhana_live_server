@extends('admin.master.master')
@section('title','Purchase Approval Settings')
@section('body')
<main class="progga-content">
    <div class="alert alert-info">Purchase Approval Settings has moved to the main Settings page.</div>
    <a class="progga-btn progga-btn-primary" href="{{ route('settings.index', ['tab' => 'purchase-approval']) }}">Open Settings → Purchase Approval</a>
</main>
@endsection
