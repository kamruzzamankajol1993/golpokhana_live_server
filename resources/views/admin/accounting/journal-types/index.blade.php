{{-- Accounting view: explicit Blade control-flow blocks prevent ambiguous compiler output. --}}
{{-- ACCOUNTING_JOURNAL_TYPES_DELETE_VISIBLE_V2_20261011 --}}
@extends('admin.master.master')
@section('title', 'Accounting Journal Types')

@section('body')
<main class="progga-content acc-shell">
    @include('admin.accounting.shared._common')
    <div class="row g-3">
        <div class="col-lg-4">
            
@can('accounting-settings')
                <div class="acc-panel">
                    <h3>Create / Update Journal Series</h3>
                    <form method="POST" action="{{ route('accounting.journal-types.save') }}" id="journalTypeForm">
                        @csrf
                        <input type="hidden" name="id" id="jt_id">
                        <div class="mb-3">
                            <label for="jt_code">Unique Prefix</label>
                            <input class="form-control" name="code" id="jt_code" maxlength="12" required placeholder="ADJ">
                        </div>
                        <div class="mb-3">
                            <label for="jt_name">Journal Name</label>
                            <input class="form-control" name="name" id="jt_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="jt_type">Category</label>
                            <select class="form-select" name="type" id="jt_type">
                                
@foreach(['sales', 'purchase', 'cash', 'bank', 'miscellaneous', 'adjustment', 'depreciation'] as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                
@endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="jt_is_active">Active</label>
                            <select name="is_active" id="jt_is_active" class="form-select">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Journal Type</button>
                        <button type="button" class="btn btn-outline-secondary" id="journalTypeReset">Clear</button>
                    </form>
                </div>
            
@endcan
        </div>
        <div class="col-lg-8">
            <div class="acc-panel">
                <h3>Journal Types</h3>
                <p class="acc-hint">Unused journal types can be deleted. Referenced types show Deactivate instead to preserve historical transactions.</p>
                
@php
                    
$accPager1 = \App\Support\Accounting\TablePaginator::paginate($journals, 'acc_journal_types_1');
                
@endphp
                <div class="acc-ajax-list" id="acc-list-journal-types-1">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr><th>Prefix</th><th>Name</th><th>Type</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                
@forelse($accPager1 as $journal)
                                    <tr>
                                        <td>{{ $journal->code }}</td>
                                        <td>{{ $journal->name }} 
@if($journal->mapping_count || $journal->entry_count)
<div class="acc-action-note">Used by {{ $journal->mapping_count }} mapping(s), {{ $journal->entry_count }} journal(s)</div>
@endif
</td>
                                        <td>{{ $journal->type }}</td>
                                        <td>{{ $journal->is_active ? 'Active' : 'Inactive' }}</td>
                                        <td>
                                            
@can('accounting-settings')
                                                <div class="d-flex align-items-center gap-2 flex-nowrap">
                                                    <button type="button" class="btn btn-sm btn-outline-primary acc-action-btn jt-edit acc-icon-action" data-record="{{ json_encode($journal, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}" title="Edit" aria-label="Edit"><i class="bi bi-pencil-square" aria-hidden="true"></i></button>
                                                    
@if((int)$journal->mapping_count === 0 && (int)$journal->entry_count === 0)
                                                        <form method="POST" action="{{ route('accounting.journal-types.delete', $journal->id) }}" data-acc-confirm="Delete this unused journal type permanently?" class="m-0">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger acc-action-btn acc-icon-action" title="Delete" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                                                        </form>
                                                    
@elseif($journal->is_active && (int)$journal->active_mapping_count === 0)
                                                        <form method="POST" action="{{ route('accounting.journal-types.deactivate', $journal->id) }}" data-acc-confirm="Deactivate this referenced journal type? Existing journals and mappings will be retained." data-acc-action="deactivate" class="m-0">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="btn btn-sm btn-outline-warning acc-action-btn acc-icon-action" title="Deactivate" aria-label="Deactivate"><i class="bi bi-slash-circle" aria-hidden="true"></i></button>
                                                        </form>
                                                    
@elseif((int)$journal->active_mapping_count > 0)
                                                        <a class="btn btn-sm btn-outline-secondary acc-action-btn acc-icon-action" href="{{ route('accounting.mappings') }}" title="Disable active mappings before deactivating this series" aria-label="Manage Mappings"><i class="bi bi-diagram-3" aria-hidden="true"></i></a>
                                                    
@else
                                                        <span class="acc-action-note">Referenced · Inactive</span>
                                                    
@endif
                                                </div>
                                            
@endcan
                                        </td>
                                    </tr>
                                
@empty
                                    <tr><td colspan="5">No journal series found.</td></tr>
                                
@endforelse
                            </tbody>
                        </table>
                    </div>
                    @include('admin.accounting.shared._pagination', ['paginator' => $accPager1, 'label' => 'journals'])
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
(function () {
    document.addEventListener('click', function (event) {
        const edit = event.target.closest('.jt-edit');
        if (!edit) return;
        const journal = JSON.parse(edit.dataset.record);
        ['id', 'code', 'name', 'type', 'is_active'].forEach(function (key) {
            const input = document.getElementById('jt_' + key);
            if (input) input.value = journal[key];
        });
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
    document.getElementById('journalTypeReset')?.addEventListener('click', function () {
        const form = document.getElementById('journalTypeForm');
        if (!form) return;
        form.reset();
        document.getElementById('jt_id').value = '';
    });
})();
</script>
@endsection
