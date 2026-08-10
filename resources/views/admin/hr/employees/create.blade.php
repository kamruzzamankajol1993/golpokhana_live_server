@extends('admin.master.master')

@section('title', 'Add Employee — ' . ($restaurantSettingName ?? 'TableTrack RMS'))

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Add Employee</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.employees.index') }}" class="progga-breadcrumb-item">Employees</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Create</span>
                </div>
            </div>
        </div>

        <form action="{{ route('hr.employees.store') }}" method="POST" enctype="multipart/form-data" id="employeeForm">
            @csrf
            @include('admin.hr.employees.partials.form')
        </form>
    </div>
</main>
@endsection

@section('script')
    @include('admin.hr.shared.plugins')
    @include('admin.hr.employees.partials.form-script')
@endsection
