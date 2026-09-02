@extends('admin.master.master')
@section('title','Booking Details')
@section('body')
<main class="progga-content">
<h1 class="progga-page-title">Booking Details</h1>
<div class="progga-card p-4">
<p><b>Booking ID:</b> {{ $booking->booking_id }}</p>
<p><b>Customer:</b> {{ $booking->customer->name ?? 'Walk-in' }}</p>
<p><b>Table:</b> {{ $booking->table->table_number ?? '' }}</p>
<p><b>Status:</b> {{ ucfirst($booking->status) }}</p>
</div>
</main>
@endsection
