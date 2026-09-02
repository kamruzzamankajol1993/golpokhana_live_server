@extends('admin.master.master')
@section('title','Add Ingredient')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Add Ingredient</h1><p class="text-muted mb-0">All internal stock quantities will be normalized to the ingredient base unit.</p></div></div>
    <form method="POST" action="{{ route('inventory.ingredients.store') }}">@csrf @include('admin.inventory.ingredients.form')</form>
</main>
@endsection
