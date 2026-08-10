@extends('admin.master.master')

@section('title', 'Salary Setup — ' . $employee->name)

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Employee Salary Setup</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.employees.index') }}" class="progga-breadcrumb-item">Employees</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <a href="{{ route('hr.employees.show', $employee) }}" class="progga-breadcrumb-item">{{ $employee->employee_code }}</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Salary Setup</span>
                </div>
            </div>
            <a href="{{ route('hr.employees.show', $employee) }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-left"></i> Employee Profile</a>
        </div>

        @php
            $editingStructure = $latestStructure;
            $componentMap = $editingStructure ? $editingStructure->components->keyBy('salary_component_id') : collect();
            $avatar = $employee->image
                ? asset($employee->image)
                : 'https://ui-avatars.com/api/?name=' . urlencode($employee->name) . '&background=21352a&color=d5aa65&size=120&bold=true';
        @endphp

        <div class="row g-3">
            <div class="col-xl-3">
                <div class="hr-card mb-3">
                    <div class="hr-card-body text-center">
                        <img src="{{ $avatar }}" class="hr-profile-image mb-3" alt="{{ $employee->name }}">
                        <div class="hr-card-title">{{ $employee->name }}</div>
                        <div class="hr-muted">{{ $employee->employee_code }}</div>
                        <div class="mt-3 d-flex gap-1 flex-wrap justify-content-center">
                            <span class="hr-badge hr-badge-primary">{{ $employee->department->name ?? 'No department' }}</span>
                            <span class="hr-badge hr-badge-neutral">{{ $employee->designation->name ?? 'No designation' }}</span>
                        </div>
                    </div>
                </div>

                <div class="hr-card mb-3">
                    <div class="hr-card-header"><div class="hr-card-title">Current Salary</div></div>
                    <div class="hr-card-body">
                        @if($currentStructure)
                            <div class="hr-money-box mb-2"><span>Basic Salary</span><strong>৳{{ number_format((float) $currentStructure->basic_salary, 2) }}</strong></div>
                            <div class="hr-money-box mb-2"><span>Estimated Gross</span><strong>৳{{ number_format((float) $currentStructure->estimated_gross, 2) }}</strong></div>
                            <div class="hr-info-list">
                                <div><span>Effective From</span><strong>{{ $currentStructure->effective_from->format('d-m-Y') }}</strong></div>
                                <div><span>Payment Method</span><strong>{{ ucwords(str_replace('_', ' ', $currentStructure->payment_method)) }}</strong></div>
                            </div>
                        @else
                            <div class="hr-empty py-4"><i class="bi bi-wallet2"></i>No active salary structure.</div>
                        @endif
                    </div>
                </div>

                <div class="hr-card">
                    <div class="hr-card-header"><div class="hr-card-title">How It Works</div></div>
                    <div class="hr-card-body">
                        <div class="hr-muted" style="line-height:1.7">
                            Basic salary and fixed allowances are employee-specific. Percentage components are calculated from basic salary. Manual components are enabled here, while overtime and attendance/leave deductions are generated automatically during monthly payroll.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-9">
                @can('employee-salary-manage')
                    <form action="{{ route('hr.employees.salary.store', $employee) }}" method="POST" id="salaryStructureForm">
                        @csrf
                        <div class="hr-card mb-3">
                            <div class="hr-card-header">
                                <div>
                                    <div class="hr-card-title">Salary Structure</div>
                                    <div class="hr-card-subtitle">Saving with a later effective date creates a new salary version and closes the previous one.</div>
                                </div>
                                <div class="hr-estimated-gross">
                                    <span>Estimated Gross</span>
                                    <strong id="estimatedGrossValue">৳0.00</strong>
                                </div>
                            </div>
                            <div class="hr-card-body">
                                @if($errors->any())
                                    <div class="alert alert-danger mb-3"><strong>Please check the form:</strong> {{ $errors->first() }}</div>
                                @endif

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="progga-form-label">Effective From <span class="progga-required">*</span></label>
                                        <input
                                            type="text"
                                            id="salaryEffectiveFrom"
                                            name="effective_from"
                                            class="progga-form-control"
                                            value="{{ old('effective_from', optional($editingStructure?->effective_from)->format('Y-m-d') ?: now()->toDateString()) }}"
                                            required
                                        >
                                        @error('effective_from')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="progga-form-label">Basic Salary <span class="progga-required">*</span></label>
                                        <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" id="basicSalary" name="basic_salary" class="progga-form-control salary-calc-input" value="{{ old('basic_salary', $editingStructure?->basic_salary ?? 0) }}" required></div>
                                        @error('basic_salary')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="progga-form-label">Overtime Rate per Hour</label>
                                        <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" name="overtime_rate" class="progga-form-control" value="{{ old('overtime_rate', $editingStructure?->overtime_rate ?? '') }}"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="progga-form-label">Payment Method</label>
                                        <select id="salaryPaymentMethod" name="payment_method" class="salary-select2" data-search="false">
                                            @foreach(['cash' => 'Cash', 'bank' => 'Bank', 'mobile_banking' => 'Mobile Banking'] as $value => $label)
                                                <option value="{{ $value }}" {{ old('payment_method', $editingStructure?->payment_method ?? 'cash') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="progga-form-label">Account Name</label>
                                        <input type="text" name="account_name" class="progga-form-control" value="{{ old('account_name', $editingStructure?->account_name) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="progga-form-label">Account / Wallet Number</label>
                                        <input type="text" name="account_number" class="progga-form-control" value="{{ old('account_number', $editingStructure?->account_number) }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="progga-form-label">Mobile Banking Provider</label>
                                        <input type="text" name="mobile_banking_provider" class="progga-form-control" placeholder="bKash, Nagad, Rocket" value="{{ old('mobile_banking_provider', $editingStructure?->mobile_banking_provider) }}">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="progga-form-label">Notes</label>
                                        <input type="text" name="notes" class="progga-form-control" value="{{ old('notes', $editingStructure?->notes) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hr-card mb-3">
                            <div class="hr-card-header">
                                <div>
                                    <div class="hr-card-title">Employee-wise Salary Components</div>
                                    <div class="hr-card-subtitle">Enable only the components applicable to this employee.</div>
                                </div>
                            </div>
                            <div class="progga-table-wrapper" style="border:0;border-radius:0">
                                <table class="progga-table hr-salary-component-table">
                                    <thead>
                                        <tr><th style="width:80px">Use</th><th>Component</th><th>Type</th><th>Calculation</th><th style="width:230px">Employee Value</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse($salaryComponents as $component)
                                            @php
                                                $saved = $componentMap->get($component->id);
                                                $enabled = (bool) old('components.' . $component->id . '.enabled', $saved?->is_active ?? false);
                                                $amount = old('components.' . $component->id . '.amount', $saved?->amount ?? $component->default_amount ?? 0);
                                                $percentage = old('components.' . $component->id . '.percentage', $saved?->percentage ?? $component->default_percentage ?? 0);
                                            @endphp
                                            <tr data-component-row data-component-type="{{ $component->type }}" data-calculation-type="{{ $component->calculation_type }}">
                                                <td>
                                                    <input type="hidden" name="components[{{ $component->id }}][enabled]" value="0">
                                                    <label class="progga-toggle">
                                                        <input type="checkbox" class="salary-component-enabled" name="components[{{ $component->id }}][enabled]" value="1" {{ $enabled ? 'checked' : '' }}>
                                                        <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                                                    </label>
                                                </td>
                                                <td>
                                                    <div style="font-weight:800">{{ $component->name }}</div>
                                                    <div class="hr-muted">{{ $component->code ?: 'No code' }}</div>
                                                </td>
                                                <td><span class="hr-badge {{ $component->type === 'earning' ? 'hr-badge-success' : 'hr-badge-danger' }}">{{ $component->type }}</span></td>
                                                <td><span class="hr-badge hr-badge-neutral">{{ $component->calculation_type === 'fixed' ? 'Employee-wise Fixed' : ucfirst($component->calculation_type) }}</span></td>
                                                <td>
                                                    @if($component->calculation_type === 'fixed')
                                                        <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" class="progga-form-control component-value salary-calc-input" name="components[{{ $component->id }}][amount]" value="{{ $amount }}"></div>
                                                    @elseif($component->calculation_type === 'percentage')
                                                        <div class="input-group"><input type="number" step="0.01" min="0" max="100" class="progga-form-control component-value salary-calc-input" name="components[{{ $component->id }}][percentage]" value="{{ $percentage }}"><span class="input-group-text">% of Basic</span></div>
                                                    @else
                                                        <div class="hr-manual-component"><i class="bi bi-pencil-square"></i> Amount will be entered during monthly payroll.</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5"><div class="hr-empty">No active salary components found in HR Settings.</div></td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="hr-form-actions mb-3">
                            <a href="{{ route('hr.employees.show', $employee) }}" class="progga-btn progga-btn-outline"><i class="bi bi-arrow-left"></i> Cancel</a>
                            <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check2-circle"></i> Save Salary Structure</button>
                        </div>
                    </form>
                @else
                    <div class="alert alert-warning">You have view permission, but you cannot change salary information.</div>
                @endcan

                <div class="hr-card">
                    <div class="hr-card-header">
                        <div><div class="hr-card-title">Salary History</div><div class="hr-card-subtitle">Previous values remain unchanged for audit and future payroll calculations.</div></div>
                    </div>
                    <div class="progga-table-wrapper" style="border:0;border-radius:0">
                        <table class="progga-table">
                            <thead><tr><th>Effective Period</th><th>Basic Salary</th><th>Estimated Gross</th><th>Payment Method</th><th>Components</th></tr></thead>
                            <tbody>
                                @forelse($salaryHistory as $history)
                                    <tr>
                                        <td>
                                            <strong>{{ $history->effective_from->format('d-m-Y') }}</strong>
                                            <div class="hr-muted">to {{ optional($history->effective_to)->format('d-m-Y') ?: 'Current' }}</div>
                                        </td>
                                        <td>৳{{ number_format((float) $history->basic_salary, 2) }}</td>
                                        <td>৳{{ number_format((float) $history->estimated_gross, 2) }}</td>
                                        <td>{{ ucwords(str_replace('_', ' ', $history->payment_method)) }}</td>
                                        <td>{{ $history->components->where('is_active', true)->count() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5"><div class="hr-empty py-4">No salary history found.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
    @include('admin.hr.shared.plugins')
    <script>
        $(function () {
            HrUi.initSelect2('.salary-select2');
            HrUi.initFlatpickr('#salaryEffectiveFrom', { minDate: @json($employee->join_date->format('Y-m-d')) });

            function refreshComponentState() {
                $('[data-component-row]').each(function () {
                    const row = $(this);
                    const enabled = row.find('.salary-component-enabled').is(':checked');
                    row.toggleClass('salary-component-disabled', !enabled);
                    row.find('.component-value').prop('disabled', !enabled);
                });
                calculateGross();
            }

            function calculateGross() {
                const basic = parseFloat($('#basicSalary').val()) || 0;
                let gross = basic;

                $('[data-component-row]').each(function () {
                    const row = $(this);
                    if (!row.find('.salary-component-enabled').is(':checked')) return;
                    if (row.data('component-type') !== 'earning') return;

                    const calculation = row.data('calculation-type');
                    const value = parseFloat(row.find('.component-value').val()) || 0;
                    if (calculation === 'fixed') gross += value;
                    if (calculation === 'percentage') gross += basic * value / 100;
                });

                $('#estimatedGrossValue').text('৳' + gross.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }

            $(document).on('change', '.salary-component-enabled', refreshComponentState);
            $(document).on('input', '.salary-calc-input', calculateGross);

            $('#salaryStructureForm').on('submit', function () {
                $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
            });

            @if(session('success'))
                Swal.fire({ icon: 'success', title: 'Saved', text: @json(session('success')), timer: 1800, showConfirmButton: false });
            @endif
            @if(session('error'))
                Swal.fire('Error', @json(session('error')), 'error');
            @endif

            refreshComponentState();
        });
    </script>
@endsection
