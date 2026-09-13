@extends('admin.master.master')

@section('title', 'Employees — ' . $restaurantSettingName)

@section('css')
    @include('admin.hr.shared.styles')
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-shell">
        <div class="progga-page-header">
            <div>
                <h1 class="progga-page-title">Employees</h1>
                <div class="progga-breadcrumb">
                    <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item">Human Resources</span>
                    <span class="progga-breadcrumb-sep">/</span>
                    <span class="progga-breadcrumb-item active">Employees</span>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap justify-content-end">
                @can('employee-delete')
                    <button type="button" class="progga-btn progga-btn-danger" id="employeeBulkDeleteBtn" disabled>
                        <i class="bi bi-trash3-fill"></i> Delete Selected <span id="employeeBulkDeleteCount"></span>
                    </button>
                @endcan

                @can('employee-create')
                    <a href="{{ route('hr.employees.create') }}" class="progga-btn progga-btn-primary">
                        <i class="bi bi-person-plus-fill"></i> Add Employee
                    </a>
                @endcan
            </div>
        </div>

        <div class="hr-stat-grid">
            <div class="hr-stat-card">
                <div class="hr-stat-icon"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="hr-stat-value">{{ $totalEmployees }}</div>
                    <div class="hr-stat-label">Total Employees</div>
                </div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-icon"><i class="bi bi-person-check-fill"></i></div>
                <div>
                    <div class="hr-stat-value">{{ $activeEmployees }}</div>
                    <div class="hr-stat-label">Active Employees</div>
                </div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-icon"><i class="bi bi-person-badge-fill"></i></div>
                <div>
                    <div class="hr-stat-value">{{ $waiterEmployees }}</div>
                    <div class="hr-stat-label">Waiter / POS Access</div>
                </div>
            </div>
            <div class="hr-stat-card">
                <div class="hr-stat-icon"><i class="bi bi-box-arrow-in-right"></i></div>
                <div>
                    <div class="hr-stat-value">{{ $loginEmployees }}</div>
                    <div class="hr-stat-label">Login Enabled</div>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="hr-card-body">
                <div class="hr-filter-grid hr-filter-grid-employees">
                    <div class="hr-search">
                        <i class="bi bi-search"></i>
                        <input
                            type="text"
                            id="employeeSearch"
                            class="progga-form-control"
                            placeholder="Search name, employee ID, NID, phone or email"
                        >
                    </div>

                    <select id="employeeDepartment" class="hr-select2">
                        <option value="">All Departments</option>
                        @foreach($departments as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>

                    <select id="employeeDesignation" class="hr-select2">
                        <option value="">All Designations</option>
                        @foreach($designations as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>

                    <select id="employeeShift" class="hr-select2">
                        <option value="">All Shifts</option>
                        @foreach($shifts as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>

                    <select id="employeeStatus" class="hr-select2" data-search="false">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="resigned">Resigned</option>
                        <option value="terminated">Terminated</option>
                    </select>

                    <select id="employeeAccess" class="hr-select2" data-search="false">
                        <option value="">All Access</option>
                        <option value="waiter">Waiter / POS</option>
                        <option value="login">Login Enabled</option>
                        <option value="no_access">No System Access</option>
                    </select>

                    <button type="button" class="progga-btn progga-btn-outline" id="employeeReset">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="hr-card" id="employeeTableContainer" data-current-page="{{ $initialTableData['employees']->currentPage() }}">
            @include('admin.hr.employees.table', $initialTableData)
        </div>
    </div>
</main>
@endsection

@section('script')
    @include('admin.hr.shared.plugins')
    <script>
        $(function () {
            let currentPage = Number($('#employeeTableContainer').data('current-page') || 1);
            let searchTimer = null;
            let activeEmployeeRequest = null;
            let employeePageInitializing = true;
            const selectedEmployees = new Set();

            HrUi.initSelect2('.hr-select2');
            HrUi.initSelect2($('#employeeTableContainer'));

            function syncEmployeeSelectionUi() {
                $('#employeeTableContainer .employee-row-checkbox').each(function () {
                    this.checked = selectedEmployees.has(String(this.value));
                });

                const visible = $('#employeeTableContainer .employee-row-checkbox').toArray();
                const selectedVisible = visible.filter(function (checkbox) { return checkbox.checked; }).length;
                $('#employeeSelectAllVisible')
                    .prop('checked', visible.length > 0 && selectedVisible === visible.length)
                    .prop('indeterminate', selectedVisible > 0 && selectedVisible < visible.length);

                const count = selectedEmployees.size;
                $('#employeeBulkDeleteBtn').prop('disabled', count === 0);
                $('#employeeBulkDeleteCount').text(count ? '(' + count + ')' : '');
            }

            function loadEmployees(page) {
                currentPage = page || 1;
                const container = $('#employeeTableContainer').addClass('hr-table-loading');

                if (activeEmployeeRequest && activeEmployeeRequest.readyState !== 4) {
                    activeEmployeeRequest.abort();
                }

                const request = $.get("{{ route('hr.employees.index') }}", {
                    page: currentPage,
                    search: $('#employeeSearch').val(),
                    department_id: HrUi.selectValue('employeeDepartment'),
                    designation_id: HrUi.selectValue('employeeDesignation'),
                    shift_id: HrUi.selectValue('employeeShift'),
                    status: HrUi.selectValue('employeeStatus'),
                    access: HrUi.selectValue('employeeAccess')
                });
                activeEmployeeRequest = request;

                request.done(function (html) {
                    container.html(html);
                    HrUi.initSelect2(container);
                    container.attr('data-current-page', currentPage);
                    syncEmployeeSelectionUi();
                }).fail(function (xhr, statusText) {
                    if (statusText === 'abort') return;
                    Swal.fire('Error', xhr.responseJSON?.message || 'Failed to load employees.', 'error');
                }).always(function () {
                    if (activeEmployeeRequest === request) {
                        container.removeClass('hr-table-loading');
                        activeEmployeeRequest = null;
                    }
                });
            }

            $('#employeeSearch').on('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    loadEmployees(1);
                }, 350);
            });

            $('.hr-select2').on('change', function () {
                if (!employeePageInitializing) loadEmployees(1);
            });

            $('#employeeReset').on('click', function () {
                employeePageInitializing = true;
                $('#employeeSearch').val('');
                [
                    'employeeDepartment',
                    'employeeDesignation',
                    'employeeShift',
                    'employeeStatus',
                    'employeeAccess'
                ].forEach(function (id) { HrUi.resetSelect(id); });
                employeePageInitializing = false;
                loadEmployees(1);
            });

            $(document).on('click', '#employeeTableContainer .report-page-link:not(.disabled)', function (event) {
                event.preventDefault();
                const url = new URL(this.href);
                loadEmployees(url.searchParams.get('page') || 1);
            });

            $(document).on('change', '#employeeSelectAllVisible', function () {
                const checked = this.checked;
                $('#employeeTableContainer .employee-row-checkbox').each(function () {
                    this.checked = checked;
                    if (checked) selectedEmployees.add(String(this.value));
                    else selectedEmployees.delete(String(this.value));
                });
                syncEmployeeSelectionUi();
            });

            $(document).on('change', '.employee-row-checkbox', function () {
                if (this.checked) selectedEmployees.add(String(this.value));
                else selectedEmployees.delete(String(this.value));
                syncEmployeeSelectionUi();
            });

            $('#employeeBulkDeleteBtn').on('click', function () {
                const ids = Array.from(selectedEmployees);
                if (!ids.length) return;

                Swal.fire({
                    title: 'Delete selected employees?',
                    html: '<strong>' + ids.length + '</strong> employee(s) selected.<br><small>Employees with protected HR, login or salary history will be skipped.</small>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete Selected',
                    confirmButtonColor: '#c63d3d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    const button = $('#employeeBulkDeleteBtn').prop('disabled', true);
                    $.ajax({
                        url: "{{ route('hr.employees.bulk.destroy') }}",
                        type: 'DELETE',
                        data: {
                            _token: "{{ csrf_token() }}",
                            employee_ids: ids
                        }
                    }).done(function (response) {
                        const skipped = Array.isArray(response.skipped_employees) && response.skipped_employees.length
                            ? '<div class="text-start mt-3"><strong>Skipped:</strong><br>' + response.skipped_employees.map(function (name) { return $('<div>').text(name).html(); }).join('<br>') + '</div>'
                            : '';
                        Swal.fire({
                            icon: response.skipped_count ? 'warning' : 'success',
                            title: response.skipped_count ? 'Bulk Delete Complete' : 'Deleted',
                            html: '<p>' + $('<div>').text(response.message).html() + '</p>' + skipped
                        });
                        selectedEmployees.clear();
                        syncEmployeeSelectionUi();
                        loadEmployees(currentPage);
                    }).fail(function (xhr) {
                        Swal.fire('Cannot delete', xhr.responseJSON?.message || 'Bulk delete failed.', 'error');
                    }).always(function () {
                        button.prop('disabled', selectedEmployees.size === 0);
                    });
                });
            });

            $(document).on('change', '.employee-status-select', function () {
                const select = this;
                const previous = select.dataset.current;

                $.ajax({
                    url: "{{ url('/hr/employees') }}/" + select.dataset.id + '/status',
                    type: 'PATCH',
                    data: {
                        _token: "{{ csrf_token() }}",
                        employment_status: select.value
                    }
                }).done(function (response) {
                    select.dataset.current = select.value;
                    if (typeof showToast === 'function') {
                        showToast('Updated', response.message);
                    } else {
                        Swal.fire({ icon: 'success', title: 'Updated', text: response.message, timer: 1500, showConfirmButton: false });
                    }
                }).fail(function (xhr) {
                    $(select).val(previous).trigger('change.select2');
                    Swal.fire('Error', xhr.responseJSON?.message || 'Could not update employee status.', 'error');
                });
            });

            $(document).on('click', '.employee-delete-btn', function () {
                const employeeId = this.dataset.id;

                Swal.fire({
                    title: 'Delete employee?',
                    text: 'Employees with HR, login or salary history cannot be deleted.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#c63d3d'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: "{{ url('/hr/employees') }}/" + employeeId,
                        type: 'DELETE',
                        data: { _token: "{{ csrf_token() }}" }
                    }).done(function (response) {
                        selectedEmployees.delete(String(employeeId));
                        syncEmployeeSelectionUi();
                        Swal.fire({ icon: 'success', title: 'Deleted', text: response.message, timer: 1500, showConfirmButton: false });
                        loadEmployees(currentPage);
                    }).fail(function (xhr) {
                        Swal.fire('Cannot delete', xhr.responseJSON?.message || 'Delete failed.', 'error');
                    });
                });
            });

            @if(session('success'))
                Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')), timer: 1800, showConfirmButton: false });
            @endif

            @if(session('error'))
                Swal.fire('Error', @json(session('error')), 'error');
            @endif

            syncEmployeeSelectionUi();
            employeePageInitializing = false;
        });
    </script>
@endsection
