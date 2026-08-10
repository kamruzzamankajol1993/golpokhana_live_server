<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-person-workspace me-2"></i>Designations</div>
            <div class="hr-settings-help">Define job titles such as Chef, Waiter, Cashier, Manager or Cleaner.</div>
        </div>
        @can('hr-setting-create')
        <button type="button" class="progga-btn progga-btn-primary" onclick="openDesignationCreate()"><i class="bi bi-plus-lg"></i> Add Designation</button>
        @endcan
    </div>
    <div class="progga-table-wrapper" style="border:none;border-radius:0;">
        <table class="progga-table">
            <thead><tr><th>#</th><th>Designation</th><th>Department</th><th>Code</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($designations as $key => $designation)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td><strong>{{ $designation->name }}</strong>@if($designation->is_system)<span class="progga-badge ms-1">System</span>@endif</td>
                    <td>{{ $designation->department->name ?? 'General' }}</td>
                    <td>{{ $designation->code ?: '-' }}</td>
                    <td><div class="hr-table-description">{{ \Illuminate\Support\Str::limit($designation->description, 70) ?: '-' }}</div></td>
                    <td><label class="progga-toggle"><input type="checkbox" onchange="toggleHrStatus('designations', {{ $designation->id }}, this)" {{ $designation->status ? 'checked' : '' }} data-on="Active" data-off="Inactive" @cannot('hr-setting-edit') disabled @endcannot><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ $designation->status ? 'Active' : 'Inactive' }}</span></label></td>
                    <td><div class="progga-table-actions">
                        @can('hr-setting-edit')<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick='editDesignation(@json($designation))'><i class="bi bi-pencil"></i></button>@endcan
                        @can('hr-setting-delete')<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick='deleteHrRecord("designations", {{ $designation->id }}, @json($designation->name), "designations")' {{ $designation->is_system ? 'disabled' : '' }}><i class="bi bi-trash"></i></button>@endcan
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center hr-empty-state"><i class="bi bi-person-workspace d-block mb-2" style="font-size:28px;"></i>No designations added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
