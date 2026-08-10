<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-calendar2-check-fill me-2"></i>Leave Types</div>
            <div class="hr-settings-help">Set annual entitlement, paid/unpaid rules and document requirements.</div>
        </div>
        @can('hr-setting-create')
        <button type="button" class="progga-btn progga-btn-primary" onclick="openLeaveTypeCreate()"><i class="bi bi-plus-lg"></i> Add Leave Type</button>
        @endcan
    </div>
    <div class="progga-table-wrapper" style="border:none;border-radius:0;">
        <table class="progga-table">
            <thead><tr><th>#</th><th>Leave Type</th><th>Days/Year</th><th>Payment</th><th>Carry Forward</th><th>Document</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($leaveTypes as $key => $leaveType)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td><strong><span class="hr-color-dot" style="background:{{ $leaveType->color ?: '#21352a' }};"></span>{{ $leaveType->name }}</strong><div class="hr-settings-help">{{ $leaveType->code ?: 'No code' }}</div></td>
                    <td>{{ rtrim(rtrim(number_format((float)$leaveType->days_per_year, 2), '0'), '.') }}</td>
                    <td><span class="progga-badge {{ $leaveType->is_paid ? 'progga-badge-success' : 'progga-badge-danger' }}">{{ $leaveType->is_paid ? 'Paid' : 'Unpaid' }}</span></td>
                    <td>{{ $leaveType->allow_carry_forward ? 'Up to '.rtrim(rtrim(number_format((float)$leaveType->max_carry_forward_days, 2), '0'), '.').' days' : 'No' }}</td>
                    <td>{{ $leaveType->requires_document ? 'Required' : 'Not Required' }}</td>
                    <td><label class="progga-toggle"><input type="checkbox" onchange="toggleHrStatus('leave-types', {{ $leaveType->id }}, this)" {{ $leaveType->status ? 'checked' : '' }} data-on="Active" data-off="Inactive" @cannot('hr-setting-edit') disabled @endcannot><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ $leaveType->status ? 'Active' : 'Inactive' }}</span></label></td>
                    <td><div class="progga-table-actions">
                        @can('hr-setting-edit')<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick='editLeaveType(@json($leaveType))'><i class="bi bi-pencil"></i></button>@endcan
                        @can('hr-setting-delete')<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick='deleteHrRecord("leave-types", {{ $leaveType->id }}, @json($leaveType->name), "leave-types")' {{ $leaveType->is_system ? 'disabled' : '' }}><i class="bi bi-trash"></i></button>@endcan
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center hr-empty-state"><i class="bi bi-calendar2-check d-block mb-2" style="font-size:28px;"></i>No leave types added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
