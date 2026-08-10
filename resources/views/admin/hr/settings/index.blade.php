@extends('admin.master.master')
@section('title', 'HR Settings — ' . ($restaurantSettingName ?? 'TableTrack RMS'))

@section('css')
<style>
    .hr-settings-shell {
        position: relative;
    }
    .hr-settings-shell::before {
        content: '';
        position: absolute;
        inset: -24px -20px auto;
        height: 210px;
        border-radius: 0 0 28px 28px;
        background: linear-gradient(135deg, rgba(33, 53, 42, .07), rgba(213, 170, 101, .10));
        pointer-events: none;
        z-index: 0;
    }
    .hr-settings-shell > * {
        position: relative;
        z-index: 1;
    }
    .hr-settings-panel { display: none; }
    .hr-settings-panel.active { display: block; animation: hrFadeIn .22s ease; }
    @keyframes hrFadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .hr-settings-help {
        font-size: 12px;
        color: var(--progga-text-muted);
        margin-top: 5px;
        line-height: 1.55;
    }
    .hr-summary-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }
    .hr-summary-card {
        background: rgba(255, 255, 255, .95);
        border: 1px solid rgba(33, 53, 42, .10);
        border-radius: 16px;
        padding: 17px;
        display: flex;
        align-items: center;
        gap: 13px;
        box-shadow: 0 8px 24px rgba(33, 53, 42, .06);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .hr-summary-card:hover {
        transform: translateY(-2px);
        border-color: rgba(213, 170, 101, .45);
        box-shadow: 0 13px 30px rgba(33, 53, 42, .10);
    }
    .hr-summary-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(145deg, rgba(33, 53, 42, .12), rgba(213, 170, 101, .18));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--progga-primary);
        font-size: 20px;
        flex-shrink: 0;
    }
    .hr-summary-value { font-weight: 800; font-size: 22px; line-height: 1; color: var(--progga-text); }
    .hr-summary-label { font-size: 12px; color: var(--progga-text-muted); margin-top: 5px; }
    .hr-color-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        border: 1px solid rgba(0,0,0,.12);
        margin-right: 6px;
        vertical-align: -1px;
    }
    .hr-inline-note {
        padding: 14px 16px;
        background: linear-gradient(135deg, rgba(213, 170, 101, .11), rgba(33, 53, 42, .05));
        border: 1px solid rgba(213, 170, 101, .35);
        border-radius: 13px;
        color: var(--progga-text);
        font-size: 13px;
        margin-bottom: 18px;
        box-shadow: 0 6px 20px rgba(33, 53, 42, .04);
    }

    /* Multi-row tabs: no horizontal scrolling */
    .hr-settings-tabs {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 20px;
        padding: 0;
        overflow: visible !important;
        border: 0 !important;
        background: transparent !important;
    }
    .hr-settings-tabs .progga-tab-item {
        min-width: 0;
        width: 100%;
        min-height: 58px;
        margin: 0 !important;
        padding: 10px 13px;
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: normal;
        text-align: left;
        border: 1px solid rgba(33, 53, 42, .11);
        border-radius: 13px;
        background: rgba(255, 255, 255, .96);
        color: var(--progga-text);
        box-shadow: 0 5px 16px rgba(33, 53, 42, .045);
        cursor: pointer;
        transition: all .18s ease;
    }
    .hr-settings-tabs .progga-tab-item::after { display: none !important; }
    .hr-settings-tabs .progga-tab-item:hover {
        transform: translateY(-1px);
        border-color: rgba(213, 170, 101, .55);
        box-shadow: 0 9px 20px rgba(33, 53, 42, .075);
    }
    .hr-settings-tabs .progga-tab-item.active {
        color: #fff;
        border-color: var(--progga-primary);
        background: linear-gradient(135deg, var(--progga-primary), #355b46);
        box-shadow: 0 10px 24px rgba(33, 53, 42, .19);
    }
    .hr-tab-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 36px;
        background: rgba(33, 53, 42, .08);
        color: var(--progga-primary);
        font-size: 16px;
    }
    .hr-settings-tabs .active .hr-tab-icon {
        color: #fff;
        background: rgba(255, 255, 255, .15);
    }
    .hr-tab-copy { min-width: 0; }
    .hr-tab-title { display: block; font-size: 13px; font-weight: 700; line-height: 1.2; }
    .hr-tab-subtitle { display: block; margin-top: 3px; font-size: 10px; color: var(--progga-text-muted); line-height: 1.2; }
    .hr-settings-tabs .active .hr-tab-subtitle { color: rgba(255,255,255,.72); }

    .hr-settings-panel .progga-card {
        border: 1px solid rgba(33, 53, 42, .10);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 28px rgba(33, 53, 42, .065);
        background: #fff;
    }
    .hr-settings-panel .progga-card-header {
        padding: 18px 20px;
        background: linear-gradient(180deg, #fff, rgba(33, 53, 42, .018));
        border-bottom: 1px solid rgba(33, 53, 42, .08);
    }
    .hr-settings-panel .progga-card-title {
        font-size: 16px;
        font-weight: 800;
    }
    .hr-settings-panel .progga-card-body { padding: 20px; }
    .hr-settings-panel .progga-table thead th {
        background: rgba(33, 53, 42, .045);
        color: var(--progga-text);
        font-size: 11px;
        letter-spacing: .025em;
        text-transform: uppercase;
    }
    .hr-settings-panel .progga-table tbody tr { transition: background .15s ease; }
    .hr-settings-panel .progga-table tbody tr:hover { background: rgba(213, 170, 101, .045); }
    .hr-table-description {
        max-width: 280px;
        font-size: 12px;
        color: var(--progga-text-muted);
        white-space: normal;
    }
    .hr-empty-state {
        padding: 38px 16px !important;
        color: var(--progga-text-muted);
    }

    .hr-settings-panel .progga-form-control,
    .hr-settings-panel .progga-select,
    .progga-modal .progga-form-control,
    .progga-modal .progga-select {
        min-height: 42px;
        border-radius: 9px;
    }
    .hr-settings-panel .progga-form-control:focus,
    .progga-modal .progga-form-control:focus {
        border-color: rgba(33, 53, 42, .55);
        box-shadow: 0 0 0 3px rgba(33, 53, 42, .08);
    }
    .progga-modal .modal-content {
        border: 0;
        border-radius: 17px;
        overflow: hidden;
        box-shadow: 0 24px 70px rgba(0,0,0,.18);
    }
    .progga-modal .modal-header {
        padding: 17px 20px;
        background: linear-gradient(135deg, var(--progga-primary), #355b46);
        color: #fff;
        border: 0;
    }
    .progga-modal .modal-header .btn-close { filter: invert(1); opacity: .85; }
    .progga-modal .modal-body { padding: 20px; }
    .progga-modal .modal-footer { padding: 14px 20px 18px; border-top-color: rgba(33, 53, 42, .08); }

    /* Select2 uses the project-wide Progga theme. */
    .hr-settings-shell .select2-container,
    .progga-modal .select2-container {
        width: 100% !important;
        min-width: 0;
    }
    .hr-settings-shell .select2-container--progga-theme .select2-selection--single,
    .progga-modal .select2-container--progga-theme .select2-selection--single {
        height: 42px;
    }
    .hr-settings-shell .select2-container--progga-theme .select2-selection--single .select2-selection__rendered,
    .progga-modal .select2-container--progga-theme .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
    }
    .hr-settings-shell .select2-container--progga-theme .select2-selection--single .select2-selection__arrow,
    .progga-modal .select2-container--progga-theme .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    .select2-container--open { z-index: 2100 !important; }

    /* Flatpickr */
    .flatpickr-calendar { border-radius: 12px; box-shadow: 0 14px 38px rgba(0,0,0,.16); }
    .flatpickr-day.selected,
    .flatpickr-day.startRange,
    .flatpickr-day.endRange {
        background: var(--progga-primary);
        border-color: var(--progga-primary);
    }

    @media (max-width: 1199px) {
        .hr-settings-tabs { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 991px) {
        .hr-summary-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .hr-settings-tabs { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575px) {
        .hr-summary-strip { grid-template-columns: 1fr; }
        .hr-settings-tabs { grid-template-columns: 1fr; }
        .progga-page-header { gap: 12px; }
        .hr-settings-panel .progga-card-header {
            align-items: flex-start;
            gap: 12px;
        }
        .hr-settings-panel .progga-card-header .progga-btn { width: 100%; justify-content: center; }
    }
</style>
@endsection

@section('body')
<main class="progga-content">
    <div class="hr-settings-shell">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">HR Settings</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item">Human Resources</span>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">HR Settings</span>
            </div>
        </div>
    </div>

    <div class="hr-summary-strip">
        <div class="hr-summary-card">
            <div class="hr-summary-icon"><i class="bi bi-diagram-3-fill"></i></div>
            <div><div class="hr-summary-value">{{ $departments->count() }}</div><div class="hr-summary-label">Departments</div></div>
        </div>
        <div class="hr-summary-card">
            <div class="hr-summary-icon"><i class="bi bi-person-workspace"></i></div>
            <div><div class="hr-summary-value">{{ $designations->count() }}</div><div class="hr-summary-label">Designations</div></div>
        </div>
        <div class="hr-summary-card">
            <div class="hr-summary-icon"><i class="bi bi-calendar2-check-fill"></i></div>
            <div><div class="hr-summary-value">{{ $leaveTypes->count() }}</div><div class="hr-summary-label">Leave Types</div></div>
        </div>
        <div class="hr-summary-card">
            <div class="hr-summary-icon"><i class="bi bi-cash-stack"></i></div>
            <div><div class="hr-summary-value">{{ $salaryComponents->count() }}</div><div class="hr-summary-label">Salary Components</div></div>
        </div>
    </div>

    <div class="hr-inline-note">
        <i class="bi bi-info-circle-fill me-2"></i>
        Employee creation will use these settings. Each employee can separately receive <strong>Waiter/POS access</strong> and a <strong>system login account</strong>.
    </div>

    <div class="progga-tab-nav hr-settings-tabs" id="hrSettingsTabs" role="tablist">
        <button type="button" class="progga-tab-item" data-hr-tab="general" role="tab"><span class="hr-tab-icon"><i class="bi bi-sliders"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">General</span><span class="hr-tab-subtitle">Codes & access</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="departments" role="tab"><span class="hr-tab-icon"><i class="bi bi-diagram-3"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Departments</span><span class="hr-tab-subtitle">Work areas</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="designations" role="tab"><span class="hr-tab-icon"><i class="bi bi-person-workspace"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Designations</span><span class="hr-tab-subtitle">Job titles</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="employment-types" role="tab"><span class="hr-tab-icon"><i class="bi bi-person-check"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Employment Types</span><span class="hr-tab-subtitle">Staff categories</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="leave-types" role="tab"><span class="hr-tab-icon"><i class="bi bi-calendar2-check"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Leave Types</span><span class="hr-tab-subtitle">Policies & limits</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="salary-components" role="tab"><span class="hr-tab-icon"><i class="bi bi-cash-stack"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Salary Components</span><span class="hr-tab-subtitle">Earnings & deductions</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="holidays" role="tab"><span class="hr-tab-icon"><i class="bi bi-calendar-event"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Holidays</span><span class="hr-tab-subtitle">Calendar setup</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="attendance" role="tab"><span class="hr-tab-icon"><i class="bi bi-fingerprint"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Attendance Rules</span><span class="hr-tab-subtitle">Late & overtime</span></span></button>
        <button type="button" class="progga-tab-item" data-hr-tab="payroll" role="tab"><span class="hr-tab-icon"><i class="bi bi-wallet2"></i></span><span class="hr-tab-copy"><span class="hr-tab-title">Payroll Settings</span><span class="hr-tab-subtitle">Salary calculation</span></span></button>
    </div>

    <section class="hr-settings-panel" data-hr-panel="general">@include('admin.hr.settings.partials.general')</section>
    <section class="hr-settings-panel" data-hr-panel="departments">@include('admin.hr.settings.partials.departments')</section>
    <section class="hr-settings-panel" data-hr-panel="designations">@include('admin.hr.settings.partials.designations')</section>
    <section class="hr-settings-panel" data-hr-panel="employment-types">@include('admin.hr.settings.partials.employment_types')</section>
    <section class="hr-settings-panel" data-hr-panel="leave-types">@include('admin.hr.settings.partials.leave_types')</section>
    <section class="hr-settings-panel" data-hr-panel="salary-components">@include('admin.hr.settings.partials.salary_components')</section>
    <section class="hr-settings-panel" data-hr-panel="holidays">@include('admin.hr.settings.partials.holidays')</section>
    <section class="hr-settings-panel" data-hr-panel="attendance">@include('admin.hr.settings.partials.attendance')</section>
    <section class="hr-settings-panel" data-hr-panel="payroll">@include('admin.hr.settings.partials.payroll')</section>
    </div>
</main>

@include('admin.hr.settings.modals.department_modal')
@include('admin.hr.settings.modals.designation_modal')
@include('admin.hr.settings.modals.employment_type_modal')
@include('admin.hr.settings.modals.leave_type_modal')
@include('admin.hr.settings.modals.salary_component_modal')
@include('admin.hr.settings.modals.holiday_modal')
@endsection

@section('script')
@include('admin.hr.shared.plugins')
<script>
$(function () {
    const settingsIndexUrl = @json(route('hr.settings.index'));
    const initialTab = @json($activeTab);
    const validTabs = ['general', 'departments', 'designations', 'employment-types', 'leave-types', 'salary-components', 'holidays', 'attendance', 'payroll'];
    const sessionSuccess = @json(session('success'));
    const sessionError = @json(session('error'));
    const validationError = @json($errors->first());
    let hrHolidayDatePicker = null;

    const routeMap = {
        departments: {
            store: @json(route('hr.settings.departments.store')),
            update: @json(route('hr.settings.departments.update', ['department' => '__ID__'])),
            status: @json(route('hr.settings.departments.status', ['department' => '__ID__'])),
            destroy: @json(route('hr.settings.departments.destroy', ['department' => '__ID__']))
        },
        designations: {
            store: @json(route('hr.settings.designations.store')),
            update: @json(route('hr.settings.designations.update', ['designation' => '__ID__'])),
            status: @json(route('hr.settings.designations.status', ['designation' => '__ID__'])),
            destroy: @json(route('hr.settings.designations.destroy', ['designation' => '__ID__']))
        },
        'employment-types': {
            store: @json(route('hr.settings.employment-types.store')),
            update: @json(route('hr.settings.employment-types.update', ['employmentType' => '__ID__'])),
            status: @json(route('hr.settings.employment-types.status', ['employmentType' => '__ID__'])),
            destroy: @json(route('hr.settings.employment-types.destroy', ['employmentType' => '__ID__']))
        },
        'leave-types': {
            store: @json(route('hr.settings.leave-types.store')),
            update: @json(route('hr.settings.leave-types.update', ['leaveType' => '__ID__'])),
            status: @json(route('hr.settings.leave-types.status', ['leaveType' => '__ID__'])),
            destroy: @json(route('hr.settings.leave-types.destroy', ['leaveType' => '__ID__']))
        },
        'salary-components': {
            store: @json(route('hr.settings.salary-components.store')),
            update: @json(route('hr.settings.salary-components.update', ['salaryComponent' => '__ID__'])),
            status: @json(route('hr.settings.salary-components.status', ['salaryComponent' => '__ID__'])),
            destroy: @json(route('hr.settings.salary-components.destroy', ['salaryComponent' => '__ID__']))
        },
        holidays: {
            store: @json(route('hr.settings.holidays.store')),
            update: @json(route('hr.settings.holidays.update', ['holiday' => '__ID__'])),
            status: @json(route('hr.settings.holidays.status', ['holiday' => '__ID__'])),
            destroy: @json(route('hr.settings.holidays.destroy', ['holiday' => '__ID__']))
        }
    };

    function routeFor(entity, action, id = null) {
        let route = routeMap[entity][action];
        return id ? route.replace('__ID__', id) : route;
    }

    function showHrAlert(title, text, icon = 'success') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            text: text || '',
            showConfirmButton: false,
            timer: 3200,
            timerProgressBar: true
        });
    }

    function setHrSelectValue(selectId, value) {
        HrUi.setSelectValue(selectId, value);
    }

    function resetHrSelects(form) {
        form.find('.hr-select2').each(function () {
            HrUi.resetSelect(this.id);
        });
    }

    function initialiseHrPlugins() {
        HrUi.initSelect2('.hr-select2');
        hrHolidayDatePicker = HrUi.initFlatpickr('#hrHolidayDate');
    }

    setTimeout(initialiseHrPlugins, 0);

    if (sessionSuccess) showHrAlert('Success', sessionSuccess, 'success');
    if (sessionError) showHrAlert('Error', sessionError, 'error');
    if (validationError) showHrAlert('Validation Error', validationError, 'error');

    function activateTab(tab) {
        if (!validTabs.includes(tab)) tab = 'general';
        $('[data-hr-tab]').removeClass('active').attr('aria-selected', 'false');
        $('[data-hr-tab="' + tab + '"]').addClass('active').attr('aria-selected', 'true');
        $('[data-hr-panel]').removeClass('active');
        $('[data-hr-panel="' + tab + '"]').addClass('active');

        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }

    activateTab(initialTab);

    $(document).on('click', '[data-hr-tab]', function () {
        activateTab($(this).data('hr-tab'));
    });

    $(document).on('change', '.progga-toggle input[type="checkbox"]', function () {
        const label = $(this).siblings('.progga-toggle-label');
        if (label.length) label.text($(this).is(':checked') ? ($(this).data('on') || 'Active') : ($(this).data('off') || 'Inactive'));
    });

    function ajaxError(xhr, fallback) {
        let message = fallback || 'Something went wrong. Please try again.';
        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                const firstKey = Object.keys(xhr.responseJSON.errors)[0];
                message = xhr.responseJSON.errors[firstKey][0];
            } else if (xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
        }
        showHrAlert('Error', message, 'error');
    }

    window.resetHrForm = function (formSelector, title, submitText) {
        const form = $(formSelector);
        form[0].reset();
        resetHrSelects(form);
        form.find('[name="id"]').val('');
        form.find('[name="status"]').prop('checked', true).trigger('change');
        if (form.is('#holidayForm') && hrHolidayDatePicker) hrHolidayDatePicker.clear();
        form.closest('.modal-content').find('.modal-title-text').text(title);
        form.find('.hr-submit-text').text(submitText || 'Save');
    };

    $('.hr-ajax-form').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        const entity = form.data('entity');
        const tab = form.data('tab');
        const id = form.find('[name="id"]').val();
        const url = id ? routeFor(entity, 'update', id) : routeFor(entity, 'store');
        const method = id ? 'PUT' : 'POST';
        const submitButton = form.find('button[type="submit"]');
        const originalHtml = submitButton.html();

        submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

        $.ajax({
            url: url,
            type: method,
            data: form.serialize(),
            success: function (response) {
                if (response.status === 'success') {
                    showHrAlert('Success', response.message, 'success');
                    setTimeout(function () {
                        window.location.href = settingsIndexUrl + '?tab=' + encodeURIComponent(tab);
                    }, 450);
                } else {
                    showHrAlert('Error', response.message || 'Unable to save record.', 'error');
                }
            },
            error: function (xhr) { ajaxError(xhr, 'Unable to save record.'); },
            complete: function () { submitButton.prop('disabled', false).html(originalHtml); }
        });
    });

    window.toggleHrStatus = function (entity, id, element) {
        const status = $(element).is(':checked') ? 1 : 0;
        $.ajax({
            url: routeFor(entity, 'status', id),
            type: 'PATCH',
            data: { status: status },
            success: function (response) {
                if (response.status === 'success') {
                    showHrAlert('Success', response.message, 'success');
                } else {
                    $(element).prop('checked', !status).trigger('change');
                    showHrAlert('Error', response.message || 'Unable to update status.', 'error');
                }
            },
            error: function (xhr) {
                $(element).prop('checked', !status).trigger('change');
                ajaxError(xhr, 'Unable to update status.');
            }
        });
    };

    window.deleteHrRecord = function (entity, id, name, tab) {
        Swal.fire({
            title: 'Delete this record?',
            text: "You are about to delete '" + name + "'.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#21352a',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Yes, Delete',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            focusCancel: true
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.ajax({
                url: routeFor(entity, 'destroy', id),
                type: 'DELETE',
                success: function (response) {
                    if (response.status === 'success') {
                        showHrAlert('Success', response.message, 'success');
                        setTimeout(function () {
                            window.location.href = settingsIndexUrl + '?tab=' + encodeURIComponent(tab);
                        }, 450);
                    } else {
                        showHrAlert('Error', response.message || 'Unable to delete record.', 'error');
                    }
                },
                error: function (xhr) { ajaxError(xhr, 'Unable to delete record.'); }
            });
        });
    };

    window.openDepartmentCreate = function () {
        resetHrForm('#departmentForm', 'Add Department', 'Save Department');
        $('#departmentModal').modal('show');
    };
    window.editDepartment = function (record) {
        resetHrForm('#departmentForm', 'Edit Department', 'Update Department');
        $('#departmentForm [name="id"]').val(record.id);
        $('#departmentForm [name="name"]').val(record.name);
        $('#departmentForm [name="code"]').val(record.code || '');
        $('#departmentForm [name="description"]').val(record.description || '');
        $('#departmentForm [name="sort_order"]').val(record.sort_order || 0);
        $('#departmentForm [name="status"]').prop('checked', !!record.status).trigger('change');
        $('#departmentModal').modal('show');
    };

    window.openDesignationCreate = function () {
        resetHrForm('#designationForm', 'Add Designation', 'Save Designation');
        $('#designationModal').modal('show');
    };
    window.editDesignation = function (record) {
        resetHrForm('#designationForm', 'Edit Designation', 'Update Designation');
        $('#designationForm [name="id"]').val(record.id);
        setHrSelectValue('hrDesignationDepartment', record.department_id || '');
        $('#designationForm [name="name"]').val(record.name);
        $('#designationForm [name="code"]').val(record.code || '');
        $('#designationForm [name="description"]').val(record.description || '');
        $('#designationForm [name="sort_order"]').val(record.sort_order || 0);
        $('#designationForm [name="status"]').prop('checked', !!record.status).trigger('change');
        $('#designationModal').modal('show');
    };

    window.openEmploymentTypeCreate = function () {
        resetHrForm('#employmentTypeForm', 'Add Employment Type', 'Save Employment Type');
        $('#employmentTypeForm [name="is_hourly"]').prop('checked', false).trigger('change');
        $('#employmentTypeModal').modal('show');
    };
    window.editEmploymentType = function (record) {
        resetHrForm('#employmentTypeForm', 'Edit Employment Type', 'Update Employment Type');
        $('#employmentTypeForm [name="id"]').val(record.id);
        $('#employmentTypeForm [name="name"]').val(record.name);
        $('#employmentTypeForm [name="code"]').val(record.code || '');
        $('#employmentTypeForm [name="description"]').val(record.description || '');
        $('#employmentTypeForm [name="sort_order"]').val(record.sort_order || 0);
        $('#employmentTypeForm [name="is_hourly"]').prop('checked', !!record.is_hourly).trigger('change');
        $('#employmentTypeForm [name="status"]').prop('checked', !!record.status).trigger('change');
        $('#employmentTypeModal').modal('show');
    };

    function toggleCarryForwardFields() {
        const enabled = $('#leaveTypeForm [name="allow_carry_forward"]').is(':checked');
        $('#leaveCarryForwardFields').toggle(enabled);
        if (!enabled) $('#leaveTypeForm [name="max_carry_forward_days"]').val(0);
    }
    $('#leaveTypeForm [name="allow_carry_forward"]').on('change', toggleCarryForwardFields);

    window.openLeaveTypeCreate = function () {
        resetHrForm('#leaveTypeForm', 'Add Leave Type', 'Save Leave Type');
        $('#leaveTypeForm [name="days_per_year"]').val(0);
        $('#leaveTypeForm [name="max_carry_forward_days"]').val(0);
        $('#leaveTypeForm [name="color"]').val('#21352a');
        $('#leaveTypeForm [name="is_paid"]').prop('checked', true).trigger('change');
        $('#leaveTypeForm [name="allow_carry_forward"]').prop('checked', false).trigger('change');
        $('#leaveTypeForm [name="requires_document"]').prop('checked', false).trigger('change');
        toggleCarryForwardFields();
        $('#leaveTypeModal').modal('show');
    };
    window.editLeaveType = function (record) {
        resetHrForm('#leaveTypeForm', 'Edit Leave Type', 'Update Leave Type');
        $('#leaveTypeForm [name="id"]').val(record.id);
        $('#leaveTypeForm [name="name"]').val(record.name);
        $('#leaveTypeForm [name="code"]').val(record.code || '');
        $('#leaveTypeForm [name="days_per_year"]').val(record.days_per_year || 0);
        $('#leaveTypeForm [name="max_carry_forward_days"]').val(record.max_carry_forward_days || 0);
        $('#leaveTypeForm [name="color"]').val(record.color || '#21352a');
        $('#leaveTypeForm [name="description"]').val(record.description || '');
        $('#leaveTypeForm [name="sort_order"]').val(record.sort_order || 0);
        $('#leaveTypeForm [name="is_paid"]').prop('checked', !!record.is_paid).trigger('change');
        $('#leaveTypeForm [name="allow_carry_forward"]').prop('checked', !!record.allow_carry_forward).trigger('change');
        $('#leaveTypeForm [name="requires_document"]').prop('checked', !!record.requires_document).trigger('change');
        $('#leaveTypeForm [name="status"]').prop('checked', !!record.status).trigger('change');
        toggleCarryForwardFields();
        $('#leaveTypeModal').modal('show');
    };

    function toggleSalaryCalculationFields() {
        const type = $('#salaryComponentForm [name="calculation_type"]').val();
        $('#salaryFixedFields').toggle(type === 'fixed');
        $('#salaryPercentageFields').toggle(type === 'percentage');
    }
    $('#salaryComponentForm [name="calculation_type"]').on('change', toggleSalaryCalculationFields);

    window.openSalaryComponentCreate = function () {
        resetHrForm('#salaryComponentForm', 'Add Salary Component', 'Save Component');
        setHrSelectValue('hrSalaryComponentType', 'earning');
        setHrSelectValue('hrSalaryCalculationType', 'fixed');
        setHrSelectValue('hrSalaryPercentageOf', 'basic_salary');
        $('#salaryComponentForm [name="default_amount"]').val(0);
        $('#salaryComponentForm [name="default_percentage"]').val(0);
        $('#salaryComponentForm [name="is_taxable"]').prop('checked', false).trigger('change');
        $('#salaryComponentForm [name="is_required"]').prop('checked', false).trigger('change');
        toggleSalaryCalculationFields();
        $('#salaryComponentModal').modal('show');
    };
    window.editSalaryComponent = function (record) {
        resetHrForm('#salaryComponentForm', 'Edit Salary Component', 'Update Component');
        $('#salaryComponentForm [name="id"]').val(record.id);
        $('#salaryComponentForm [name="name"]').val(record.name);
        $('#salaryComponentForm [name="code"]').val(record.code || '');
        setHrSelectValue('hrSalaryComponentType', record.type);
        setHrSelectValue('hrSalaryCalculationType', record.calculation_type);
        setHrSelectValue('hrSalaryPercentageOf', record.percentage_of || 'basic_salary');
        $('#salaryComponentForm [name="default_amount"]').val(record.default_amount || 0);
        $('#salaryComponentForm [name="default_percentage"]').val(record.default_percentage || 0);
        $('#salaryComponentForm [name="description"]').val(record.description || '');
        $('#salaryComponentForm [name="sort_order"]').val(record.sort_order || 0);
        $('#salaryComponentForm [name="is_taxable"]').prop('checked', !!record.is_taxable).trigger('change');
        $('#salaryComponentForm [name="is_required"]').prop('checked', !!record.is_required).trigger('change');
        $('#salaryComponentForm [name="status"]').prop('checked', !!record.status).trigger('change');
        toggleSalaryCalculationFields();
        $('#salaryComponentModal').modal('show');
    };

    window.openHolidayCreate = function () {
        resetHrForm('#holidayForm', 'Add Holiday', 'Save Holiday');
        setHrSelectValue('hrHolidayType', 'company');
        $('#holidayForm [name="is_paid"]').prop('checked', true).trigger('change');
        $('#holidayModal').modal('show');
    };
    window.editHoliday = function (record) {
        resetHrForm('#holidayForm', 'Edit Holiday', 'Update Holiday');
        $('#holidayForm [name="id"]').val(record.id);
        $('#holidayForm [name="name"]').val(record.name);
        if (hrHolidayDatePicker) {
            hrHolidayDatePicker.setDate(record.holiday_date ? String(record.holiday_date).substring(0, 10) : '', true, 'Y-m-d');
        } else {
            $('#holidayForm [name="holiday_date"]').val(record.holiday_date ? String(record.holiday_date).substring(0, 10) : '');
        }
        setHrSelectValue('hrHolidayType', record.holiday_type || 'company');
        $('#holidayForm [name="description"]').val(record.description || '');
        $('#holidayForm [name="is_paid"]').prop('checked', !!record.is_paid).trigger('change');
        $('#holidayForm [name="status"]').prop('checked', !!record.status).trigger('change');
        $('#holidayModal').modal('show');
    };

    $('.hr-settings-panel form:not(.hr-ajax-form)').on('submit', function () {
        Swal.fire({
            title: 'Saving settings...',
            text: 'Please do not close this page.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });
    });
});
</script>
@endsection
