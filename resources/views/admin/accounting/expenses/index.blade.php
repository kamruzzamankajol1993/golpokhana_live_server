{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
@extends('admin.master.master')
@section('title','Expense Management')
@section('body')
<main class="progga-content acc-shell">@include('admin.accounting.shared._common')
<div class="row g-3"><div class="col-lg-4">
@can('accounting-expense')
<div class="acc-panel"><h3>Record an Expense</h3><p class="acc-hint">Create → independent approval → accounting draft → submit / approve / post. Payment is separate and must clear accrued liability.</p>
<form method="post" action="{{ route('accounting.expenses.store') }}">@csrf
<div class="mb-3"><label>Category</label><select class="form-select" name="expense_category_id" required>
@foreach($categories as $c)
<option value="{{ $c->id }}">{{ $c->name }}</option>
@endforeach
</select></div>
<div class="mb-3"><label>Expense Date</label><input class="form-control" type="date" name="expense_date" value="{{ date('Y-m-d') }}" required></div>
<div class="mb-3"><label>Amount (BDT)</label><input class="form-control" type="number" name="amount" step="0.0001" min="0.0001" required></div>
<div class="mb-3"><label>Description / Source Evidence</label><textarea class="form-control" name="description" required maxlength="3000"></textarea></div><button class="btn btn-primary">Save Expense Draft</button>
</form></div>
@endcan
</div><div class="col-lg-8"><div class="acc-panel"><h3>Expense Register</h3><div class="table-responsive">
@php 
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($expenses, 'acc_expenses_1'); 
@endphp
<div class="acc-ajax-list" id="acc-list-expenses-1">
<table class="table"><thead><tr><th>No / Date</th><th>Category / Description</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($accPager1 as $e)
<tr><td>{{ $e->expense_no }}<div class="acc-hint">{{ $e->expense_date }}</div></td><td><b>{{ $e->category }}</b><div class="acc-hint">{{ $e->description }}</div></td><td class="text-end">{{ number_format($e->amount,2) }}</td><td>{{ $e->status }}</td><td>
@if($e->status==='DRAFT')
<form method="post" action="{{ route('accounting.expenses.attach',$e->id) }}" enctype="multipart/form-data">@csrf<input type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg" required class="form-control form-control-sm"><button class="btn btn-sm btn-outline-secondary mt-1 acc-icon-action" title="Attach receipt" aria-label="Attach receipt"><i class="bi bi-paperclip" aria-hidden="true"></i></button></form>
@endif
 
@if($e->journal_entry_id)
<a class="btn btn-sm btn-outline-primary acc-icon-action" href="{{ route('accounting.journals.show',$e->journal_entry_id) }}" title="View journal" aria-label="View journal"><i class="bi bi-journal-text" aria-hidden="true"></i></a>
@elseif($e->status==='DRAFT')

@can('accounting-journal-approve')

@if($e->created_by!==auth()->id())
<form data-acc-confirm="Approve expense after evidence review?" method="post" action="{{ route('accounting.expenses.approve',$e->id) }}">@csrf<button class="btn btn-sm btn-outline-success acc-icon-action" title="Approve" aria-label="Approve"><i class="bi bi-check-circle" aria-hidden="true"></i></button></form>
@else
<span class="acc-hint">Awaiting checker</span>
@endif

@endcan

@endif
@if($e->status==='DRAFT' && !$e->journal_entry_id)

@can('accounting-expense')
<form method="post" class="mt-1" action="{{ route('accounting.expenses.delete',$e->id) }}" data-acc-confirm="Delete this unapproved expense draft? Only drafts without receipt attachments can be deleted.">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger acc-icon-action" title="Delete" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
@endcan

@endif
</td></tr>
@empty
<tr><td colspan="5">No expenses yet.</td></tr>
@endforelse
</tbody></table>
@include('admin.accounting.shared._pagination', ['paginator'=>$accPager1, 'label'=>'expenses'])
</div></div></div></div></div></main>
@endsection
