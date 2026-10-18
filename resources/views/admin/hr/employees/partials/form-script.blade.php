@php
    $isEdit = $isEdit ?? isset($employee);
@endphp
<script>
$(function () {
    HrUi.initSelect2('.employee-form-select2');

    document.querySelectorAll('.employee-date').forEach(function (element) {
        HrUi.initFlatpickr(element);
    });

    function toggleSalarySetup() {
        const enabled = $('#employeeSalaryEnabled').is(':checked');
        $('#employeeSalaryFields').toggle(enabled);
    }

    function refreshEmployeeSalaryBasis() {
        const basis = $('select[name="salary_basis"]').val() || 'period_rate';
        const frequency = $('select[name="salary_frequency"]').val() || 'monthly';
        $('#employeeBasicSalaryLabel').text(basis === 'per_day' ? 'Daily Rate *' : 'Basic Salary *');
        $('#employeeBasicSalaryHelp').text(basis === 'per_day'
            ? 'Enter the rate for one payable day. Half-day attendance counts as 0.5 day; fixed salary/allowance components are also treated as daily rates.'
            : (frequency === 'weekly' ? 'Enter the full 7-day week rate.' : 'Enter the full month rate.'));
        $('#employeeSalaryFrequencyHelp').text(basis === 'per_day'
            ? 'This controls when payroll is generated; payment is calculated from payable attendance days.'
            : (frequency === 'weekly' ? 'Weekly pay is prorated by paid attendance days.' : 'Monthly payroll is generated once per month.'));
    }

    function toggleEmployeeRuleRows() {
        $('.employee-payroll-rule-row').each(function () {
            const mode = $(this).find('.employee-rule-mode').val();
            const custom = mode === 'custom';
            $(this).find('.employee-custom-rule-wrap').toggleClass('opacity-50', !custom);
            $(this).find('.employee-custom-rule-wrap input').prop('disabled', !custom);
        });
    }

    function toggleLeaveBalanceRows() {
        $('.employee-leave-balance-row').each(function () {
            const mode = $(this).find('.employee-leave-balance-mode').val();
            const custom = mode === 'custom';
            $(this).find('.employee-leave-custom-wrap').toggleClass('opacity-50', !custom);
            $(this).find('.employee-leave-custom-input').prop('disabled', !custom);
        });
    }

    function toggleAccessFields() {
        const waiterEnabled = $('#employeeIsWaiter').is(':checked');
        const loginEnabled = $('#employeeCanLogin').is(':checked');
        const status = HrUi.selectValue('employeeEmploymentStatus');

        $('#waiterAccessFields').toggle(waiterEnabled);
        $('#loginAccessFields').toggle(loginEnabled);
        $('#employeeExitDateWrap').toggle(['resigned', 'terminated'].includes(status));
    }

    $('#employeeIsWaiter, #employeeCanLogin').on('change', toggleAccessFields);
    $('#employeeSalaryEnabled').on('change', toggleSalarySetup);
    $(document).on('change', 'select[name="salary_basis"], select[name="salary_frequency"]', refreshEmployeeSalaryBasis);

    @if(!$isEdit)
        let salaryEffectiveDateTouched = false;
        $('#employeeSalaryEffectiveFrom').on('change input', function () {
            salaryEffectiveDateTouched = true;
        });
        $('#employeeJoinDate').on('change input', function () {
            if (!salaryEffectiveDateTouched && $(this).val()) {
                $('#employeeSalaryEffectiveFrom').val($(this).val()).trigger('change');
                salaryEffectiveDateTouched = false;
            }
        });
    @endif
    $(document).on('change', '.employee-rule-mode', toggleEmployeeRuleRows);
    $(document).on('change', '.employee-leave-balance-mode', toggleLeaveBalanceRows);
    $('#employeeEmploymentStatus').on('change', toggleAccessFields);

    $('#employeeImage').on('change', function () {
        if (this.files && this.files[0]) {
            $('#employeeImagePreview').attr('src', URL.createObjectURL(this.files[0]));
        }
    });

    $('#employeeNidImage').on('change', function () {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            Swal.fire('Invalid File', 'Please select a JPG, PNG or WEBP image.', 'warning');
            this.value = '';
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        $('#employeeNidImagePreview')
            .attr('src', previewUrl)
            .show()
            .one('load', function () {
                URL.revokeObjectURL(previewUrl);
            });
        $('#employeeNidImageEmpty').hide();
    });

    // Keep each new array index unique, even after removing a previously added row.
    let nextEducationIndex = Math.max(0, ...Array.from(document.querySelectorAll('#employeeEducationRows input[name^="educations["]'))
        .map(input => Number((input.name.match(/^educations\[(\d+)\]/) || [])[1]))
        .filter(Number.isFinite)) + 1;
    let nextCertificateIndex = Math.max(0, ...Array.from(document.querySelectorAll('#employeeCertificateRows input[name^="certificates["]'))
        .map(input => Number((input.name.match(/^certificates\[(\d+)\]/) || [])[1]))
        .filter(Number.isFinite)) + 1;
    let nextEmergencyContactIndex = Math.max(0, ...Array.from(document.querySelectorAll('#employeeEmergencyContactRows input[name^="emergency_contacts["]'))
        .map(input => Number((input.name.match(/^emergency_contacts\[(\d+)\]/) || [])[1]))
        .filter(Number.isFinite)) + 1;

    function toggleEmptyNotes() {
        $('#employeeEducationEmpty').toggle($('#employeeEducationRows .employee-education-row').length === 0);
        $('#employeeCertificateEmpty').toggle($('#employeeCertificateRows .employee-certificate-row').length === 0);
        $('#employeeEmergencyContactEmpty').toggle($('#employeeEmergencyContactRows .employee-emergency-contact-row').length === 0);
    }
    function addRepeatableRow(templateId, targetId, index) {
        const template = document.getElementById(templateId);
        document.getElementById(targetId).insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        toggleEmptyNotes();
    }
    $('#employeeAddEducation').on('click', function () {
        addRepeatableRow('employeeEducationTemplate', 'employeeEducationRows', nextEducationIndex++);
    });
    $('#employeeAddCertificate').on('click', function () {
        addRepeatableRow('employeeCertificateTemplate', 'employeeCertificateRows', nextCertificateIndex++);
    });
    $('#employeeAddEmergencyContact').on('click', function () {
        addRepeatableRow('employeeEmergencyContactTemplate', 'employeeEmergencyContactRows', nextEmergencyContactIndex++);
    });
    $(document).on('click', '#employeeEducationRows .employee-remove-row, #employeeCertificateRows .employee-remove-row, #employeeEmergencyContactRows .employee-remove-row', function () {
        $(this).closest('.employee-education-row, .employee-certificate-row, .employee-emergency-contact-row').remove();
        toggleEmptyNotes();
    });
    toggleEmptyNotes();

    $('#employeeForm').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
    });

    @if(session('error'))
        Swal.fire('Error', @json(session('error')), 'error');
    @endif

    toggleAccessFields();
    toggleSalarySetup();
    refreshEmployeeSalaryBasis();
    toggleEmployeeRuleRows();
    toggleLeaveBalanceRows();
});
</script>
