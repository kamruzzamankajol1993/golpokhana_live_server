<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-cash-stack me-2"></i>Salary Components</div>
            <div class="hr-settings-help">Configure earnings and deductions used while creating employee salary structures.</div>
        </div>
        @can('hr-setting-create')
        <button type="button" class="progga-btn progga-btn-primary" onclick="openSalaryComponentCreate()"><i class="bi bi-plus-lg"></i> Add Component</button>
        @endcan
    </div>
    <div class="progga-table-wrapper" style="border:none;border-radius:0;">
        <table class="progga-table">
            <thead><tr><th>#</th><th>Component</th><th>Type</th><th>Calculation</th><th>Default Value</th><th>Required</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($salaryComponents as $key => $component)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td><strong>{{ $component->name }}</strong><div class="hr-settings-help">{{ $component->code ?: 'No code' }}</div></td>
                    <td><span class="progga-badge {{ $component->type === 'earning' ? 'progga-badge-success' : 'progga-badge-danger' }}">{{ ucfirst($component->type) }}</span></td>
                    <td>{{ ucfirst($component->calculation_type) }}</td>
                    <td>
                        @if($component->calculation_type === 'fixed')
                            {{ number_format((float)$component->default_amount, 2) }}
                        @elseif($component->calculation_type === 'percentage')
                            {{ rtrim(rtrim(number_format((float)$component->default_percentage, 4), '0'), '.') }}% of {{ str_replace('_', ' ', $component->percentage_of) }}
                        @else
                            Manual
                        @endif
                    </td>
                    <td>{{ $component->is_required ? 'Yes' : 'No' }}</td>
                    <td><label class="progga-toggle"><input type="checkbox" onchange="toggleHrStatus('salary-components', {{ $component->id }}, this)" {{ $component->status ? 'checked' : '' }} data-on="Active" data-off="Inactive" @cannot('hr-setting-edit') disabled @endcannot><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ $component->status ? 'Active' : 'Inactive' }}</span></label></td>
                    <td><div class="progga-table-actions">
                        @can('hr-setting-edit')<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick='editSalaryComponent(@json($component))'><i class="bi bi-pencil"></i></button>@endcan
                        @can('hr-setting-delete')<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick='deleteHrRecord("salary-components", {{ $component->id }}, @json($component->name), "salary-components")' {{ $component->is_system ? 'disabled' : '' }}><i class="bi bi-trash"></i></button>@endcan
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center hr-empty-state"><i class="bi bi-cash-stack d-block mb-2" style="font-size:28px;"></i>No salary components added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
