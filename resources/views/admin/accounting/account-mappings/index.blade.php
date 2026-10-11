{{-- ACCOUNTING_MAPPING_BLADE_SAFE_V2_20261011 --}}
@extends('admin.master.master')
@section('title', 'Account Mapping Rules')

@section('body')
<main class="progga-content acc-shell">
    @include('admin.accounting.shared._common')

    
@php
        
$editId = request('edit');
        $edit = $editId ? $mappings->firstWhere('id', (int) $editId) : null;
        $editRules = $edit ? ($rules[$edit->id] ?? collect()) : collect();
    
@endphp

    <div class="acc-panel">
        <h3>Source Event → Multi-line Debit/Credit Mapping</h3>
        <p class="acc-hint">Mappings define accounts for saved source transaction amounts. They do not change invoice values or existing posted journals.</p>

        
@can('accounting-mapping')
            <form method="POST" action="{{ route('accounting.mappings.save') }}" data-acc-confirm="Save this mapping and its debit/credit rules?">
                @csrf
                
@if($edit)
                    <input type="hidden" name="id" value="{{ $edit->id }}">
                
@endif

                <div class="row g-2">
                    <div class="col-lg-3">
                        <label for="mapping_name">Mapping Name</label>
                        <input id="mapping_name" class="form-control" name="name" value="{{ old('name', $edit->name ?? '') }}" required>
                    </div>
                    <div class="col-lg-2">
                        <label for="mapping_source">Source Type</label>
                        <input id="mapping_source" class="form-control" name="source_type" value="{{ old('source_type', $edit->source_type ?? '') }}" placeholder="orders" required>
                    </div>
                    <div class="col-lg-3">
                        <label for="mapping_event">Event Type</label>
                        <input id="mapping_event" class="form-control" name="event_type" value="{{ old('event_type', $edit->event_type ?? '') }}" placeholder="SALES_COMPLETED" required>
                    </div>
                    <div class="col-lg-2">
                        <label for="mapping_journal">Journal Series</label>
                        <select id="mapping_journal" class="form-select" name="journal_id" required>
                            
@foreach($journals as $journal)
                                <option value="{{ $journal->id }}" @selected(old('journal_id', $edit->journal_id ?? '') == $journal->id)>{{ $journal->code }}</option>
                            
@endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label for="mapping_active">State</label>
                        <select id="mapping_active" name="is_active" class="form-select">
                            <option value="0" @selected((int) old('is_active', $edit->is_active ?? 0) === 0)>Inactive</option>
                            <option value="1" @selected((int) old('is_active', $edit->is_active ?? 0) === 1)>Active</option>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-lg-4">
                        <label for="mapping_approval">Posting Approval Policy</label>
                        <select id="mapping_approval" name="requires_approval" class="form-select">
                            <option value="1" @selected((int) old('requires_approval', $edit->requires_approval ?? 1) === 1)>Manual approval required (safe)</option>
                            <option value="0" @selected((int) old('requires_approval', $edit->requires_approval ?? 1) === 0)>Eligible for automatic posting</option>
                        </select>
                        <small class="acc-hint">Auto posting is permitted only for validated events and approved accounting policy.</small>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table" id="mappingRuleTable">
                        <thead>
                            <tr><th>Accounting Role</th><th>Side</th><th>Account</th><th>Snapshot Amount Key</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            
@foreach($editRules->isNotEmpty() ? $editRules : [null, null] as $i => $rule)
                                <tr>
                                    <td><input class="form-control" name="rules[{{ $i }}][role]" value="{{ $rule->role ?? '' }}" placeholder="CASH_RECEIVED" required></td>
                                    <td>
                                        <select name="rules[{{ $i }}][direction]" class="form-select">
                                            <option value="debit" @selected(($rule->direction ?? 'debit') === 'debit')>Debit</option>
                                            <option value="credit" @selected(($rule->direction ?? '') === 'credit')>Credit</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="rules[{{ $i }}][account_id]" class="form-select" required>
                                            
@foreach($accounts as $account)
                                                <option value="{{ $account->id }}" @selected(($rule->account_id ?? '') == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                            
@endforeach
                                        </select>
                                    </td>
                                    <td><input name="rules[{{ $i }}][amount_key]" class="form-control" value="{{ $rule->amount_key ?? '' }}" placeholder="cash_paid" required></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger mapping-line-remove acc-icon-action" aria-label="Remove mapping line" title="Remove mapping line"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
                                </tr>
                            
@endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-outline-secondary btn-sm" id="addMappingLine">+ Add Mapping Line</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ $edit ? 'Update Mapping' : 'Create Mapping' }}</button>
                
@if($edit)
                    <a href="{{ route('accounting.mappings') }}" class="btn btn-sm btn-outline-secondary">Cancel Edit</a>
                
@endif
            </form>
        
@endcan
    </div>

    <div class="acc-panel">
        <h3>Existing Mappings</h3>
        
@php
            
$accPager2 = \App\Support\Accounting\TablePaginator::paginate($mappings, 'acc_account_mappings_2');
        
@endphp
        <div class="acc-ajax-list" id="acc-list-account-mappings-2">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Name / Source</th><th>Journal</th><th>Rule Lines</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        
@forelse($accPager2 as $mapping)
                            <tr>
                                <td>
                                    <strong>{{ $mapping->name }}</strong>
                                    <div class="acc-hint">{{ $mapping->source_type }} / {{ $mapping->event_type }}</div>
                                </td>
                                <td>{{ $mapping->journal_code }}</td>
                                <td>
                                    
@foreach(($rules[$mapping->id] ?? collect()) as $line)
                                        <div>{{ $line->role }}: {{ ucfirst($line->direction) }} · {{ $line->amount_key }}</div>
                                    
@endforeach
                                </td>
                                <td><span class="badge {{ $mapping->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $mapping->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    
@can('accounting-mapping')
                                        <div class="d-flex gap-1 flex-nowrap">
                                            <a class="btn btn-sm btn-outline-primary acc-icon-action" href="{{ route('accounting.mappings', ['edit' => $mapping->id]) }}" title="Edit" aria-label="Edit"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                                            
@if(!$mapping->is_active)
                                                <form method="POST" action="{{ route('accounting.mappings.delete', $mapping->id) }}" data-acc-confirm="Delete this inactive mapping? Only unused mappings can be deleted.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger acc-icon-action" title="Delete" aria-label="Delete"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                                                </form>
                                            
@endif
                                        </div>
                                    
@endcan
                                </td>
                            </tr>
                        
@empty
                            <tr><td colspan="5">No mappings found. Create a mapping and approve it before enabling posting.</td></tr>
                        
@endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.accounting.shared._pagination', ['paginator' => $accPager2, 'label' => 'mappings'])
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
(function () {
    const table = document.getElementById('mappingRuleTable');
    if (!table) return;
    let nextIndex = table.querySelectorAll('tbody tr').length;
    document.getElementById('addMappingLine')?.addEventListener('click', function () {
        const example = table.querySelector('tbody tr');
        if (!example) return;
        const row = example.cloneNode(true);
        const index = nextIndex++;
        row.querySelectorAll('input, select').forEach(function (input) {
            input.name = input.name.replace(/rules\[\d+\]/, 'rules[' + index + ']');
            if (input.tagName === 'INPUT') input.value = '';
        });
        table.querySelector('tbody').appendChild(row);
    });
    table.addEventListener('click', function (event) {
        const remove = event.target.closest('.mapping-line-remove');
        if (!remove) return;
        const rows = table.querySelectorAll('tbody tr');
        if (rows.length <= 2) return; // A mapping must contain at least two debit/credit lines.
        remove.closest('tr').remove();
    });
})();
</script>
@endsection
