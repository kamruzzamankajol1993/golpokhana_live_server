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

    $('#employeeForm').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
    });

    @if(session('error'))
        Swal.fire('Error', @json(session('error')), 'error');
    @endif

    toggleAccessFields();
    toggleSalarySetup();
    toggleEmployeeRuleRows();
    toggleLeaveBalanceRows();
});
</script>
