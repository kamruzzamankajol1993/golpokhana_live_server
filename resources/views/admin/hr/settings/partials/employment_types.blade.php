<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-person-check-fill me-2"></i>Employment Types</div>
            <div class="hr-settings-help">Examples: Permanent, Contract, Part-time, Daily or Hourly.</div>
        </div>
        @can('hr-setting-create')
        <button type="button" class="progga-btn progga-btn-primary" onclick="openEmploymentTypeCreate()"><i class="bi bi-plus-lg"></i> Add Employment Type</button>
        @endcan
    </div>
    <div class="progga-table-wrapper" style="border:none;border-radius:0;">
        <table class="progga-table">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Salary Basis</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($employmentTypes as $key => $type)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td><strong>{{ $type->name }}</strong>@if($type->is_system)<span class="progga-badge ms-1">System</span>@endif</td>
                    <td>{{ $type->code ?: '-' }}</td>
                    <td><span class="progga-badge {{ $type->is_hourly ? 'progga-badge-warning' : 'progga-badge-success' }}">{{ $type->is_hourly ? 'Hourly' : 'Monthly/Fixed' }}</span></td>
                    <td><div class="hr-table-description">{{ \Illuminate\Support\Str::limit($type->description, 70) ?: '-' }}</div></td>
                    <td><label class="progga-toggle"><input type="checkbox" onchange="toggleHrStatus('employment-types', {{ $type->id }}, this)" {{ $type->status ? 'checked' : '' }} data-on="Active" data-off="Inactive" @cannot('hr-setting-edit') disabled @endcannot><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ $type->status ? 'Active' : 'Inactive' }}</span></label></td>
                    <td><div class="progga-table-actions">
                        @can('hr-setting-edit')<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick='editEmploymentType(@json($type))'><i class="bi bi-pencil"></i></button>@endcan
                        @can('hr-setting-delete')<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick='deleteHrRecord("employment-types", {{ $type->id }}, @json($type->name), "employment-types")' {{ $type->is_system ? 'disabled' : '' }}><i class="bi bi-trash"></i></button>@endcan
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center hr-empty-state"><i class="bi bi-person-check d-block mb-2" style="font-size:28px;"></i>No employment types added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
