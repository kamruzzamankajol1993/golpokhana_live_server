<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-calendar-event-fill me-2"></i>Holiday Setup</div>
            <div class="hr-settings-help">Maintain public, company and special holidays used by attendance and payroll.</div>
        </div>
        @can('hr-setting-create')
        <button type="button" class="progga-btn progga-btn-primary" onclick="openHolidayCreate()"><i class="bi bi-plus-lg"></i> Add Holiday</button>
        @endcan
    </div>
    <div class="progga-table-wrapper" style="border:none;border-radius:0;">
        <table class="progga-table">
            <thead><tr><th>#</th><th>Holiday</th><th>Date</th><th>Type</th><th>Payment</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($holidays as $key => $holiday)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td><strong>{{ $holiday->name }}</strong></td>
                    <td>{{ optional($holiday->holiday_date)->format('d-m-Y') }}</td>
                    <td><span class="progga-badge">{{ ucfirst($holiday->holiday_type) }}</span></td>
                    <td>{{ $holiday->is_paid ? 'Paid' : 'Unpaid' }}</td>
                    <td><div class="hr-table-description">{{ \Illuminate\Support\Str::limit($holiday->description, 70) ?: '-' }}</div></td>
                    <td><label class="progga-toggle"><input type="checkbox" onchange="toggleHrStatus('holidays', {{ $holiday->id }}, this)" {{ $holiday->status ? 'checked' : '' }} data-on="Active" data-off="Inactive" @cannot('hr-setting-edit') disabled @endcannot><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ $holiday->status ? 'Active' : 'Inactive' }}</span></label></td>
                    <td><div class="progga-table-actions">
                        @can('hr-setting-edit')<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick='editHoliday(@json($holiday))'><i class="bi bi-pencil"></i></button>@endcan
                        @can('hr-setting-delete')<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick='deleteHrRecord("holidays", {{ $holiday->id }}, @json($holiday->name), "holidays")'><i class="bi bi-trash"></i></button>@endcan
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center hr-empty-state"><i class="bi bi-calendar-event d-block mb-2" style="font-size:28px;"></i>No holidays added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
