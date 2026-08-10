@php
    $isEdit = isset($employee);
    $employeeValue = fn ($field, $default = null) => old($field, $isEdit ? data_get($employee, $field) : $default);
    $isWaiterChecked = old('is_waiter', $isEdit ? $employee->is_waiter : false);
    $canLoginChecked = old('can_login', $isEdit ? $employee->can_login : false);
    $avatar = $isEdit && $employee->image
        ? asset($employee->image)
        : 'https://ui-avatars.com/api/?name=' . urlencode($isEdit ? $employee->name : 'Employee') . '&background=21352a&color=d5aa65&size=160&bold=true';
@endphp

@if($errors->any())
    <div class="alert alert-danger mb-3">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please correct the highlighted fields.</div>
        <div style="font-size:12px">{{ $errors->first() }}</div>
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-3">
        <div class="hr-card h-100">
            <div class="hr-card-body text-center">
                <img id="employeeImagePreview" src="{{ $avatar }}" class="hr-profile-image mb-3" alt="Employee image">
                <div class="hr-card-title">{{ $isEdit ? $employee->employee_code : 'Employee Photo' }}</div>
                <div class="hr-muted mb-3">JPG, PNG or WEBP · Maximum 2 MB</div>
                <label class="progga-btn progga-btn-outline w-100 justify-content-center" for="employeeImage">
                    <i class="bi bi-camera"></i> Choose Photo
                </label>
                <input type="file" class="d-none" name="image" id="employeeImage" accept="image/*">
                @error('image')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="col-xl-9">
        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Personal Information</div>
                    <div class="hr-card-subtitle">Basic identity and communication details.</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="progga-form-label">Full Name <span class="progga-required">*</span></label>
                        <input type="text" class="progga-form-control" name="name" value="{{ $employeeValue('name') }}" required>
                        @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Phone <span class="progga-required">*</span></label>
                        <input type="text" class="progga-form-control" name="phone" value="{{ $employeeValue('phone') }}" required>
                        @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Gender</label>
                        <select id="employeeGender" name="gender" class="employee-form-select2" data-search="false">
                            <option value="">Select Gender</option>
                            <option value="male" {{ $employeeValue('gender') === 'male' ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ $employeeValue('gender') === 'female' ? 'selected' : '' }}>Female</option>
                            <option value="other" {{ $employeeValue('gender') === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Date of Birth</label>
                        <input type="text" id="employeeDob" class="progga-form-control employee-date" name="date_of_birth" value="{{ old('date_of_birth', $isEdit ? optional($employee->date_of_birth)->format('Y-m-d') : '') }}">
                        @error('date_of_birth')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="progga-form-label">Address</label>
                        <input type="text" class="progga-form-control" name="address" value="{{ $employeeValue('address') }}">
                        @error('address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Employment Information</div>
                    <div class="hr-card-subtitle">Department, designation, shift and service dates.</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="progga-form-label">Department <span class="progga-required">*</span></label>
                        <select id="employeeDepartmentId" name="department_id" class="employee-form-select2" required>
                            <option value="">Select Department</option>
                            @foreach($departments as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('department_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Designation <span class="progga-required">*</span></label>
                        <select id="employeeDesignationId" name="designation_id" class="employee-form-select2" required>
                            <option value="">Select Designation</option>
                            @foreach($designations as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('designation_id') === (string) $item->id ? 'selected' : '' }}>
                                    {{ $item->name }}{{ $item->department ? ' — ' . $item->department->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('designation_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Employment Type <span class="progga-required">*</span></label>
                        <select id="employeeEmploymentTypeId" name="employment_type_id" class="employee-form-select2" required>
                            <option value="">Select Employment Type</option>
                            @foreach($employmentTypes as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('employment_type_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('employment_type_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Joining Date <span class="progga-required">*</span></label>
                        <input type="text" id="employeeJoinDate" class="progga-form-control employee-date" name="join_date" value="{{ old('join_date', $isEdit ? optional($employee->join_date)->format('Y-m-d') : now()->toDateString()) }}" required>
                        @error('join_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Probation End</label>
                        <input type="text" id="employeeProbationEnd" class="progga-form-control employee-date" name="probation_end_date" value="{{ old('probation_end_date', $isEdit ? optional($employee->probation_end_date)->format('Y-m-d') : '') }}">
                        @error('probation_end_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Default Shift</label>
                        <select id="employeeDefaultShiftId" name="default_shift_id" class="employee-form-select2">
                            <option value="">No Default Shift</option>
                            @foreach($shifts as $item)
                                <option value="{{ $item->id }}" {{ (string) $employeeValue('default_shift_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }} — {{ $item->time_range }}</option>
                            @endforeach
                        </select>
                        @error('default_shift_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Employment Status</label>
                        <select id="employeeEmploymentStatus" name="employment_status" class="employee-form-select2" data-search="false">
                            @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'resigned' => 'Resigned', 'terminated' => 'Terminated'] as $value => $label)
                                <option value="{{ $value }}" {{ $employeeValue('employment_status', 'active') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3" id="employeeExitDateWrap">
                        <label class="progga-form-label">Exit Date</label>
                        <input type="text" id="employeeExitDate" class="progga-form-control employee-date" name="exit_date" value="{{ old('exit_date', $isEdit ? optional($employee->exit_date)->format('Y-m-d') : '') }}">
                        @error('exit_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Access & Operational Role</div>
                    <div class="hr-card-subtitle">Waiter/POS access and system login are separate permissions.</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="hr-option-card">
                            <label class="progga-toggle">
                                <input type="checkbox" name="is_waiter" id="employeeIsWaiter" value="1" {{ $isWaiterChecked ? 'checked' : '' }}>
                                <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                                <span class="progga-toggle-label fw-bold">Waiter / POS Access</span>
                            </label>
                            <div class="hr-muted mt-2">The employee will be linked with the existing waiter/POS module.</div>
                            <div id="waiterAccessFields" class="mt-3">
                                <label class="progga-form-label">Assigned Zone <span class="progga-required">*</span></label>
                                <select id="employeeZoneId" name="zone_id" class="employee-form-select2">
                                    <option value="">Select Zone</option>
                                    @foreach($zones as $item)
                                        <option value="{{ $item->id }}" {{ (string) $employeeValue('zone_id') === (string) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @error('zone_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="hr-option-card">
                            <label class="progga-toggle">
                                <input type="checkbox" name="can_login" id="employeeCanLogin" value="1" {{ $canLoginChecked ? 'checked' : '' }}>
                                <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                                <span class="progga-toggle-label fw-bold">Can Login to System</span>
                            </label>
                            <div class="hr-muted mt-2">A user account will be created or linked for this employee.</div>
                            <div id="loginAccessFields" class="row g-2 mt-2">
                                <div class="col-12">
                                    <label class="progga-form-label">Login Email</label>
                                    <input type="email" class="progga-form-control" name="email" value="{{ $employeeValue('email') }}">
                                    @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="progga-form-label">{{ $isEdit ? 'New Password' : 'Password' }}</label>
                                    <input type="password" class="progga-form-control" name="password" autocomplete="new-password">
                                    @if($isEdit)<div class="hr-muted mt-1">Leave blank to keep the existing password.</div>@endif
                                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="progga-form-label">Confirm Password</label>
                                    <input type="password" class="progga-form-control" name="password_confirmation" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card">
            <div class="hr-card-header">
                <div>
                    <div class="hr-card-title">Emergency Contact & Notes</div>
                </div>
            </div>
            <div class="hr-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="progga-form-label">Emergency Contact Name</label>
                        <input type="text" class="progga-form-control" name="emergency_contact_name" value="{{ $employeeValue('emergency_contact_name') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Emergency Contact Phone</label>
                        <input type="text" class="progga-form-control" name="emergency_contact_phone" value="{{ $employeeValue('emergency_contact_phone') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="progga-form-label">Internal Notes</label>
                        <textarea class="progga-form-control" name="notes" rows="3">{{ $employeeValue('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="hr-form-actions mt-3">
    <a href="{{ $isEdit ? route('hr.employees.show', $employee) : route('hr.employees.index') }}" class="progga-btn progga-btn-outline">
        <i class="bi bi-arrow-left"></i> Cancel
    </a>
    <button type="submit" class="progga-btn progga-btn-primary">
        <i class="bi bi-check2-circle"></i> {{ $isEdit ? 'Update Employee' : 'Save Employee' }}
    </button>
</div>
