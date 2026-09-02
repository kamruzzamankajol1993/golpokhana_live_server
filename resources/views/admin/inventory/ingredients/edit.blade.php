@extends('admin.master.master')
@section('title','Edit Ingredient')
@section('body')
<main class="progga-content">
    <div class="progga-page-header"><div><h1 class="progga-page-title">Edit Ingredient</h1><p class="text-muted mb-0">Historical stock remains in the saved base quantities even when package conversions change later.</p></div></div>
    <form method="POST" action="{{ route('inventory.ingredients.update',$ingredient) }}">@csrf @method('PUT') @include('admin.inventory.ingredients.form')</form>
</main>
@endsection
