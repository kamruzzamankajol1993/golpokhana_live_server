<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-diagram-3-fill me-2"></i>Departments</div>
            <div class="hr-settings-help">Group restaurant employees by working area, such as Kitchen, Service or Accounts.</div>
        </div>
        @can('hr-setting-create')
        <button type="button" class="progga-btn progga-btn-primary" onclick="openDepartmentCreate()"><i class="bi bi-plus-lg"></i> Add Department</button>
        @endcan
    </div>
    <div class="progga-table-wrapper" style="border:none;border-radius:0;">
        <table class="progga-table">
            <thead><tr><th>#</th><th>Department</th><th>Code</th><th>Designations</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($departments as $key => $department)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td><strong>{{ $department->name }}</strong>@if($department->is_system)<span class="progga-badge ms-1">System</span>@endif</td>
                    <td>{{ $department->code ?: '-' }}</td>
                    <td>{{ $department->designations_count }}</td>
                    <td><div class="hr-table-description">{{ \Illuminate\Support\Str::limit($department->description, 70) ?: '-' }}</div></td>
                    <td>
                        <label class="progga-toggle">
                            <input type="checkbox" onchange="toggleHrStatus('departments', {{ $department->id }}, this)" {{ $department->status ? 'checked' : '' }} data-on="Active" data-off="Inactive" @cannot('hr-setting-edit') disabled @endcannot>
                            <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                            <span class="progga-toggle-label">{{ $department->status ? 'Active' : 'Inactive' }}</span>
                        </label>
                    </td>
                    <td><div class="progga-table-actions">
                        @can('hr-setting-edit')<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick='editDepartment(@json($department))'><i class="bi bi-pencil"></i></button>@endcan
                        @can('hr-setting-delete')<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick='deleteHrRecord("departments", {{ $department->id }}, @json($department->name), "departments")' {{ $department->is_system || $department->designations_count > 0 ? 'disabled' : '' }}><i class="bi bi-trash"></i></button>@endcan
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center hr-empty-state"><i class="bi bi-diagram-3 d-block mb-2" style="font-size:28px;"></i>No departments added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
