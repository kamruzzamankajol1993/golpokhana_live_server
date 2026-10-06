@extends('admin.master.master')
@section('title','TIPSOI Sync History — '.$restaurantSettingName)
@section('css')@include('admin.hr.shared.styles')@endsection
@section('body')
<main class="progga-content"><div class="hr-shell"><div class="progga-page-header"><div><h1 class="progga-page-title">TIPSOI Sync History</h1><div class="progga-breadcrumb"><a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item">HR</span><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item">TIPSOI</span><span class="progga-breadcrumb-sep">/</span><span class="progga-breadcrumb-item active">Sync History</span></div></div><a href="{{ route('hr.attendance.index') }}" class="progga-btn progga-btn-primary">Attendance Sync</a></div><div class="hr-card">@include('admin.hr.tipsoi.sync-history-table',['histories'=>$histories])</div></div></main>
@endsection
@section('script')@include('admin.hr.shared.plugins')@endsection
