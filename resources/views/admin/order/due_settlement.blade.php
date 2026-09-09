@extends('admin.master.master')
@section('title', 'Due Settlement — Order #' . $order->order_number)

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Due Settlement — Order #{{ $order->order_number }}</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <a href="{{ route('order.index') }}" class="progga-breadcrumb-item">Order List</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">Due Settlement</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('order.details', $order->id) }}" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-card-list"></i> Full Details
            </a>
            <a href="{{ route('order.index') }}" class="progga-btn progga-btn-outline progga-btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success fw-bold"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>
    @endif

    <div class="progga-card p-4">
        <div class="row g-3 align-items-stretch">
            <div class="col-md-3">
                <div class="h-100 p-3 rounded border bg-light">
                    <div class="text-muted" style="font-size:11px;font-weight:700;">CUSTOMER</div>
                    <div class="fw-bold mt-1">{{ $order->customer->name ?? 'Walk-in Customer' }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="h-100 p-3 rounded border bg-light">
                    <div class="text-muted" style="font-size:11px;font-weight:700;">ORDER TOTAL</div>
                    <div class="fw-bold mt-1">৳{{ number_format($order->grand_total ?? 0, 0) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="h-100 p-3 rounded border bg-light">
                    <div class="text-muted" style="font-size:11px;font-weight:700;">TOTAL PAID</div>
                    <div class="fw-bold text-success mt-1">৳{{ number_format($order->total_paid_amount ?? 0, 0) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="h-100 p-3 rounded border {{ ($order->due ?? 0) > 0 ? 'border-danger' : 'border-success' }}">
                    <div class="text-muted" style="font-size:11px;font-weight:700;">CURRENT DUE</div>
                    <div class="fw-bold {{ ($order->due ?? 0) > 0 ? 'text-danger' : 'text-success' }} mt-1">৳{{ number_format($order->due ?? 0, 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.order.partials._due_payment_history', ['order' => $order])
</main>

@can('order-edit')
    @include('admin.order.partials._due_payment_modal', ['order' => $order])
@endcan
@endsection
