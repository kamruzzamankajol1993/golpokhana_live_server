<div class="progga-card">
    <div class="progga-card-header">
        <div>
            <div class="progga-card-title"><i class="bi bi-diagram-3-fill me-2"></i>Payroll Components & Rules</div>
            <div class="hr-settings-help">Salary, Allowance and Deduction rows are dynamic. Permanent rules can be Global or Employee-specific; Manual at Payroll rows are entered month-by-month.</div>
        </div>
        @can('hr-setting-create')<button type="button" class="progga-btn progga-btn-primary" onclick="openSalaryComponentCreate()"><i class="bi bi-plus-lg"></i> Add Component</button>@endcan
    </div>
    <div class="p-3 border-bottom">
        <div class="alert alert-light border m-0" style="font-size:12px">
            <strong>Permanent rule priority:</strong> Employee Custom → Global Rule → Configuration Warning. <strong>0 is a valid configured value.</strong>
            Components marked <strong>Manual at Payroll</strong> do not belong in Employee Create/Edit; their amount is entered for that payroll month only.
        </div>
    </div>
    <div class="progga-table-wrapper" style="border:none;border-radius:0">
        <table class="progga-table">
            <thead><tr><th>SL</th><th>Component</th><th>Section</th><th>Calculation</th><th>Global / Input Rule</th><th>Employee Override</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($salaryComponents as $key=>$component)
                @php
                    $group=$component->component_group ?: ($component->type==='deduction'?'deduction':'salary');
                    $ruleCode=strtolower((string)($component->rule_code ?: 'standard'));
                    $isOt=in_array($ruleCode,['ot_day_off','ot_gov_off'],true);
                    $isAttendanceDeduction=in_array($ruleCode,['late','lwp_absent'],true);
                    $isAutoRecovery=in_array($ruleCode,['salary_advance','loan_adjustment'],true);
                    $isPayrollManual=$component->calculation_type==='manual' && !$isAutoRecovery;
                @endphp
                <tr>
                    <td>{{ $key+1 }}</td>
                    <td><strong>{{ $component->display_label }}</strong><div class="hr-settings-help">{{ $component->code ?: 'No code' }}{{ $component->payslip_label ? ' · Master: '.$component->name : '' }}</div></td>
                    <td><span class="progga-badge {{ $group==='deduction'?'progga-badge-danger':($group==='allowance'?'progga-badge-warning':'progga-badge-success') }}">{{ ucfirst($group) }}</span></td>
                    <td>
                        @if($isOt)
                            {{ $component->calculation_type==='percentage' ? '% of Basic Hourly' : 'Fixed Per Hour' }}
                        @elseif($isPayrollManual)
                            Manual at Payroll
                        @elseif($isAutoRecovery)
                            Auto Recovery
                        @elseif($isAttendanceDeduction && $component->calculation_type==='percentage')
                            % of Daily Deduction Rate
                        @else
                            {{ ucwords(str_replace('_',' ',$component->calculation_type)) }}
                        @endif
                        <div class="hr-settings-help">{{ ucwords(str_replace('_',' ',$ruleCode)) }}</div>
                    </td>
                    <td>
                        @if($isPayrollManual)
                            <span class="progga-badge progga-badge-warning">Enter during payroll creation</span>
                        @elseif($isAutoRecovery)
                            <span class="progga-badge progga-badge-success">From {{ $ruleCode==='salary_advance' ? 'Salary Advance' : 'Loan' }} module</span>
                        @elseif(!$component->global_configured)
                            <span class="progga-badge progga-badge-danger">Not Configured</span>
                        @elseif($component->calculation_type==='percentage')
                            {{ number_format((float)$component->default_percentage, 2, '.', '') }}%
                            @if($isOt) of Basic Hourly
                            @elseif($isAttendanceDeduction) of Daily Deduction Rate
                            @else of {{ str_replace('_',' ',$component->percentage_of) }}
                            @endif
                        @else
                            {{ number_format((float)$component->default_amount,2) }}{{ $isOt ? ' / hour' : '' }}
                        @endif
                        @if(!$isPayrollManual && !$isAutoRecovery)<div class="hr-settings-help">{{ $component->apply_to_all ? 'Applies globally' : 'Employee assignment only' }}</div>@endif
                    </td>
                    <td>{{ $component->allow_employee_override ? 'Allowed' : 'Locked' }}</td>
                    <td><label class="progga-toggle"><input type="checkbox" onchange="toggleHrStatus('salary-components', {{ $component->id }}, this)" {{ $component->status?'checked':'' }} data-on="Active" data-off="Inactive" @cannot('hr-setting-edit') disabled @endcannot><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">{{ $component->status?'Active':'Inactive' }}</span></label></td>
                    <td><div class="progga-table-actions">@can('hr-setting-edit')<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" onclick='editSalaryComponent(@json($component))'><i class="bi bi-pencil"></i></button>@endcan @can('hr-setting-delete')<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" onclick='deleteHrRecord("salary-components", {{ $component->id }}, @json($component->name), "salary-components")' {{ $component->is_system?'disabled':'' }}><i class="bi bi-trash"></i></button>@endcan</div></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center hr-empty-state">No payroll components added yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
