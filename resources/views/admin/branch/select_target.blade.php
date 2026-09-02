@extends('admin.master.master')
@section('title', ($moduleName ?? 'Branch Workspace') . ' — ' . ($restaurantSettingName ?? 'Restaurant'))

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">{{ $moduleName ?? 'Branch Workspace' }}</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Select Branch</span>
            </div>
        </div>
    </div>

    <div class="progga-card" style="max-width:820px;padding:22px;">
        <div class="mb-3">
            <h5 class="mb-1">Choose branch for this module</h5>
            <p class="text-muted mb-0" style="font-size:13px;">Your header will remain on <strong>All Branches</strong>. This selection only scopes this branch-owned workspace/form.</p>
        </div>
        <form method="GET" action="{{ $targetUrl ?? url()->current() }}">
            @include('admin.branch.partials.form_branch_selector', [
                'selectedBranchId' => request('branch_id'),
                'label' => 'Branch',
            ])
            @foreach(request()->except(['branch_id', 'page']) as $key => $value)
                @if(is_scalar($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-box-arrow-in-right"></i> Open {{ $moduleName ?? 'Module' }}</button>
        </form>
    </div>
</main>
@endsection
